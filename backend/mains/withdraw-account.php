<?php
include '../includes/initiate.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

// Get posted json data
$data = $fileGetContent->get_content();

// Process account data
if (isset($data['userID']) && isset($data['type'])) {
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
        $type = $data['type'];
        if ($type == 'create') {
            $phone = $data['phone'];
            $account_name = $data['accountName'];
            $pin = $data['pin'];
            
            $insert_withdraw = $query->update('wallets', [
                'userID' => $userID,
                'withdrawal_account' => $phone,
                'withdrawal_name' => $account_name,
                'withdrawal_pin' => $pin
            ], ['userID' => $userID]);

            $response = [
                'status' => 'Success',
                'message' => 'Withdrawal request submitted successfully!'
            ];
        }elseif($type == 'update'){
            $phone = $data['phone'];
            $account_name = $data['accountName'];
            $pin = $data['pin'];
            $wallets = $query->select('wallets', '*', ['userID' => $userID]);
            $withdrawal_pin = $wallets[0]['withdrawal_pin'];
            if($pin == $withdrawal_pin){
                $update_withdraw = $query->update('wallets', [
                'withdrawal_account' => $phone,
                'withdrawal_name' => $account_name,
                ],[
                    'userID' => $userID
                ]);
    
                $response = [
                    'status' => 'Success',
                    'message' => 'Withdrawal account updated successfully!'
                ];
            }else{
                $response = [
                    'status' => 'Failed',
                    'message' => 'Incorrect withdrawal pin!'
                ];
            }
            
        }elseif($type == 'update_pin'){
            $pin = $data['pin'];
            
            $update_withdraw = $query->update('wallets', [
                'withdrawal_pin' => $pin
            ],[
                'userID' => $userID
            ]);

            $response = [
                'status' => 'Success',
                'message' => 'Withdrawal account updated successfully!'
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
    $response = [
            'status' => 'Failed',
            'message' => 'Some fields are empty'
        ];
    $fileGetContent->send_content($response);
}