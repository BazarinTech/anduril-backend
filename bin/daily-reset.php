<?php
/**
 * DAILY RESET -- NO LONGER REQUIRED
 * ================================
 * This job used to be what made claiming work: every night it set
 * `orders.rolls = 1` to re-arm the day's claim, and zeroed
 * `wallets.today_income`.
 *
 * Neither is needed now. Claimability is derived from the clock and each
 * order's own claim counter (bootstrap/claims.php), and today_income rolls
 * over on the first credit of a new period. Nothing has to fire at midnight
 * for tomorrow to work.
 *
 * The file stays, and stays harmless, because an installed cron entry will
 * keep calling it until somebody removes that entry -- and a cron job that
 * suddenly exits non-zero starts mailing the operator every night. It reports
 * what changed and exits 0.
 *
 *     REMOVE THE CRON ENTRY when convenient:
 *         crontab -e   # delete the daily-reset.php line
 *
 * CLI ONLY, as before.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("daily-reset.php is a cron job and cannot be run over HTTP.\n");
}

require_once __DIR__ . '/../bootstrap/api.php';

echo "[" . date('Y-m-d H:i:s') . "] daily reset\n";
echo "  This job is no longer required.\n";
echo "  Claiming is driven by the window on the admin Platform Control page;\n";
echo "  it does not depend on anything running overnight.\n";

$window = claim_window(claim_settings($query));

echo "  Window: " . ($window['enabled'] ? 'enforced' : 'not enforced')
    . ", opens " . $window['opens']
    . ($window['closes'] ? ', closes ' . $window['closes'] : '')
    . ", " . $window['per_day'] . " claim(s) per investment per day\n";
echo "  Currently " . ($window['open'] ? 'OPEN' : 'CLOSED')
    . " (period " . $window['period'] . ")\n";
echo "  You can safely remove this entry from crontab.\n";

exit(0);
