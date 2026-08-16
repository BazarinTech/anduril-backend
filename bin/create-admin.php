<?php
/**
 * CREATE AN ADMINISTRATOR
 * =======================
 *     php bin/create-admin.php
 *     php bin/create-admin.php --email=you@example.com --name="Your Name" --username=you --phone=2547...
 *     php bin/create-admin.php --email=you@example.com --password='...' --permissions='[view][edit]'
 *
 * The way to get an admin account onto a live deployment.
 *
 * WHY NOT JUST SEED ONE
 * ---------------------
 * db/seed.sql creates administrators whose passwords are written in its own
 * comments -- admin123 and 445566gh. That is fine for a laptop and unacceptable
 * anywhere reachable, so bin/db-init.php will not apply it without
 * SEED_DEMO_DATA=1. This script fills the gap: an account whose password you
 * choose, hashed the same way the login path verifies it.
 *
 * Prompts for anything not supplied. The password prompt is not echoed, and a
 * password given as an argument is flagged, because command lines end up in
 * shell history and process listings.
 *
 * ROWS IT WRITES
 * --------------
 * Three, because admin/login.php authenticates against `users` and then
 * requires a matching `admins` row, and every balance read assumes a wallet:
 *
 *   users    the credential, role 'admin'
 *   admins   the panel permissions
 *   wallets  so the account does not break pages that read a balance
 *
 * CLI ONLY.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("create-admin.php is a maintenance script and cannot be run over HTTP.\n");
}

require_once __DIR__ . '/../bootstrap/api.php';

/** Read --key=value from the command line. */
function arg($name, $default = null)
{
    global $argv;

    foreach ($argv as $a) {
        if (strpos($a, "--{$name}=") === 0) {
            return substr($a, strlen($name) + 3);
        }
    }

    return $default;
}

function prompt($label, $default = '')
{
    $suffix = $default !== '' ? " [{$default}]" : '';
    echo "  {$label}{$suffix}: ";

    $line = trim((string) fgets(STDIN));

    return $line === '' ? $default : $line;
}

/** Read without echoing, so the password does not end up on screen. */
function prompt_secret($label)
{
    echo "  {$label}: ";

    // `stty -echo` is the portable-enough way; if it is unavailable the input
    // is visible, which is worth saying rather than failing.
    $hasStty = strlen((string) shell_exec('command -v stty')) > 0;

    if ($hasStty) {
        shell_exec('stty -echo');
    }

    $value = trim((string) fgets(STDIN));

    if ($hasStty) {
        shell_exec('stty echo');
    }

    echo "\n";

    if (!$hasStty) {
        echo "  (note: this terminal echoed the password)\n";
    }

    return $value;
}

echo "\nCreate an administrator\n";
echo "-----------------------\n";

$email = arg('email');

if ($email === null) {
    $email = prompt('Email');
}

$email = trim(strtolower($email));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "\n  '{$email}' is not a valid email address.\n\n");
    exit(1);
}

// A duplicate would create a second users row that login could match either
// way -- refuse rather than produce an ambiguous account.
if ($query->select('users', '*', ['email' => $email]) !== []) {
    fwrite(STDERR, "\n  An account already exists for {$email}.\n");
    fwrite(STDERR, "  Use --reset-password to set a new password on it instead.\n\n");

    if (!in_array('--reset-password', $argv, true)) {
        exit(1);
    }
}

$resetting = in_array('--reset-password', $argv, true)
    && $query->select('users', '*', ['email' => $email]) !== [];

$name     = arg('name')     ?? ($resetting ? '' : prompt('Full name', 'Administrator'));
$username = arg('username') ?? ($resetting ? '' : prompt('Username', explode('@', $email)[0]));
$phone    = arg('phone')    ?? ($resetting ? '' : prompt('Phone (2547...)', ''));

$password = arg('password');

if ($password !== null) {
    echo "\n  Warning: a password given on the command line is stored in your shell\n";
    echo "  history and visible in the process list. Prefer the prompt.\n";
} else {
    $password = prompt_secret('Password');
    $confirm  = prompt_secret('Confirm password');

    if ($password !== $confirm) {
        fwrite(STDERR, "\n  Passwords do not match.\n\n");
        exit(1);
    }
}

if (strlen($password) < 8) {
    fwrite(STDERR, "\n  Password must be at least 8 characters.\n\n");
    exit(1);
}

/**
 * Must contain [edit] or every action endpoint refuses this account -- the
 * guard in admin/actions/initiate.php checks for it explicitly.
 */
$permissions = arg('permissions', '[view][edit][add][finance]');

$hash = password_hash($password, PASSWORD_DEFAULT);

$pdo->beginTransaction();

try {
    if ($resetting) {
        $query->update('users', ['passwrd' => $hash], ['email' => $email]);
        $pdo->commit();

        echo "\n  Password updated for {$email}.\n\n";
        exit(0);
    }

    $query->insert('users', [
        'email'    => $email,
        'phone'    => $phone,
        'passwrd'  => $hash,
        'status'   => 'Active',
        'upline'   => 0,
        'name'     => $name,
        'username' => $username,
        'role'     => 'admin',
        'country'  => '254',
    ]);

    $userID = (int) $pdo->lastInsertId();

    // Pages that read a balance assume every account has one.
    $query->insert('wallets', ['userID' => $userID]);

    $query->insert('admins', [
        'userID'      => $userID,
        'roles'       => 'superadmin',
        'permissions' => $permissions,
        'status'      => 'Active',
        'username'    => $username,
        'name'        => $name,
        'email'       => $email,
        'phone'       => $phone,
    ]);

    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, "\n  Could not create the account: " . $e->getMessage() . "\n\n");
    exit(1);
}

echo "\n  Created administrator #{$userID}\n";
echo "    email:       {$email}\n";
echo "    username:    {$username}\n";
echo "    permissions: {$permissions}\n";
echo "\n  Sign in at /admin/login\n\n";

exit(0);
