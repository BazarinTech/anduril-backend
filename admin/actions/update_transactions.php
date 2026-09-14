<?php
include 'initiate.php';
require_once __DIR__ . '/../includes/field-rules.php';

// Phase 4.11 -- `email` is the owning user's address, joined in for display
// and not a column here. Editing a row only relabels it; it does not move
// money. Field rules: admin/includes/field-rules.php.
admin_update_action($query, $fileGetContent, 'transactions');
