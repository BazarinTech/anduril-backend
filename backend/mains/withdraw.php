<?php
//get initiator
include '../includes/initiate.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
include 'send-email.php';
include 'transaction-sms.php';

//get data posted remotely
$data = $fileGetContent->get_content();

//generate tracking ID based on date and time 
function create_tracking_ID() {
    // Get the current time formatted as 'His' (HourMinuteSecond)
    $timestamp = date('His');

    // Generate a random number between 100 and 999 for simplicity
    $random_number = rand(100, 999);

    // Format the tracking ID as 'INV-<timestamp>-<random_number>'
    $tracking_ID = 'INV-' . $timestamp . '-' . $random_number;

    return $tracking_ID;
};

 
//payment method payhero auto mpesa function
// function mpesa_auto($query, $userID, $amount, $account, $api){
//     $trackingID = create_tracking_ID();
//     $fees = $amount * 0.08;
//     $send_amount = $amount - $fees;
//     $headers = [
//         'Authorization: Basic OGlUSFZFaUhMWnN3a2hHVVBHc3A6anVoWFZrRk5qSVl0MGNMOERGMlR3dlhrQ0VWUWJHNDVVVnNaMEdDSw=='
//     ];
//     $data = [
//         "amount" => (float) $send_amount,
//         "phone_number" => $account,
//         "channel_id" => 1549, 
//         "external_reference" => $trackingID,
//         "callback_url" => "https://m-verbal.club",
//         "network_code" => "63902",
//         "channel" => "mobile",
//         "payment_service" => "b2c" 
//     ];
//     $inititate = $api->request('https://backend.payhero.co.ke/api/v2/withdraw', 'POST', $data, $headers);

//     if (!isset($inititate['error_code']) && isset($inititate['status'])){
//         if($inititate['status'] === 'QUEUED'){
//             $query->insert('transactions', ['userID' => $userID, 'amount' => $amount, 'fees' => $fees, 'account' => $account, 'trackingID' => $trackingID, 'type' => 'Withdraw', 'status' => 'Success' ]);
//             $res = [
//                 'status' => 'Success',
//                 'message' => 'Withdrawal made succesfully, fee charge Kes '.$fees.' amount to recieve kes '.$send_amount.'. Expect to recieve your funds within 1 minute. If not, contact our support team.',
//             ];
//         }else{
//             $res = [
//             'status' => 'Failed',
//             'message' => 'An error occured!'
//         ];
//         }

//     }else {
//         $res = [
//             'status' => 'Failed',
//             'message' => $inititate['error_message']
//         ];
//     }

//     return $res;
// }

// Palplus auto payment method
function mpesa_auto($query, $userID, $amount, $account, $api, $trackingID){
    $headers = [
        'Authorization: Basic pp_live_537b508caed8ce9e4a39d3f38d975b039b1df6ac355f3851'
    ];
    $data = [
        "amount" => (float) $amount,
        "phone" => $account,
        "reference" => $trackingID,
        "callbackUrl" => "https://sanderson.xgramm.com/backend/mains/callbacks/palpluss_b2c_callback.php",
        "description" => "BusinessPayment"
    ];
    $inititate = $api->request('https://api.palpluss.com/v1/b2c/payouts', 'POST', $data, $headers);

  if ($inititate['success']) {
        $res = [
            'status' => 'Success',
            'message' => 'Mpesa transaction initiated successfully',
        ];
    }elseif(isset($inititate['error'])){
        $res = [
            'status' => 'Failed',
            'message' => $inititate['error']['message'],
        ];
    }else{
        $res = [
            'status' => 'Failed',
            'message' => 'Transaction Failed. Kindly reach our customer service for quick assistance',
        ];
    }

    return $res;
}

// // Swiftwallet auto payment method
// function mpesa_auto($query, $userID, $amount, $account, $api, $trackingID){
//     $headers = [
//         'Authorization: Bearer sw_ce486d659125d489f08c2db78f00fa434f6e7fd1b0938a389dfc2c40'
//     ];
//     $data = [
//         "amount" => (float) $amount,
//         "phone_number" => $account,
//         "external_reference" => $trackingID,
//         "callback_url" => "https://sanderson.xgramm.com/backend/mains/callbacks/swiftwallet_b2c_callback.php"
//     ];
//     $inititate = $api->request('https://swiftwallet.co.ke/v3/pay-request/', 'POST', $data, $headers);

//   if ($inititate['success']) {
//         $res = [
//             'status' => 'Success',
//             'message' => 'Mpesa transaction initiated successfully',
//         ];
//     }elseif(isset($inititate['error'])){
//         $res = [
//             'status' => 'Failed',
//             'message' => $inititate['error'],
//         ];
//     }else{
//         $res = [
//             'status' => 'Failed',
//             'message' => 'Transaction Failed. Kindly reach our customer service for quick assistance',
//         ];
//     }

//     return $res;
// }

//process withdrwal request
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
        $method = $data['method'];
        $pin = $data['pin'];

        //get transaction controls from database
        $controls = $query->select('controls');
        $control = $controls[0];
        $min = $control['minWith'];

        //get user wallet details
        $user_wallet = $query->select('wallets', '*', ['userID' => $userID]);
        $user_wallet = $user_wallet[0];
        $balance = $user_wallet['balance'];
        $account = $user_wallet['withdrawal_account'];
        $name = $user_wallet['withdrawal_name'];
        $withdrawal_pin = $user_wallet['withdrawal_pin'];

        // Check if withdrawal account is set
        if (empty($account) || empty($name)) {
            $fileGetContent->send_content([
                'status' => 'Error',
                'message' => 'Please set your withdrawal account details before making a withdrawal.'
            ]);
            exit;
        }
        
        // Get user details 
        $user_details = $query->select('users', '*', ['ID' => $userID]);
        
        // check withdrawal pin
        if($withdrawal_pin == $pin){
            
             //check if user account balance is sufficient
        if($balance >= $amount){

            //check if the amount is greater than the minimum withdrawal
            if ($amount >= $min) {

                //initiate withdrawal transaction
                $trackingID = create_tracking_ID();
                $fees = $amount * $control['withFee'] / 100;
                $send_amount = $amount - $fees;
                $balance -= $amount;
                $query->update('wallets', ['balance' => $balance], ['userID' => $userID]);
                
                $initiate = mpesa_auto($query, $userID, $send_amount, $account, $curl, $trackingID);
                
                //  $initiate = $query->insert('transactions', ['userID' => $userID, 'amount' => $amount, 'fees' => $fees, 'account' => $account, 'trackingID' => $trackingID, 'type' => 'Withdraw', 'status' => 'Pending', 'method' => $method ]);
        
                if ($initiate['status'] == "Success") {
                    
                    $query->insert('transactions', ['userID' => $userID, 'amount' => $amount, 'fees' => $fees, 'account' => $account, 'trackingID' => $trackingID, 'type' => 'Withdraw', 'status' => 'Processing', 'method' => $method ]);
                    $body = pending_withdrawal_template($user_details[0]['name'], $amount, $fees);
                    $email_res = send_email($user_details[0]['email'], 'Withdrawal Submitted Succesfully', $body);
                    
                    $body = success_withdrawal_template($user_details[0]['name'], $amount, $trackingID);
                    $email_res = send_email($user_details[0]['email'], 'Withdrawal Processed Successfully', $body);
                    $response = [
                        'status' => 'Success',
                        'message' => 'Withdrawal made succesfully, fee charge Kes '.$fees.' amount to recieve kes '.$send_amount.'. Expect to recieve your funds within 3hours. If not, contact our support team.'
                    ];
                }else{
                    
                    $query->insert('transactions', ['userID' => $userID, 'amount' => $amount, 'fees' => $fees, 'account' => $account, 'trackingID' => $trackingID, 'type' => 'Withdraw', 'status' => 'Pending', 'method' => $method ]);
                    $body = pending_withdrawal_template($user_details[0]['name'], $amount, $fees);
                    $email_res = send_email($user_details[0]['email'], 'Withdrawal Submitted Succesfully', $body);
                    $response = [
                        'status' => 'Success',
                        'message' => 'Withdrawal made succesfully, fee charge Kes '.$fees.' amount to recieve kes '.$send_amount.'. Expect to recieve your funds within 3hours. If not, contact our support team.'
                    ];
                }
                
                if ($initiate){
                    $response = [
                        'status' => 'Success',
                        'message' => 'Withdrawal made succesfully, fee charge Kes '.$fees.' amount to recieve kes '.$send_amount.'. Expect to recieve your funds within 3hours. If not, contact our support team.'
                    ];
                }else{
                    $response = [
                        'status' => 'Failed',
                        'message' => 'An error occured while trying to initaiate request. Please try again later.',
                    ];
                }
                
            }else{
                $response = [
                    'status' => 'Failed',
                    'message' => 'Minimum withdrawal is kes '.$min,
                ];
            }
        }else{
            $response = [
                'status' => 'Failed',
                'message' => 'Insuficient balance to perform this transaction!',
            ];
        }
            
        }else{
            $response = [
                        'status' => 'Failed',
                        'message' => 'Incorrect withdrawal pin.',
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