<?php
/**
 * MIGRATION 004 -- TIME-BASED CLAIM WINDOW
 * ========================================
 * Moves claiming off the `orders.rolls` flag and onto a configurable daily
 * window. See bootstrap/claims.php for why.
 *
 * Adds:
 *   orders.claim_period      DATE     -- which claim period the counter belongs to
 *   orders.claims_in_period  INT      -- claims taken during that period
 *   orders.last_claim_at     DATETIME -- when the last claim landed
 *   wallets.income_period    DATE     -- lets today_income reset without a cron
 *   controls.claimWindowOn   -- '1' to enforce the window, '0' to accept any hour
 *   controls.claimOpensAt    TIME     -- e.g. 07:00:00
 *   controls.claimClosesAt   TIME     -- NULL means open until the next opening
 *   controls.claimsPerDay    INT      -- claims allowed per order per period
 *
 * `orders.rolls` is deliberately left in place. Nothing reads it for the claim
 * decision any more, but dropping a column is not something to do in the same
 * change that stops using it -- if this needs backing out, the old flag is
 * still there and still being zeroed on every claim.
 *
 * Idempotent: safe to run more than once.
 *
 *     php db/migrations/004_claim_window.php
 */

require_once __DIR__ . '/../../bootstrap/api.php';

$pdo = $db->getConnection();
$dbName = env('DB_NAME');

function column_exists(PDO $pdo, $dbName, $table, $column)
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$dbName, $table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

$additions = [
    ['orders',   'claim_period',     "ADD COLUMN claim_period DATE NULL DEFAULT NULL AFTER rolls"],
    ['orders',   'claims_in_period', "ADD COLUMN claims_in_period INT NOT NULL DEFAULT 0 AFTER claim_period"],
    ['orders',   'last_claim_at',    "ADD COLUMN last_claim_at DATETIME NULL DEFAULT NULL AFTER claims_in_period"],
    ['wallets',  'income_period',    "ADD COLUMN income_period DATE NULL DEFAULT NULL"],
    ['controls', 'claimWindowOn',    "ADD COLUMN claimWindowOn VARCHAR(1) NOT NULL DEFAULT '1'"],
    ['controls', 'claimOpensAt',     "ADD COLUMN claimOpensAt TIME NOT NULL DEFAULT '07:00:00'"],
    ['controls', 'claimClosesAt',    "ADD COLUMN claimClosesAt TIME NULL DEFAULT NULL"],
    ['controls', 'claimsPerDay',     "ADD COLUMN claimsPerDay INT NOT NULL DEFAULT 1"],
];

$applied = 0;

foreach ($additions as [$table, $column, $clause]) {
    if (column_exists($pdo, $dbName, $table, $column)) {
        echo "  skip   {$table}.{$column} already exists\n";
        continue;
    }

    $pdo->exec("ALTER TABLE {$table} {$clause}");
    echo "  add    {$table}.{$column}\n";
    $applied++;
}

// An index on the claim period keeps the "who has claimed today" style query
// cheap once there are a lot of orders.
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'orders' AND INDEX_NAME = 'idx_orders_claim_period'"
);
$stmt->execute([$dbName]);

if ((int) $stmt->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE orders ADD KEY idx_orders_claim_period (claim_period)");
    echo "  add    index orders.idx_orders_claim_period\n";
    $applied++;
}

/**
 * Carry the old flag across, so nobody loses a claim on the day this ships.
 *
 * An active order sitting at rolls = 1 was claimable under the old model, and
 * must still be claimable under the new one: leaving claim_period NULL does
 * exactly that. An order at rolls = 0 had already claimed today, so it is
 * stamped with today's period and a used-up counter.
 */
if ($applied > 0) {
    $window = claim_window(claim_settings($query));

    $stmt = $pdo->prepare(
        "UPDATE orders
            SET claim_period = :period, claims_in_period = :perDay
          WHERE type = 'investment' AND status = 'Active'
            AND rolls = 0 AND claim_period IS NULL"
    );
    $stmt->execute([':period' => $window['period'], ':perDay' => $window['per_day']]);

    echo "  carry  {$stmt->rowCount()} already-claimed order(s) into period {$window['period']}\n";
}

echo $applied > 0
    ? "Migration 004 applied ({$applied} change(s)).\n"
    : "Migration 004: nothing to do.\n";
