<?php
/**
 * Compatibility shim.
 *
 * Configuration and service construction moved to bootstrap/ in Phase 0.
 * This file stays because ~20 endpoints under backend/mains/ include it by
 * relative path; it publishes exactly the same variables and constants as
 * before ($db, $query, $fileHelper, $dateHelper, $curl, $api, $headers,
 * $fileGetContent, $cryptions, JWT_SECRET, JWT_ALGO).
 */

require_once __DIR__ . '/../../bootstrap/api.php';
