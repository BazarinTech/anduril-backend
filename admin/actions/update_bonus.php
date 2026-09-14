<?php
include 'initiate.php';
require_once __DIR__ . '/../includes/field-rules.php';

// Field rules and column widths: admin/includes/field-rules.php.
admin_update_action($query, $fileGetContent, 'bonus');
