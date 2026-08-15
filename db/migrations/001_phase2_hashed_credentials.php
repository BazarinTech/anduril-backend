<?php
/**
 * PHASE 2 MIGRATION -- HASHED CREDENTIALS
 * =======================================
 * Run once against any database that predates Phase 2:
 *
 *     php db/migrations/001_phase2_hashed_credentials.php
 *
 * It is safe to run more than once. Every step checks the current state
 * first, and already-hashed values are recognised and left alone, so a
 * half-finished run can simply be repeated.
 *
 * What it does:
 *   1. Widens wallets.withdrawal_pin from VARCHAR(6) to VARCHAR(255).
 *      A bcrypt digest is 60 characters and would be truncated at 6, which
 *      under strict mode is an error and without it would silently destroy
 *      the hash.
 *   2. Replaces plaintext passwords in `users` and `admins` with
 *      password_hash() digests.
 *   3. Replaces plaintext withdrawal PINs with digests.
 *
 * The plaintext values cannot be recovered afterwards. That is the point --
 * but it does mean anyone who knew a password from the admin panel's user
 * list no longer can, and support flows that relied on reading passwords back
 * need to become resets instead.
 */

require_once __DIR__ . '/../../bootstrap/api.php';

$pdo = $db->getConnection();

/** password_hash output always starts with $2y$ (bcrypt) or $argon. */
function already_hashed($value)
{
    return is_string($value) && preg_match('/^\$(2y|2a|2b|argon2)/', $value) === 1;
}

echo "Phase 2 credential migration\n";
echo "============================\n";
echo 'Database: ' . env('DB_NAME') . " on " . env('DB_HOST') . "\n\n";

// -- 1. Widen the PIN column -------------------------------------------------
$column = $pdo->query(
    "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wallets'
       AND COLUMN_NAME = 'withdrawal_pin'"
)->fetchColumn();

if ($column === false) {
    echo "  ! wallets.withdrawal_pin not found -- is this the right database?\n";
    exit(1);
}

if ((int) $column < 255) {
    $pdo->exec("ALTER TABLE wallets MODIFY withdrawal_pin VARCHAR(255) NOT NULL DEFAULT ''");
    echo "  widened wallets.withdrawal_pin {$column} -> 255\n";
} else {
    echo "  wallets.withdrawal_pin already {$column} chars, leaving it\n";
}

// -- 2. Hash account passwords ----------------------------------------------
foreach (['users', 'admins'] as $table) {
    $rows = $pdo->query("SELECT ID, passwrd FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("UPDATE {$table} SET passwrd = :hash WHERE ID = :id");

    $changed = 0;
    $skipped = 0;

    foreach ($rows as $row) {
        if ($row['passwrd'] === '' || already_hashed($row['passwrd'])) {
            $skipped++;
            continue;
        }

        $stmt->execute([
            ':hash' => password_hash($row['passwrd'], PASSWORD_DEFAULT),
            ':id'   => $row['ID'],
        ]);
        $changed++;
    }

    echo "  {$table}: hashed {$changed}, left {$skipped} (already hashed or empty)\n";
}

// -- 3. Hash withdrawal PINs -------------------------------------------------
$wallets = $pdo->query("SELECT ID, withdrawal_pin FROM wallets")->fetchAll(PDO::FETCH_ASSOC);
$stmt    = $pdo->prepare("UPDATE wallets SET withdrawal_pin = :hash WHERE ID = :id");

$changed = 0;
$skipped = 0;

foreach ($wallets as $wallet) {
    if ($wallet['withdrawal_pin'] === '' || already_hashed($wallet['withdrawal_pin'])) {
        $skipped++;
        continue;
    }

    $stmt->execute([
        ':hash' => password_hash($wallet['withdrawal_pin'], PASSWORD_DEFAULT),
        ':id'   => $wallet['ID'],
    ]);
    $changed++;
}

echo "  wallets: hashed {$changed} PIN(s), left {$skipped} (already hashed or unset)\n";

echo "\nDone.\n";
