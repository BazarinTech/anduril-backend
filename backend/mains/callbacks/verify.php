<?php
/**
 * PAYMENT CALLBACK VERIFICATION
 * =============================
 * Phase 1.4.
 *
 * Before this, every file in this directory would credit a wallet on the word
 * of whoever posted to it. There was no signature, no IP check, and no
 * comparison against what was actually paid -- so anyone could initiate a
 * large deposit, never pay it, post a forged SUCCESS callback, and be
 * credited in full, referral commissions included.
 *
 * Palpluss does not sign its callbacks. What we do control is the URL handed
 * to the provider per transaction, so the shared secret travels in that URL
 * and comes back with the call. That is a bearer secret: it is only as good
 * as HTTPS and the provider's own logs, which is why the amount cross-check
 * below exists as a second line rather than a nicety.
 */

/**
 * Ends the request unless the caller presents the shared callback secret.
 *
 * Also records the caller's IP. Palpluss does not publish its egress ranges,
 * so CALLBACK_IPS starts empty and the check is skipped; populate it from
 * these log lines once you have seen real provider traffic, and the allowlist
 * starts being enforced automatically.
 */
function verify_callback_request($context = 'callback')
{
    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $expected = (string) env('CALLBACK_TOKEN', '');
    $provided = (string) ($_GET['t'] ?? '');

    // Fail closed. An unset secret means misconfiguration, and the safe
    // reading of "I cannot verify this" is "I do not accept this".
    if ($expected === '') {
        error_log("[{$context}] rejected from {$remoteIp}: CALLBACK_TOKEN is not configured");
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Callback verification unavailable']);
        exit;
    }

    if ($provided === '' || !hash_equals($expected, $provided)) {
        error_log("[{$context}] rejected from {$remoteIp}: bad or missing callback token");
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }

    $allowedIps = array_filter(array_map('trim', explode(',', (string) env('CALLBACK_IPS', ''))));

    if (!empty($allowedIps) && !in_array($remoteIp, $allowedIps, true)) {
        error_log("[{$context}] rejected: {$remoteIp} is not in CALLBACK_IPS");
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }

    error_log("[{$context}] accepted from {$remoteIp}");
}

/**
 * Pull the amount the provider says was actually paid.
 *
 * Providers disagree about where this lives and the field is not always
 * present, so this returns null when it cannot find one rather than
 * guessing -- callers treat null as "cannot cross-check".
 */
function callback_reported_amount($data)
{
    $candidates = [
        $data['transaction']['amount'] ?? null,
        $data['amount']                ?? null,
        $data['result']['Amount']      ?? null,
        $data['transaction_info']['amount'] ?? null,
    ];

    foreach ($candidates as $value) {
        if ($value !== null && is_numeric($value)) {
            return (float) $value;
        }
    }

    return null;
}

/**
 * True when the provider's amount contradicts our pending record.
 *
 * Compared with a small tolerance because providers round differently and
 * some return the figure as a string.
 */
function callback_amount_mismatch($reported, $expected, $context = 'callback')
{
    if ($reported === null) {
        return false;
    }

    if (abs($reported - (float) $expected) > 0.01) {
        error_log("[{$context}] amount mismatch: provider reported {$reported}, record expects {$expected}");
        return true;
    }

    return false;
}
