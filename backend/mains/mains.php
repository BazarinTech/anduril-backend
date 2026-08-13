<?php
include '../includes/initiate.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

// Get posted json data
$data = $fileGetContent->get_content();

// Process the main output

if (isset($data['userID'])) {
    try {
        $decoded = JWT::decode($data['userID'], new Key(JWT_SECRET, JWT_ALGO));
        $userID = $decoded->userID ?? $decoded->sub ?? null;

        if (!$userID) {
            $fileGetContent->send_content([
                'status' => 'Error',
                'message' => 'Invalid token'
            ]);
            exit;
        }
        $time_now = time();

        // Get user details and wallets
        $users = $query->select('users', '*', ['ID' => $userID]);
        $uplineID = $users[0]['upline'];
        $uplineDetails = $query->select('users', '*', ['ID' => $uplineID]);
        $uplineCount = count($uplineDetails);

        if ($uplineCount > 0) {
            $upline_email = $uplineDetails[0]['email'];
        }else{
            $upline_email = '';
        }

        $user = [
            'email' => $users[0]['email'],
            'ID' => $users[0]['ID'],
            'phone' => $users[0]['phone'],
            'date_joined' => $users[0]['date_created'],
            'status' => $users[0]['status'],
            'upline' => $upline_email,
            'username' => $users[0]['username'],
            'role' => $users[0]['role'],
            'country' => $users[0]['country']
        ];

        $wallets = $query->select('wallets', '*', ['userID' => $userID]);
        $transactions = $query->select('transactions', '*', ['userID' => $userID], ['column' => 'ID', 'direction' => 'desc']);
        $inv_orders = $query->select('orders', '*', ['userID' => $userID, 'type' => 'investment']);
        $total_deposits = 0;
        $total_withdrawals = 0;
        $total_invested = 0;
        

        foreach ($transactions as $transaction) {
            if ($transaction['type'] == 'Deposit' && $transaction['status'] == 'Success') {
                $total_deposits += $transaction['amount'];
            }elseif ($transaction['type'] == 'Withdraw' && $transaction['status'] == 'Success') {
                $total_withdrawals += $transaction['amount'];
            }
        }
        
        foreach ($inv_orders as $row) {
            $total_invested += $row['amount'];
        }


        $wallet = [
            'balance' => $wallets[0]['balance'],
            'total_deposits' => $total_deposits,
            'total_withdrawals' => $total_withdrawals,
            'income' => $wallets[0]['income'],
            'invite_income' => $wallets[0]['invite_income'],
            'status' => $wallets[0]['status'],
            'total_invested' => $total_invested,
            'today_income' => $wallets[0]['today_income'],
            'total_income' => $wallets[0]['income'],
            'level' => $wallets[0]['level'],
            'withdrawal_account' => $wallets[0]['withdrawal_account'],
            'withdrawal_name' => $wallets[0]['withdrawal_name'],
        ];

        // Get user's referrals details
        $referrals = $query->select('users', '*', ['upline' => $userID], ['column' => 'ID', 'direction' => 'desc']);
        $level1_count = count($referrals);
        $active_referral = count($query->select('users', '*', ['status' => 'Active', 'upline' => $userID]));
        $total_refferal_deposits = 0;
        $level1_downlines = [];
        $level2_downlines = [];
        $level3_downlines = [];
        $refferal_count = 0;
        foreach ($referrals as $ref) {
            $ref_transactions = $query->select('transactions', '*', ['userID' => $ref['ID'], 'type' => 'Deposit']);
            $ref_deps = 0;
            $ref_downlines = count($query->select('users', '*', ['upline' => $ref['ID']], ['column' => 'ID', 'direction' => 'desc']));

            foreach ($ref_transactions as $ref_tran) {
                $total_refferal_deposits += $ref_tran['amount'];
                $ref_deps += $ref_tran['amount'];
            }

            $controls = $query->select('controls');
            $control = $controls[0];
            $level1 = $control['level1'] / 100;
            $level1_downlines[] = [
                'userID' => $ref['ID'],
                'email' => $ref['email'],
                'phone' => $ref['phone'],
                'date_joined' => $ref['date_created'],
                'status' => $ref['status'],
                'username' => $ref['username'],
                'deposits' => $ref_deps,
                'commission' => $ref_deps * $level1,
                'downlines' => $ref_downlines,
                'level' => 'Level 1',
                'refer' => 'Direct'
            ];

            // Get level 2 downlines
            $level2_refs = $query->select('users', '*', ['upline' => $ref['ID']], ['column' => 'ID', 'direction' => 'desc']);
            $level2_ref_details = $query->select('users', '*', ['ID' => $ref['ID']], ['column' => 'ID', 'direction' => 'desc']);
            $level2_rate = $control['level2'] / 100;
            foreach ($level2_refs as $l2ref) {
                $l2ref_transactions = $query->select('transactions', '*', ['userID' => $l2ref['ID'], 'type' => 'Deposit']);
                $l2ref_deps = 0;
                $l2ref_downlines = count($query->select('users', '*', ['upline' => $l2ref['ID']], ['column' => 'ID', 'direction' => 'desc']));
                $level2_downlines[] = [
                    'userID' => $l2ref['ID'],
                    'email' => $l2ref['email'],
                    'phone' => $l2ref['phone'],
                    'date_joined' => $l2ref['date_created'],
                    'status' => $l2ref['status'],
                    'username' => $l2ref['username'],
                    'deposits' => $l2ref_deps,
                    'commission' => $l2ref_deps * $level2_rate,
                    'downlines' => $l2ref_downlines,
                    'level' => 'Level 2',
                    'refer' => $level2_ref_details[0]['phone']
                ];
            }

            // Get level 3 downlines
            $level3_refs = [];
            foreach ($level2_refs as $l2ref) {
                $level3_refs = array_merge($level3_refs, $query->select('users', '*', ['upline' => $l2ref['ID']], ['column' => 'ID', 'direction' => 'desc']));
            }
            $level3_rate = $control['level3'] / 100;
            foreach ($level3_refs as $l3ref) {
                $l3ref_transactions = $query->select('transactions', '*', ['userID' => $l3ref['ID'], 'type' => 'Deposit']);
                $l3ref_deps = 0;
                $l3ref_downlines = count($query->select('users', '*', ['upline' => $l3ref['ID']], ['column' => 'ID', 'direction' => 'desc']));
                $level3_ref_details = $query->select('users', '*', ['ID' => $l3ref['ID']], ['column' => 'ID', 'direction' => 'desc']);
                $level3_downlines[] = [
                    'userID' => $l3ref['ID'],
                    'email' => $l3ref['email'],
                    'phone' => $l3ref['phone'],
                    'date_joined' => $l3ref['date_created'],
                    'status' => $l3ref['status'],
                    'username' => $l3ref['username'],
                    'deposits' => $l3ref_deps,
                    'commission' => $l3ref_deps * $level3_rate,
                    'downlines' => $l3ref_downlines,
                    'level' => 'Level 3',
                    'refer' => $level3_ref_details[0]['phone']
                ];
            }

            $refferal_count = $level1_count + count($level2_downlines) + count($level3_downlines);
        }
        
        // Get investments products and user orders
        $products = $query->select('products', '*', ['status' => 'Active']);
        $investment_orders = $query->select('orders', '*', ['type' => 'investment', 'userID' => $userID], ['column' => 'ID', 'direction' => 'desc']);
        $active_investments = 0;
        $total_return = 0;
        $user_orders = [];
        foreach ($investment_orders as $order) {
            $prodID = $order['prodID'];
            $user_product = $query->select('products', '*', ['ID' => $prodID]);
            $rem = 0;
            if ($order['status'] == 'Active') {
                $active_investments++;
                $hours_neg = 3 * 60 * 60;
                $total_return += $user_product[0]['returns'];
                $initial_time = strtotime($order['time']);
                $final_time = $initial_time + (86400 * $order['duration']) - $hours_neg;
                $rem = $final_time - time();
                $rem = $rem / 86400;
            }
            
            $user_orders[] = [
                'ID' => $order['ID'],
                'product_name' => $user_product[0]['name'],
                'product_description' => $user_product[0]['description'],
                'product_price' => $order['amount'],
                'duration' => $order['duration'],
                'status' => $order['status'],
                'amount' => $order['amount'],
                'investment_date' => $order['time'],
                'total_returns' => $order['returns'],
                'return_rate' => $user_product[0]['returns'],
                'remaining' => $rem,
                'image' => $user_product[0]['image'],
                'roll' => $order['rolls']
            ];

        }

        // Get bonuses
        $bonuses = $query->select('bonus', '*', ['status' => 'Active']);
        $bonus_records = [];
        foreach ($bonuses as $bonus) {
            $bonus_orders = $query->select('orders', '*', ['userID' => $userID, 'type' => 'bonus', 'prodID' => $bonus['ID']]);
            $bonus_count = count($bonus_orders);
            $progress = 0; 
            if ($bonus_count > 0) {
                $isClaimed = true;
            }else{
                $isClaimed = false;
            }

            if ($bonus['type'] == 'users') {
                $progress = $refferal_count;
            }elseif ($bonus['type'] == 'actives') {
                $progress = $active_referral;
            }
            $bonus_records[] = [
                'ID' => $bonus['ID'],
                'name' => $bonus['name'],
                'type' => $bonus['type'],
                'reward' => $bonus['reward'],
                'is_claimed' => $isClaimed,
                'target' => $bonus['target'],
                'status' => $bonus['status'],
                'time' => $bonus['time'],
                'reward_type' => $bonus['reward_type'],
                'progress' => $progress
            ];

        }

        // Transaction controls and others
        $controls = $query->select('controls');
        $control = $controls[0];

        // Get incentives
        $incentives_records = $query->select('incentives', '*', ['status' => 'Active'], ['column' => 'ID', 'direction' => 'asc']);
        $incentives = [];
        foreach ($incentives_records as $row) {
            $incentive_reqs = $query->select('incentives_requests', '*', ['userID' => $userID, 'incentiveID' => $row['ID']]);
            if(count($incentive_reqs) > 0){
                $isClaimed = true;
            }else{
                $isClaimed = false;
            }
    
            $incentives[] = [
                    'name' => $row['name'],
                    'referrals' => $row['referrals'],
                    'salary' => $row['salary'],
                    'status' => $row['status'],
                    'level' => $row['level'],
                    'date' => $row['date'],
                    'bonusItem' => $row['bonusItem'],
                    'isClaimed' => $isClaimed,
                    'ID' => $row['ID']
                ];
        }

        $response = [
            'user' => $user,
            'wallet' => $wallet,
            'referral' => [
                'level1' => $level1_downlines,
                'level2' => $level2_downlines,
                'level3' => $level3_downlines,
                'total_downlines' => $refferal_count,
                'active_downlines' => $active_referral,
                'referral_deposits' => $total_refferal_deposits
            ],
            'products' => $products,
            'user_investments' => $user_orders,
            'bonuses' => $bonus_records,
            'transactions' => $transactions,
            'active_investment' => $active_investments,
            'average_return' => $total_return,
            'controls' => [
                'minWithdrawal' => $control['minWith'],
                'minTransfer' => $control['minTransfer'],
                'withFee' => $control['withFee'],
                'tranFee' => $control['tranFee']
            ],
            'incentives' => $incentives
        ];
        
        $fileGetContent->send_content($response);

    } catch (ExpiredException $e) {
        $fileGetContent->send_content([
            'status' => 'Error',
            'message' => 'Token expired'
        ]);

    } catch (SignatureInvalidException $e) {
        $fileGetContent->send_content([
            'status' => 'Error',
            'message' => 'Invalid token signature'
        ]);
    } catch (\Exception $e) {
        $fileGetContent->send_content([
            'status' => 'Error',
            'message' => 'Invalid token'
        ]);
    }


}else{
    $response = [
            'status' => 'Failed',
            'message' => 'No token provided'
        ];
    $fileGetContent->send_content($response);
}




