<?php
//get initiator
include 'initiate.php';
include 'verify.php';
include 'send-email.php';
include '../transaction-sms.php';

// Reject anything that cannot prove it came from the payment provider. This
// callback refunds a wallet on failure, so an unauthenticated caller could
// mint balance by replaying a "failed payout" for any Processing withdrawal.
verify_callback_request('palpluss_b2c');

//get data posted remotely
$data = $fileGetContent->get_content();

 //process b2c transaction status
 if (isset($data)) {

    $trackingID = $data['transaction']['external_reference'] ?? '';
    $reference = $data['transaction']['mpesa_receipt'] ?? '';
    $status = $data['transaction']['status'] ?? '';
    $account = $data['transaction']['phone_number'] ?? '';

    if ($trackingID === '') {
        error_log('[palpluss_b2c] rejected: callback carried no external_reference');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing reference']);
        exit;
    }

    //get transaction details
    $transactions = $query->select('transactions', '*', ['trackingID' => $trackingID, 'status' => 'Processing']);

    // Only a payout still in Processing can be settled or refunded. Anything
    // else is a replay, and refunding twice would credit the user for money
    // they already received.
    if (empty($transactions)) {
        error_log("[palpluss_b2c] no processing transaction for {$trackingID}; ignoring");
        echo json_encode(['status' => 'ok', 'message' => 'No processing transaction']);
        exit;
    }

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