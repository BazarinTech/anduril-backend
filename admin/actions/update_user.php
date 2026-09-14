<?php
include 'initiate.php';
require_once __DIR__ . '/../includes/field-rules.php';

/**
 * Email and phone are what users sign in with, so both must stay unique: a
 * second account with the same address would make login pick whichever row
 * the database returns first. The rules enforce that, and no longer accept
 * `upline` -- the Users page shows it as read-only because changing it
 * re-points referral commission. See admin/includes/field-rules.php.
 */
admin_update_action($query, $fileGetContent, 'users');
