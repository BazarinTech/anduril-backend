<?php
/**
 * PALPLUSS WALLET BALANCES
 * ========================
 * Reads the provider's float balances for the admin dashboard:
 *
 *   GET {base}/v1/wallets/b2c/balance      -- the float payouts draw from
 *   GET {base}/v1/wallets/service/balance  -- the service wallet
 *
 * Both are called with `Authorization: Basic <key>` and no body.
 *
 * Why this does not go through Bazarin\APIS\Curl:
 *
 *   - That class hardcodes a 30-second timeout. Two balance calls on a
 *     dashboard load means a stalled provider can hang the page for a minute.
 *     Six seconds is plenty for a balance read, and a stale number is worth
 *     far less than a responsive panel.
 *   - It returns null indistinguishably for "no response" and "response was
 *     not JSON", so the caller cannot tell an outage from an empty wallet.
 *
 * That distinction is the whole point here. A float balance of zero and an
 * unknown float balance look identical on screen if you render both as
 * "Kes 0.00", and they mean opposite things to whoever is deciding whether
 * payouts will clear.
 */

/**
 * Fetch a wallet balance.
 *
 * @param string $wallet 'b2c' or 'service'
 * @param int    $ttl    Seconds to reuse a previous reading. The dashboard is
 *                       reloaded often and this number moves slowly.
 *
 * @return array{ok:bool, balance:float|null, currency:string, error:string|null, cached:bool, raw:mixed}
 */
if (!function_exists('palpluss_wallet_balance')) {
    function palpluss_wallet_balance($wallet = 'b2c', $ttl = 30)
    {
        $urls = [
            'b2c'     => env('PALPLUSS_B2C_BALANCE_URL'),
            'service' => env('PALPLUSS_SERVICE_BALANCE_URL'),
        ];

        $url = $urls[$wallet] ?? null;

        if (!$url) {
            return ['ok' => false, 'balance' => null, 'currency' => 'KES', 'error' => 'No URL configured for the ' . $wallet . ' wallet', 'cached' => false, 'raw' => null];
        }

        $cacheFile = sys_get_temp_dir() . '/palpluss-balance-' . preg_replace('/[^a-z0-9]/i', '', $wallet) . '.json';

        if ($ttl > 0 && is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
            $cached = json_decode(file_get_contents($cacheFile), true);

            if (is_array($cached)) {
                $cached['cached'] = true;
                return $cached;
            }
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Basic ' . env('PALPLUSS_KEY'),
                'Accept: application/json',
            ],
        ]);

        $body     = curl_exec($ch);
        $curlErr  = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $curlErr !== '') {
            error_log("[palpluss] {$wallet} balance unreachable: {$curlErr}");
            return ['ok' => false, 'balance' => null, 'currency' => 'KES', 'error' => 'Provider unreachable', 'cached' => false, 'raw' => null];
        }

        $decoded = json_decode($body, true);

        if (!is_array($decoded) || $httpCode >= 400) {
            error_log("[palpluss] {$wallet} balance HTTP {$httpCode}: " . substr((string) $body, 0, 200));
            return ['ok' => false, 'balance' => null, 'currency' => 'KES', 'error' => 'Provider returned HTTP ' . $httpCode, 'cached' => false, 'raw' => $decoded];
        }

        /**
         * The response shape is not documented for the balance endpoints. The
         * service wallet is known to answer {"data":{"ledgerBalance":...}},
         * so that is tried first and the other plausible spellings after it,
         * rather than assuming one and rendering a blank card if it differs.
         */
        $candidates = [
            $decoded['data']['ledgerBalance']    ?? null,
            $decoded['data']['availableBalance'] ?? null,
            $decoded['data']['balance']          ?? null,
            $decoded['ledgerBalance']            ?? null,
            $decoded['availableBalance']         ?? null,
            $decoded['balance']                  ?? null,
        ];

        $balance = null;
        foreach ($candidates as $value) {
            if ($value !== null && is_numeric($value)) {
                $balance = (float) $value;
                break;
            }
        }

        if ($balance === null) {
            // Log the whole payload once so the right key can be pinned down
            // from real traffic instead of guessed at again.
            error_log("[palpluss] {$wallet} balance: no recognised balance field in " . substr($body, 0, 300));
            return ['ok' => false, 'balance' => null, 'currency' => 'KES', 'error' => 'Unrecognised response shape', 'cached' => false, 'raw' => $decoded];
        }

        $result = [
            'ok'       => true,
            'balance'  => $balance,
            'currency' => $decoded['data']['currency'] ?? $decoded['currency'] ?? 'KES',
            'error'    => null,
            'cached'   => false,
            'raw'      => $decoded,
        ];

        @file_put_contents($cacheFile, json_encode($result));

        return $result;
    }
}
