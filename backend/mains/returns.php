<?php
include '../includes/initiate.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

// Get posted json data
$data = $fileGetContent->get_content();

// Process investment income
if (isset($data['userID']) && isset($data['orderID'])) {
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

        $orderID = $data['orderID'];

        /**
         * Phase 3.5 -- this was the easiest race in the system to exploit.
         *
         * The old sequence was: read `rolls`, return early if it is 0, credit
         * the wallet, then set `rolls` to 0. Two requests fired together both
         * read 1, both passed the check, and both paid out -- as many times as
         * you could get requests in flight before the first write landed.
         *
         * The claim is still a single conditional UPDATE, so exactly one
         * caller can see a row count of 1 and everyone else is told the claim
         * is already taken. What changed is the condition: it is now "this
         * order has claims left in the current period" rather than "somebody
         * set rolls back to 1 last night". See bootstrap/claims.php.
         *
         * The wallet lock that follows protects the credit arithmetic itself.
         */
        $window = claim_window(claim_settings($query));

        // Refuse outside the window before touching anything. Doing this first
        // means a closed window costs one query, not a transaction.
        if (!$window['open']) {
            $fileGetContent->send_content([
                'status'         => 'Error',
                'message'        => $window['message'],
                'order'          => $orderID,
                'claim_opens_at' => $window['opens'],
                'next_claim_at'  => $window['next_open'],
            ]);
            exit;
        }

        $pdo->beginTransaction();

        try {
            // Fetch only this user's investment orders
            $orders = $query->select('orders', '*', [
                'userID' => $userID,
                'type'   => 'investment',
                'ID'     => $orderID,
            ]);
            $order = $orders[0] ?? null;

            if ($order === null) {
                $pdo->rollBack();
                $fileGetContent->send_content(['status' => 'Error', 'message' => 'Order not found', 'order' => $orderID]);
                exit;
            }

            if ($order['status'] !== 'Active') {
                $pdo->rollBack();
                $fileGetContent->send_content(['status' => 'Error', 'message' => 'Order is expired please buy a new one', 'order' => $order['ID']]);
                exit;
            }

            // Take the claim, or discover this order has none left today.
            if (!take_order_claim($pdo, $orderID, $userID, $window)) {
                $pdo->rollBack();

                $perDay = $window['per_day'];

                $fileGetContent->send_content([
                    'status'  => 'Error',
                    'message' => $perDay > 1
                        ? 'This order has used all ' . $perDay . ' of its claims for today. Please come back tomorrow.'
                        : 'You have already claimed this order today. Please come back tomorrow.',
                    'order'          => $order['ID'],
                    'claim_opens_at' => $window['opens'],
                    // The window is open right now -- what this caller is
                    // waiting for is the *next* one.
                    'next_claim_at'  => $window['next_period_open'],
                ]);
                exit;
            }

            $products = $query->select('products', '*', ['ID' => $order['prodID']]);
            $product = $products[0] ?? null;

            if ($product === null) {
                $pdo->rollBack();
                $fileGetContent->send_content(['status' => 'Error', 'message' => 'Product not found for this order', 'order' => $order['ID']]);
                exit;
            }

            // Calculate profit
            $profit = money($product['returns']);

            $user_wallet = wallet_for_update($pdo, $userID);

            if ($user_wallet === null) {
                $pdo->rollBack();
                $fileGetContent->send_content(['status' => 'Error', 'message' => 'Wallet not found', 'order' => $order['ID']]);
                exit;
            }

            $newBalance = money($user_wallet['balance']) + $profit;
            $newIncome  = money($user_wallet['income']) + $profit;
            $newRemainingDays = (int) $order['remaining'] - 1;

            // today_income used to be zeroed by the nightly job. It now rolls
            // over on the first credit of a new period, so it reads correctly
            // without anything having had to run at midnight.
            $todayIncome = wallet_day_income($user_wallet, $profit, $window);

            // Update wallet
            $query->update('wallets', [
                'balance'       => money_str($newBalance),
                'income'        => money_str($newIncome),
                'today_income'  => $todayIncome,
                'income_period' => $window['period'],
            ], ['userID' => $userID]);

            // The old description claimed the capital had been returned. It
            // never was -- only the flat daily return is credited (finding 4.6).
            $insert_transaction = $query->insert('transactions', [
                    'userID'      => $userID,
                    'type'        => 'Product Income',
                    'amount'      => money_str($profit),
                    'description' => 'Daily return credited for investment order #' . $order['ID'] . '.',
                    'status'      => 'Completed'
                ]);

            // Mark investment as complete if days have endend (Expired)
            if ($newRemainingDays <= 0) {
                $query->update('orders', [
                    'status'  => 'Expired',
                    'returns' => money_str(money($order['returns']) + $profit),
                    'remaining' => 0
                ], ['ID' => $order['ID']]);

                $response = ['status' => 'Success', 'message' => 'Order promoted succesfully and your investment has matured', 'order' => $order['ID']];
            } else {
                // Update only returns and remaining days
                $query->update('orders', [
                    'returns' => money_str(money($order['returns']) + $profit),
                    'remaining' => $newRemainingDays
                ], ['ID' => $order['ID']]);

                $response = ['status' => 'Success', 'message' => 'Order promoted succesfully and income credited to your wallet', 'order' => $order['ID']];
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('[returns] ' . $e->getMessage());

            $response = ['status' => 'Error', 'message' => 'Could not credit the return. Please try again.', 'order' => $orderID];
        }

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
    $fileGetContent->send_content([
        'status' => 'Error',
        'message' => 'Some fields are empty'
    ]);
}
