<?php
/**
 * GENERATE THE DEPLOY VARIABLE LIST
 * =================================
 *     php bin/railway-env.php              # names + guidance, secrets redacted
 *     php bin/railway-env.php --values     # real values, ready to paste
 *
 * Reads config/env.php and prints the KEY=VALUE block to paste into
 * Railway > anduril-backend > Variables > Raw Editor.
 *
 * WHY THIS EXISTS
 * ---------------
 * bootstrap.php reads a real environment variable before it reads
 * config/env.php, so a deployed service needs no config file at all -- which
 * is the right shape for a container, where the file would either be baked
 * into the image or missing. The catch is that the list of keys is spread
 * across ~40 call sites, and one missing DB_PASS is a 500 on every request.
 * This prints the list from a single source.
 *
 * SECRETS ARE REDACTED BY DEFAULT. Pass --values only when you are about to
 * paste them somewhere, and remember the terminal keeps scrollback.
 *
 * CLI ONLY.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This is a maintenance script and cannot be run over HTTP.\n");
}

$showValues = in_array('--values', $argv, true);

$configPath = __DIR__ . '/../config/env.php';

if (!is_file($configPath)) {
    fwrite(STDERR, "config/env.php not found. Nothing to read local values from.\n");
    exit(1);
}

$config = require $configPath;

/**
 * Keys whose local value is meaningless in production, with what to use
 * instead. Pasting the local values for these is the mistake this guards
 * against -- a deployed API pointed at a laptop's database.
 */
$overrides = [
    'DB_HOST' => '${{MySQL.MYSQLHOST}}',
    'DB_NAME' => '${{MySQL.MYSQLDATABASE}}',
    'DB_USER' => '${{MySQL.MYSQLUSER}}',
    'DB_PASS' => '${{MySQL.MYSQLPASSWORD}}',
    'DB_PORT' => '${{MySQL.MYSQLPORT}}',
];

$notes = [
    'DB_HOST'  => 'Railway reference variable. Change "MySQL" to whatever the database service is called.',
    'DB_NAME'  => 'Same.',
    'DB_USER'  => 'Same.',
    'DB_PASS'  => 'Same.',
    'APP_URL'  => 'The deployed backend URL. Used to build the image proxy URL and links in emails.',
    'APP_DEBUG'=> 'Must stay false. True renders stack traces to the browser.',
    'CORS_ORIGIN' => 'Pin this to the frontend origin. "*" is still open.',
    'CALLBACK_BASE_URL' => 'Must be the DEPLOYED backend, or the payment provider posts callbacks into the void.',
    'JWT_SECRET' => 'Must match the frontend byte for byte.',
    'S3_ENDPOINT' => 'From the bucket service. Can be a ${{roomy-wardrobe.*}} reference.',
];

/** Secrets: redacted unless --values. */
$secretish = [
    'DB_PASS', 'JWT_SECRET', 'PALPLUSS_KEY', 'CALLBACK_TOKEN',
    'SMTP_PASS', 'SMS_PASSWORD', 'SMS_API_KEY', 'CRYPT_KEY',
    'S3_ACCESS_KEY_ID', 'S3_SECRET_ACCESS_KEY',
];

/**
 * In git history and therefore already public. Copying these to a new
 * environment carries the exposure forward.
 */
$rotate = ['DB_PASS', 'PALPLUSS_KEY', 'SMTP_PASS', 'SMS_API_KEY'];

// Keys read only by local development.
$skip = ['UPLOAD_DIR', 'LOCAL_UPLOAD_BASE_URL'];

echo "# ------------------------------------------------------------------\n";
echo "# Railway > anduril-backend > Variables > Raw Editor\n";
echo "#\n";
echo "# A real environment variable beats config/env.php, so the deployed\n";
echo "# service needs no config file. config/env.php stays gitignored and\n";
echo "# local-only.\n";
if (!$showValues) {
    echo "#\n# Secrets are redacted. Re-run with --values to print them.\n";
}
echo "# ------------------------------------------------------------------\n\n";

$rotateSeen = [];

foreach ($config as $key => $value) {
    if (in_array($key, $skip, true)) {
        continue;
    }

    if (isset($notes[$key])) {
        echo "# {$notes[$key]}\n";
    }

    if (in_array($key, $rotate, true)) {
        echo "# !! This value is in git history. Rotate it at the provider rather than reusing it.\n";
        $rotateSeen[] = $key;
    }

    if (array_key_exists($key, $overrides)) {
        echo "{$key}={$overrides[$key]}\n\n";
        continue;
    }

    if (is_bool($value)) {
        $printable = $value ? 'true' : 'false';
    } elseif ($value === null) {
        $printable = '';
    } else {
        $printable = (string) $value;
    }

    if (!$showValues && in_array($key, $secretish, true) && $printable !== '') {
        $printable = '<redacted, ' . strlen($printable) . ' chars>';
    }

    echo "{$key}={$printable}\n";

    if (isset($notes[$key]) || in_array($key, $rotate, true)) {
        echo "\n";
    }
}

echo "\n# ------------------------------------------------------------------\n";
echo "# Before you paste:\n";
echo "#\n";
echo "#   APP_URL, CALLBACK_BASE_URL and CORS_ORIGIN below still hold local\n";
echo "#   or old-host values. Set them to the deployed URLs.\n";

if ($rotateSeen) {
    echo "#\n#   Rotate first: " . implode(', ', $rotateSeen) . "\n";
}

echo "# ------------------------------------------------------------------\n";
