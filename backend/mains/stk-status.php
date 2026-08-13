<?php
//get initiator
include '../includes/initiate.php';

//get data posted remotely
$data = $fileGetContent->get_content();

// Process get status
if (isset($data['trackingID'])) {
    $trackingID = $data['trackingID'];
    $transaction = $query->select('transactions', '*', ['trackingID' => $trackingID]);
    if(count($transaction)){
        $status = $transaction[0]['status'];
        if($status == 'Success'){
            $response = [
                'status' => 'Success',
                'message' => 'Deposit was completed successfully'
                ];
        }else{
            $response = [
                'status' => 'Failed',
                'message' => 'Deposit was not completed successfully'
                ];
        }
    }else{
        $response = [
                'status' => 'Failed',
                'message' => 'Transaction does not exists'
                ];
    }
    $fileGetContent->send_content($response);
}else{
    $fileGetContent->send_content([
        'status' => 'Error',
        'message' => 'Some fields are empty'
    ]);
}