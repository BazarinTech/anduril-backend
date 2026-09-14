<?php
include 'initiate.php';
require_once __DIR__ . '/../includes/field-rules.php';

/**
 * An admin may not change their own permissions or status.
 *
 * Without this, any admin holding [edit] could grant themselves [finance] and
 * [add] from the Admins table in one click -- and could equally remove their
 * own [edit] or deactivate themselves by accident, with nobody left able to
 * put it back. Changes to one admin now always come from another, and nobody
 * can grant a permission they do not hold. An admin who holds something the
 * acting admin lacks cannot have their permissions or status changed by them
 * at all, so a lesser admin cannot strip or deactivate a fuller one.
 * Field rules: admin/includes/field-rules.php.
 */
admin_update_action($query, $fileGetContent, 'admins', function ($field, $value, $row) use ($query) {
    $self = (int) ($row['userID'] ?? 0) === (int) ($_SESSION['userID'] ?? -1);

    if ($self && in_array($field, ['permissions', 'status'], true)) {
        return 'You cannot change your own ' . $field . '. Ask another admin to do it.';
    }

    // An admin holding a permission you lack is out of reach: removing their
    // [edit] would leave them unable to undo it, and deactivating them is as
    // consequential as stripping their rights.
    if (in_array($field, ['permissions', 'status'], true) && admin_outranks($query, $row['permissions'] ?? '')) {
        return 'You cannot change the ' . $field . ' of an admin who has permissions you do not.';
    }

    if ($field === 'permissions') {
        return admin_grant_problem($query, $value, $row['permissions'] ?? '');
    }

    return null;
});
