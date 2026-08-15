<?php
/**
 * API CONTEXT BOOTSTRAP
 * =====================
 * For everything under backend/ -- the JSON endpoints and payment callbacks.
 *
 * Uses backend/vendor, which is the only tree carrying firebase/php-jwt and
 * the QueryBuilder::selectOR() that auth.php and transfer.php depend on.
 */

$BAZARIN_VENDOR = __DIR__ . '/../backend/vendor/autoload.php';

require_once __DIR__ . '/bootstrap.php';

/**
 * ===========================
 * JWT
 * ===========================
 * Tokens are *minted* by the frontend BFF route (anduril-market:
 * app/(auth)/api/auth/route.ts) and only *verified* here. JWT_SECRET must be
 * byte-identical on both sides. See docs/AUTH.md for why it lives there and
 * what still needs fixing about it.
 */
if (!defined('JWT_SECRET')) {
    define('JWT_SECRET', env_required('JWT_SECRET'));
}

if (!defined('JWT_ALGO')) {
    define('JWT_ALGO', env('JWT_ALGO', 'HS256'));
}
