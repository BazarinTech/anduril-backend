<?php
/**
 * Compatibility shim.
 *
 * The DbSessionHandler class and session startup moved to bootstrap/admin.php
 * in Phase 0. This file publishes the same variables as before ($db, $query,
 * $fileHelper, $curl, $api, $headers, $fileGetContent) and starts the session,
 * so admin/includes/main.php, login.php and register.php are unaffected.
 */

require_once __DIR__ . '/../../bootstrap/admin.php';
