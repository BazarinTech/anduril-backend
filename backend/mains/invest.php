<?php
include '../includes/initiate.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

// Check limit
function check_limit($prodID, $userID, $query){
    $user_orders = $query->select('orders', '*', ['prodID' => $prodID, 'userID' => $userID, 'type' => 'investment', 'status' => 'Active']);
    $prod_details = $query->select('products', '*', ['ID' => $prodID]);
    $prod_limit = $prod_details[0]['order_limit'];
    
    if(count($user_orders) >= $prod_limit){
        return true;
    }else{
        if($prodID == 1 ){
            $free_orders = $query->select('orders', '*', ['prodID' => $prodID, 'userID' => $userID, 'type' => 'investment']);
           if(count($free_orders) >= 1){
               return true;
           }else{
               return false;
           }
        }
        return false;
    }
}

// Get posted json data
$data = $fileGetContent->get_content();

// Process order investment
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
        
        $prodID = $data['prodID'];
        $amount = $data['amount'];

        // Phase 3.6 -- reject anything that is not a positive number before it
        // reaches a wallet. "50abc" used to pass a `>= $min` comparison as 50.
        if (!is_valid_amount($amount)) {
            $fileGetContent->send_content([
                'status' => 'Failed',
                'message' => 'Please enter a valid amount.',
            ]);
            exit;
        }
        $amount = money($amount);

        $products = $query->select('products', '*', ['ID' => $prodID]);
        $product = $products[0] ?? null;

        // Phase 4.7 territory, but a missing or retired product must not be
        // purchasable at all, and this is the transaction boundary.
        if ($product === null || $product['status'] !== 'Active') {
            $fileGetContent->send_content([
                'status' => 'Failed',
                'message' => 'This product is not available.',
            ]);
            exit;
        }

        $min = $product['min'];
        $max = $product['max'];
        $is_limit_exceeded = check_limit($prodID, $userID, $query);

        if($is_limit_exceeded){
            $response = [
                    'status' => 'Failed',
                    'message' => 'You cannot enroll in this product beyond its limit!',
                ];
            $fileGetContent->send_content($response);
            exit;
        }

        if ($amount >= money($min)) {
            if ($amount <= money($max)) {

                // Phase 3.2 -- lock the wallet, then check and debit. Reading
                // the balance before the lock is what let two simultaneous
                // purchases both pass an "enough balance" check.
                $pdo->beginTransaction();

                try {
                    $wallet = wallet_for_update($pdo, $userID);
                    $user_balance = money($wallet['balance'] ?? 0);

                    if ($wallet !== null && $user_balance >= $amount) {
                        $user_balance -= $amount;
                        $insert_orders = $query->insert('orders', ['type' => 'investment', 'userID' => $userID, 'remaining' => $product['duration'], 'returns' => '0', 'amount' => money_str($amount), 'prodID' => $prodID, 'duration' => $product['duration']]);
                        $update_wallet = $query->update('wallets', ['balance' => money_str($user_balance)], ['userID' => $userID]);

                        if($prodID != 1){
                            $update_user_status = $query->update('users', ['status' => 'Active'], ['ID' => $userID]);
                        }

                        $pdo->commit();

                        $response = [
                            'status' => 'Success',
                            'message' => 'Enrolled Successful',
                        ];
                    }else{
                        $pdo->rollBack();

                        $response = [
                            'status' => 'Failed',
                            'message' => 'Not enough balance',
                        ];
                    }
                } catch (\Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    error_log('[invest] ' . $e->getMessage());

                    $response = [
                        'status' => 'Failed',
                        'message' => 'Could not complete the investment. Please try again.',
                    ];
                }
            }else{
                $response = [
                    'status' => 'Failed',
                    'message' => 'maximum investment of this product is Kes'.$max.'',
                ];
            }

        }else{
            $response = [
                    'status' => 'Failed',
                    'message' => 'Minimum investment of this product is Kes'.$min.'',
                ];
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