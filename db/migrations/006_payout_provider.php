<?php
/**
 * MIGRATION 006 -- SELECTABLE PAYOUT RAIL
 * =======================================
 * Adds:
 *   controls.payoutProvider  'palpluss' | 'daraja'
 *
 * Which provider sends customer payouts is now an admin setting on the
 * Platform Control page rather than a code path. The default is 'palpluss',
 * which is what every existing deployment was already using -- a migration
 * must never quietly move live payouts to a provider nobody selected.
 *
 * Idempotent: safe to run more than once.
 *
 *     php db/migrations/006_payout_provider.php
 */

require_once __DIR__ . '/../../bootstrap/api.php';

$pdo = $db->getConnection();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'controls' AND COLUMN_NAME = 'payoutProvider'"
);
$stmt->execute();

if ((int) $stmt->fetchColumn() > 0) {
    echo "  skip   controls.payoutProvider already exists\n";
} else {
    $pdo->exec("ALTER TABLE controls ADD COLUMN payoutProvider VARCHAR(20) NOT NULL DEFAULT 'palpluss'");
    echo "  add    controls.payoutProvider (default 'palpluss')\n";
}
