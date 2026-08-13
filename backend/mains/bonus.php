<?php
include '../includes/initiate.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
// Get posted json data
$data = $fileGetContent->get_content();

// Process bonus 
if (isset($data)) {
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
        $wallet = $query->select('wallets', '*', ['userID' => $userID]);
        $balance = $wallet[0]['balance'];
        $bonusID = $data['bonusID'];
        $bonus = $query->select('bonus', '*', ['ID' => $bonusID]);
        $bonus = $bonus[0];
        $reward = $bonus['reward'];
        $target = $bonus['target'];
        $bonus_type = $bonus['type'];
        $reward_type = $bonus['reward_type'];
    
        // Check is bonus order exists
        $bonus_order = $query->select('orders', '*', ['type' => 'bonus', 'userID' => $userID, 'prodID' => $bonusID]);
        if(count($bonus_order) == 0){
            if ($bonus_type == 'users') {
                $downlines = count($query->select('users', '*', ['upline' => $userID]));
                if ($downlines >= $target){
                    if ($reward_type == 'money') {
                        $balance += $reward;
                        $update_wallet = $query->update('wallets', ['balance' => $balance], ['userID' => $userID]);
                        $insert_order = $query->insert('orders', ['userID' => $userID, 'prodID' => $bonusID, 'type' => 'bonus', 'amount' => $reward]);
                        $response = ['status' => 'Success'];
                    }
                }else{
                    $response = ['status' => 'Failed'];
                }
            }elseif ($bonus_type == 'actives') {
                $downlines = count($query->select('users', '*', ['upline' => $userID, 'status' => 'Active']));
                if ($downlines >= $target){
                    if ($reward_type == 'money') {
                        $balance += $reward;
                        $update_wallet = $query->update('wallets', ['balance' => $balance], ['userID' => $userID]);
                        $insert_order = $query->insert('orders', ['userID' => $userID, 'prodID' => $bonusID, 'type' => 'bonus', 'amount' => $reward]);
                        $insert_transaction = $query->insert('transactions', [
                            'userID'      => $userID,
                            'type'        => 'Bonus',
                            'amount'      => $reward,
                            'description' => 'Bonus credited successfully to your wallet.',
                            'status'      => 'Completed'
                        ]);
                        $response = ['status' => 'Success', 'message' => 'Bonus credited successfully'];
                    }
                }else{
                    $response = ['status' => 'Failed'];
                }
            }
        }else{
            $response = ['status' => 'Failed'];
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
    $response = [
            'status' => 'Failed',
            'message' => 'No token provided'
        ];
    $fileGetContent->send_content($response);
}