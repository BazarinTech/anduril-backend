<?php
/**
 * TRANSACTION SEARCH
 * ==================
 * Finds a transaction that is older than the recent window the Transactions
 * page loads, without putting an unbounded query in front of the database.
 *
 * WHY THE PAGE CANNOT JUST LOAD EVERYTHING
 * data_table() ships every row it is handed into the HTML, so the page shows
 * the most recent ADMIN_TABLE_LIMIT rows and its search box filters those.
 * That is fine for the day's work and useless for "the customer says they
 * deposited in March". This answers that question instead.
 *
 * HOW IT STAYS CHEAP
 * Every search is driven by an indexed column -- transactions has indexes on
 * userID, trackingID, local_ref, type and status, and users on email and
 * phone:
 *
 *   an email or phone   resolved to user IDs first, then userID IN (...)
 *   a reference         exact match on trackingID or local_ref
 *   a number            the transaction's own ID, or a user ID
 *   type / status       indexed equality
 *   a date range        a backwards walk over the primary key, capped
 *
 * Three further rules keep the cost flat no matter how large the table grows:
 *
 *   1. No COUNT(*). Counting matches means scanning them; the page asks for
 *      one row more than it shows and says "more available" if it arrives.
 *   2. Paging is keyset, not OFFSET: "older" means `ID < the last id seen`,
 *      which stays one index seek at any depth, while OFFSET 50000 makes the
 *      database walk 50,000 rows to throw them away.
 *   3. The page size is capped, so a single request can never return more
 *      than a couple of hundred rows.
 *
 * Description and amount are deliberately not searchable here: neither is
 * indexed, so matching them means reading the whole table. The in-page box
 * still filters those within the rows on screen.
 */

if (!function_exists('transaction_search_active')) {
    /** Whether the admin has actually asked for anything. */
    function transaction_search_active(array $filters)
    {
        foreach (['q', 'type', 'status', 'from', 'to'] as $key) {
            if (trim((string) ($filters[$key] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('transaction_search')) {
    /**
     * @param array $filters q, type, status, from, to, before (keyset cursor), limit
     *
     * @return array{rows:array, has_more:bool, next_cursor:int|null, criteria:string, error:string}
     */
    function transaction_search(PDO $pdo, array $filters)
    {
        $q      = trim((string) ($filters['q'] ?? ''));
        $type   = trim((string) ($filters['type'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $from   = trim((string) ($filters['from'] ?? ''));
        $to     = trim((string) ($filters['to'] ?? ''));
        $before = (int) ($filters['before'] ?? 0);

        // Capped here rather than trusted from the query string: this number
        // is how much HTML the page has to carry.
        $limit = (int) ($filters['limit'] ?? 100);
        $limit = max(10, min(200, $limit));

        $where  = [];
        $params = [];
        $criteria = [];

        if ($q !== '') {
            if (strpos($q, '@') !== false) {
                // An email: resolved against the users index first, so the
                // transactions query is an indexed userID lookup.
                $stmt = $pdo->prepare("SELECT ID FROM users WHERE email = :exact OR email LIKE :prefix LIMIT 50");
                $stmt->execute([':exact' => $q, ':prefix' => $q . '%']);
                $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (!$ids) {
                    return ['rows' => [], 'has_more' => false, 'next_cursor' => null,
                            'criteria' => 'email ' . $q, 'error' => 'No account uses that email address.'];
                }

                $where[] = 'userID IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                $params = array_merge($params, array_map('strval', $ids));
                $criteria[] = 'email ' . $q;
            } elseif (preg_match('/^\+?\d[\d\s-]{6,}$/', $q)) {
                // A phone number: the same trick, plus the account field the
                // payout was sent to.
                $digits = preg_replace('/\D/', '', $q);

                $stmt = $pdo->prepare("SELECT ID FROM users WHERE phone = :p1 OR phone = :p2 LIMIT 50");
                $stmt->execute([':p1' => $digits, ':p2' => '0' . substr($digits, 3)]);
                $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $clause = ['account = ?'];
                $params[] = $digits;

                if ($ids) {
                    $clause[] = 'userID IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                    $params = array_merge($params, array_map('strval', $ids));
                }

                $where[] = '(' . implode(' OR ', $clause) . ')';
                $criteria[] = 'phone ' . $q;
            } elseif (ctype_digit($q)) {
                // A bare number is either a transaction id or a user id.
                $where[] = '(ID = ? OR userID = ?)';
                $params[] = $q;
                $params[] = $q;
                $criteria[] = 'id ' . $q;
            } else {
                // A provider or local reference, both indexed.
                $where[] = '(trackingID = ? OR local_ref = ?)';
                $params[] = $q;
                $params[] = $q;
                $criteria[] = 'reference ' . $q;
            }
        }

        if ($type !== '') {
            $where[] = 'type = ?';
            $params[] = $type;
            $criteria[] = 'type ' . $type;
        }

        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
            $criteria[] = 'status ' . $status;
        }

        if ($from !== '') {
            $where[] = 'time >= ?';
            $params[] = $from . ' 00:00:00';
            $criteria[] = 'from ' . $from;
        }

        if ($to !== '') {
            $where[] = 'time <= ?';
            $params[] = $to . ' 23:59:59';
            $criteria[] = 'to ' . $to;
        }

        // Keyset paging: everything older than the last row of the page before.
        if ($before > 0) {
            $where[] = 'ID < ?';
            $params[] = $before;
        }

        $sql = 'SELECT * FROM transactions'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY ID DESC LIMIT ' . ($limit + 1);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // The extra row only tells us whether there is another page.
        $has_more = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);

        // Emails for just this page of results, in one query.
        $emails = [];
        $userIds = array_values(array_unique(array_map(static function ($r) { return (string) $r['userID']; }, $rows)));

        if ($userIds) {
            $stmt = $pdo->prepare('SELECT ID, email FROM users WHERE ID IN (' . implode(',', array_fill(0, count($userIds), '?')) . ')');
            $stmt->execute($userIds);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $user) {
                $emails[(string) $user['ID']] = $user['email'];
            }
        }

        // Shaped exactly like $transactions_records so data_table renders it
        // with the same columns and the same edit modal.
        $records = [];

        foreach ($rows as $row) {
            $records[] = [
                'id'          => $row['ID'],
                'email'       => $emails[(string) $row['userID']] ?? '(user removed)',
                'amount'      => 'Kes ' . $row['amount'],
                'type'        => $row['type'],
                'status'      => $row['status'],
                'description' => $row['description'],
                'fee'         => $row['fees'],
                'time'        => $row['time'],
                'account'     => $row['account'],
                'method'      => $row['method'],
                'amount_raw'  => $row['amount'],
            ];
        }

        return [
            'rows'        => $records,
            'has_more'    => $has_more,
            'next_cursor' => $records ? (int) end($records)['id'] : null,
            'criteria'    => $criteria ? implode(', ', $criteria) : 'everything',
            'error'       => '',
        ];
    }
}
