<?php
//get initiator
include 'initiate.php';
include 'send-email.php';
include '../transaction-sms.php';

//function for income algorithm
function refferal_algo($query, $userID, $amount){
    
    //get uplineID for level 1 from  user detail
    $users = $query->select('users', '*', ['ID' => $userID]);
    $uplineID = $users[0]['upline'];
    
    //get levels income from database and convert to income amount
    $controls = $query->select('controls');
    $control = $controls[0];
    $level1 = $control['level1'] / 100;
    $level2 = $control['level2'] / 100;
    $level3 = $control['level3'] / 100;
    $level1_income = $amount * $level1;
    $level2_income = $amount * $level2;
    $level3_income = $amount * $level3;
    
    //get level 1 upline and update its wallet income and balance
    $users = $query->select('users', '*', ['ID' => $uplineID]);
    $level1_upline = $users[0];
    $lv1ID = $level1_upline['ID'];
    $uplineID_2 = $level1_upline['upline'];
    $wallets = $query->select('wallets', '*', ['userID' => $lv1ID]);
    $wallet = $wallets[0];
    $balance = $wallet['balance'] + $level1_income;
    $invite_income = $wallet['invite_income'] + $level1_income;
    $update_1 = $query->update('wallets', ['balance' => $balance, 'invite_income' => $invite_income], ['userID' => $lv1ID]);
    $insert_transaction = $query->insert('transactions', [
                'userID'      => $lv1ID,
                'type'        => 'Commission',
                'amount'      => $level1_income,
                'description' => 'Commission from referral of user ' . $userID . '. Amount of ' . $level1_income . ' has been credited to your wallet.',
                'status'      => 'Completed'
            ]);

    //get level 2 upline and update its wallet income and balance
    if($uplineID_2 != 0){
        $users = $query->select('users', '*', ['ID' => $uplineID_2]);
        $level2_upline = $users[0];
        $lv2ID = $level2_upline['ID'];
        $wallets = $query->select('wallets', '*', ['userID' => $lv2ID]);
        $wallet = $wallets[0];
        $balance = $wallet['balance'] + $level2_income;
        $invite_income = $wallet['invite_income'] + $level2_income;
        $update_2 = $query->update('wallets', ['balance' => $balance, 'invite_income' => $invite_income], ['userID' => $lv2ID]);
        $insert_transaction = $query->insert('transactions', [
                'userID'      => $lv2ID,
                'type'        => 'Commission',
                'amount'      => $level2_income,
                'description' => 'Commission from referral of user ' . $userID . '. Amount of ' . $level2_income . ' has been credited to your wallet.',
                'status'      => 'Completed'
            ]);
    }
    //get level 3 upline and update its wallet income and balance
    $users = $query->select('users', '*', ['ID' => $uplineID_2]);
    $level2_upline = $users[0];
    $uplineID_3 = $level2_upline['upline'];
    if($uplineID_3 != 0){
        $users = $query->select('users', '*', ['ID' => $uplineID_3]);
        $level3_upline = $users[0];
        $lv3ID = $level3_upline['ID'];
        $wallets = $query->select('wallets', '*', ['userID' => $lv3ID]);
        $wallet = $wallets[0];
        $balance = $wallet['balance'] + $level3_income;
        $invite_income = $wallet['invite_income'] + $level3_income;
        $update_3 = $query->update('wallets', ['balance' => $balance, 'invite_income' => $invite_income], ['userID' => $lv3ID]);
        $insert_transaction = $query->insert('transactions', [
                'userID'      => $lv3ID,
                'type'        => 'Commission',
                'amount'      => $level3_income,
                'description' => 'Commission from referral of user ' . $userID . '. Amount of ' . $level3_income . ' has been credited to your wallet.',
                'status'      => 'Completed'
            ]);
    }
    
}

//get data posted remotely
$data = $fileGetContent->get_content();

 //process data insertion and automatic deposit
 if (isset($data)) {
    
    // $trackingID = $data['external_reference'];
    // $reference = $data['result']['MpesaReceiptNumber'];
    // $status = $data['status'];
    // $account = $data['result']['Phone'];
    
    $trackingID = $data['transaction']['external_reference'];
    $reference = $data['transaction']['mpesa_receipt'];
    $status = $data['transaction']['status'];
    $account = $data['transaction']['phone_number'];
    
    //get transaction details
    $transactions = $query->select('transactions', '*', ['trackingID' => $trackingID, 'status' => "Pending"]);
    $transaction = $transactions[0];

    
    if ($status == 'SUCCESS') {
        $status = "Success";
        
        if(count($transactions)){
            
            $userID = $transaction['userID'];
            $amount = $transaction['amount'];
            //get user wallet and update
            $wallet = $query->select('wallets', '*', ['userID' => $userID]);
            $balance = $wallet[0]['balance'] + $amount;
            $query->update('wallets', ['balance' => $balance], ['userID' => $userID]);
            
            // get user details
            $user_details = $query->select('users', '*', ['ID' => $userID]);
            
            //update transaction status
            $query->update('transactions', ['status' => $status, 'description' => $reference], ['trackingID' => $trackingID]);
            
            //refferal income
            refferal_algo($query, $userID, $amount);
            
            //Send confimation email
            $body = deposit_template($user_details[0]['name'], $amount, 'M-Pesa');
            $email_res = send_email($user_details[0]['email'], 'Deposit Recieved Successfully', $body);
            
            // // Send sms
            // $message = "Congratulations your deposit of amount KSH ".$amount." have been recieved successfully. Mpesa Ref ".$reference;
            // $sms_res = sendSMS($account, $query, $curl, $message);
        }
        
    }else{
        //update transaction status
        $query->update('transactions', ['status' => 'Failed'], ['trackingID' => $trackingID]);
    }
}