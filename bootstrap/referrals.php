<?php
/**
 * REFERRAL COMMISSION
 * ===================
 * One implementation, shared by the deposit callback and the admin deposit
 * approval. There were previously two, and they disagreed:
 *
 *   - backend/mains/callbacks/palpluss_deposit_callback.php paid three levels
 *   - admin/approve-deposits.php paid two
 *
 * So the same deposit paid different commission depending on whether it
 * settled automatically or an admin approved it by hand (finding 4.3).
 *
 * The old version also walked the tree with three copy-pasted blocks and read
 * the level-3 upline *outside* the guard that checked whether a level-2 upline
 * existed, so any deposit from a user near the top of the tree dereferenced a
 * missing row. That is the "Undefined array key 0" warning pair and the
 * `Column 'userID' cannot be null` fatal in callbacks/error_log (finding 4.4).
 *
 * Written as a walk instead: climb until the tree runs out or the configured
 * levels are exhausted, whichever comes first.
 *
 * MUST be called inside a transaction -- it takes row locks and is part of the
 * caller's atomic credit.
 */

/**
 * Fetch a user's three-level referral tree in a fixed number of queries.
 *
 * Phase 5.1. The dashboard used to build this with nested loops: for every
 * level 1 referral it re-read the controls row, re-queried that person's
 * deposits, counted their downlines, then did the same for each level 2 and
 * level 3 member individually. A 200-person team cost over 900 queries and
 * grew quadratically -- a large team could time the request out entirely.
 *
 * This walks the tree one level at a time (three queries) and then resolves
 * deposits and downline counts for everyone at once (two more), so the cost is
 * five queries no matter how large the team is.
 *
 * Returns each level as a list of rows plus the derived figures the dashboard
 * and the bonus check both need.
 */
if (!function_exists('referral_tree')) {
    function referral_tree(PDO $pdo, $userID)
    {
        /** Fetch all users whose upline is in $parentIds. */
        $childrenOf = function (array $parentIds) use ($pdo) {
            if (empty($parentIds)) {
                return [];
            }

            $in   = implode(',', array_fill(0, count($parentIds), '?'));
            $stmt = $pdo->prepare("SELECT * FROM users WHERE upline IN ($in) ORDER BY ID DESC");
            $stmt->execute(array_values($parentIds));

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        };

        $level1 = $childrenOf([$userID]);
        $level2 = $childrenOf(array_column($level1, 'ID'));
        $level3 = $childrenOf(array_column($level2, 'ID'));

        $everyone = array_merge($level1, $level2, $level3);
        $allIds   = array_column($everyone, 'ID');

        // Settled deposits per person, in one pass.
        $deposits = [];
        if (!empty($allIds)) {
            $in   = implode(',', array_fill(0, count($allIds), '?'));
            $stmt = $pdo->prepare(
                "SELECT userID, SUM(amount) AS total FROM transactions
                  WHERE type = 'Deposit' AND status = 'Success' AND userID IN ($in)
                  GROUP BY userID"
            );
            $stmt->execute($allIds);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $deposits[(string) $row['userID']] = money($row['total']);
            }
        }

        // Direct downline counts per person, in one pass.
        $downlines = [];
        if (!empty($allIds)) {
            $in   = implode(',', array_fill(0, count($allIds), '?'));
            $stmt = $pdo->prepare(
                "SELECT upline, COUNT(*) AS total FROM users WHERE upline IN ($in) GROUP BY upline"
            );
            $stmt->execute($allIds);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $downlines[(string) $row['upline']] = (int) $row['total'];
            }
        }

        // Phone lookup for the "referred by" column, built from rows already
        // in hand rather than a query per person.
        $phoneById = [];
        foreach ($everyone as $person) {
            $phoneById[(string) $person['ID']] = $person['phone'];
        }

        $activesDirect = 0;
        foreach ($level1 as $person) {
            if ($person['status'] === 'Active') {
                $activesDirect++;
            }
        }

        return [
            'level1'    => $level1,
            'level2'    => $level2,
            'level3'    => $level3,
            'deposits'  => $deposits,
            'downlines' => $downlines,
            'phoneById' => $phoneById,
            'counts'    => [
                'users'   => count($everyone),
                'actives' => $activesDirect,
            ],
            'total_deposits' => array_sum($deposits),
        ];
    }
}

/**
 * How many downlines count toward a bonus target.
 *
 * Phase 4.2. The dashboard and the claim endpoint disagreed: mains.php showed
 * progress for a 'users' bonus as the *three-level* team size, while bonus.php
 * checked only *direct* referrals. Users watched the bar reach 100%, pressed
 * claim, and were told they were not eligible.
 *
 * Both now call this, so the number a user is shown is by construction the
 * number they are judged against.
 *
 * THE BUSINESS DEFINITIONS LIVE HERE:
 *   'users'   -> everyone in the three-level team. This matches what the app
 *                already presents as "your team", and is the promise the
 *                progress bar has been making.
 *   'actives' -> direct referrals with Active status, which is what both
 *                sides already agreed on.
 *
 * Making 'users' three-level rather than direct-only makes these bonuses
 * meaningfully easier to earn, and that is a cost decision as much as a bug
 * fix. If the intent was direct-only, change the 'users' branch below to
 * return $direct and the two sides stay consistent either way.
 *
 * @return array{users:int, actives:int}
 */
if (!function_exists('referral_progress')) {
    function referral_progress(PDO $pdo, $userID)
    {
        $tree = referral_tree($pdo, $userID);

        return $tree['counts'];
    }
}

/**
 * Progress toward one bonus, by its type.
 *
 * Pass $counts when the caller already has a tree in hand (the dashboard
 * does), so rendering a list of bonuses does not re-walk it once per bonus.
 */
if (!function_exists('bonus_progress')) {
    function bonus_progress(PDO $pdo, $userID, $bonusType, array $counts = null)
    {
        if ($counts === null) {
            $counts = referral_progress($pdo, $userID);
        }

        return $counts[$bonusType] ?? 0;
    }
}

if (!function_exists('referral_commission')) {
    /**
     * Pay upline commission on a deposit.
     *
     * @return array Levels actually paid, for logging: [['userID'=>, 'level'=>, 'amount'=>], ...]
     */
    function referral_commission(PDO $pdo, $query, $userID, $amount)
    {
        if (!$pdo->inTransaction()) {
            throw new RuntimeException('referral_commission() must run inside the caller transaction.');
        }

        $controls = $query->select('controls');
        $control  = $controls[0] ?? [];

        // Rates are whole percentages in the controls row: 10 means 10%.
        $rates = [
            1 => money($control['level1'] ?? 0) / 100,
            2 => money($control['level2'] ?? 0) / 100,
            3 => money($control['level3'] ?? 0) / 100,
        ];

        $paid = [];

        // Start from the depositor and climb.
        $current = $query->select('users', '*', ['ID' => $userID]);
        $current = $current[0] ?? null;

        if ($current === null) {
            return $paid;
        }

        // Guards against a cycle in the upline chain -- a self-referral or a
        // hand-edited row would otherwise loop until the request times out.
        $seen = [(string) $userID => true];

        for ($level = 1; $level <= 3; $level++) {
            $uplineID = $current['upline'] ?? 0;

            // Top of the tree, or a broken link. Either way, stop.
            if (empty($uplineID) || isset($seen[(string) $uplineID])) {
                break;
            }

            $upline = $query->select('users', '*', ['ID' => $uplineID]);
            $upline = $upline[0] ?? null;

            if ($upline === null) {
                break;
            }

            $seen[(string) $uplineID] = true;
            $commission = money($amount) * $rates[$level];

            if ($commission > 0) {
                $wallet = wallet_for_update($pdo, $uplineID);

                if ($wallet !== null) {
                    $query->update('wallets', [
                        'balance'       => money_str(money($wallet['balance']) + $commission),
                        'invite_income' => money_str(money($wallet['invite_income']) + $commission),
                    ], ['userID' => $uplineID]);

                    $query->insert('transactions', [
                        'userID'      => $uplineID,
                        'type'        => 'Commission',
                        'amount'      => money_str($commission),
                        'description' => 'Level ' . $level . ' commission from referral of user ' . $userID . '.',
                        'status'      => 'Completed',
                    ]);

                    $paid[] = ['userID' => $uplineID, 'level' => $level, 'amount' => $commission];
                }
            }

            $current = $upline;
        }

        return $paid;
    }
}
