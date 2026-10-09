<?php
include 'initiate.php';
require_once __DIR__ . '/../includes/field-rules.php';

/**
 * Email and phone are what users sign in with, so both must stay unique: a
 * second account with the same address would make login pick whichever row
 * the database returns first. See admin/includes/field-rules.php.
 *
 * `upline` is the referrer's user ID. Re-pointing it moves future referral
 * commission to a different person, so it is checked rather than trusted:
 * the id must belong to a real account, nobody may be their own referrer, and
 * the chain must not close into a loop -- referral_commission() walks up three
 * levels, and a cycle there would pay the same people round and round until
 * its own guard stopped it.
 */
admin_update_action($query, $fileGetContent, 'users', function ($field, $value, $row) use ($query) {
    if ($field !== 'upline') {
        return null;
    }

    $uplineID = (int) $value;
    $userID   = (int) $row['ID'];

    // 0 is "no referrer", which is what a direct sign-up carries.
    if ($uplineID === 0) {
        return null;
    }

    if ($uplineID === $userID) {
        return 'A user cannot be their own upline.';
    }

    $upline = $query->select('users', '*', ['ID' => $uplineID])[0] ?? null;

    if ($upline === null) {
        return 'No user has ID ' . $uplineID . '. Use the # column to find the referrer.';
    }

    // Walk up from the proposed upline: if this user appears above them, the
    // change would close a loop.
    $seen    = [$uplineID => true];
    $current = $upline;

    for ($hops = 0; $hops < 20; $hops++) {
        $next = (int) ($current['upline'] ?? 0);

        if ($next === 0) {
            break;
        }

        if ($next === $userID) {
            return 'That would create a referral loop: ' . ($upline['email'] ?? ('user ' . $uplineID))
                . ' is already below this user.';
        }

        if (isset($seen[$next])) {
            break;
        }

        $seen[$next] = true;
        $current = $query->select('users', '*', ['ID' => $next])[0] ?? null;

        if ($current === null) {
            break;
        }
    }

    return null;
});
