<?php
/**
 * Compatibility shim for the payment callbacks.
 *
 * Was a byte-for-byte duplicate of backend/includes/initiate.php, including
 * its own copy of the database credentials. Both now resolve to the same
 * bootstrap.
 */

require_once __DIR__ . '/../../../bootstrap/api.php';
