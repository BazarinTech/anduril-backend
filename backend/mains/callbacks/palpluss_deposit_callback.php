<?php
/**
 * FIRST: record that this endpoint was reached at all.
 *
 * Runs before every include and every check, so a request that is
 * later rejected -- or that dies inside bootstrap -- still leaves a
 * line saying it arrived. Without it, 'the provider never called' and
 * 'the provider called and we threw it away' are indistinguishable.
 */
require_once __DIR__ . '/trace.php';
callback_trace('palpluss_deposit_callback');

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
    
    /**
     * ALWAYS log the delivered payload.
     *
     * A callback that cannot be parsed used to log only "carried no
     * transaction reference", which says the parse failed but not what
     * arrived -- so the one piece of evidence needed to fix it was the one
     * thing not recorded. This is money arriving; the body is worth the log
     * line.
     */
    error_log('[palpluss_deposit] payload: ' . substr(json_encode($data), 0, 800));

    /**
     * The reference the callback quotes.
     *
     * Read from every plausible envelope rather than one.
     *
     * This used to look only inside $data['transaction']. The provider wraps
     * its other responses as {"success":true,"data":{...}}, so a callback
     * shaped that way -- or a flat one -- found nothing, was rejected as
     * "Missing reference", and the customer's payment was never credited.
     * Guessing one envelope and failing closed on the rest is not a safe
     * trade when the failure mode is lost money.
     */
    $containers = array_filter([
        $data['transaction'] ?? null,
        $data['data'] ?? null,
        $data['data']['transaction'] ?? null,
        $data,
    ], 'is_array');

    /** First non-empty value for any of $keys, across every container. */
    $pick = function (array $keys) use ($containers) {
        foreach ($containers as $container) {
            foreach ($keys as $key) {
                if (isset($container[$key]) && $container[$key] !== '') {
                    return $container[$key];
                }
            }
        }

        return '';
    };

    $trackingID = (string) $pick([
        'transaction_id', 'transactionId', 'external_reference', 'externalReference', 'id',
    ]);

    $reference = (string) $pick([
        'mpesa_receipt', 'mpesaReceipt', 'mpesaReceiptNumber', 'MpesaReceiptNumber',
        'receipt', 'reference',
    ]);

    $status = (string) $pick(['status', 'transactionStatus', 'resultDesc']);

    $account = (string) $pick(['phone_number', 'phoneNumber', 'phone', 'msisdn', 'account']);

    if ($trackingID === '') {
        error_log('[palpluss_deposit] rejected: no transaction reference in the payload above');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing reference']);
        exit;
    }

    //get transaction details
    $transactions = $query->select('transactions', '*', ['trackingID' => $trackingID, 'status' => "Pending"]);

    /**
     * Fall back to our own reference.
     *
     * We send `accountReference` and `Idempotency-Key` as a value we generate,
     * and store it in `local_ref`. If the provider echoes that back instead of
     * its own UUID, matching on trackingID alone would find nothing and the
     * deposit would sit Pending forever with the customer's money taken. This
     * makes the lookup work either way, and says which path it took so the
     * assumption can be checked against real traffic.
     */
    if (empty($transactions)) {
        $transactions = $query->select('transactions', '*', ['local_ref' => $trackingID, 'status' => "Pending"]);

        if (!empty($transactions)) {
            error_log("[palpluss_deposit] matched {$trackingID} on local_ref, not transactionId");
            // Key everything below on the row's own trackingID so the claim
            // and the status update address the right row.
            $trackingID = $transactions[0]['trackingID'];
        }
    }

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