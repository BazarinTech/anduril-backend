<?php
/**
 * ROUTER FOR PHP'S BUILT-IN SERVER
 * ===============================
 * Only used for local development:
 *
 *     php -S 127.0.0.1:8090 -t . bin/router.php
 *
 * Production runs on Apache/LiteSpeed, where admin/.htaccess rewrites
 * extensionless URLs onto .php files:
 *
 *     RewriteCond %{REQUEST_FILENAME} !-d
 *     RewriteCond %{REQUEST_FILENAME}\.php -f
 *     RewriteRule ^([^/]+)/?$ $1.php [L]
 *
 * The built-in server does not read .htaccess, so without this every
 * extensionless link 404s locally -- and the panel navigates entirely by
 * extensionless paths. `header('Location: dashboard')` after login, every
 * sidebar link, every form action. The app is effectively unusable on
 * `php -S` without it.
 *
 * This reproduces that one rule, and nothing else.
 */

$docroot = realpath(__DIR__ . '/..');
$path    = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Refuse anything trying to climb out of the document root.
$requested = realpath($docroot . $path);

if ($requested !== false && strpos($requested, $docroot) !== 0) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Directories that carry their own deny-all .htaccess in production.
 *
 * The built-in server ignores .htaccess, so without this the local server
 * behaves *less* strictly than the real one -- which is the wrong direction
 * for a dev environment to differ in.
 */
foreach (['/config/', '/bootstrap/', '/lib/', '/bin/', '/db/'] as $denied) {
    if (strpos($path, $denied) === 0) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "403 Forbidden: {$denied} is not web-accessible\n";
        return true;
    }
}

$target = $docroot . $path;

// A real file (asset, or an explicit .php URL): let the server serve it.
if ($path !== '/' && is_file($target)) {
    return false;
}

// Extensionless -> .php, the rewrite above.
if (is_file($target . '.php')) {
    $script = $target . '.php';

    // Match what Apache reports, so anything reading these sees the real
    // script rather than the router.
    $_SERVER['SCRIPT_FILENAME'] = $script;
    $_SERVER['SCRIPT_NAME']     = $path . '.php';
    $_SERVER['PHP_SELF']        = $path . '.php';

    // Apache runs a script with its own directory as the working directory.
    // The endpoints include by relative path ('includes/main.php'), so this
    // has to match or those includes resolve against the docroot instead.
    chdir(dirname($script));

    require $script;
    return true;
}

// Directory: try its index.php, as the server would.
if (is_dir($target) && is_file(rtrim($target, '/') . '/index.php')) {
    $script = rtrim($target, '/') . '/index.php';

    $_SERVER['SCRIPT_FILENAME'] = $script;
    $_SERVER['SCRIPT_NAME']     = rtrim($path, '/') . '/index.php';

    chdir(dirname($script));

    require $script;
    return true;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "404 Not Found: {$path}\n";
return true;
