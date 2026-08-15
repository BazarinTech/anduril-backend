<?php
include 'initiate.php';
$data = $fileGetContent->get_content();

$id    = intval($data['id'] ?? 0);
$table = $data['table'] ?? '';

/**
 * QueryBuilder::delete() interpolates the table name straight into
 * "DELETE FROM $table", so this value must never be taken on trust.
 * Only the tables the panel actually offers a delete control for are allowed.
 */
$allowedTables = [
    'users',
    'transactions',
    'orders',
    'products',
    'bonus',
    'admins',
    'incentives',
];

if (!in_array($table, $allowedTables, true)) {
    http_response_code(400);
    $fileGetContent->send_content([
        'success' => false,
        'message' => 'Invalid table',
    ]);
    exit;
}

if ($id <= 0) {
    http_response_code(400);
    $fileGetContent->send_content([
        'success' => false,
        'message' => 'Invalid record id',
    ]);
    exit;
}

if ($table === 'users') {
    // A user's wallet is meaningless without the user, so it goes too.
    // Their orders and transactions are deliberately left in place: they are
    // financial history, and admin/includes/main.php reads them for the
    // dashboard totals.
    $query->delete('wallets', ['userID' => $id]);
}

$deleted = $query->delete($table, ['ID' => $id]);

if ($deleted === false) {
    http_response_code(500);
    $fileGetContent->send_content([
        'success' => false,
        'message' => 'Delete failed',
    ]);
    exit;
}

$fileGetContent->send_content([
    'success' => (bool) $deleted,
    'message' => $deleted ? 'Deleted successfully' : 'No matching record found',
]);
