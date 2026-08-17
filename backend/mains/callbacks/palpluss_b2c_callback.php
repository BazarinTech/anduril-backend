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
callback_trace('palpluss_b2c_callback');

//get initiator
include 'initiate.php';
include 'verify.php';
include 'send-email.php';
include '../transaction-sms.php';

// Reject anything that cannot prove it came from the payment provider. This
// callback refunds a wallet on failure, so an unauthenticated caller could
// mint balance by replaying a "failed payout" for any Processing withdrawal.
verify_callback_request('palpluss_b2c');

//get data posted remotely
$data = $fileGetContent->get_content();

 //process b2c transaction status
 if (isset($data)) {

    $trackingID = $data['transaction']['external_reference'] ?? '';
    $reference = $data['transaction']['mpesa_receipt'] ?? '';
    $status = $data['transaction']['status'] ?? '';
    $account = $data['transaction']['phone_number'] ?? '';

    if ($trackingID === '') {
        error_log('[palpluss_b2c] rejected: callback carried no external_reference');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing reference']);
        exit;
    }

    //get transaction details
    $transactions = $query->select('transactions', '*', ['trackingID' => $trackingID, 'status' => 'Processing']);

    // Only a payout still in Processing can be settled or refunded. Anything
    // else is a replay, and refunding twice would credit the user for money
    // they already received.
    if (empty($transactions)) {
        error_log("[palpluss_b2c] no processing transaction for {$trackingID}; ignoring");
        echo json_encode(['status' => 'ok', 'message' => 'No processing transaction']);
        exit;
    }

    $transaction = $transactions[0];
    $userID = $transaction['userID'];
    $amount = money($transaction['amount']);

    /**
     * Phase 3.2 -- the refund path is the dangerous one here.
     *
     * On failure this hands money back, and it did so with an unlocked
     * read-modify-write. Two deliveries of the same failure callback could
     * both find the row in Processing and both refund it, paying the user
     * twice for a payout that never happened. Flipping the status inside the
     * same transaction closes that window: the row is only selected while
     * still Processing, so the second delivery matches nothing.
     */
    $pdo->beginTransaction();

    try {
        // The status change is the claim -- see claim_transaction(). Without
        // it, simultaneous deliveries of one failure callback would each pass
        // the 'Processing' filter above and each refund the same payout.
        $targetStatus = ($status == 'SUCCESS') ? 'Success' : 'Failed';
        $note = ($status == 'SUCCESS') ? $reference : 'Payout failed; amount refunded';

        if (!claim_transaction($pdo, $trackingID, 'Processing', $targetStatus, $note)) {
            $pdo->rollBack();
            error_log("[palpluss_b2c] {$trackingID} already settled; ignoring duplicate delivery");
            echo json_encode(['status' => 'ok', 'message' => 'Already processed']);
            exit;
        }

        if ($status == 'SUCCESS') {
            $pdo->commit();

            error_log("[palpluss_b2c] payout {$trackingID} settled");
        }else{
            //Give the reserved funds back
            $wallet = wallet_for_update($pdo, $userID);

            if ($wallet === null) {
                $pdo->rollBack();
                error_log("[palpluss_b2c] REFUND FAILED for {$trackingID}: no wallet for user {$userID}");
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Wallet missing']);
                exit;
            }

            $query->update('wallets', ['balance' => money_str(money($wallet['balance']) + $amount)], ['userID' => $userID]);
            $pdo->commit();

            error_log("[palpluss_b2c] payout {$trackingID} failed; refunded {$amount} to user {$userID}");
        }
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log("[palpluss_b2c] could not settle {$trackingID}: " . $e->getMessage());

        // Stay Processing and let the provider retry, rather than acknowledging
        // a settlement or refund that did not happen.
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Could not settle payout']);
        exit;
    }

    echo json_encode(['status' => 'ok']);
}