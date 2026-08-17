<?php
/**
 * FIRST: record that this endpoint was reached at all.
 *
 * Runs before every include and every check, so a request that is
 * later rejected -- or that dies inside bootstrap -- still leaves a
 * line saying it arrived. Without it, 'the provider never called' and
 * 'the provider called and we threw it away' are indistinguishable.
 */
require_once __DIR__ . '/trace.php';
callback_trace('payhero_b2c_callback');

//get initiator
include 'initiate.php';
include 'verify.php';

// Not wired to any live payment flow, but still publicly reachable and still
// talking to the same wallets table. Guarded on the same shared secret as the
// Palpluss callbacks, which in practice means it now rejects everything until
// a payout is actually routed through this provider.
verify_callback_request('payhero_b2c');
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