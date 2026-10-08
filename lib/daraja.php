<?php
/**
 * SAFARICOM DARAJA (M-PESA) B2C
 * =============================
 * The second payout rail, alongside Palpluss. Which one runs is an admin
 * setting -- see lib/payouts.php, which is what the rest of the code calls.
 *
 * Daraja differs from Palpluss in three ways that shape this file:
 *
 *   1. Every call needs an OAuth token, fetched with the consumer key/secret
 *      and valid for an hour. It is cached, because fetching one per payout
 *      doubles the number of round trips to Safaricom.
 *   2. The B2C request is authorised by a `SecurityCredential`: the initiator
 *      password encrypted with Safaricom's public certificate. That encryption
 *      is done once, out of band, and the result is pasted into an env var --
 *      the certificate is per-environment and the result does not change.
 *   3. The response only acknowledges the REQUEST. Whether the money moved
 *      arrives later on the ResultURL, which is why a payout stays Processing
 *      until backend/mains/callbacks/daraja_b2c_callback.php settles it.
 *
 * `OriginatorConversationID` carries our own tracking id, and Daraja echoes it
 * back in the result. That is what ties a callback to a withdrawal, exactly as
 * `external_reference` does for Palpluss.
 */

if (!function_exists('daraja_base_url')) {
    /**
     * Sandbox and production are different hosts; nothing else differs.
     */
    function daraja_base_url()
    {
        $explicit = trim((string) env('DARAJA_BASE_URL', ''));

        if ($explicit !== '') {
            return rtrim($explicit, '/');
        }

        return strtolower((string) env('DARAJA_ENV', 'sandbox')) === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }
}

if (!function_exists('daraja_missing_config')) {
    /**
     * The settings a payout cannot be attempted without.
     *
     * Returned as a list rather than a boolean so the admin panel can name
     * exactly what is missing instead of saying "not configured".
     */
    function daraja_missing_config()
    {
        $required = [
            'DARAJA_CONSUMER_KEY',
            'DARAJA_CONSUMER_SECRET',
            'DARAJA_SHORTCODE',
            'DARAJA_INITIATOR_NAME',
            'DARAJA_SECURITY_CREDENTIAL',
        ];

        $missing = [];

        foreach ($required as $key) {
            if (trim((string) env($key, '')) === '') {
                $missing[] = $key;
            }
        }

        return $missing;
    }
}

if (!function_exists('daraja_msisdn')) {
    /**
     * A Kenyan number in the 2547XXXXXXXX form Daraja insists on.
     *
     * Users type 07..., +2547..., 2547... and sometimes with spaces. Palpluss
     * accepts most of those; Daraja rejects anything else outright, so the
     * normalising happens here rather than at each call site.
     *
     * @return string|null null when it cannot be read as a Kenyan mobile.
     */
    function daraja_msisdn($phone)
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        // 0712345678 -> 254712345678
        if (strlen($digits) === 10 && $digits[0] === '0') {
            $digits = '254' . substr($digits, 1);
        }

        // 712345678 -> 254712345678
        if (strlen($digits) === 9 && ($digits[0] === '7' || $digits[0] === '1')) {
            $digits = '254' . $digits;
        }

        return preg_match('/^254[17]\d{8}$/', $digits) ? $digits : null;
    }
}

if (!function_exists('daraja_http')) {
    /**
     * One HTTP call, with its own timeouts.
     *
     * Bazarin\APIS\Curl is not used here for the same reason lib/palpluss.php
     * avoids it: a fixed 30-second timeout, and a null return that cannot
     * distinguish "no response" from "not JSON". A payout needs to know which.
     *
     * @return array{ok:bool, status:int, body:array|null, raw:string, error:string|null}
     */
    function daraja_http($method, $url, array $headers, $body = null, $timeout = 20)
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
        }

        $raw      = curl_exec($ch);
        $curlErr  = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $curlErr !== '') {
            return ['ok' => false, 'status' => 0, 'body' => null, 'raw' => '', 'error' => $curlErr ?: 'No response'];
        }

        $decoded = json_decode($raw, true);

        return [
            'ok'     => $httpCode >= 200 && $httpCode < 300,
            'status' => $httpCode,
            'body'   => is_array($decoded) ? $decoded : null,
            'raw'    => (string) $raw,
            'error'  => null,
        ];
    }
}

if (!function_exists('daraja_access_token')) {
    /**
     * An OAuth token, cached until shortly before it expires.
     *
     * Daraja issues these for an hour. The cache is a file rather than a
     * static, because each payout is its own PHP request -- an in-process
     * cache would never be reused. The expiry it reports is honoured but
     * shortened by a minute, so a token is never spent in the second it dies.
     *
     * @return array{ok:bool, token:string|null, error:string|null}
     */
    function daraja_access_token($force = false)
    {
        $key    = trim((string) env('DARAJA_CONSUMER_KEY', ''));
        $secret = trim((string) env('DARAJA_CONSUMER_SECRET', ''));

        if ($key === '' || $secret === '') {
            return ['ok' => false, 'token' => null, 'error' => 'DARAJA_CONSUMER_KEY / DARAJA_CONSUMER_SECRET are not set'];
        }

        // Keyed by the credentials and host, so switching environment or
        // rotating a key does not serve a token minted for the other one.
        $cacheFile = sys_get_temp_dir() . '/daraja-token-' . substr(hash('sha256', $key . '|' . daraja_base_url()), 0, 16) . '.json';

        if (!$force && is_file($cacheFile)) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);

            if (is_array($cached) && !empty($cached['token']) && ($cached['expires_at'] ?? 0) > time()) {
                return ['ok' => true, 'token' => $cached['token'], 'error' => null];
            }
        }

        $res = daraja_http(
            'GET',
            daraja_base_url() . '/oauth/v1/generate?grant_type=client_credentials',
            [
                'Authorization: Basic ' . base64_encode($key . ':' . $secret),
                'Accept: application/json',
            ],
            null,
            10
        );

        if (!$res['ok'] || !is_array($res['body']) || empty($res['body']['access_token'])) {
            error_log('[daraja] token request failed: '
                . ($res['error'] ?? ('HTTP ' . $res['status'] . ' ' . substr($res['raw'], 0, 200))));

            return ['ok' => false, 'token' => null, 'error' => 'Could not authenticate with Safaricom'];
        }

        $token   = (string) $res['body']['access_token'];
        $expires = (int) ($res['body']['expires_in'] ?? 3599);

        @file_put_contents($cacheFile, json_encode([
            'token'      => $token,
            'expires_at' => time() + max(60, $expires - 60),
        ]));
        @chmod($cacheFile, 0600);

        return ['ok' => true, 'token' => $token, 'error' => null];
    }
}

if (!function_exists('daraja_b2c_send')) {
    /**
     * Ask Daraja to pay a customer.
     *
     * A "Success" here means Safaricom ACCEPTED the request, not that the
     * money has moved -- the same contract the Palpluss branch returns, so
     * withdraw.php can treat both identically and leave the row Processing
     * until the result callback arrives.
     *
     * @return array{status:string, message:string, reference:string|null, raw:mixed}
     */
    function daraja_b2c_send($phone, $amount, $trackingID, $resultUrl, $timeoutUrl)
    {
        $missing = daraja_missing_config();

        if ($missing) {
            error_log('[daraja] payout refused: missing ' . implode(', ', $missing));

            return [
                'status'    => 'Failed',
                'message'   => 'Payouts are temporarily unavailable. Please try again shortly.',
                'reference' => null,
                'raw'       => null,
            ];
        }

        $msisdn = daraja_msisdn($phone);

        if ($msisdn === null) {
            return [
                'status'    => 'Failed',
                'message'   => 'That M-Pesa number is not valid. Update your withdrawal account and try again.',
                'reference' => null,
                'raw'       => null,
            ];
        }

        $token = daraja_access_token();

        if (!$token['ok']) {
            return [
                'status'    => 'Failed',
                'message'   => 'Payment provider is unreachable. Please try again shortly.',
                'reference' => null,
                'raw'       => null,
            ];
        }

        /**
         * Amounts are whole shillings. Daraja rejects decimals, and rounding
         * down is the safe direction: it can never pay out more than was
         * reserved from the wallet.
         */
        $payable = (int) floor((float) $amount);

        if ($payable < 1) {
            return [
                'status'    => 'Failed',
                'message'   => 'Payout amount is too small to send.',
                'reference' => null,
                'raw'       => null,
            ];
        }

        $payload = [
            // Our tracking id. Daraja echoes it in the result, and it is also
            // what makes a retried request idempotent on their side.
            'OriginatorConversationID' => $trackingID,
            'InitiatorName'            => (string) env('DARAJA_INITIATOR_NAME', ''),
            'SecurityCredential'       => (string) env('DARAJA_SECURITY_CREDENTIAL', ''),
            'CommandID'                => (string) env('DARAJA_B2C_COMMAND_ID', 'BusinessPayment'),
            'Amount'                   => $payable,
            'PartyA'                   => (string) env('DARAJA_SHORTCODE', ''),
            'PartyB'                   => $msisdn,
            'Remarks'                  => 'Withdrawal',
            'QueueTimeOutURL'          => $timeoutUrl,
            'ResultURL'                => $resultUrl,
            'Occasion'                 => 'Withdrawal',
        ];

        $res = daraja_http(
            'POST',
            daraja_base_url() . '/mpesa/b2c/v3/paymentrequest',
            [
                'Authorization: Bearer ' . $token['token'],
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            $payload
        );

        /**
         * A rejected token is worth one retry.
         *
         * The cache can hold a token Safaricom has already invalidated -- a
         * key rotation, or a restart on their side. Without this, every payout
         * fails until the cached token's hour is up.
         */
        if ($res['status'] === 401) {
            $token = daraja_access_token(true);

            if ($token['ok']) {
                $res = daraja_http(
                    'POST',
                    daraja_base_url() . '/mpesa/b2c/v3/paymentrequest',
                    [
                        'Authorization: Bearer ' . $token['token'],
                        'Content-Type: application/json',
                        'Accept: application/json',
                    ],
                    $payload
                );
            }
        }

        // Logged in full: the failure modes here are all in the response body,
        // and without it a refused payout leaves nothing to diagnose.
        error_log('[daraja] b2c response for ' . $trackingID . ': HTTP ' . $res['status'] . ' ' . substr($res['raw'], 0, 400));

        if (!is_array($res['body'])) {
            return [
                'status'    => 'Failed',
                'message'   => 'Payment provider is unreachable. Please try again shortly.',
                'reference' => null,
                'raw'       => $res['raw'],
            ];
        }

        $body = $res['body'];

        // ResponseCode "0" is the acceptance. Anything else is a refusal, and
        // errorMessage is where Daraja puts the reason.
        if ($res['ok'] && (string) ($body['ResponseCode'] ?? '') === '0') {
            return [
                'status'    => 'Success',
                'message'   => 'Mpesa transaction initiated successfully',
                'reference' => (string) ($body['ConversationID'] ?? ''),
                'raw'       => $body,
            ];
        }

        $reason = $body['errorMessage']
            ?? $body['ResponseDescription']
            ?? $body['ErrorMessage']
            ?? 'Payout rejected by M-Pesa.';

        return [
            'status'    => 'Failed',
            'message'   => (string) $reason,
            'reference' => null,
            'raw'       => $body,
        ];
    }
}

if (!function_exists('daraja_result_fields')) {
    /**
     * Flatten a Result callback's ResultParameter list into a map.
     *
     * Daraja sends [{"Key":"TransactionReceipt","Value":"QKA81LK5CY"}, ...],
     * which is awkward to read directly at the two or three places that need
     * a receipt number.
     */
    function daraja_result_fields(array $result)
    {
        $params = $result['ResultParameters']['ResultParameter'] ?? [];

        // A single parameter arrives as one object rather than a list.
        if (isset($params['Key'])) {
            $params = [$params];
        }

        $fields = [];

        foreach (is_array($params) ? $params : [] as $param) {
            if (isset($param['Key'])) {
                $fields[(string) $param['Key']] = $param['Value'] ?? '';
            }
        }

        return $fields;
    }
}
