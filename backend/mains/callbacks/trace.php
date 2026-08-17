<?php
/**
 * CALLBACK ARRIVAL TRACE
 * ======================
 * Records that *something* reached a callback endpoint, before anything can
 * reject it.
 *
 * WHY THIS IS SEPARATE FROM verify.php
 * ------------------------------------
 * Every other log line in this directory is written after some check has
 * already passed. That leaves one question unanswerable from the logs, and it
 * is the first question worth asking when a deposit will not settle: did the
 * provider call us at all?
 *
 * A missing token, a malformed body, a wrong path, a fatal in initiate.php --
 * all of them look identical from the outside, which is to say they look like
 * silence. This runs first and unconditionally, so silence afterwards means
 * "nothing arrived" rather than "something arrived and was discarded quietly".
 *
 * Deliberately dependency-free: no config, no database, no bootstrap. It has
 * to work even when the thing that is broken is one of those.
 *
 * Grep the platform logs for CALLBACK-HIT.
 */

if (!function_exists('callback_trace')) {
    function callback_trace($endpoint)
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '?';
        $uri    = $_SERVER['REQUEST_URI'] ?? '?';
        $ip     = $_SERVER['REMOTE_ADDR'] ?? '?';
        $agent  = $_SERVER['HTTP_USER_AGENT'] ?? '-';

        // Whether the query string survived the round trip is the single most
        // useful fact here: the shared secret travels in it.
        $hasToken = isset($_GET['t']) && $_GET['t'] !== '' ? 'yes' : 'NO';

        $raw = file_get_contents('php://input');
        $len = strlen((string) $raw);

        error_log(sprintf(
            '[CALLBACK-HIT] %s %s %s from=%s token-in-query=%s body=%dB agent=%s',
            $endpoint,
            $method,
            $uri,
            $ip,
            $hasToken,
            $len,
            substr($agent, 0, 60)
        ));

        // Truncated: a callback body is small, but a provider having a bad day
        // should not be able to fill the log.
        error_log('[CALLBACK-HIT] ' . $endpoint . ' body: ' . substr((string) $raw, 0, 1000));
    }
}
