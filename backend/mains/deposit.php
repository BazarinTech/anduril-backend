<?php
//get initiator
include '../includes/initiate.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

//get data posted remotely
$data = $fileGetContent->get_content();

//generate tracking ID based on date and time 
function create_tracking_ID() {
    $timestamp = date('His');

    $random_number = rand(100, 999);

    $tracking_ID = 'INV-' . $timestamp . '-' . $random_number;

    return $tracking_ID;
};

// payment method auto mpesa function for palpluss
function mpesa_auto($query, $userID, $amount, $account, $api, $trackingID){
    $headers = [
        'Authorization: Basic ' . env('PALPLUSS_KEY')
    ];

    $data = [
        "amount" => (float) $amount,
        "phone" => $account,
        "accountReference" => $trackingID,
        "callbackUrl" => callback_url('palpluss_deposit_callback.php'),
        "channelId" => env('PALPLUSS_CHANNEL_ID'),
        "credential_id" => env('PALPLUSS_CREDENTIAL_ID')
    ];

    $inititate = $api->request(env('PALPLUSS_STK_URL'), 'POST', $data, $headers);
    

    if ($inititate['success']) {
        $query->insert('transactions', ['userID' => $userID, 'amount' => $amount,  'account' => $account, 'trackingID' => $trackingID, 'type' => 'Deposit' ]);
        $res = [
            'status' => 'Success',
            'message' => 'Mpesa transaction initiated successfully. Kindly confirm with mpesa pin to complete',
            'trackingID' => $trackingID
        ];
    }else{
        $res = [
            'status' => 'Failed',
            'message' => $inititate['error']['message'],
        ];
    }

    return $res;
}

// process deposits
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
        $amount = $data['amount'];
        $account = $data['account'];
        $method = $data['method'];
        $trackingID = create_tracking_ID();
    
        // get transaction controls from database
        $controls = $query->select('controls');
        $control = $controls[0];
        $min = $control['minDep'];
        
        // Get last deposit details
        $user_transactions = $query->select('transactions', '*', ['userID' => $userID, 'type' => 'Deposit'], ['direction' => 'desc', 'column' => 'ID']);
        $last_deposit = $user_transactions[0];
        
        // Now ensure that last deposit must take more than 10 seconds before initating another one 
        $time_diff = time() - strtotime($last_deposit['time']);
        // if($time_diff < 10){
        //     $response = [
        //         'status' => 'Failed',
        //         'message' => 'Next transaction should be initated after 10 seconds',
        //     ];
        //     $fileGetContent->send_content($response);
        //     exit;
        // }
    
        // check if the amount is greater than the minimum deposit
        if ($amount >= $min) {
            
            if($method == 'mpesa'){
                $response = mpesa_auto($query, $userID, $amount, $account, $curl, $trackingID);
            }else{
               $initiate = $query->insert('transactions', ['userID' => $userID, 'amount' => $amount,  'account' => $account, 'trackingID' => $trackingID, 'type' => 'Deposit', 'method'=> $method ]);
               $response = [
                    'status' => 'Success',
                    'message' => $method.' transaction initiated successfully. Your funds will reflect instantly after review',
                ];
            }
            
            
        }else{
            $response = [
                'status' => 'Failed',
                'message' => 'Minimum deposit is kes '.$min,
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





