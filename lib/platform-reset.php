<?php
/**
 * PLATFORM RESET
 * ==============
 * Removes every non-admin account and everything that belongs to one, leaving
 * the platform's configuration in place. Used from admin/platform-control.php.
 *
 * WHAT GOES
 *   users                 every row that is not an admin's login
 *   wallets               balances, incomes and the withdrawal account/PIN,
 *                         which live on the wallet row
 *   transactions          deposits, withdrawals, transfers, commissions ...
 *   orders                investments and bonus/coupon/reward redemptions
 *   incentives_requests   incentive applications
 *   verification_codes    outstanding SMS codes for the removed phones
 *
 * WHAT STAYS
 *   admins and the `users` rows they sign in with (admin login authenticates
 *   against `users`, so deleting those rows would lock every admin out), with
 *   their own wallets, transactions and orders; products and their images,
 *   bonuses, coupons, incentives, controls, and admin sessions.
 *
 * WHY "NOT AN ADMIN" RATHER THAN "BELONGS TO A REMOVED USER"
 * Deleting a single user from the Users page leaves that user's transactions
 * and orders behind on purpose. Keying the reset on the removed users' IDs
 * would leave those orphans in place after a "reset", so every dependent
 * table is cleared of rows whose owner is not an admin -- orphans included.
 *
 * All of it runs in one database transaction: a failure part way through
 * rolls back to the state before the reset, never to half a platform.
 */

if (!function_exists('platform_reset_admin_ids')) {
    /**
     * The user IDs admins sign in with, as positive integers.
     */
    function platform_reset_admin_ids(PDO $pdo)
    {
        $ids = [];

        foreach ($pdo->query('SELECT userID FROM admins')->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $id = (int) $id;

            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }
}

if (!function_exists('platform_reset_plan')) {
    /**
     * The WHERE clause, parameters and label for each table the reset touches.
     *
     * `transactions.userID` and `incentives_requests.userID` are VARCHAR while
     * the admin IDs are integers. Comparing a string column against integer
     * literals makes MySQL cast every row to a number, so '7abc' would count as
     * user 7. The IDs are bound as strings there instead, which compares them
     * exactly -- and a row whose userID is not an admin's exact ID is removed.
     */
    function platform_reset_plan(array $adminIds, array $adminPhones)
    {
        $marks   = implode(',', array_fill(0, count($adminIds), '?'));
        $asInt   = array_map('intval', $adminIds);
        $asStr   = array_map('strval', $adminIds);

        $plan = [
            'transactions'        => ['label' => 'Transactions',          'where' => "userID NOT IN ($marks)", 'params' => $asStr],
            'orders'              => ['label' => 'Orders',                'where' => "userID NOT IN ($marks)", 'params' => $asInt],
            'incentives_requests' => ['label' => 'Incentive applications', 'where' => "userID NOT IN ($marks)", 'params' => $asStr],
            'wallets'             => ['label' => 'Wallets',               'where' => "userID NOT IN ($marks)", 'params' => $asInt],
        ];

        // Codes are keyed by phone, not user. Keep only the admins' own.
        if ($adminPhones) {
            $phoneMarks = implode(',', array_fill(0, count($adminPhones), '?'));
            $plan['verification_codes'] = ['label' => 'Verification codes', 'where' => "phone NOT IN ($phoneMarks)", 'params' => array_values($adminPhones)];
        } else {
            $plan['verification_codes'] = ['label' => 'Verification codes', 'where' => '1 = 1', 'params' => []];
        }

        // Users last, so nothing above can be left pointing at a user that
        // has already gone if the order of these ever matters.
        $plan['users'] = ['label' => 'User accounts', 'where' => "ID NOT IN ($marks)", 'params' => $asInt];

        return $plan;
    }
}

if (!function_exists('platform_reset_context')) {
    /**
     * Admin IDs and phones, or null when there is no admin to preserve.
     */
    function platform_reset_context(PDO $pdo)
    {
        $adminIds = platform_reset_admin_ids($pdo);

        if (!$adminIds) {
            return null;
        }

        $marks = implode(',', array_fill(0, count($adminIds), '?'));
        $stmt  = $pdo->prepare("SELECT phone FROM users WHERE ID IN ($marks)");
        $stmt->execute($adminIds);

        $phones = array_values(array_unique(array_filter(
            array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)),
            'strlen'
        )));

        return ['admin_ids' => $adminIds, 'admin_phones' => $phones];
    }
}

if (!function_exists('platform_reset_preview')) {
    /**
     * How many rows a reset would remove right now, per table.
     *
     * Also reports how many withdrawal accounts are set up among the wallets
     * that would go, because admins think of those separately even though they
     * are columns on the wallet row.
     */
    function platform_reset_preview(PDO $pdo)
    {
        $context = platform_reset_context($pdo);

        if ($context === null) {
            return null;
        }

        $counts = [];

        foreach (platform_reset_plan($context['admin_ids'], $context['admin_phones']) as $table => $step) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$step['where']}");
            $stmt->execute($step['params']);
            $counts[$table] = ['label' => $step['label'], 'count' => (int) $stmt->fetchColumn()];
        }

        $wallets = platform_reset_plan($context['admin_ids'], $context['admin_phones'])['wallets'];
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM wallets WHERE {$wallets['where']} AND withdrawal_account <> ''");
        $stmt->execute($wallets['params']);

        return [
            'counts'              => $counts,
            'withdrawal_accounts' => (int) $stmt->fetchColumn(),
            'admins_kept'         => count($context['admin_ids']),
        ];
    }
}

if (!function_exists('platform_reset_run')) {
    /**
     * Perform the reset. Returns rows removed per table.
     *
     * Throws on failure, after rolling back. The caller decides what to show.
     *
     * $actingAdminId must be one of the preserved admin IDs. That is always
     * true for a signed-in admin, and checking it here means a caller that got
     * the ID wrong cannot run a reset that deletes the person running it.
     */
    function platform_reset_run(PDO $pdo, $actingAdminId)
    {
        $pdo->beginTransaction();

        try {
            // Read the admin list inside the transaction, locked, so an admin
            // added or removed while this runs cannot change what is kept.
            $pdo->query('SELECT ID FROM admins FOR UPDATE')->fetchAll();

            $context = platform_reset_context($pdo);

            if ($context === null) {
                throw new RuntimeException('No admin accounts exist; refusing to remove every user.');
            }

            if (!in_array((int) $actingAdminId, $context['admin_ids'], true)) {
                throw new RuntimeException('The acting account is not an admin; refusing to reset.');
            }

            $removed = [];

            foreach (platform_reset_plan($context['admin_ids'], $context['admin_phones']) as $table => $step) {
                $stmt = $pdo->prepare("DELETE FROM {$table} WHERE {$step['where']}");
                $stmt->execute($step['params']);
                $removed[$table] = ['label' => $step['label'], 'count' => $stmt->rowCount()];
            }

            // An admin who registered through someone's referral link would
            // otherwise keep pointing at a user who no longer exists.
            $marks = implode(',', array_fill(0, count($context['admin_ids']), '?'));
            $stmt  = $pdo->prepare("UPDATE users SET upline = 0 WHERE upline <> 0 AND upline NOT IN ($marks)");
            $stmt->execute($context['admin_ids']);

            $pdo->commit();

            return $removed;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}
