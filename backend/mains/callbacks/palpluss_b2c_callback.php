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

    /**
     * Settling and refunding are shared with the Daraja callback
     * (settle_payout() in lib/payouts.php), so both rails behave identically:
     * the status change is the claim, so two deliveries of one failure
     * callback cannot refund the same payout twice.
     */
    $succeeded = $status == 'SUCCESS';

    // The failure note is what an admin reads in the approval queue, so it
    // carries whatever the provider said rather than a generic sentence.
    $reason = (string) ($data['transaction']['result_desc']
        ?? $data['transaction']['resultDesc']
        ?? $data['transaction']['message']
        ?? $data['message']
        ?? '');

    $note = $succeeded
        ? $reference
        : 'M-Pesa payout failed'
            . ($status !== '' ? ' (' . substr((string) $status, 0, 30) . ')' : '')
            . ($reason !== '' ? ': ' . substr($reason, 0, 150) : '');

    $outcome = settle_payout($pdo, $query, $trackingID, $succeeded, $note, 'palpluss_b2c');

    if (!$outcome['ok']) {
        // Stay Processing and let the provider retry, rather than
        // acknowledging a settlement or refund that did not happen.
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $outcome['message']]);
        exit;
    }

    echo json_encode(['status' => 'ok']);
}