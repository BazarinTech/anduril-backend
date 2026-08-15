<?php
/**
 * LEDGER HELPERS
 * ==============
 * Phase 3. Everything that moves money goes through here.
 *
 * The problem these solve: every balance change in this codebase was a
 * read-modify-write with nothing holding the row in between --
 *
 *     $wallet  = $query->select('wallets', '*', ['userID' => $userID]);
 *     $balance = $wallet[0]['balance'] + $amount;
 *     $query->update('wallets', ['balance' => $balance], ...);
 *
 * Two requests interleaving there both read the same starting balance and the
 * second write silently discards the first. For a credit that mints money; for
 * a debit it spends the same funds twice. The daily-claim path was the easiest
 * to hit, but withdraw, transfer, invest, bonus and coupon were all exposed.
 *
 * The fix is boring and standard: take the row lock inside a transaction,
 * do the arithmetic, commit. `SELECT ... FOR UPDATE` makes the second request
 * wait at the lock rather than racing past it.
 *
 * These are plain functions on the PDO handle rather than methods on
 * QueryBuilder, because that class lives in vendor/ and the two vendored
 * copies have already drifted apart once (see docs/AUTH.md). Nothing here
 * requires touching it again.
 *
 * NESTING: PDO has no nested transactions. Helpers here never begin one of
 * their own -- callers own the transaction boundary. That matters because
 * referral_commission() takes further wallet locks inside the deposit
 * callback's transaction.
 */

/**
 * Read a wallet and hold it until the transaction ends.
 *
 * Must be called inside a transaction; outside one, InnoDB releases the lock
 * immediately and the call silently degrades to an ordinary read. Rather than
 * let that happen quietly, this refuses.
 */
if (!function_exists('wallet_for_update')) {
    function wallet_for_update(PDO $pdo, $userID)
    {
        if (!$pdo->inTransaction()) {
            throw new RuntimeException('wallet_for_update() called outside a transaction; the lock would not be held.');
        }

        $stmt = $pdo->prepare('SELECT * FROM wallets WHERE userID = :userID FOR UPDATE');
        $stmt->execute([':userID' => $userID]);

        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

        return $wallet === false ? null : $wallet;
    }
}

/**
 * Lock two wallets in a stable order.
 *
 * A transfer touches both sides. If one request locks the sender first and a
 * simultaneous transfer in the other direction locks what is its own sender,
 * each waits on a row the other holds and InnoDB kills one with a deadlock.
 * Always taking the lower userID first means the two requests queue instead.
 *
 * Returns [walletA, walletB] keyed by the userIDs passed in, not by lock order.
 */
if (!function_exists('wallets_for_update')) {
    function wallets_for_update(PDO $pdo, $userIdA, $userIdB)
    {
        $ordered = [$userIdA, $userIdB];
        sort($ordered, SORT_NUMERIC);

        $locked = [];
        foreach ($ordered as $id) {
            $locked[(string) $id] = wallet_for_update($pdo, $id);
        }

        return [$locked[(string) $userIdA] ?? null, $locked[(string) $userIdB] ?? null];
    }
}

/**
 * Claim today's payout for an order, atomically.
 *
 * `rolls` is the daily-claim gate: 1 means claimable, 0 means already taken.
 * Checking it and then setting it in two statements leaves a window where two
 * requests both see 1 and both pay out. This does it in one statement -- the
 * UPDATE only matches while rolls is still 1, so exactly one caller can ever
 * see a row count of 1 for a given day.
 *
 * Returns true for the caller that won the claim, false for everyone else.
 */
if (!function_exists('claim_order_roll')) {
    function claim_order_roll(PDO $pdo, $orderID, $userID)
    {
        $stmt = $pdo->prepare(
            "UPDATE orders
                SET rolls = 0
              WHERE ID = :orderID
                AND userID = :userID
                AND rolls = 1
                AND status = 'Active'"
        );

        $stmt->execute([':orderID' => $orderID, ':userID' => $userID]);

        return $stmt->rowCount() === 1;
    }
}

/**
 * Claim a transaction by moving it out of a status, atomically.
 *
 * This is the idempotency primitive for anything driven by an external event:
 * payment callbacks (which providers deliver more than once by design) and
 * admin approvals (which a double-click fires twice).
 *
 * Selecting a row "WHERE status = 'Pending'" and *then* acting on it is not
 * enough -- concurrent deliveries all run their SELECT before any of them
 * commits, so they all see Pending and all proceed. Locking the wallet makes
 * the arithmetic correct but still applies the deposit N times.
 *
 * The status change has to *be* the claim. This UPDATE only matches while the
 * row is still in $fromStatus, so exactly one caller ever sees a row count of
 * 1; everyone else gets false and must not touch a balance.
 *
 * Must run inside the caller's transaction, so that rolling back releases the
 * claim along with everything else.
 *
 * @return bool True for the single caller that won the claim.
 */
if (!function_exists('claim_transaction')) {
    function claim_transaction(PDO $pdo, $trackingID, $fromStatus, $toStatus, $description = null)
    {
        if (!$pdo->inTransaction()) {
            throw new RuntimeException('claim_transaction() must run inside a transaction.');
        }

        $params = [':t' => $trackingID, ':to' => $toStatus, ':from' => $fromStatus];
        $setDescription = '';

        if ($description !== null) {
            $setDescription = ', description = :descr';
            $params[':descr'] = $description;
        }

        $stmt = $pdo->prepare(
            "UPDATE transactions
                SET status = :to{$setDescription}
              WHERE trackingID = :t
                AND status = :from"
        );

        $stmt->execute($params);

        return $stmt->rowCount() === 1;
    }
}

/**
 * Is this a usable money amount?
 *
 * Amounts arrive as JSON from the client and were previously compared straight
 * against a minimum, so "12abc" passed as 12 and a negative amount passed a
 * `>= $min` check on a negative minimum. Reject anything that is not a finite
 * positive number, and cap the magnitude so a value cannot overflow the
 * VARCHAR(10) money columns and be silently truncated by MySQL.
 */
if (!function_exists('is_valid_amount')) {
    function is_valid_amount($value, $max = 99999999)
    {
        if (is_bool($value) || is_array($value) || is_null($value)) {
            return false;
        }

        // is_numeric rejects "12abc", "" and " ", which (float) would not.
        if (!is_numeric($value)) {
            return false;
        }

        $amount = (float) $value;

        return is_finite($amount) && $amount > 0 && $amount <= $max;
    }
}

/**
 * Normalise a money value read from one of the VARCHAR columns.
 *
 * Money is stored as text (see db/schema.sql for why that is preserved), so
 * every read needs coercing before arithmetic.
 */
if (!function_exists('money')) {
    function money($value)
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}

/**
 * Format a money value for storage.
 *
 * Two decimal places, no thousands separator, no scientific notation -- the
 * columns are text, and "1.0E+5" would be stored verbatim and read back as 1.
 */
if (!function_exists('money_str')) {
    function money_str($value)
    {
        return number_format((float) $value, 2, '.', '');
    }
}
