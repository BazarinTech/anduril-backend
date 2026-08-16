<?php
/**
 * SHARED BOOTSTRAP
 * ================
 * The single place where configuration is read and shared services are built.
 * Do not require this file directly -- require one of the context bootstraps,
 * which set $BAZARIN_VENDOR first:
 *
 *   bootstrap/api.php    JSON API (backend/)   -> uses backend/vendor
 *   bootstrap/admin.php  Admin panel (admin/)  -> uses admin/vendor
 *
 * The two vendor/ trees are NOT interchangeable: backend/vendor carries
 * firebase/php-jwt and a hand-patched QueryBuilder with selectOR(), which
 * admin/vendor does not have. Keeping each context on its own tree preserves
 * today's behaviour exactly. See docs/AUTH.md.
 *
 * Everything below is defined in the *global* scope of whichever script
 * included it, so the variable names published here ($db, $query, $curl, ...)
 * are the same ones the ~35 existing endpoints already expect.
 */

if (!isset($BAZARIN_VENDOR)) {
    http_response_code(500);
    exit('bootstrap.php requires a context bootstrap (api.php or admin.php).');
}

require_once $BAZARIN_VENDOR;

use Bazarin\Database\Connection;
use Bazarin\Database\QueryBuilder;
use Bazarin\Helpers\DateHelper;
use Bazarin\Helpers\FileHelper;
use Bazarin\APIS\Curl;
use Bazarin\APIS\FileGetContent;
use Bazarin\Security\Cryptions;

/**
 * Read a configuration value.
 *
 * Lookup order:
 *   1. A real environment variable of the same name (production wins)
 *   2. config/env.php
 *   3. The $default argument
 *
 * Values arriving from the environment are strings, so booleans are
 * normalised here rather than at every call site.
 */
if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        static $config = null;

        if ($config === null) {
            $path   = __DIR__ . '/../config/env.php';
            $config = is_file($path) ? require $path : [];

            if (!is_array($config)) {
                $config = [];
            }
        }

        $value = getenv($key);

        if ($value === false) {
            $value = array_key_exists($key, $config) ? $config[$key] : $default;
        }

        if (is_string($value)) {
            switch (strtolower($value)) {
                case 'true':  return true;
                case 'false': return false;
                case 'null':  return null;
            }
        }

        return $value;
    }
}

/**
 * Fail loudly at boot for values that have no safe fallback, rather than
 * silently connecting to the wrong database or signing with an empty secret.
 */
if (!function_exists('env_required')) {
    function env_required(string $key)
    {
        $value = env($key);

        if ($value === null || $value === '') {
            // Two ways to supply it, and the advice differs by environment:
            // on a container host there is no config file to copy.
            error_log(
                "Configuration error: {$key} is not set. Set it as an environment "
                . "variable (Railway: service > Variables), or add it to config/env.php "
                . "(copy config/env.example.php)."
            );
            http_response_code(500);
            exit('Server configuration error.');
        }

        return $value;
    }
}

/**
 * ===========================
 * ERROR REPORTING
 * ===========================
 * Errors are always logged; they are only rendered when APP_DEBUG is on.
 * Previously this was set per-directory in .htaccess and so did not apply
 * to the backend/ tree at all.
 */
$appDebug = (bool) env('APP_DEBUG', false);

ini_set('log_errors', '1');
ini_set('display_errors', $appDebug ? '1' : '0');
error_reporting($appDebug ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);

/**
 * ===========================
 * TIME
 * ===========================
 * PHP and MySQL must agree on what "now" means.
 *
 * They did not. PHP defaulted to UTC while MySQL used the server's local zone
 * (EAT, +03:00), so strtotime() on any column read back from the database
 * produced a moment three hours in the future. Every age calculation in the
 * codebase came out negative, which silently disabled:
 *
 *   - verification code and password-reset expiry
 *   - coupon expiry (backend/mains/coupon.php)
 *   - the deposit throttle (backend/mains/deposit.php)
 *   - "users joined today" on the admin dashboard
 *
 * and skewed the investment countdown in mains.php -- which is why that file
 * carries a hand-rolled `$hours_neg = 3 * 60 * 60` correction.
 *
 * Both sides are now pinned to one configured zone. MySQL is given a numeric
 * offset rather than a name so this works on servers without the timezone
 * tables loaded.
 */
$appTimezone = (string) env('APP_TIMEZONE', 'Africa/Nairobi');
date_default_timezone_set($appTimezone);

/**
 * ===========================
 * DATABASE
 * ===========================
 */
$db = new Connection([
    'host'     => env_required('DB_HOST'),
    'user'     => env_required('DB_USER'),
    'password' => env_required('DB_PASS'),
    'database' => env_required('DB_NAME'),
    // Optional. Left unset it means 3306, which is what a database on the same
    // private network answers on. Managed databases reached through a proxy
    // do not, and the resulting failure never mentions the port.
    'port'     => env('DB_PORT', ''),
    'charset'  => env('DB_CHARSET', 'utf8mb4'),
]);

$db->getConnection()->exec(
    "SET time_zone = '" . (new DateTime('now', new DateTimeZone($appTimezone)))->format('P') . "'"
);

$query = new QueryBuilder($db->getConnection());

// Raw handle for the paths that need row locks and explicit transactions.
// QueryBuilder cannot express either, and it lives in vendor/.
$pdo = $db->getConnection();

require_once __DIR__ . '/ledger.php';
require_once __DIR__ . '/referrals.php';
require_once __DIR__ . '/claims.php';
// Where product images live, and how to build a URL for one.
require_once __DIR__ . '/../lib/storage.php';

/**
 * ===========================
 * HELPERS
 * ===========================
 */
$fileHelper = new FileHelper();
$dateHelper = new DateHelper();

/**
 * ===========================
 * HTTP CLIENTS
 * ===========================
 * $curl carries the Palpluss authorization header by default.
 * $api is a bare client for calls that must not send it.
 */
$paymentHeaders = ['Authorization: Basic ' . env('PALPLUSS_KEY', '')];

$curl = new Curl($paymentHeaders);
$api  = new Curl();

// Preserved for backwards compatibility: admin/includes/initiate.php and
// admin/actions/initiate.php both published a $headers variable.
$headers = $paymentHeaders;

/**
 * Build the callback URL handed to a payment provider.
 *
 * The shared secret rides along in the query string; the callback verifies it
 * on the way back in (backend/mains/callbacks/verify.php). Because the URL is
 * supplied per transaction rather than pre-registered with the provider,
 * rotating CALLBACK_TOKEN takes effect on the next payment with no
 * coordination needed.
 */
if (!function_exists('callback_url')) {
    function callback_url($script)
    {
        $base  = rtrim((string) env('CALLBACK_BASE_URL', ''), '/');
        $token = (string) env('CALLBACK_TOKEN', '');

        return $base . '/' . ltrim($script, '/') . '?t=' . rawurlencode($token);
    }
}

/**
 * ===========================
 * REQUEST / RESPONSE
 * ===========================
 * CORS_ORIGIN accepts a comma-separated allowlist. The request's own Origin is
 * echoed back when it is on the list, which is what credentialed requests
 * require -- a literal '*' is rejected by browsers when credentials are in
 * play, so the wildcard only remains meaningful for anonymous calls.
 */
$corsAllowed = array_filter(array_map('trim', explode(',', (string) env('CORS_ORIGIN', '*'))));
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array('*', $corsAllowed, true)) {
    $corsOrigin = '*';
} elseif ($requestOrigin !== '' && in_array($requestOrigin, $corsAllowed, true)) {
    $corsOrigin = $requestOrigin;
} else {
    // Not on the list: answer with the primary origin so the browser blocks
    // the response rather than us leaking it.
    $corsOrigin = $corsAllowed[0] ?? '';
}

// The response varies by request Origin, so caches must not share it.
if (!headers_sent() && $corsOrigin !== '*') {
    header('Vary: Origin');
}

$fileGetContent = new FileGetContent($corsOrigin);

/**
 * ===========================
 * SECURITY / ENCRYPTION
 * ===========================
 */
$cryptions = new Cryptions(env('CRYPT_KEY', 'key'));
