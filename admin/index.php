<?php
/**
 * /admin ENTRY POINT
 * ==================
 * The panel has no page at its own root, so /admin used to answer 403/404 and
 * the only way in was to already know the login URL. This sends a visitor to
 * the right place instead: the dashboard when they are signed in as an admin,
 * the login screen otherwise.
 *
 * The redirect is a root-relative path built from this script's own location
 * rather than a bare 'login'. A bare name resolves against the URL the browser
 * is on, and /admin (no trailing slash) would send it to /login -- outside the
 * panel.
 */

require_once __DIR__ . '/includes/initiate.php';

$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php')), '/');

header('Location: ' . $base . '/' . (admin_identity($query) !== null ? 'dashboard' : 'login'), true, 302);
exit;
