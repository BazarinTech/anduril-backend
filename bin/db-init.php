<?php
/**
 * IDEMPOTENT DATABASE INITIALISER
 * ===============================
 *     php bin/db-init.php            # apply what is missing
 *     php bin/db-init.php --dry-run  # report what it would do
 *
 * Brings an empty or partially-built database up to date, and does nothing at
 * all to one that is already current. Run automatically at container start
 * (docker/entrypoint.sh) unless SKIP_DB_INIT=1.
 *
 * It applies, in order and only where needed:
 *
 *   1. db/schema.sql          -- every table is CREATE TABLE IF NOT EXISTS,
 *                                so missing ones appear and existing ones are
 *                                left exactly as they are.
 *   2. db/seed-required.sql   -- the `controls` row, and only when that table
 *                                is empty. The application cannot boot without
 *                                it: every read is select('controls')[0] with
 *                                no empty check.
 *   3. db/seed.sql            -- development data. NEVER automatic. Opt in with
 *                                SEED_DEMO_DATA=1, and read the warning below
 *                                first.
 *   4. db/migrations/*.php    -- each already idempotent; they check
 *                                information_schema before altering anything.
 *
 * WHY DEMO DATA IS NOT AUTOMATIC
 * ------------------------------
 * db/seed.sql creates two administrator accounts and five users whose
 * passwords are printed in its own comments -- admin123, 445566gh, test1234.
 * Seeding that into a live environment hands anyone who has read the
 * repository an admin login on a platform that moves money. It stays behind an
 * explicit flag for that reason, and the flag should never be set in
 * production.
 *
 * CONCURRENCY
 * -----------
 * A platform can start several replicas at once, and all of them would run
 * this at the same moment. MySQL's GET_LOCK serialises them: the first to
 * arrive does the work, the others wait and then find nothing left to do.
 * Without it, two containers race to CREATE TABLE and one gets an error that
 * takes the deploy down.
 *
 * CLI ONLY.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("db-init.php is a maintenance script and cannot be run over HTTP.\n");
}

$dryRun = in_array('--dry-run', $argv, true);

require_once __DIR__ . '/../bootstrap/api.php';

$root = __DIR__ . '/..';
$pdo  = $db->getConnection();
$name = env('DB_NAME');

$applied = [];
$skipped = [];
$ran     = [];

function say($line)
{
    echo '  ' . $line . "\n";
}

echo "\n[db-init] database: {$name}" . ($dryRun ? '  (dry run)' : '') . "\n\n";

/* -------------------------------------------------------------------------
 * Serialise concurrent starts.
 * ---------------------------------------------------------------------- */
if (!$dryRun) {
    $lock = $pdo->prepare('SELECT GET_LOCK(?, ?)');
    $lock->execute(['anduril_db_init', 60]);

    if ((int) $lock->fetchColumn() !== 1) {
        // Another replica held it for the full minute. That one is doing the
        // work; carrying on regardless would be the race this guards against.
        say('Another instance holds the init lock. Skipping.');
        exit(0);
    }
}

try {
    /* ---------------------------------------------------------------------
     * 1. Tables
     * ------------------------------------------------------------------ */
    $expected = [];
    $schemaSql = file_get_contents($root . '/db/schema.sql');

    if ($schemaSql === false) {
        fwrite(STDERR, "  db/schema.sql is missing.\n");
        exit(1);
    }

    preg_match_all('/CREATE TABLE IF NOT EXISTS (\w+)/i', $schemaSql, $m);
    $expected = $m[1];

    $stmt = $pdo->prepare(
        'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?'
    );
    $stmt->execute([$name]);
    $present = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $missing = array_values(array_diff($expected, $present));

    if ($missing === []) {
        say('Tables:  all ' . count($expected) . ' present, nothing to create.');
        $skipped[] = 'schema';
    } else {
        say('Tables:  ' . count($missing) . ' missing -> ' . implode(', ', $missing));

        if (!$dryRun) {
            // Safe to run whole: every statement is IF NOT EXISTS, so the
            // tables that already exist are untouched.
            $pdo->exec($schemaSql);
            say('         created.');
        }

        $applied[] = 'schema (' . count($missing) . ' tables)';
    }

    /* ---------------------------------------------------------------------
     * 2. The required settings row
     * ------------------------------------------------------------------ */
    $controls = 0;

    if (in_array('controls', $present, true) || $missing !== []) {
        try {
            $controls = (int) $pdo->query('SELECT COUNT(*) FROM controls')->fetchColumn();
        } catch (\Throwable $e) {
            $controls = 0; // table was only just created in a dry run
        }
    }

    if ($controls > 0) {
        say('Settings: controls row already present.');
        $skipped[] = 'seed-required';
    } else {
        say('Settings: controls table is empty -> seeding platform defaults.');

        if (!$dryRun) {
            $pdo->exec(file_get_contents($root . '/db/seed-required.sql'));
            say('         seeded.');
        }

        $applied[] = 'seed-required';
    }

    /* ---------------------------------------------------------------------
     * 3. Development data -- explicit opt-in only
     * ------------------------------------------------------------------ */
    $wantDemo = (string) env('SEED_DEMO_DATA', '0') === '1';

    if (!$wantDemo) {
        say('Demo data: not requested (SEED_DEMO_DATA is not 1). Skipping.');
        $skipped[] = 'seed-demo';
    } else {
        $users = 0;

        try {
            $users = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        } catch (\Throwable $e) {
            $users = 0;
        }

        if ($users > 0) {
            say('Demo data: users table is not empty. Skipping.');
            $skipped[] = 'seed-demo';
        } else {
            say('Demo data: SEED_DEMO_DATA=1 and no users exist -> seeding.');
            say('           !! This creates accounts with passwords published in');
            say('           !! db/seed.sql. Never set this flag in production.');

            if (!$dryRun) {
                $pdo->exec(file_get_contents($root . '/db/seed.sql'));
                say('           seeded.');
            }

            $applied[] = 'seed-demo';
        }
    }

    /* ---------------------------------------------------------------------
     * 4. Migrations
     * ------------------------------------------------------------------ */
    $migrations = glob($root . '/db/migrations/*.php');
    sort($migrations);

    say('Migrations: ' . count($migrations) . ' found.');

    foreach ($migrations as $migration) {
        $label = basename($migration);

        if ($dryRun) {
            say('           would run ' . $label);
            continue;
        }

        /**
         * Each runs in its own process. They are written as standalone
         * scripts that bootstrap themselves, and including them here would
         * redeclare every function this file has already loaded.
         */
        $descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(PHP_BINARY . ' ' . escapeshellarg($migration), $descriptor, $pipes);

        if (!is_resource($process)) {
            fwrite(STDERR, "           could not start {$label}\n");
            exit(1);
        }

        $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);

        if ($status !== 0) {
            fwrite(STDERR, "           FAILED {$label}\n" . $out . "\n");
            exit(1);
        }

        /**
         * Migrations are not reported as "applied".
         *
         * Whether one changed anything can only be inferred from its stdout,
         * and they do not agree on how to say it -- 001 prints "hashed 0",
         * 004 prints "nothing to do". Guessing produced a summary claiming
         * work that had not happened, which is worse than saying less. They
         * are idempotent and self-reporting, so this records that each ran
         * cleanly and leaves the detail to their own output.
         */
        $ran[] = $label;
        say('           ok  ' . $label);
    }
} finally {
    if (!$dryRun) {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute(['anduril_db_init']);
    }
}

echo "\n";

if ($applied === []) {
    say('Schema and seed: nothing to do, already up to date.');
} else {
    say('Applied: ' . implode(', ', $applied));
}

if ($ran !== []) {
    say('Migrations run cleanly: ' . count($ran) . ' (see their output above for what each changed).');
}

echo "\n";
exit(0);
