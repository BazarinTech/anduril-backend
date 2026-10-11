<?php
require_once __DIR__ . '/initiate.php';
// Shared record-table renderer. Every listing page calls data_table().
require_once __DIR__ . '/data-table.php';
//GET DASHBAORD SUMMURIES AND DASHBOARD DATA

// CHECK IF ADMIN IS LOGGED IN
if (!isset($_SESSION['userID'])) {
    header('Location: login');
    exit;
} else {
    $adminID = $_SESSION['userID'];
}

// GET ADMIN DETAILS (safe)
$admins = $query->select('admins', '*', ['userID' => $adminID]);
$admin_details = $query->select('users', '*', ['ID' => $adminID]);

if (empty($admins) || empty($admin_details)) {
    // Not an admin OR corrupted session/user record
    // Option 1: force logout
    session_destroy();
    header('Location: login');
    exit;

    // Option 2 (if you want): redirect to a "no access" page instead
    // header('Location: no-access');
    // exit;
}

$admin = $admins[0];
$userRow = $admin_details[0];

$permissions = $admin['permissions'] ?? '';
$admin_email = $userRow['email'] ?? '';
$admin_role  = $admin['roles'] ?? '';
$admin_phone = $userRow['phone'] ?? '';
$admin_pass  = $userRow['passwrd'] ?? '';
$admin_uname = $admin['username'] ?? '';
// Phase 4.15 -- admin/profile.php renders $admin_name in two places and
// nothing ever defined it, so the name field came up blank with a warning.
// It comes from the users row, which is where profile.php writes it back.
$admin_name  = $userRow['name'] ?? '';


if (preg_match_all('/\[(.*?)\]/', $permissions, $matches)) {
    $perm_array = $matches[1];
}else{
    $perm_array = [];
}
$isEdit = in_array('edit', $perm_array);
$isAdd = in_array('add', $perm_array);
$isView = in_array('view', $perm_array);
$isFinance = in_array('finance', $perm_array);

/**
 * ===========================================================================
 * WHY THIS FILE READS THE WAY IT DOES
 * ===========================================================================
 * Every admin page includes this file, and it used to issue one query per row
 * it displayed: an upline lookup per user, two per wallet, two per order, one
 * per transaction. On a live database that was ~36,000 queries for a single
 * page load -- about eight seconds against a local socket, and well past
 * Cloudflare's 100-second limit against a database on another host, which is
 * what made the whole panel answer with a 524 while the API stayed fine.
 *
 * Two rules keep it fast, and both matter more as the platform grows:
 *
 *   1. Totals are counted by the database (COUNT/SUM), not by loading every
 *      row into PHP and adding them up.
 *   2. Anything looked up per row -- a user's email, a product's name -- is
 *      fetched once into a map and read from memory inside the loop.
 *
 * The cost of a page load is now a fixed handful of queries no matter how
 * many users, orders or transactions exist.
 */

/** One scalar from an aggregate query. */
$scalar = function ($sql, array $params = []) use ($pdo) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchColumn();
};

/**
 * The biggest tables are capped rather than rendered whole.
 *
 * data_table() ships every row it is given to the browser inside the page,
 * so an unbounded list is both a large query and a megabytes-wide HTML
 * attribute -- 24,000 transactions cost 60 MB of PHP memory against a 128 MB
 * limit. The newest rows are the ones an admin acts on; older ones are still
 * in the database and in the record's own page.
 */
$admin_table_limit = max(50, (int) env('ADMIN_TABLE_LIMIT', 1000));

//total balances
$total_balance = (float) $scalar("SELECT COALESCE(SUM(balance), 0) FROM wallets");

//total deposits
$total_deposits = (float) $scalar("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'Deposit' AND status = 'Success'");
$dep_count      = (int) $scalar("SELECT COUNT(*) FROM transactions WHERE type = 'Deposit' AND status = 'Success'");

//total withdrawals
$total_withdrawals = (float) $scalar("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'Withdraw' AND status = 'Success'");
$with_count        = (int) $scalar("SELECT COUNT(*) FROM transactions WHERE type = 'Withdraw' AND status = 'Success'");

//total users
$total_users = (int) $scalar("SELECT COUNT(*) FROM users");

//total active users
$total_active_users = (int) $scalar("SELECT COUNT(*) FROM users WHERE status = 'Active'");

//users joined today
// 86400 seconds in a day. This read 84600 -- a digit transposition that
// shortened the window by half an hour. Combined with the timezone skew fixed
// in Phase 2, this counter was reporting nonsense; it works now. The window is
// applied by the database rather than by walking every user in PHP.
$users_joined_today = (int) $scalar("SELECT COUNT(*) FROM users WHERE date_created >= (NOW() - INTERVAL 1 DAY)");

/**
 * ===========================
 * SHARED LOOKUPS
 * ===========================
 * Loaded once here, read from memory by every loop below.
 */
$users = $query->select('users', '*', [], ['column' => 'ID', 'direction' => 'desc']);

$users_by_id = [];
foreach ($users as $row) {
    $users_by_id[(string) $row['ID']] = $row;
}

/** A user's email, or a placeholder when the account has been deleted. */
$email_of = function ($userID) use (&$users_by_id) {
    return $users_by_id[(string) $userID]['email'] ?? '(user removed)';
};

$wallets = $query->select('wallets', '*', [], ['column' => 'ID', 'direction' => 'desc']);

$products = $query->select('products');

$products_by_id = [];
foreach ($products as $row) {
    $products_by_id[(string) $row['ID']] = $row;
}

/**
 * Settled deposit and withdrawal totals per user, in one grouped query
 * instead of one query per wallet.
 */
$settled_by_user = [];
$stmt = $pdo->query(
    "SELECT userID, type, COALESCE(SUM(amount), 0) AS total
       FROM transactions
      WHERE status = 'Success' AND type IN ('Deposit', 'Withdraw')
      GROUP BY userID, type"
);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $settled_by_user[(string) $row['userID']][$row['type']] = (float) $row['total'];
}

// GET ALL TABLES RECORDS DETAILS// GET ALL TABLES RECORDS DETAILS

//users records
$users_records = [];
foreach ($users as $row) {
    // Was a query per user. The map above already holds every account.
    $upline_email = $users_by_id[(string) $row['upline']]['email'] ?? '';

    $users_records[] = [
        'id' => $row['ID'],
        'name' => $row['name'],
        'username' => $row['username'],
        'email' => $row['email'],
        'phone' => $row['phone'],
        'status' => $row['status'],
        'upline' => $upline_email,
        // The editable value: users.upline holds an ID, the column shows the
        // referrer's email. data-table posts `upline_id` and re-renders from
        // the server, which is the only side that can resolve one to the other.
        'upline_id' => $row['upline'],
        'date' => $row['date_created'],
        // Phase 2.2 -- 'password' => $row['passwrd'] used to be here, which put
        // every user's credential into the admin page's HTML. Removed; the
        // stored value is a one-way hash now and still has no business
        // leaving the server.
        'roles' => $row['role']
    ];
}

//wallets records
$wallets_records = [];
foreach ($wallets as $row) {
    // Was two queries per wallet; both come from the lookups above.
    $settled = $settled_by_user[(string) $row['userID']] ?? [];
    $deps  = $settled['Deposit'] ?? 0;
    $withs = $settled['Withdraw'] ?? 0;

    $wallets_records[] = [
        'id' => $row['ID'],
        'email' => $email_of($row['userID']),
        'balance' => 'Kes '.number_format($row['balance'], 2),
        'income' => 'Kes '.number_format($row['income'], 2),
        'downline' => 'Kes '.number_format($row['invite_income'], 2),
        'withdrawals' => 'Kes '.number_format($withs, 2),
        'deposits' => 'Kes '.number_format($deps, 2),
        // Raw counterparts for the edit modal. The display values above are
        // formatted strings, and the inline editor used to post them straight
        // back -- MySQL received "Kes 1,200.00" for a DECIMAL column, which
        // under STRICT_TRANS_TABLES is an error and otherwise truncates to 0.
        'balance_raw' => $row['balance'],
        'income_raw' => $row['income'],
        'downline_raw' => $row['invite_income'],
    ];
}

//order records
// Phase 4.10 -- this was initialised as $order_records and appended to as
// $orders_records. PHP created the second one on first append, so it worked
// by accident, and orders.php rendered an undefined variable whenever there
// were no investment orders at all.
$orders_records = [];
$orders_total = (int) $scalar("SELECT COUNT(*) FROM orders WHERE type = 'investment'");
$orders = $query->select('orders', '*', ['type' => 'investment'], ['column' => 'ID', 'direction' => 'desc'], $admin_table_limit);
foreach ($orders as $row) {
    // Was two queries per order; both come from the lookups above.
    $product = $products_by_id[(string) $row['prodID']] ?? null;

    $orders_records[] = [
        'id' => $row['ID'],
        // A deleted user or product leaves the order behind; render a
        // placeholder instead of dereferencing a missing row.
        'name' => $product['name'] ?? '(product removed)',
        'user' => $email_of($row['userID']),
        'price' => 'Kes '.number_format(money($row['amount']), 2),
        'income' => 'Kes '.number_format(money($row['returns']), 2),
        'status' => $row['status'],
        'time' => $row['time'],
        'rate' => $product['returns'] ?? 0
    ];
}

//products records
$products_records = [];
foreach ($products as $row) {
    $products_records[] = [
        'id' => $row['ID'],
        // The Image column rendered a fixed placeholder for every row; this is
        // what it actually shows now, and what the edit modal previews.
        'image_url' => product_image_url($row['image'] ?? ''),
        'name' => $row['name'],
        'return' => $row['returns'],
        'min' => $row['min'],
        'max' => $row['max'],
        'status' => $row['status'],
        'time' => $row['time'],
        'duration' => $row['duration'],
        'tier' => $row['tier'],
        'order_limit' => $row['order_limit'],
    ];
}

//bonus records
$bonus_records = [];
$bonus = $query->select('bonus');
foreach ($bonus as $row) {
    $bonus_records[] = [
        'id' => $row['ID'],
        'name' => $row['name'],
        'target' => $row['target'],
        'type' => $row['reward_type'],
        'income' => $row['reward'],
        'status' => $row['status'],
        'time' => $row['time'],
        'target_type' => $row['type'],
    ];
}

//transaction records
$transactions_records = [];
$transactions_total = (int) $scalar("SELECT COUNT(*) FROM transactions");
$transactions = $query->select('transactions', '*', [], ['column' => 'ID', 'direction' => 'desc'], $admin_table_limit);
foreach ($transactions as $row) {
    // Was a query per transaction -- the single biggest cost on this page.
    $transactions_records[] = [
        'id' => $row['ID'],
        'email' => $email_of($row['userID']),
        'amount' => 'Kes '.$row['amount'],
        'type' => $row['type'],
        'status' => $row['status'],
        'description' => $row['description'],
        'fee' => $row['fees'],
        'time' => $row['time'],
        'account' => $row['account'],
        'method' => $row['method'],
        // See the wallets note above: 'amount' carries a "Kes " prefix for
        // display, so the editor reads the raw column instead.
        'amount_raw' => $row['amount'],
    ];
}

/**
 * The three pending queues are built by functions rather than inline, because
 * the approval pages need to rebuild them.
 *
 * main.php is included at the top of every page, so these lists are computed
 * *before* the page's own POST handler runs. Approving a deposit therefore
 * used to re-render the page still showing that deposit as pending, and the
 * obvious response -- clicking Approve again -- came back "Transaction is
 * already approved!". Now the handler calls the matching function again on its
 * way out, and the queue on screen reflects the action just taken.
 */
function pending_queue_records($query, array $where)
{
    $records = [];
    $rows    = $query->select('transactions', '*', $where, ['column' => 'ID', 'direction' => 'desc']);

    // One query for the owners of the whole queue, rather than one per row.
    $emails = [];
    foreach ($rows as $row) {
        $emails[(string) $row['userID']] = null;
    }

    if ($emails) {
        foreach ($query->select('users') as $user) {
            if (array_key_exists((string) $user['ID'], $emails)) {
                $emails[(string) $user['ID']] = $user['email'];
            }
        }
    }

    foreach ($rows as $row) {
        $records[] = [
            'id' => $row['ID'],
            'email' => $emails[(string) $row['userID']] ?? '(user removed)',
            'amount' => 'Kes '.$row['amount'] - $row['fees'],
            'type' => $row['type'],
            'status' => $row['status'],
            'description' => $row['description'],
            'fees' => $row['fees'],
            'time' => $row['time'],
            'account' => $row['account'],
            'trackingID' => $row['trackingID'],
            'method' => $row['method']
        ];
    }

    return $records;
}

function pending_withdrawal_records($query)
{
    return pending_queue_records($query, ['type' => 'Withdraw', 'status' => 'Pending']);
}

function pending_deposit_records($query)
{
    return pending_queue_records($query, ['type' => 'Deposit', 'status' => 'Pending', 'method' => 'binance']);
}

//Pending Withdrawals
$withdraw_records = pending_withdrawal_records($query);

//Pending Deposits
$deposits_records = pending_deposit_records($query);

//transaction controlls
$transaction_controls = $query->select('controls');
$transaction_controls = $transaction_controls['0'];
$min_dep = $transaction_controls['minDep'];
$min_with = $transaction_controls['minWith'];
$with_fee = $transaction_controls['withFee'];
$tran_fee = $transaction_controls['tranFee'];
$min_transfer = $transaction_controls['minTransfer'];
$level1 = $transaction_controls['level1'];
$level2 = $transaction_controls['level2'];
$level3 = $transaction_controls['level3'];
$transaction_type = $transaction_controls['transactionType'];
$transaction_account = $transaction_controls['transactionAccount'];

//admin records
$admins_records = [];
$admins = $query->select('admins');
foreach ($admins as $row) {
    $admins_records[] = [
        'id' => $row['ID'],
        'username' => $row['username'],
        'role' => $row['roles'],
        'permissions' => $row['permissions'],
        'status' => $row['status'],
        'time' => $row['date_created'],
    ];
}

// Incentives and coupons
// Rebuilt after an approval for the same reason as the queues above.
function incentive_application_records($query)
{
    $records = [];

    $requests = $query->select('incentives_requests', '*', ['status' => 'Pending']);

    if (empty($requests)) {
        return $records;
    }

    // Two maps instead of two queries per application.
    $incentive_names = [];
    foreach ($query->select('incentives') as $incentive) {
        $incentive_names[(string) $incentive['ID']] = $incentive['name'];
    }

    $emails = [];
    foreach ($query->select('users') as $user) {
        $emails[(string) $user['ID']] = $user['email'];
    }

    foreach ($requests as $row) {
        $records[] = [
            'ID' => $row['ID'],
            // Was 'Incentive'. The table renders item.incentive, and JS keys
            // are case-sensitive, so the column came up blank on every row.
            'incentive' => $incentive_names[(string) $row['incentiveID']] ?? '(incentive removed)',
            'name' => $row['name'],
            'phone' => $row['phone'],
            'email' => $emails[(string) $row['userID']] ?? '(user removed)',
            'personal_ID' => $row['personal_ID'],
            'date' => $row['date'],
            'status' => $row['status']
        ];
    }

    return $records;
}

$incentive_applications = incentive_application_records($query);

$incentives = $query->select('incentives', '*', [], ['direction' => 'desc', 'column' => 'ID']);
$coupons = $query->select('coupons', '*', [], ['direction' => 'desc', 'column' => 'ID']);


// var_dump($users_records);


















