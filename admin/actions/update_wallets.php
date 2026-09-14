<?php
include 'initiate.php';
require_once __DIR__ . '/../includes/field-rules.php';

// Phase 4.11 -- `email` lives on users, `rolls` on orders, and `total_income` /
// `bonus_income` do not exist; the UI's "total income" is the `income` column.
// Field rules: admin/includes/field-rules.php.
admin_update_action($query, $fileGetContent, 'wallets');
