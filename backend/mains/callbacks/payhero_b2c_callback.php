<?php
//get initiator
include 'initiate.php';
include 'send-email.php';
include '../transaction-sms.php';

//get data posted remotely
$data = $fileGetContent->get_content();

 //process data insertion and automatic deposit
 if (isset($data)) {
    
    $trackingID = $data['transaction']['external_reference'];
    $reference = $data['transaction']['mpesa_receipt'] ?? '';
    $status = $data['transaction']['status'];
    $account = $data['transaction']['phone_number'];
    
    // $trackingID = $data['response']['ExternalReference'];
    // $reference = $data['response']['MpesaReceiptNumber'];
    // $status = $data['response']['Status'];
    // $account = $data['response']['Phone'];
    
    //get transaction details
    $transactions = $query->select('transactions', '*', ['trackingID' => $trackingID]);
    $transaction = $transactions[0];
    $userID = $transaction['userID'];
    $amount = $transaction['amount'];
    
    if ($status == 'SUCCESS') {
        //update transaction status
        $query->update('transactions', ['status' => 'Success', 'description' => $reference], ['trackingID' => $trackingID]);
        
    }else{
         //update transaction status
        $query->update('transactions', ['status' => 'Failed'], ['trackingID' => $trackingID]);
        
        //Get user details and update
        $wallet = $query->select('wallets', '*', ['userID' => $userID]);
        $balance = $wallet[0]['balance'] + $amount;
        $query->update('wallets', ['balance' => $balance], ['userID' => $userID]);
    }
}