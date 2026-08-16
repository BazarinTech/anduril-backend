<?php
include '../includes/initiate.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

/**
 * Total value of a user's *settled* deposits.
 *
 * Phase 4.1. The level 1 block summed deposits inline but counted every row
 * regardless of status, so pending and failed attempts inflated the figure.
 * The level 2 and level 3 blocks fetched the transactions and then never
 * summed them at all -- `$l2ref_deps` and `$l3ref_deps` were initialised to
 * zero and used as-is, so every downline past the first level showed 0
 * deposits and 0 commission no matter what they had actually paid in.
 */
function settled_deposit_total($query, $userID)
{
    $rows  = $query->select('transactions', '*', ['userID' => $userID, 'type' => 'Deposit', 'status' => 'Success']);
    $total = 0;

    foreach ($rows as $row) {
        $total += money($row['amount']);
    }

    return $total;
}

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

        // Transaction controls. Read once here -- the old code re-read this
        // single-row table inside the level 1 loop, once per referral.
        $controls = $query->select('controls');
        $control = $controls[0];

        /**
         * Get user's referrals details.
         *
         * Phase 5.1 -- one tree walk in a fixed five queries, replacing nested
         * loops that re-queried deposits, downline counts and the controls row
         * for every single member of the team.
         */
        $tree = referral_tree($pdo, $userID);

        $level1_count            = count($tree['level1']);
        $active_referral         = $tree['counts']['actives'];
        $refferal_count          = $tree['counts']['users'];

        // Direct referrals only -- this is what the previous implementation
        // reported, and the rewrite deliberately preserves it rather than
        // quietly redefining a number the app already displays. ($tree also
        // carries 'total_deposits' for the whole three-level team, if this is
        // ever meant to mean team-wide instead.)
        $total_refferal_deposits = 0;
        foreach ($tree['level1'] as $direct) {
            $total_refferal_deposits += $tree['deposits'][(string) $direct['ID']] ?? 0;
        }

        $rates = [
            1 => money($control['level1']) / 100,
            2 => money($control['level2']) / 100,
            3 => money($control['level3']) / 100,
        ];

        $level1_downlines = [];
        $level2_downlines = [];
        $level3_downlines = [];

        foreach ([1 => 'level1', 2 => 'level2', 3 => 'level3'] as $depth => $key) {
            $bucket = [];

            foreach ($tree[$key] as $person) {
                $id   = (string) $person['ID'];
                $deps = $tree['deposits'][$id] ?? 0;

                $bucket[] = [
                    'userID'      => $person['ID'],
                    'email'       => $person['email'],
                    'phone'       => $person['phone'],
                    'date_joined' => $person['date_created'],
                    'status'      => $person['status'],
                    'username'    => $person['username'],
                    'deposits'    => $deps,
                    'commission'  => $deps * $rates[$depth],
                    'downlines'   => $tree['downlines'][$id] ?? 0,
                    'level'       => 'Level ' . $depth,
                    // Level 1 came from this user directly; deeper members name
                    // whoever referred them, resolved from rows already loaded.
                    'refer'       => $depth === 1
                        ? 'Direct'
                        : ($tree['phoneById'][(string) $person['upline']] ?? ''),
                ];
            }

            if ($depth === 1) { $level1_downlines = $bucket; }
            elseif ($depth === 2) { $level2_downlines = $bucket; }
            else { $level3_downlines = $bucket; }
        }
        // Get investments products and user orders
        $products = $query->select('products', '*', ['status' => 'Active']);

        // Resolve each product's image to a URL, for the same reason as above.
        foreach ($products as $i => $p) {
            $products[$i]['image_url'] = product_image_url($p['image'] ?? '');
        }
        $investment_orders = $query->select('orders', '*', ['type' => 'investment', 'userID' => $userID], ['column' => 'ID', 'direction' => 'desc']);

        // Phase 5.1 -- every product once, indexed by ID, instead of one query
        // per order. Retired products are included: an existing order still
        // needs to render its plan name even after the plan is deactivated.
        $product_by_id = [];
        foreach ($query->select('products') as $p) {
            $product_by_id[(string) $p['ID']] = $p;
        }

        /**
         * Claim state is derived, not stored. `orders.rolls` used to answer
         * "can this be claimed?", which meant the answer was only correct if
         * something had run overnight to set it. The window is computed once
         * here and applied to every order in the loop.
         */
        $claim_window = claim_window(claim_settings($query));

        $active_investments = 0;
        $total_return = 0;
        $user_orders = [];
        foreach ($investment_orders as $order) {
            $prodID = $order['prodID'];
            $user_product = isset($product_by_id[(string) $prodID]) ? [$product_by_id[(string) $prodID]] : [];
            $rem = 0;
            if ($order['status'] == 'Active') {
                $active_investments++;
                $total_return += money($user_product[0]['returns'] ?? 0);

                /**
                 * Phase 4.14 -- the `- $hours_neg` term (a flat three hours)
                 * that used to sit in this sum was compensating for PHP and
                 * MySQL disagreeing about the timezone by exactly +03:00.
                 * That cause was fixed in bootstrap.php, so the correction is
                 * now an over-correction that matures every order three hours
                 * early. Removed.
                 */
                $initial_time = strtotime($order['time']);
                $final_time = $initial_time + (86400 * (int) $order['duration']);
                $rem = ($final_time - time()) / 86400;
            }
            
            /**
             * Three separate reasons an order cannot be claimed, and the app
             * should be able to tell them apart: the window is shut, today's
             * claims are spent, or the investment has finished.
             */
            $claims_left = order_claims_left($order, $claim_window);

            if ($order['status'] !== 'Active') {
                $claimable    = false;
                $claim_reason = 'This investment has matured.';
            } elseif (!$claim_window['open']) {
                $claimable    = false;
                $claim_reason = $claim_window['message'];
            } elseif ($claims_left < 1) {
                $claimable    = false;
                $claim_reason = 'Already claimed today. Opens again at '
                    . claim_pretty_time($claim_window['opens']) . '.';
            } else {
                $claimable    = true;
                $claim_reason = $claim_window['per_day'] > 1
                    ? $claims_left . ' of ' . $claim_window['per_day'] . ' claims left today.'
                    : 'Ready to claim.';
            }

            $user_orders[] = [
                'ID' => $order['ID'],
                // A product deleted out from under an existing order must not
                // take the whole dashboard down with it.
                'product_name' => $user_product[0]['name'] ?? '(product removed)',
                'product_description' => $user_product[0]['description'] ?? '',
                'product_price' => $order['amount'],
                'duration' => $order['duration'],
                'status' => $order['status'],
                'amount' => $order['amount'],
                'investment_date' => $order['time'],
                'total_returns' => $order['returns'],
                'return_rate' => $user_product[0]['returns'] ?? 0,
                'remaining' => $rem,
                /**
                 * A resolved URL, not a bare filename.
                 *
                 * The app used to build this itself by pasting the filename
                 * onto a hardcoded `https://sanderson.xgramm.com/admin/uploads/`.
                 * That put knowledge of where images live in a client that
                 * cannot be redeployed as easily as this one, and it broke the
                 * moment storage moved. `image` is kept alongside it so an
                 * older build of the app keeps working.
                 */
                'image_url' => product_image_url($user_product[0]['image'] ?? ''),
                'image' => $user_product[0]['image'] ?? '',
                /**
                 * `roll` stays a 0/1 int because that is the contract the app
                 * already reads -- it disables the claim button on 0. What it
                 * means has changed underneath: it is now "claimable right
                 * now", computed from the window and this order's own counter,
                 * rather than a flag waiting to be reset.
                 */
                'roll' => $claimable ? 1 : 0,

                // Richer state, for a UI that wants to say more than
                // "please come back later".
                'claimable'      => $claimable,
                'claims_left'    => $claims_left,
                'claims_per_day' => $claim_window['per_day'],
                'claim_reason'   => $claim_reason,
                'claim_opens_at' => $claim_window['opens'],
                'claim_closes_at'=> $claim_window['closes'],
                'next_claim_at'  => $claimable ? null : ($claims_left < 1 ? $claim_window['next_period_open'] : $claim_window['next_open']),
                'last_claim_at'  => $order['last_claim_at'] ?? null,
            ];

        }

        // Get bonuses
        $bonuses = $query->select('bonus', '*', ['status' => 'Active']);
        $bonus_records = [];

        // Phase 5.1 -- one query for every bonus this user has claimed,
        // instead of one per bonus on offer.
        $claimed_bonus_ids = [];
        foreach ($query->select('orders', '*', ['userID' => $userID, 'type' => 'bonus']) as $claim) {
            $claimed_bonus_ids[(string) $claim['prodID']] = true;
        }

        foreach ($bonuses as $bonus) {
            $progress = 0;
            $isClaimed = isset($claimed_bonus_ids[(string) $bonus['ID']]);

            // Phase 4.2 -- progress comes from the same counts bonus.php
            // judges the claim with, so the bar and the button cannot disagree.
            // The tree is already in hand, so this costs no further queries.
            if ($bonus['type'] == 'users' || $bonus['type'] == 'actives') {
                $progress = bonus_progress($pdo, $userID, $bonus['type'], $tree['counts']);
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

        // Get incentives
        // ($control was read once near the top, before the referral walk.)
        $incentives_records = $query->select('incentives', '*', ['status' => 'Active'], ['column' => 'ID', 'direction' => 'asc']);
        $incentives = [];

        // Phase 5.1 -- one query for this user's applications, rather than one
        // per incentive on offer.
        $applied_incentive_ids = [];
        foreach ($query->select('incentives_requests', '*', ['userID' => $userID]) as $req) {
            $applied_incentive_ids[(string) $req['incentiveID']] = true;
        }

        foreach ($incentives_records as $row) {
            $isClaimed = isset($applied_incentive_ids[(string) $row['ID']]);


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
            /**
             * The window itself, so the app can show one banner ("Claiming
             * opens at 7:00 AM") instead of repeating the same reason on every
             * order card, and can count down to next_opens_at without polling.
             */
            'claim_window' => [
                'enforced'      => $claim_window['enabled'],
                'open'          => $claim_window['open'],
                'opens_at'      => $claim_window['opens'],
                'closes_at'     => $claim_window['closes'],
                'claims_per_day'=> $claim_window['per_day'],
                'next_opens_at' => $claim_window['next_open'],
                'closes_on'     => $claim_window['closes_at'],
                'message'       => $claim_window['message'],
                'server_time'   => date('Y-m-d H:i:s'),
                'timezone'      => date_default_timezone_get(),
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




