<?php
/**
 * INCENTIVE SALARY DISBURSEMENT
 * =============================
 * Pays the incentive salary to every user whose application has been
 * approved. Driven by the button on admin/platform-control.php.
 *
 * WHO IS PAID
 * -----------
 * Everyone with an Approved row in `incentives_requests`. The amount is the
 * salary of the incentive they were approved for.
 *
 * A user can hold more than one approval -- they apply again as they climb the
 * tiers. The one that pays is the incentive matching the level on their wallet
 * (admin/incentive-application.php writes that level when an application is
 * approved), which is their current tier. If no approval matches the wallet's
 * level, the highest-paying approval is used, so a missing or stale level
 * never silently pays nothing.
 *
 * Retired incentives are skipped: an incentive set Inactive is one the
 * platform has stopped offering, and paying a salary for it would be paying
 * for something that no longer exists.
 *
 * ONCE A WEEK
 * -----------
 * A user who has been paid an incentive salary in the last seven days is
 * skipped. The window is rolling rather than a calendar week on purpose: a
 * calendar week would let a run on Sunday and another on Monday pay twice
 * within two days, which is the mistake this guard exists to prevent.
 *
 * The check reads the user's own 'Incentive' transactions, so the ledger is
 * the record -- there is no separate "last paid" column to drift out of step
 * with the money actually credited.
 *
 * The page also confirms before running and redirects afterwards, so a
 * refresh cannot replay a run.
 */

// Days a user must wait between incentive payments.
const INCENTIVE_PAY_INTERVAL_DAYS = 7;

if (!function_exists('incentive_disbursement_plan')) {
    /**
     * Who would be paid right now, and how much.
     *
     * `rows` is who is due; `skipped` is who is eligible but was paid inside
     * the last INCENTIVE_PAY_INTERVAL_DAYS, so the admin can see that a run
     * which pays nobody is a guard working rather than a list that is empty.
     *
     * @return array{rows:array, skipped:array, total:float, users:int}
     */
    function incentive_disbursement_plan($query)
    {
        $approved = $query->select('incentives_requests', '*', ['status' => 'Approved']);

        if (empty($approved)) {
            return ['rows' => [], 'skipped' => [], 'total' => 0.0, 'users' => 0];
        }

        /**
         * When each user was last paid an incentive salary.
         *
         * Read from the transactions they actually received, in one pass --
         * a query per approved applicant would make a large run expensive.
         */
        $lastPaid = [];
        foreach ($query->select('transactions', '*', ['type' => 'Incentive']) as $row) {
            $userID = (string) $row['userID'];
            $when   = strtotime((string) $row['time']);

            if ($when !== false && $when > ($lastPaid[$userID] ?? 0)) {
                $lastPaid[$userID] = $when;
            }
        }

        // Indexed once rather than queried per application.
        $incentives = [];
        foreach ($query->select('incentives') as $row) {
            $incentives[(string) $row['ID']] = $row;
        }

        $walletLevel = [];
        foreach ($query->select('wallets') as $row) {
            $walletLevel[(string) $row['userID']] = (string) ($row['level'] ?? '');
        }

        $emails = [];
        foreach ($query->select('users') as $row) {
            $emails[(string) $row['ID']] = $row['email'];
        }

        /** The pick per user: level match first, then the highest salary. */
        $best = [];

        foreach ($approved as $request) {
            $userID    = (string) $request['userID'];
            $incentive = $incentives[(string) $request['incentiveID']] ?? null;

            // A deleted incentive, or one the platform has retired.
            if ($incentive === null || ($incentive['status'] ?? '') !== 'Active') {
                continue;
            }

            // A deleted user leaves the application behind.
            if (!isset($emails[$userID])) {
                continue;
            }

            $salary = money($incentive['salary']);

            if ($salary <= 0) {
                continue;
            }

            $matchesLevel = ($walletLevel[$userID] ?? '') !== ''
                && (string) $incentive['level'] === $walletLevel[$userID];

            $candidate = [
                'userID'      => $request['userID'],
                'email'       => $emails[$userID],
                'incentiveID' => $incentive['ID'],
                'incentive'   => $incentive['name'],
                'level'       => $incentive['level'],
                'salary'      => $salary,
                'matches'     => $matchesLevel,
            ];

            if (!isset($best[$userID])) {
                $best[$userID] = $candidate;
                continue;
            }

            $current = $best[$userID];

            // A level match always wins; between two of the same kind, the
            // larger salary wins.
            if (($candidate['matches'] && !$current['matches'])
                || ($candidate['matches'] === $current['matches'] && $candidate['salary'] > $current['salary'])) {
                $best[$userID] = $candidate;
            }
        }

        $cutoff  = time() - (INCENTIVE_PAY_INTERVAL_DAYS * 86400);
        $rows    = [];
        $skipped = [];
        $total   = 0.0;

        foreach ($best as $userID => $row) {
            $paidAt = $lastPaid[(string) $userID] ?? null;

            if ($paidAt !== null && $paidAt > $cutoff) {
                $row['last_paid'] = date('Y-m-d H:i', $paidAt);
                $row['due_in']    = max(1, (int) ceil((($paidAt + INCENTIVE_PAY_INTERVAL_DAYS * 86400) - time()) / 86400));
                $skipped[] = $row;
                continue;
            }

            $rows[] = $row;
            $total += $row['salary'];
        }

        return ['rows' => $rows, 'skipped' => $skipped, 'total' => $total, 'users' => count($rows)];
    }
}

if (!function_exists('incentive_disburse')) {
    /**
     * Pay everyone in the plan.
     *
     * Each user is credited in their own transaction, with their wallet
     * locked for the arithmetic -- the same primitive every other credit uses.
     * One user's failure therefore costs that user's payment and no one
     * else's, rather than rolling back a run that was half done.
     *
     * Anyone paid within the last INCENTIVE_PAY_INTERVAL_DAYS is not in the
     * plan, so a second run in the same week pays only users approved since.
     *
     * @return array{paid:int, total:float, failed:int, skipped:int, errors:array}
     */
    function incentive_disburse(PDO $pdo, $query, $adminID = null)
    {
        $plan = incentive_disbursement_plan($query);

        $paid   = 0;
        $total  = 0.0;
        $failed = 0;
        $errors = [];

        foreach ($plan['rows'] as $row) {
            $userID = $row['userID'];
            $salary = $row['salary'];

            $pdo->beginTransaction();

            try {
                $wallet = wallet_for_update($pdo, $userID);

                if ($wallet === null) {
                    $pdo->rollBack();
                    $failed++;
                    $errors[] = 'No wallet for user ' . $userID;
                    continue;
                }

                $query->update('wallets', [
                    'balance' => money_str(money($wallet['balance']) + $salary),
                    // Incentive pay is income the user did not invest for, and
                    // the app shows invite_income as "team earnings", so it is
                    // deliberately left alone -- only the balance moves.
                ], ['userID' => $userID]);

                $query->insert('transactions', [
                    'userID'      => $userID,
                    'type'        => 'Incentive',
                    'amount'      => money_str($salary),
                    'description' => substr('Incentive salary: ' . $row['incentive'] . ' (' . $row['level'] . ')', 0, 255),
                    'status'      => 'Completed',
                ]);

                $pdo->commit();

                $paid++;
                $total += $salary;
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $failed++;
                $errors[] = 'User ' . $userID . ': ' . $e->getMessage();
                error_log('[incentive-disburse] user ' . $userID . ' failed: ' . $e->getMessage());
            }
        }

        // One line per run. There is no period guard, so the log is the record
        // of how many runs happened and what each one cost.
        $skipped = count($plan['skipped']);

        // One line per run: how many were paid, what it cost, and how many the
        // weekly guard held back.
        error_log('[incentive-disburse] admin ' . ($adminID ?? '?') . ' paid ' . $paid . ' user(s), '
            . 'total ' . money_str($total)
            . ($skipped ? ', ' . $skipped . ' skipped (paid within ' . INCENTIVE_PAY_INTERVAL_DAYS . ' days)' : '')
            . ($failed ? ', ' . $failed . ' failed' : ''));

        return ['paid' => $paid, 'total' => $total, 'failed' => $failed, 'skipped' => $skipped, 'errors' => $errors];
    }
}
