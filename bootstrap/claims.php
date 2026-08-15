<?php
/**
 * CLAIM WINDOWS
 * =============
 * When an investment can be claimed, and whether a given order has used up its
 * claims for the current window.
 *
 * WHAT THIS REPLACES
 * ------------------
 * Claiming used to hang off `orders.rolls`, a flag somebody had to set back to
 * 1 every night -- originally an admin pressing a button, later a cron job.
 * Both share the same defect: claimability was *stored state* that something
 * external had to maintain, so the product silently stopped working whenever
 * that something did not run. Miss a night and nobody could claim; run it
 * twice and everybody claimed twice.
 *
 * Claimability is now *derived*: an order can be claimed when the clock is
 * inside the configured window and the order has not already taken its claims
 * for the current period. Nothing has to fire on a schedule for tomorrow to
 * work. There is no state to get stale.
 *
 * THE PERIOD KEY
 * --------------
 * A "claim period" is one turn of the window, identified by the date the
 * window *opened*. With a 07:00 open time, the period beginning 15 Aug 07:00
 * is keyed '2026-08-15' no matter how late into the evening the claim happens.
 *
 * That matters for windows that cross midnight (opens 22:00, closes 02:00):
 * a claim at 01:30 belongs to the period that opened the previous evening, so
 * it correctly counts as the same day's claim rather than a fresh one.
 *
 * TIMEZONE
 * --------
 * Everything here is in APP_TIMEZONE, which bootstrap.php pins for both PHP
 * and the MySQL session. The period key is computed in PHP and passed into
 * SQL as a literal, so there is exactly one clock deciding what day it is.
 */

if (!function_exists('claim_settings')) {
    /**
     * Read the window configuration from `controls`, with defaults that match
     * the schema so a row missing the columns still behaves sensibly.
     *
     * @return array{opens:string, closes:string|null, per_day:int, enabled:bool}
     */
    function claim_settings($query)
    {
        $rows = $query->select('controls');
        $row  = $rows[0] ?? [];

        $opens  = $row['claimOpensAt'] ?? '07:00:00';
        $closes = $row['claimClosesAt'] ?? null;

        // An empty string is what an untouched form field sends; treat it the
        // same as "not set" rather than as midnight.
        if ($closes === '' || $closes === '00:00:00') {
            $closes = null;
        }

        return [
            'opens'   => claim_normalise_time($opens, '07:00:00'),
            'closes'  => $closes === null ? null : claim_normalise_time($closes, null),
            'per_day' => max(1, (int) ($row['claimsPerDay'] ?? 1)),
            // The kill switch. With this off the window is ignored and claims
            // are accepted at any hour, which is how the platform behaved
            // before this feature existed.
            'enabled' => (string) ($row['claimWindowOn'] ?? '1') === '1',
        ];
    }
}

if (!function_exists('claim_normalise_time')) {
    /**
     * Accepts '7', '7:00', '07:00' or '07:00:00' and returns 'HH:MM:SS'.
     * Anything unparseable falls back rather than throwing -- a malformed
     * setting must not take the claim endpoint down.
     */
    function claim_normalise_time($value, $fallback = '07:00:00')
    {
        $value = trim((string) $value);

        if ($value === '') {
            return $fallback;
        }

        if (preg_match('/^(\d{1,2})(?::(\d{2}))?(?::(\d{2}))?$/', $value, $m)) {
            $h = (int) $m[1];
            $i = isset($m[2]) ? (int) $m[2] : 0;
            $s = isset($m[3]) ? (int) $m[3] : 0;

            if ($h <= 23 && $i <= 59 && $s <= 59) {
                return sprintf('%02d:%02d:%02d', $h, $i, $s);
            }
        }

        return $fallback;
    }
}

if (!function_exists('claim_window')) {
    /**
     * Where the clock currently sits relative to the claim window.
     *
     * @return array{
     *   enabled:bool, open:bool, period:string, opens:string, closes:string|null,
     *   per_day:int, opened_at:string, closes_at:string|null, next_open:string,
     *   message:string
     * }
     */
    function claim_window(array $settings, DateTimeInterface $now = null)
    {
        $now = $now ? DateTime::createFromFormat('U', $now->format('U')) : new DateTime();
        $now->setTimezone(new DateTimeZone(date_default_timezone_get()));

        $opens  = $settings['opens'];
        $closes = $settings['closes'];

        if (!$settings['enabled']) {
            // No window: every calendar day is one period, always open.
            return [
                'enabled'   => false,
                'open'      => true,
                'period'    => $now->format('Y-m-d'),
                'opens'     => $opens,
                'closes'    => $closes,
                'per_day'   => $settings['per_day'],
                'opened_at' => $now->format('Y-m-d') . ' 00:00:00',
                'closes_at' => null,
                'next_open' => $now->format('Y-m-d H:i:s'),
                'next_period_open' => (new DateTime($now->format('Y-m-d') . ' 00:00:00'))->modify('+1 day')->format('Y-m-d H:i:s'),
                'message'   => 'Claiming is open.',
            ];
        }

        $today = $now->format('Y-m-d');

        $openToday  = new DateTime($today . ' ' . $opens);
        $spansNight = $closes !== null && $closes <= $opens;

        /**
         * Which period are we in?
         *
         * Before today's open time, the live period is either yesterday's (if
         * the window runs past midnight) or none at all. After it, it is
         * today's.
         */
        if ($now >= $openToday) {
            $periodStart = clone $openToday;
        } else {
            $periodStart = (clone $openToday)->modify('-1 day');
        }

        $period = $periodStart->format('Y-m-d');

        if ($closes === null) {
            // Open from the start time until the window opens again tomorrow.
            $closesAt = null;
            $open     = $now >= $openToday;
        } else {
            $closesAt = new DateTime($periodStart->format('Y-m-d') . ' ' . $closes);

            if ($spansNight) {
                $closesAt->modify('+1 day');
            }

            $open = $now >= $periodStart && $now < $closesAt;
        }

        // When it opens next, for the "come back at" message.
        $nextOpen = $open ? clone $periodStart : clone $openToday;

        if (!$open && $now >= $openToday) {
            // Today's window has already closed.
            $nextOpen = (clone $openToday)->modify('+1 day');
        }

        /**
         * When the *following* window opens.
         *
         * These are two different questions and answering them with one value
         * was wrong: a caller told "you have already claimed today" while the
         * window is still open needs tomorrow's opening, not the one that
         * happened this morning. `next_open` is for "the window is shut";
         * `next_period_open` is for "you are out of claims".
         */
        $nextPeriodOpen = $open
            ? (clone $periodStart)->modify('+1 day')
            : clone $nextOpen;

        return [
            'enabled'   => true,
            'open'      => $open,
            'period'    => $period,
            'opens'     => $opens,
            'closes'    => $closes,
            'per_day'   => $settings['per_day'],
            'opened_at' => $periodStart->format('Y-m-d H:i:s'),
            'closes_at' => $closesAt ? $closesAt->format('Y-m-d H:i:s') : null,
            'next_open' => $nextOpen->format('Y-m-d H:i:s'),
            'next_period_open' => $nextPeriodOpen->format('Y-m-d H:i:s'),
            'message'   => $open
                ? ($closesAt
                    ? 'Claiming is open until ' . claim_pretty_time($closes) . '.'
                    : 'Claiming is open.')
                : 'Claiming opens at ' . claim_pretty_time($opens) . '.',
        ];
    }
}

if (!function_exists('claim_pretty_time')) {
    /** '07:00:00' -> '7:00 AM', for messages shown to users. */
    function claim_pretty_time($time)
    {
        $parsed = DateTime::createFromFormat('H:i:s', $time);

        return $parsed ? $parsed->format('g:i A') : $time;
    }
}

if (!function_exists('order_claims_left')) {
    /**
     * How many claims this order has left in the current period.
     *
     * Reads the row as it stands; the authoritative check is the conditional
     * UPDATE in take_order_claim(). This is for display only.
     */
    function order_claims_left(array $order, array $window)
    {
        if (($order['claim_period'] ?? null) !== $window['period']) {
            return $window['per_day'];
        }

        return max(0, $window['per_day'] - (int) ($order['claims_in_period'] ?? 0));
    }
}

if (!function_exists('take_order_claim')) {
    /**
     * Take one claim for this order in the current period, atomically.
     *
     * The same shape as the old claim_order_roll(): a single conditional
     * UPDATE, so exactly one of any number of simultaneous requests can see a
     * row count of 1. Reading the counter and then writing it would let two
     * requests both pass the check -- that race is how the claim endpoint used
     * to be drained.
     *
     * The period is passed in rather than computed in SQL so that PHP's clock
     * is the only one that decides what day it is.
     *
     * @return bool True if this caller took a claim.
     */
    function take_order_claim(PDO $pdo, $orderID, $userID, array $window)
    {
        $stmt = $pdo->prepare(
            "UPDATE orders
                SET claims_in_period = IF(claim_period = :period1, claims_in_period + 1, 1),
                    claim_period     = :period2,
                    last_claim_at    = NOW(),
                    rolls            = 0
              WHERE ID     = :orderID
                AND userID = :userID
                AND status = 'Active'
                AND (
                      claim_period IS NULL
                   OR claim_period <> :period3
                   OR claims_in_period < :perDay
                )"
        );

        $stmt->execute([
            ':period1' => $window['period'],
            ':period2' => $window['period'],
            ':period3' => $window['period'],
            ':perDay'  => $window['per_day'],
            ':orderID' => $orderID,
            ':userID'  => $userID,
        ]);

        return $stmt->rowCount() === 1;
    }
}

if (!function_exists('wallet_day_income')) {
    /**
     * Add to `wallets.today_income`, rolling it over when the period changes.
     *
     * This counter used to be zeroed by the nightly job. With the job gone it
     * resets lazily instead: the first credit of a new period replaces the
     * value rather than adding to it, so it is correct on read without anything
     * having had to run at midnight.
     *
     * @return string The new today_income value.
     */
    function wallet_day_income(array $wallet, $amount, array $window)
    {
        $sameperiod = ($wallet['income_period'] ?? null) === $window['period'];

        return money_str($sameperiod ? money($wallet['today_income']) + $amount : $amount);
    }
}
