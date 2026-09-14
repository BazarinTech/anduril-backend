<?php
include 'initiate.php';
require_once __DIR__ . '/../includes/field-rules.php';

// Phase 4.11 -- status is the only thing an admin can meaningfully change on
// an order; `email` is joined in from users for display. See
// admin/includes/field-rules.php.
admin_update_action($query, $fileGetContent, 'orders');
