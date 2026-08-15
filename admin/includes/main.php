<?php
require_once __DIR__ . '/initiate.php';
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


if (preg_match_all('/\[(.*?)\]/', $permissions, $matches)) {
    $perm_array = $matches[1];
}else{
    $perm_array = [];
}
$isEdit = in_array('edit', $perm_array);
$isAdd = in_array('add', $perm_array);
$isView = in_array('view', $perm_array);
$isFinance = in_array('finance', $perm_array);

//total balances
$wallets = $query->select('wallets', '*', [], ['column' => 'ID', 'direction' => 'desc']);
$total_balance = 0;
foreach ($wallets as $row) {
    $total_balance += $row['balance'];
}

//total deposits
$total_deposits_transactions =  $query->select('transactions', '*', ['type' => 'Deposit', 'status' => 'Success']);
$total_deposits = 0;
$dep_count = count($total_deposits_transactions);
foreach ($total_deposits_transactions as $row) {
    $total_deposits += $row['amount'];
}

//total withdrawals
$total_withdrawals_transactions =  $query->select('transactions', '*', ['type' => 'Withdraw', 'status' => 'Success']);
$total_withdrawals = 0;
$with_count = count($total_withdrawals_transactions);
foreach ($total_withdrawals_transactions as $row) {
    $total_withdrawals += $row['amount'];
}

//total users
$total_users = count($query->select('users'));

//total active users
$total_active_users = count($query->select('users', '*', ['status' => 'Active']));

//users joined today
$users = $query->select('users', '*', [], ['column' => 'ID', 'direction' => 'desc']);
$users_joined_today = 0;
foreach ($users as $row) {
    $time_def = time() - strtotime($row['date_created']);
    $time_def = $time_def / 84600;
    if ($time_def <= 1) {
        $users_joined_today++;
    }
};

// GET ALL TABLES RECORDS DETAILS

//users records
$users_records = [];
foreach ($users as $row) {
    $upline_user = $query->select('users', '*', ['ID' => $row['upline']]);
    $upline_num = count($upline_user);
    if ($upline_num) {
        $upline_email = $upline_user[0]['email'];
    }else{
        $upline_email = '';
    }

    $users_records[] = [
        'id' => $row['ID'],
        'name' => $row['name'],
        'username' => $row['username'],
        'email' => $row['email'],
        'phone' => $row['phone'],
        'status' => $row['status'],
        'upline' => $upline_email,
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
    $user_transactions = $query->select('transactions', '*', ['userID' => $row['userID'], 'status' => 'Success']);
    $user_details = $query->select('users', '*', ['ID' => $row['userID']]);
    $withs = 0;
    $deps = 0;
    foreach ($user_transactions as $transaction) {
       if ($transaction['type'] == 'Deposit') {
        $deps += $transaction['amount'];
       }elseif ($transaction['type'] == 'Withdraw') {
        $withs += $transaction['amount'];
       }
    }
    $wallets_records[] = [
        'id' => $row['ID'],
        'email' => $user_details[0]['email'],
        'balance' => 'Kes '.number_format($row['balance'], 2),
        'income' => 'Kes '.number_format($row['income'], 2),
        'downline' => 'Kes '.number_format($row['invite_income'], 2),
        'withdrawals' => 'Kes '.number_format($withs, 2),
        'deposits' => 'Kes '.number_format($deps, 2)
    ];
}

//order records
$order_records = [];
$orders = $query->select('orders', '*', ['type' => 'investment'], ['column' => 'ID', 'direction' => 'desc']);
foreach ($orders as $row) {
    $product = $query->select('products', '*', ['ID' => $row['prodID']]);
    $user_prod = $query->select('users', '*', ['ID' => $row['userID']]);
    $orders_records[] = [
        'id' => $row['ID'],
        'name' => $product[0]['name'],
        'user' => $user_prod[0]['email'],
        'price' => 'Kes '.number_format($row['amount'], 2),
        'income' => 'Kes '.number_format($row['returns'], 2),
        'status' => $row['status'],
        'time' => $row['time'],
        'rate' => $product[0]['returns']
    ];
}

//products records
$products_records = [];
$products = $query->select('products');
foreach ($products as $row) {
    $products_records[] = [
        'id' => $row['ID'],
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
$transactions = $query->select('transactions', '*', [], ['column' => 'ID', 'direction' => 'desc']);
foreach ($transactions as $row) {
    $user_trans = $query->select('users', '*', ['ID' => $row['userID']]);
    $transactions_records[] = [
        'id' => $row['ID'],
        'email' => $user_trans[0]['email'],
        'amount' => 'Kes '.$row['amount'],
        'type' => $row['type'],
        'status' => $row['status'],
        'description' => $row['description'],
        'fee' => $row['fees'],
        'time' => $row['time'],
        'account' => $row['account'],
        'method' => $row['method']
    ];
}

//Pending Withdrawals
$withdraw_records = [];
$withdraw_transactions = $query->select('transactions', '*', ['type' => 'Withdraw', 'status' => 'Pending'], ['column' => 'ID', 'direction' => 'desc']);
foreach ($withdraw_transactions as $row) {
    $user_trans = $query->select('users', '*', ['ID' => $row['userID']]);
    $withdraw_records[] = [
        'id' => $row['ID'],
        'email' => $user_trans[0]['email'],
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

//Pending Deposits
$deposits_records = [];
$deposit_transactions = $query->select('transactions', '*', ['type' => 'Deposit', 'status' => 'Pending', 'method' => 'binance'], ['column' => 'ID', 'direction' => 'desc']);
foreach ($deposit_transactions as $row) {
    $user_trans = $query->select('users', '*', ['ID' => $row['userID']]);
    $deposits_records[] = [
        'id' => $row['ID'],
        'email' => $user_trans[0]['email'],
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
    $user_admin = $query->select('users', '*', ['ID' => $row['userID']]);
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
$incentive_requests = $query->select('incentives_requests', '*', ['status' => 'Pending']);
$incentive_applications = [];
foreach ($incentive_requests as $row) {
    $incentive_details = $query->select('incentives', '*', ['ID' => $row['incentiveID']]);
    $user_details = $query->select('users', '*', ['ID' => $row['userID']]);
    $incentive_applications[] = [
            'ID' => $row['ID'],
            'Incentive' => $incentive_details[0]['name'],
            'name' => $row['name'],
            'phone' => $row['phone'],
            'email' => $user_details[0]['email'],
            'personal_ID' => $row['personal_ID'],
            'date' => $row['date'],
            'status' => $row['status']
        ];
}

$incentives = $query->select('incentives', '*', [], ['direction' => 'desc', 'column' => 'ID']);
$coupons = $query->select('coupons', '*', [], ['direction' => 'desc', 'column' => 'ID']);


// var_dump($users_records);


















