<?php
/**
 * VERIFICATION CODE HELPERS
 * =========================
 * Shared by verification.php and forgot-password.php. Kept in its own file
 * because both are request endpoints -- including one from the other would
 * execute it and emit a second response body.
 */

/**
 * True when a code row is older than its own expiry window.
 *
 * `expiry` is a count of MINUTES measured from time_created, not a timestamp.
 * A missing or non-positive window is treated as already expired: a code we
 * cannot date is a code we should not honour.
 */
if (!function_exists('code_expired')) {
    function code_expired(array $row)
    {
        $minutes = (int) ($row['expiry'] ?? 0);

        if ($minutes <= 0) {
            return true;
        }

        return (strtotime($row['time_created']) + ($minutes * 60)) < time();
    }
}

/**
 * Throttle verification-code requests per phone number.
 *
 * Without this the endpoint is an open SMS pump: it costs real money per
 * message and lets anyone flood a stranger's handset.
 */
if (!function_exists('sms_rate_limited')) {
    function sms_rate_limited($query, $phone, $maxPerWindow = 3, $windowMinutes = 15)
    {
        $recent = $query->select('verification_codes', '*', ['phone' => $phone]);
        $cutoff = time() - ($windowMinutes * 60);
        $count  = 0;

        foreach ($recent as $row) {
            if (strtotime($row['time_created']) >= $cutoff) {
                $count++;
            }
        }

        return $count >= $maxPerWindow;
    }
}
