<?php
/**
 * FIRST: record that this endpoint was reached at all.
 *
 * Runs before every include and every check, so a request that is later
 * rejected -- or that dies inside bootstrap -- still leaves a line saying it
 * arrived. Without it, 'Safaricom never called' and 'Safaricom called and we
 * threw it away' are indistinguishable.
 */
require_once __DIR__ . '/trace.php';
callback_trace('daraja_b2c_callback');

//get initiator
include 'initiate.php';
include 'verify.php';
include 'send-email.php';
include '../transaction-sms.php';

/**
 * SAFARICOM DARAJA B2C RESULT
 * ===========================
 * Serves as both the ResultURL and the QueueTimeOutURL handed to Daraja in
 * lib/payouts.php. A timeout is simply a failure with a non-zero ResultCode,
 * and treating it as one is what stops a queued payout from sitting in
 * Processing for ever with the customer's money reserved.
 *
 *   { "Result": { "ResultCode": 0, "ResultDesc": "...",
 *                 "OriginatorConversationID": "<our tracking id>",
 *                 "ConversationID": "AG_...",
 *                 "TransactionID": "QKA81LK5CY",
 *                 "ResultParameters": { "ResultParameter": [ {Key, Value}, ... ] } } }
 *
 * `OriginatorConversationID` is the tracking id we sent, which is what ties
 * the result back to a withdrawal -- the same job `external_reference` does
 * for Palpluss.
 *
 * Settling and refunding are settle_payout() in lib/payouts.php, shared with
 * the Palpluss callback so both rails behave identically: the status change
 * is the claim, so a replayed failure cannot refund the same payout twice.
 */

// Reject anything that cannot prove it came from Safaricom. This callback
// refunds a wallet on failure, so an unauthenticated caller could mint
// balance by replaying a "failed payout" for any Processing withdrawal.
verify_callback_request('daraja_b2c');

//get data posted remotely
$data = $fileGetContent->get_content();

if (!is_array($data)) {
    error_log('[daraja_b2c] rejected: body was not JSON');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Malformed body']);
    exit;
}

// Money arriving (or failing to): the body is worth the log line.
error_log('[daraja_b2c] payload: ' . substr(json_encode($data), 0, 800));

// Daraja nests everything under "Result"; a flat body is accepted too rather
// than failing closed on a shape we have not seen.
$result = is_array($data['Result'] ?? null) ? $data['Result'] : $data;

$trackingID = trim((string) ($result['OriginatorConversationID'] ?? ''));
$resultCode = $result['ResultCode'] ?? null;
$resultDesc = (string) ($result['ResultDesc'] ?? '');

if ($trackingID === '') {
    error_log('[daraja_b2c] rejected: callback carried no OriginatorConversationID');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing reference']);
    exit;
}

/**
 * ResultCode 0 is the only success.
 *
 * A missing code is NOT treated as success: an unrecognised body must never
 * mark a payout paid. It is a failure, which refunds the customer -- the safe
 * direction, because a wrongly refunded payout is visible in the ledger and
 * recoverable, while a wrongly settled one quietly keeps the customer's money.
 */
$succeeded = $resultCode !== null && (string) $resultCode === '0';

$fields  = daraja_result_fields($result);
$receipt = (string) ($fields['TransactionReceipt'] ?? $result['TransactionID'] ?? '');

// What Safaricom says it paid, for the log. The wallet was debited when the
// withdrawal was raised, so this is a reconciliation aid, not a gate.
$paidAmount = $fields['TransactionAmount'] ?? null;

$note = $succeeded
    ? ($receipt !== '' ? $receipt : 'Paid')
    : 'Payout failed; amount refunded' . ($resultDesc !== '' ? ' (' . substr($resultDesc, 0, 120) . ')' : '');

$outcome = settle_payout($pdo, $query, $trackingID, $succeeded, $note, 'daraja_b2c');

if (!$outcome['ok']) {
    // Stay Processing and let Safaricom retry, rather than acknowledging a
    // settlement or refund that did not happen.
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $outcome['message']]);
    exit;
}

if ($outcome['outcome'] === 'settled') {
    error_log("[daraja_b2c] {$trackingID} paid, receipt {$receipt}"
        . ($paidAmount !== null ? ", amount {$paidAmount}" : ''));
} elseif ($outcome['outcome'] === 'refunded') {
    error_log("[daraja_b2c] {$trackingID} failed (code {$resultCode}: {$resultDesc})");
}

echo json_encode(['status' => 'ok', 'message' => $outcome['message']]);
