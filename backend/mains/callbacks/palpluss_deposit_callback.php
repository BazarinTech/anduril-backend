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

    /**
     * THE TRANSACTION UUID IS THE REFERENCE.
     *
     * Palpluss keys a deposit on `transaction.id`, and that is the value
     * deposit.php stores in `transactions.trackingID` when the STK push is
     * accepted. It is the one identifier both sides agree on.
     *
     * `external_reference` is deliberately NOT read here any more. It used to
     * be tried first, which is why a real payment went uncredited: for a
     * WALLET_TOPUP_B2C the provider fills that field with its own composite
     * value -- "WALLET_TOPUP_B2C:8d43cf3f-..." -- rather than echoing the
     * reference we sent, so the lookup searched for something that was never
     * in our table. It is still used, but as a *fallback against local_ref*
     * below, because for an STK it does carry the merchant's own reference.
     */
    $trackingID = (string) $pick([
        'id', 'transaction_id', 'transactionId',
    ]);

    // Kept separate so it can be matched against our own reference, not
    // mistaken for the provider's transaction id.
    $externalRef = (string) $pick(['external_reference', 'externalReference']);

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

    /**
     * Look the deposit up by the transaction UUID, then by our own reference.
     *
     * Each attempt is one indexed lookup, and the cost of not finding the row
     * is a customer whose money left their phone and never arrived. Trying the
     * plausible references is cheaper than being wrong about which one the
     * provider will quote.
     */
    $attempts = [];

    // 1. The provider's transaction UUID, which is what we store.
    $transactions = $query->select('transactions', '*', ['trackingID' => $trackingID, 'status' => 'Pending']);
    $attempts[] = "trackingID={$trackingID}";

    // 2. Our own reference, if the provider echoed it back. For an STK the
    //    external_reference field carries exactly what we sent as
    //    accountReference; for a wallet top-up it carries their own value,
    //    which simply will not match and costs one query to rule out.
    if (empty($transactions) && $externalRef !== '') {
        $transactions = $query->select('transactions', '*', ['local_ref' => $externalRef, 'status' => 'Pending']);
        $attempts[] = "local_ref={$externalRef}";

        if (!empty($transactions)) {
            error_log("[palpluss_deposit] matched on local_ref {$externalRef}, not the transaction id");
            // Key everything below on the row's own trackingID so the claim
            // and the status update address the right row.
            $trackingID = $transactions[0]['trackingID'];
        }
    }

    // 3. Some providers put the merchant reference in external_reference and
    //    their own id nowhere else. One more indexed lookup.
    if (empty($transactions) && $externalRef !== '') {
        $transactions = $query->select('transactions', '*', ['trackingID' => $externalRef, 'status' => 'Pending']);
        $attempts[] = "trackingID={$externalRef}";

        if (!empty($transactions)) {
            error_log("[palpluss_deposit] matched external_reference {$externalRef} against trackingID");
            $trackingID = $transactions[0]['trackingID'];
        }
    }

    // A replayed or unknown reference matches nothing. Acknowledge it so the
    // provider stops retrying, but do not touch a wallet. This also removes
    // the "Undefined array key 0" warnings that filled the error log, because
    // $transactions[0] was being read before its existence was checked.
    if (empty($transactions)) {
        /**
         * Say what was looked for AND what is actually on file.
         *
         * "no pending transaction for X" alone cannot be acted on -- it does
         * not reveal whether the reference is wrong, the row was already
         * settled, or the deposit was never recorded. The pending references
         * are printed alongside so the mismatch is visible in one line
         * instead of requiring a database session.
         */
        $pending = [];
        foreach ($query->select('transactions', '*', ['type' => 'Deposit', 'status' => 'Pending']) as $row) {
            $pending[] = $row['trackingID'] . ' (local_ref ' . $row['local_ref'] . ')';
        }

        error_log(
            '[palpluss_deposit] no pending deposit matched. tried: ' . implode(', ', $attempts)
            . ' | pending on file: ' . (empty($pending) ? 'none' : implode('; ', array_slice($pending, 0, 5)))
        );
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