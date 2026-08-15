<?php
/**
 * MIGRATION -- DEPOSIT PROVIDER REFERENCE
 * ======================================
 * Run once against any database created before the Palpluss top-up switch:
 *
 *     php db/migrations/003_deposit_provider_reference.php
 *
 * Safe to re-run.
 *
 * Deposits now go through `POST {base}/v1/wallets/b2c/topups`, which returns a
 * `transactionId` UUID. That UUID becomes the transaction's `trackingID`,
 * because it is the value the provider quotes in callbacks and in support.
 *
 * The reference we generate is still sent as `accountReference` and as the
 * `Idempotency-Key`, and is now kept in a `local_ref` column so a deposit can
 * be traced from either side -- and so a callback that quotes our reference
 * rather than the provider's still finds its row.
 *
 * Existing rows keep their old references in `trackingID`; `local_ref` is
 * backfilled from it so historical deposits remain matchable by either key.
 */

require_once __DIR__ . '/../../bootstrap/api.php';

$pdo = $db->getConnection();

echo "Deposit provider reference migration\n";
echo "====================================\n";
echo 'Database: ' . env('DB_NAME') . ' on ' . env('DB_HOST') . "\n\n";

// -- 1. Add the column ------------------------------------------------------
$exists = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transactions'
        AND COLUMN_NAME = 'local_ref'"
)->fetchColumn();

if (!$exists) {
    $pdo->exec("ALTER TABLE transactions ADD COLUMN local_ref VARCHAR(50) NOT NULL DEFAULT '' AFTER trackingID");
    echo "  added transactions.local_ref\n";
} else {
    echo "  transactions.local_ref already present\n";
}

// -- 2. Index it ------------------------------------------------------------
$indexed = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transactions'
        AND INDEX_NAME = 'idx_tx_local'"
)->fetchColumn();

if (!$indexed) {
    $pdo->exec("ALTER TABLE transactions ADD INDEX idx_tx_local (local_ref)");
    echo "  added index idx_tx_local\n";
} else {
    echo "  index idx_tx_local already present\n";
}

// -- 3. Backfill ------------------------------------------------------------
// Historical deposits were keyed on our generated reference. Copying it into
// local_ref means the callback's fallback lookup can still find them.
$backfilled = $pdo->exec(
    "UPDATE transactions SET local_ref = trackingID
      WHERE local_ref = '' AND trackingID <> ''"
);

echo "  backfilled local_ref on {$backfilled} existing row(s)\n";

echo "\nDone.\n";
