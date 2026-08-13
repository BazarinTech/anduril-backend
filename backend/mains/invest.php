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
        $products = $query->select('products', '*', ['ID' => $prodID]);
        $product = $products[0];
        $user_wallet = $query->select('wallets', '*', ['userID' => $userID]);
        $user_balance = $user_wallet[0]['balance'];
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

        if ($amount >= $min) {
            if ($amount <= $max) {
                if ($user_balance >= $amount) {
                    $user_balance -= $amount;
                    $insert_orders = $query->insert('orders', ['type' => 'investment', 'userID' => $userID, 'remaining' => $product['duration'], 'returns' => '0', 'amount' => $amount, 'prodID' => $prodID, 'duration' => $product['duration']]);
                    $update_wallet = $query->update('wallets', ['balance' => $user_balance], ['userID' => $userID]);
                    
                    if($prodID != 1){
                        $update_user_status = $query->update('users', ['status' => 'Active'], ['ID' => $userID]);
                    }
                    
                    $response = [
                        'status' => 'Success',
                        'message' => 'Enrolled Successful',
                    ];
                }else{
                    $response = [
                        'status' => 'Failed',
                        'message' => 'Not enough balance',
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