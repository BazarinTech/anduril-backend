<?php
/**
 * PAYOUT RAILS
 * ============
 * One entry point for sending money to a customer, with two implementations
 * behind it:
 *
 *   palpluss  POST {PALPLUSS_B2C_URL}        -> palpluss_b2c_callback.php
 *   daraja    Safaricom M-Pesa B2C v3        -> daraja_b2c_callback.php
 *
 * Which one runs is `controls.payoutProvider`, set by an admin on the
 * Platform Control page. The switch takes effect on the next payout; anything
 * already in flight settles through the callback of the provider that
 * accepted it, because each callback matches on the tracking id we sent and
 * does not care which rail is currently selected.
 *
 * Both branches return the same shape, and "Success" means the same thing in
 * each: the provider ACCEPTED the request. The money has not moved yet. The
 * withdrawal stays Processing until the provider's callback settles or
 * refunds it.
 *
 * There were previously two copies of the Palpluss call -- one in
 * backend/mains/withdraw.php for user-initiated payouts and one in
 * admin/approve-withdrawals.php for admin-approved ones. They had already
 * drifted (different response handling, different messages). Both now call
 * payout_send(), so switching provider switches both at once and a fix to one
 * is a fix to both.
 */

require_once __DIR__ . '/daraja.php';

if (!function_exists('payout_providers')) {
    /** Selectable rails, as value => label for the admin dropdown. */
    function payout_providers()
    {
        return [
            'palpluss' => 'Palpluss',
            'daraja'   => 'Safaricom Daraja (M-Pesa)',
        ];
    }
}

if (!function_exists('payout_provider')) {
    /**
     * The rail payouts currently use.
     *
     * Falls back to Palpluss, which is what every existing deployment used
     * before this setting existed -- an unreadable or unknown value must not
     * silently move payouts to a provider nobody chose.
     */
    function payout_provider($query = null)
    {
        $configured = '';

        if ($query !== null) {
            $controls = $query->select('controls');
            $configured = strtolower(trim((string) ($controls[0]['payoutProvider'] ?? '')));
        }

        if ($configured === '') {
            $configured = strtolower(trim((string) env('PAYOUT_PROVIDER', '')));
        }

        return array_key_exists($configured, payout_providers()) ? $configured : 'palpluss';
    }
}

if (!function_exists('payout_provider_status')) {
    /**
     * Whether a rail is usable, for the admin panel to show before an admin
     * switches to it rather than after a customer's payout fails.
     *
     * @return array{ok:bool, missing:array, note:string}
     */
    function payout_provider_status($provider)
    {
        if ($provider === 'daraja') {
            $missing = daraja_missing_config();

            return [
                'ok'      => $missing === [],
                'missing' => $missing,
                'note'    => $missing === []
                    ? 'Configured for the ' . (strtolower((string) env('DARAJA_ENV', 'sandbox')) === 'production' ? 'production' : 'sandbox') . ' environment.'
                    : 'Not configured: ' . implode(', ', $missing) . '.',
            ];
        }

        $missing = [];

        foreach (['PALPLUSS_KEY', 'PALPLUSS_B2C_URL'] as $key) {
            if (trim((string) env($key, '')) === '') {
                $missing[] = $key;
            }
        }

        return [
            'ok'      => $missing === [],
            'missing' => $missing,
            'note'    => $missing === [] ? 'Configured.' : 'Not configured: ' . implode(', ', $missing) . '.',
        ];
    }
}

if (!function_exists('payout_send')) {
    /**
     * Send a payout on whichever rail is selected.
     *
     * @param object      $api        Curl client (Palpluss branch only).
     * @param string      $trackingID Our reference. Both providers echo it back.
     * @param string|null $provider   Overrides the configured rail; used by tests.
     *
     * @return array{status:string, message:string, provider:string, reference:string|null}
     */
    function payout_send($query, $api, $phone, $amount, $trackingID, $provider = null)
    {
        $provider = $provider ?? payout_provider($query);

        if ($provider === 'daraja') {
            $result = daraja_b2c_send(
                $phone,
                $amount,
                $trackingID,
                callback_url('daraja_b2c_callback.php'),
                // Safaricom posts here when the request ages out in their
                // queue. It is a failure like any other, and the same endpoint
                // handles it -- so the money goes back rather than the
                // withdrawal sitting Processing for ever.
                callback_url('daraja_b2c_callback.php')
            );

            return [
                'status'    => $result['status'],
                'message'   => $result['message'],
                'provider'  => 'daraja',
                'reference' => $result['reference'],
            ];
        }

        return payout_send_palpluss($api, $phone, $amount, $trackingID);
    }
}

if (!function_exists('payout_send_palpluss')) {
    /**
     * The original rail, unchanged in behaviour.
     */
    function payout_send_palpluss($api, $phone, $amount, $trackingID)
    {
        $headers = ['Authorization: Basic ' . env('PALPLUSS_KEY')];

        $data = [
            'amount'      => (float) $amount,
            'phone'       => $phone,
            'reference'   => $trackingID,
            'callbackUrl' => callback_url('palpluss_b2c_callback.php'),
            'description' => 'BusinessPayment',
        ];

        $initiated = $api->request(env('PALPLUSS_B2C_URL'), 'POST', $data, $headers);

        // An unreachable provider makes Curl::request() return null. Treat
        // "no answer" as an explicit failure so the caller refunds, rather
        // than reading array keys off null.
        if (!is_array($initiated)) {
            error_log('[payout] palpluss returned no parseable response for ' . $trackingID);

            return [
                'status'    => 'Failed',
                'message'   => 'Payment provider is unreachable. Please try again shortly.',
                'provider'  => 'palpluss',
                'reference' => null,
            ];
        }

        if (!empty($initiated['success'])) {
            return [
                'status'    => 'Success',
                'message'   => 'Mpesa transaction initiated successfully',
                'provider'  => 'palpluss',
                'reference' => (string) ($initiated['transactionId'] ?? $initiated['data']['transactionId'] ?? ''),
            ];
        }

        if (isset($initiated['error'])) {
            return [
                'status'    => 'Failed',
                'message'   => is_array($initiated['error'])
                    ? ($initiated['error']['message'] ?? 'Payout rejected')
                    : (string) $initiated['error'],
                'provider'  => 'palpluss',
                'reference' => null,
            ];
        }

        return [
            'status'    => 'Failed',
            'message'   => 'Transaction Failed. Kindly reach our customer service for quick assistance',
            'provider'  => 'palpluss',
            'reference' => null,
        ];
    }
}

if (!function_exists('settle_payout')) {
    /**
     * Settle or refund a payout, from either provider's callback.
     *
     * The refund path is the dangerous one: it hands money back, and it used
     * to do so with an unlocked read-modify-write, so two deliveries of one
     * failure callback could both refund the same payout. The status change is
     * the claim -- only the caller whose UPDATE moves the row out of
     * 'Processing' may touch the wallet, so a replay settles nothing.
     *
     * @return array{ok:bool, outcome:string, message:string}
     *         outcome: settled | refunded | duplicate | error
     */
    function settle_payout(PDO $pdo, $query, $trackingID, $succeeded, $note, $context = 'payout')
    {
        $transactions = $query->select('transactions', '*', ['trackingID' => $trackingID, 'status' => 'Processing']);

        if (empty($transactions)) {
            error_log("[{$context}] no processing transaction for {$trackingID}; ignoring");

            return ['ok' => true, 'outcome' => 'duplicate', 'message' => 'No processing transaction'];
        }

        $transaction = $transactions[0];
        $userID      = $transaction['userID'];
        $amount      = money($transaction['amount']);

        $pdo->beginTransaction();

        try {
            $targetStatus = $succeeded ? 'Success' : 'Failed';

            if (!claim_transaction($pdo, $trackingID, 'Processing', $targetStatus, $note)) {
                $pdo->rollBack();
                error_log("[{$context}] {$trackingID} already settled; ignoring duplicate delivery");

                return ['ok' => true, 'outcome' => 'duplicate', 'message' => 'Already processed'];
            }

            if ($succeeded) {
                $pdo->commit();
                error_log("[{$context}] payout {$trackingID} settled");

                return ['ok' => true, 'outcome' => 'settled', 'message' => 'Settled'];
            }

            // Give the reserved funds back.
            $wallet = wallet_for_update($pdo, $userID);

            if ($wallet === null) {
                $pdo->rollBack();
                error_log("[{$context}] REFUND FAILED for {$trackingID}: no wallet for user {$userID}");

                return ['ok' => false, 'outcome' => 'error', 'message' => 'Wallet missing'];
            }

            $query->update('wallets', ['balance' => money_str(money($wallet['balance']) + $amount)], ['userID' => $userID]);
            $pdo->commit();

            error_log("[{$context}] payout {$trackingID} failed; refunded {$amount} to user {$userID}");

            return ['ok' => true, 'outcome' => 'refunded', 'message' => 'Refunded'];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log("[{$context}] could not settle {$trackingID}: " . $e->getMessage());

            // Stay Processing and let the provider retry, rather than
            // acknowledging a settlement or refund that did not happen.
            return ['ok' => false, 'outcome' => 'error', 'message' => 'Could not settle payout'];
        }
    }
}
