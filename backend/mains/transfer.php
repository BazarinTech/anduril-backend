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
    // Get the current time formatted as 'His' (HourMinuteSecond)
    $timestamp = date('His');

    // Generate a random number between 100 and 999 for simplicity
    $random_number = rand(100, 999);

    // Format the tracking ID as 'INV-<timestamp>-<random_number>'
    $tracking_ID = 'INV-' . $timestamp . '-' . $random_number;

    return $tracking_ID;
};

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
        $recipient = $data['recipient'];
        $method = $data['method'];

        //get transaction controls from database
        $controls = $query->select('controls');
        $control = $controls[0];
        $min = $control['minTransfer'];

         //get user wallet details
        $user_wallet = $query->select('wallets', '*', ['userID' => $userID]);
        $user_wallet = $user_wallet[0];
        $balance = $user_wallet['balance'];

        // Declare fees and total amount to be charges
        $fees = $amount * $control['tranFee'] / 100;
        $total_charge = $amount + $fees;
        
        // Get recipient details 
        $recipient_details = $query->selectOR('users', '*', ['username' => $recipient, 'email' => $recipient]);
        
        // Get recipient details 
        $sender_details = $query->select('users', '*', ['ID' => $userID]);
        $sender_uname = $sender_details[0]['username'];
        $sender_email = $sender_details[0]['email'];
        
        if($sender_email == $recipient OR $sender_uname == $recipient){
            $fileGetContent->send_content([
                'status' => 'Failed',
                'message' => 'The recipient user cannot be the sender!'
            ]);
            exit;
        }
        
        // Check if recipient exists
        if (count($recipient_details) < 1) {
            $fileGetContent->send_content([
                'status' => 'Failed',
                'message' => 'The recipient user seems does not exist!'
            ]);
            exit;
        }

        $recipient_wallet = $query->select('wallets', '*', ['userID' => $recipient_details[0]['ID']]);

        //check if user account balance is sufficient
        if($balance >= $total_charge){
            
            //check if the amount is greater than the minimum deposit
            if ($amount >= $min) {

                // Initiate transfer tracking ID
                $trackingID = create_tracking_ID();
        
                // Process transfer
                $balance -= $total_charge;
                $query->update('wallets', ['balance' => $balance], ['userID' => $userID]);
                $query->update('wallets', ['balance' => $recipient_wallet[0]['balance'] + $amount], ['userID' => $recipient_details[0]['ID']]);

                // Save to database
                $initiate1 = $query->insert('transactions', ['userID' => $userID, 'amount' => $total_charge, 'fees' => $fees, 'account' => $recipient, 'trackingID' => $trackingID, 'type' => 'Transfer', 'status' => 'Success', 'method'=> $method]);
                $initiate2 = $query->insert('transactions', ['userID' => $recipient_details[0]['ID'], 'amount' => $amount, 'fees' => 0, 'account' => $userID, 'trackingID' => $trackingID, 'type' => 'Transfer', 'status' => 'Success', 'method'=> $method]);

                $response = [
                    'status' => 'Success',
                    'message' => 'Transfer made successfully, fee charged Kes '.$fees
                ];
                
            }else{
                $response = [
                    'status' => 'Failed',
                    'message' => 'Minimum transfer is kes '.$min,
                ];
            }
        }else{
            $response = [
                'status' => 'Failed',
                'message' => 'Insuficient balance to perform this transaction!',
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