<?php
/**
 * PHASE 4 MIGRATION -- COLUMN WIDTH FIXES
 * =======================================
 * Run once against any database created before Phase 4:
 *
 *     php db/migrations/002_phase4_column_fixes.php
 *
 * Safe to re-run; every step checks the current state first.
 *
 * Finding 4.12: `transactions.type` is VARCHAR(10) on the deployed database,
 * but backend/mains/returns.php writes 'Product Income' -- fourteen
 * characters. Under STRICT_TRANS_TABLES that is error 1406, not a truncation,
 * so the INSERT fails outright. The wallet is still credited (that happens in
 * a separate statement), so users see the money but no transaction record
 * exists for it. Check for the symptom with:
 *
 *     SELECT COUNT(*) FROM transactions WHERE type = 'Product Income';
 *
 * A zero there on a live system with active investments confirms it.
 */

require_once __DIR__ . '/../../bootstrap/api.php';

$pdo = $db->getConnection();

echo "Phase 4 column fixes\n";
echo "====================\n";
echo 'Database: ' . env('DB_NAME') . ' on ' . env('DB_HOST') . "\n\n";

$columns = [
    // table.column => [required length, definition]
    'transactions.type' => [30, "VARCHAR(30) NOT NULL"],
];

foreach ($columns as $target => [$needed, $definition]) {
    [$table, $column] = explode('.', $target);

    $stmt = $pdo->prepare(
        "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c"
    );
    $stmt->execute([':t' => $table, ':c' => $column]);
    $current = $stmt->fetchColumn();

    if ($current === false) {
        echo "  ! {$target} not found -- wrong database?\n";
        continue;
    }

    if ((int) $current < $needed) {
        $pdo->exec("ALTER TABLE {$table} MODIFY {$column} {$definition}");
        echo "  widened {$target} {$current} -> {$needed}\n";
    } else {
        echo "  {$target} already {$current} chars, leaving it\n";
    }
}

// Report the symptom so it is obvious whether records were being lost.
$hasIncome = (int) $pdo->query(
    "SELECT COUNT(*) FROM transactions WHERE type = 'Product Income'"
)->fetchColumn();

$activeOrders = (int) $pdo->query(
    "SELECT COUNT(*) FROM orders WHERE type = 'investment'"
)->fetchColumn();

echo "\n  'Product Income' rows: {$hasIncome}   investment orders: {$activeOrders}\n";

if ($activeOrders > 0 && $hasIncome === 0) {
    echo "  NOTE: investments exist but no Product Income rows were ever written.\n";
    echo "        Daily returns were credited to wallets without a transaction record.\n";
    echo "        Those historical rows cannot be reconstructed from here.\n";
}

echo "\nDone.\n";
