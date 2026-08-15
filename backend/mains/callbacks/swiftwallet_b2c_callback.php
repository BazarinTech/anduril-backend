<?php
//get initiator
include 'initiate.php';
include 'verify.php';

// Not wired to any live payment flow, but still publicly reachable and still
// talking to the same wallets table. Guarded on the same shared secret as the
// Palpluss callbacks, which in practice means it now rejects everything until
// a payout is actually routed through this provider.
verify_callback_request('swiftwallet_b2c');
include 'send-email.php';
include '../transaction-sms.php';

//get data posted remotely
$data = $fileGetContent->get_content();

 //process data insertion and automatic deposit
 if (isset($data)) {
    
    $trackingID = $data['external_reference'];
    $reference = $data['result']['MpesaReceiptNumber'] ?? '';
    $status = $data['status'];
    $account = $data['transaction_info']['phone_number'];
    
    // $trackingID = $data['response']['ExternalReference'];
    // $reference = $data['response']['MpesaReceiptNumber'];
    // $status = $data['response']['Status'];
    // $account = $data['response']['Phone'];
    
    //get transaction details
    $transactions = $query->select('transactions', '*', ['trackingID' => $trackingID, 'status' => 'Processing']);
    $transaction = $transactions[0];
    $userID = $transaction['userID'];
    $amount = $transaction['amount'];
    
    if ($status == 'completed') {
        
        //update transaction status
        $query->update('transactions', ['status' => 'Success', 'description' => $reference], ['trackingID' => $trackingID]);
        
    }elseif($status == 'failed' || $status == 'cancelled'){
         //update transaction status
        $query->update('transactions', ['status' => 'Failed'], ['trackingID' => $trackingID]);
        
        //Get user details and update
        $wallet = $query->select('wallets', '*', ['userID' => $userID]);
        $balance = $wallet[0]['balance'] + $amount;
        $query->update('wallets', ['balance' => $balance], ['userID' => $userID]);
    }
}