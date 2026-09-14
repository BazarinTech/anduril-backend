<?php
/**
 * MIGRATION 005 -- INCENTIVE COLUMN WIDTHS
 * ========================================
 * `incentives.name` was VARCHAR(20), `level` VARCHAR(10) and `salary`
 * VARCHAR(10). Under STRICT_TRANS_TABLES a longer value is error 1406 rather
 * than a truncation, so adding an incentive called "Diamond Ambassador Tier"
 * crashed the admin page with a 500 while shorter names saved fine -- which
 * looked like "the first one works, the next one fails".
 *
 *   incentives.name    20 -> 100
 *   incentives.level   10 -> 50
 *   incentives.salary  10 -> 20
 *   wallets.level      20 -> 50
 *
 * wallets.level goes with incentives.level: approving an incentive
 * application copies the incentive's level onto the applicant's wallet
 * (admin/incentive-application.php), so a wider incentive level on a narrower
 * wallet column would move the same crash to the approval step.
 *
 * The admin forms now validate against these same limits
 * (admin/includes/field-rules.php), so an over-long value gets a message
 * instead of a database error either way.
 *
 * Widening only; never narrows a column that is already wider. Idempotent.
 *
 *     php db/migrations/005_incentive_column_widths.php
 */

require_once __DIR__ . '/../../bootstrap/api.php';

$pdo = $db->getConnection();

$columns = [
    // [table, column, required length, definition]
    ['incentives', 'name',   100, "VARCHAR(100) NOT NULL"],
    ['incentives', 'level',  50,  "VARCHAR(50) NOT NULL"],
    ['incentives', 'salary', 20,  "VARCHAR(20) NOT NULL"],
    ['wallets',    'level',  50,  "VARCHAR(50) NOT NULL DEFAULT 'lvl1'"],
];

$stmt = $pdo->prepare(
    "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c"
);

foreach ($columns as [$table, $column, $needed, $definition]) {
    $stmt->execute([':t' => $table, ':c' => $column]);
    $current = $stmt->fetchColumn();

    if ($current === false) {
        echo "  ! {$table}.{$column} not found -- wrong database?\n";
        continue;
    }

    if ((int) $current < $needed) {
        $pdo->exec("ALTER TABLE {$table} MODIFY {$column} {$definition}");
        echo "  widen  {$table}.{$column} {$current} -> {$needed}\n";
    } else {
        echo "  skip   {$table}.{$column} already {$current} chars\n";
    }
}
