<?php
/**
 * MIGRATION 007 -- INDEXES FOR TRANSACTION LOOKUP
 * ===============================================
 * The admin Transactions page can now search the whole table rather than the
 * recent window it renders (admin/includes/transaction-search.php). Paging is
 * keyset -- "older than the last id seen" -- which combines a filter with a
 * descending walk of the primary key.
 *
 * With only single-column indexes, MySQL has to choose between using the
 * filter's index (then sorting) or walking the primary key (then testing each
 * row against the filter). It picks the walk, and a filter that matches few
 * old rows makes it read a long way before finding a page:
 *
 *   type='Withdraw' AND status='Pending' AND ID < ...  ->  rows examined 119,874
 *
 * These composite indexes end with ID so the filter and the ordering come
 * from one index, and the engine can stop as soon as it has a page.
 *
 *   transactions (type, ID)
 *   transactions (status, ID)
 *   transactions (userID, ID)
 *   transactions (time)      -- for the date-range filter
 *
 * The single-column indexes they extend are left in place: other queries use
 * them, and an index is cheap next to a scan of a quarter-million rows.
 *
 * Idempotent: each index is created only if it is missing.
 *
 *     php db/migrations/007_transaction_lookup_indexes.php
 */

require_once __DIR__ . '/../../bootstrap/api.php';

$pdo = $db->getConnection();

$indexes = [
    'idx_tx_type_id'   => 'type, ID',
    'idx_tx_status_id' => 'status, ID',
    'idx_tx_user_id'   => 'userID, ID',
    'idx_tx_time'      => 'time',
];

$check = $pdo->prepare(
    "SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transactions' AND INDEX_NAME = :name"
);

foreach ($indexes as $name => $columns) {
    $check->execute([':name' => $name]);

    if ((int) $check->fetchColumn() > 0) {
        echo "  skip   transactions.{$name} already exists\n";
        continue;
    }

    // Building an index on a large table takes a moment; say so rather than
    // appearing to hang during a deploy.
    echo "  add    transactions.{$name} ({$columns}) ...";
    $pdo->exec("ALTER TABLE transactions ADD KEY {$name} ({$columns})");
    echo " done\n";
}
