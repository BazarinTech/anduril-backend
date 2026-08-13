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

        // Fetch only active investments
        $orders = $query->select('orders', '*', [
            'userID' => $userID,
            'type'   => 'investment',
            'ID'     => $orderID,
        ]);
        $order = $orders[0];

        $products = $query->select('products', '*', ['ID' => $order['prodID']]);
        $product = $products[0];

        // Check if  the order has a roll of 0
        $order_roll = $order['rolls'];

        if ($order_roll == 0) {
            $response = ['status' => 'Error', 'message' => 'Seems you have already claimed this order, please comeback later for another claim', 'order' => $order['ID']];
            $fileGetContent->send_content($response);
            exit;
        }

        if ($order['status'] === 'Active') {
            // Calculate profit
            $profit = $product['returns'];

            // Add both capital and profit to user wallet
            $user_wallet = $query->select('wallets', '*', ['userID' => $userID]);
            $newBalance = $user_wallet[0]['balance'] + $profit;
            $newIncome  = $user_wallet[0]['income'] + $profit;
            $newRemainingDays = $order['remaining'] - 1;
            $todayIncome = $user_wallet[0]['today_income'] + $profit;

            // Update wallet
            $query->update('wallets', [
                'balance' => $newBalance,
                'income'  => $newIncome,
                'today_income' => $todayIncome
            ], ['userID' => $userID]);
            
            $query->update('orders', [
                'rolls' => '0',
            ], ['ID' => $orderID]);

             $insert_transaction = $query->insert('transactions', [
                    'userID'      => $userID,
                    'type'        => 'Product Income',
                    'amount'      => $profit,
                    'description' => 'Investment order #' . $order['ID'] . ' has matured. Capital of ' . $product['max'] . ' has been returned to your wallet.',
                    'status'      => 'Completed'
                ]);

            // Mark investment as complete if days have endend (Expired)
            if ($newRemainingDays <= 0) {
                $query->update('orders', [
                    'status'  => 'Expired',
                    'returns' => $order['returns'] + $profit,
                    'remaining' => 0
                ], ['ID' => $order['ID']]);


                $response = ['status' => 'Success', 'message' => 'Order promoted succesfully and your investment has matured', 'order' => $order['ID']];
            } else {
                // Update only returns and remaining days
                $query->update('orders', [
                    'returns' => $order['returns'] + $profit,
                    'remaining' => $newRemainingDays
                ], ['ID' => $order['ID']]);

                $response = ['status' => 'Success', 'message' => 'Order promoted succesfully and income credited to your wallet', 'order' => $order['ID']];
            }
        } else {
            $response = ['status' => 'Error', 'message' => 'Order is expired please buy a new one', 'order' => $order['ID']];
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
