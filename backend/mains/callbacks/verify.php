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

    /**
     * The secret can arrive three ways.
     *
     * It is handed to the provider in the callback URL's query string, which
     * is the only channel we control per transaction. But providers vary in
     * how faithfully they reproduce that URL -- some drop the query string
     * entirely, some re-encode it -- and when that happens every callback is
     * a 403, nothing is credited, and the customer's deposit sits Pending
     * with no indication that money arrived.
     *
     * A header or a body field carries exactly the same shared secret, so
     * accepting them costs nothing in strength and removes a whole class of
     * silent failure.
     */
    $body = json_decode(file_get_contents('php://input'), true);
    $body = is_array($body) ? $body : [];

    $provided = (string) (
        $_GET['t']
        ?? $_SERVER['HTTP_X_CALLBACK_TOKEN']
        ?? $_POST['t']
        ?? $body['t']
        ?? $body['token']
        ?? ''
    );

    // Fail closed. An unset secret means misconfiguration, and the safe
    // reading of "I cannot verify this" is "I do not accept this".
    if ($expected === '') {
        error_log("[{$context}] rejected from {$remoteIp}: CALLBACK_TOKEN is not configured");
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Callback verification unavailable']);
        exit;
    }

    if ($provided === '' || !hash_equals($expected, $provided)) {
        /**
         * Log enough to tell the two cases apart.
         *
         * "No token at all" usually means the provider did not reproduce the
         * query string it was given, which is a configuration problem on
         * their side. "Wrong token" means CALLBACK_TOKEN was rotated without
         * the in-flight transactions being re-issued. The old message said
         * "bad or missing" and left you unable to tell which -- while real
         * deposits hung Pending.
         */
        $why = $provided === ''
            ? 'no token present (the provider may have dropped the query string)'
            : 'token did not match';

        error_log(
            "[{$context}] rejected from {$remoteIp}: {$why}"
            . ' | uri=' . ($_SERVER['REQUEST_URI'] ?? '?')
            . ' | body=' . substr(json_encode($body), 0, 400)
        );

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
