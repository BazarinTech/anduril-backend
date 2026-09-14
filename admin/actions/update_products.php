<?php
include 'initiate.php';
require_once __DIR__ . '/../includes/field-rules.php';

/**
 * invest.php accepts an amount between `min` and `max`. A price set below the
 * minimum leaves no amount that passes, so the product silently becomes
 * impossible to buy. Field rules: admin/includes/field-rules.php.
 */
admin_update_action($query, $fileGetContent, 'products', function ($field, $value, $row) {
    if ($field === 'max' && (float) $value < (float) $row['min']) {
        return 'Price cannot be lower than the product minimum (' . $row['min'] . ').';
    }

    if ($field === 'min' && (float) $value > (float) $row['max']) {
        return 'Minimum cannot be higher than the price (' . $row['max'] . ').';
    }

    return null;
});
