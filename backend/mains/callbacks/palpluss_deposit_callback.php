<?php
//get initiator
include 'initiate.php';
include 'verify.php';
include 'send-email.php';
include '../transaction-sms.php';

// Reject anything that cannot prove it came from the payment provider.
// Runs before any parsing so a forged body never reaches the wallet logic.
verify_callback_request('palpluss_deposit');

// refferal_algo() lived here as a 90-line copy that paid three levels, while
// admin/approve-deposits.php carried a two-level copy of the same thing. Both
// are replaced by referral_commission() in bootstrap/referrals.php.

//get data posted remotely
$data = $fileGetContent->get_content();

 //process data insertion and automatic deposit
 if (isset($data)) {
    
    // $trackingID = $data['external_reference'];
    // $reference = $data['result']['MpesaReceiptNumber'];
    // $status = $data['status'];
    // $account = $data['result']['Phone'];
    
    $trackingID = $data['transaction']['external_reference'] ?? '';
    $reference = $data['transaction']['mpesa_receipt'] ?? '';
    $status = $data['transaction']['status'] ?? '';
    $account = $data['transaction']['phone_number'] ?? '';

    if ($trackingID === '') {
        error_log('[palpluss_deposit] rejected: callback carried no external_reference');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing reference']);
        exit;
    }

    //get transaction details
    $transactions = $query->select('transactions', '*', ['trackingID' => $trackingID, 'status' => "Pending"]);

    // A replayed or unknown reference matches nothing. Acknowledge it so the
    // provider stops retrying, but do not touch a wallet. This also removes
    // the "Undefined array key 0" warnings that filled the error log, because
    // $transactions[0] was being read before its existence was checked.
    if (empty($transactions)) {
        error_log("[palpluss_deposit] no pending transaction for {$trackingID}; ignoring");
        echo json_encode(['status' => 'ok', 'message' => 'No pending transaction']);
        exit;
    }

    $transaction = $transactions[0];

    // Cross-check what the provider says was paid against what we are about to
    // credit. The token check above is the primary control; this catches a
    // leaked token being used to inflate an otherwise legitimate deposit.
    $reportedAmount = callback_reported_amount($data);

    if (callback_amount_mismatch($reportedAmount, $transaction['amount'], 'palpluss_deposit')) {
        $query->update(
            'transactions',
            ['status' => 'Failed', 'description' => 'Amount mismatch on callback'],
            ['trackingID' => $trackingID]
        );

        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'Amount mismatch']);
        exit;
    }

    if ($status == 'SUCCESS') {
        $userID = $transaction['userID'];
        $amount = money($transaction['amount']);

        /**
         * Phase 3.2 -- the credit, the status flip and the upline commission
         * are one atomic unit.
         *
         * Two things made this dangerous before. The depositor's wallet was
         * read and written without a lock, so a deposit landing at the same
         * moment as any other credit could lose one of them. And the commission
         * walk wrote to three more wallets with no lock at all -- an active
         * upline collecting their own returns while a downline deposited could
         * silently drop either amount.
         *
         * Marking the transaction Success inside the same transaction is what
         * makes a replayed callback harmless: the row is only selected while
         * still Pending, so the second delivery finds nothing to do.
         */
        $pdo->beginTransaction();

        try {
            // Claim the row first. The SELECT above filtered on status
            // 'Pending', but it ran before this transaction opened, so
            // simultaneous deliveries all passed it. Only the caller whose
            // UPDATE actually moves the row may credit anything.
            if (!claim_transaction($pdo, $trackingID, 'Pending', 'Success', $reference)) {
                $pdo->rollBack();
                error_log("[palpluss_deposit] {$trackingID} already settled; ignoring duplicate delivery");
                echo json_encode(['status' => 'ok', 'message' => 'Already processed']);
                exit;
            }

            $wallet = wallet_for_update($pdo, $userID);

            if ($wallet === null) {
                $pdo->rollBack();
                error_log("[palpluss_deposit] no wallet for user {$userID} on {$trackingID}");
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Wallet missing']);
                exit;
            }

            $query->update('wallets', ['balance' => money_str(money($wallet['balance']) + $amount)], ['userID' => $userID]);
            $query->update('transactions', ['status' => 'Success', 'description' => $reference], ['trackingID' => $trackingID]);

            //refferal income
            $paid = referral_commission($pdo, $query, $userID, $amount);

            $pdo->commit();

            error_log("[palpluss_deposit] credited {$amount} to user {$userID}; commission paid to " . count($paid) . " upline(s)");
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log("[palpluss_deposit] credit failed for {$trackingID}: " . $e->getMessage());

            // Leave the row Pending and fail loudly so the provider retries
            // rather than us acknowledging a deposit we did not apply.
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Could not apply deposit']);
            exit;
        }

        // Email is sent after the commit -- SMTP is slow and must never hold a
        // row lock, and a bounced email should not roll back a real deposit.
        $user_details = $query->select('users', '*', ['ID' => $userID]);

        if (!empty($user_details)) {
            $body = deposit_template($user_details[0]['name'], money_str($amount), 'M-Pesa');
            $email_res = send_email($user_details[0]['email'], 'Deposit Recieved Successfully', $body);
        }

        echo json_encode(['status' => 'ok']);

    }else{
        //update transaction status
        $query->update('transactions', ['status' => 'Failed'], ['trackingID' => $trackingID]);
        echo json_encode(['status' => 'ok']);
    }
}