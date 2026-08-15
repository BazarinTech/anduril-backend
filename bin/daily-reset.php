<?php
/**
 * DAILY RESET
 * ===========
 * Phase 5.4. Re-arms every active investment for the day's claim and zeroes
 * the per-day income counter.
 *
 * This is the job that makes the product work. Until now it lived behind a
 * button on admin/admin-roll.php that a human had to remember to press: miss a
 * day and nobody can claim; press it twice and everyone claims twice.
 *
 * Install as a cron job, once a day, just after local midnight. On cPanel:
 *
 *     0 0 * * *  /usr/local/bin/php /home/USER/site/bin/daily-reset.php >> /home/USER/logs/daily-reset.log 2>&1
 *
 * Adjust the PHP binary path to whatever `which php` reports on the host --
 * cPanel usually wants the ea-php81 binary specifically.
 *
 * CLI ONLY. Refuses to run over HTTP, because a web-reachable "give everyone
 * another claim" endpoint is exactly the sort of thing that gets found.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("daily-reset.php is a cron job and cannot be run over HTTP.\n");
}

require_once __DIR__ . '/../bootstrap/api.php';

$startedAt = date('Y-m-d H:i:s');
echo "[{$startedAt}] daily reset starting\n";

$pdo = $db->getConnection();

try {
    $pdo->beginTransaction();

    // Re-arm the claim on every live investment. Expired orders stay at 0 --
    // the old button set `rolls = 1` on *every* row in the table, which handed
    // a fresh claim to orders that had already run their course.
    $arm = $pdo->prepare("UPDATE orders SET rolls = 1 WHERE type = 'investment' AND status = 'Active'");
    $arm->execute();
    $armed = $arm->rowCount();

    // Reset the per-day income counter for everyone.
    $reset = $pdo->prepare("UPDATE wallets SET today_income = '0.00' WHERE today_income <> '0.00'");
    $reset->execute();
    $cleared = $reset->rowCount();

    $pdo->commit();

    echo "  re-armed {$armed} active investment(s)\n";
    echo "  cleared today_income on {$cleared} wallet(s)\n";
    echo "[" . date('Y-m-d H:i:s') . "] done\n";

    exit(0);
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $message = 'daily reset FAILED: ' . $e->getMessage();
    error_log('[daily-reset] ' . $message);
    fwrite(STDERR, "  {$message}\n");

    // Non-zero so cron's MAILTO actually tells somebody.
    exit(1);
}
