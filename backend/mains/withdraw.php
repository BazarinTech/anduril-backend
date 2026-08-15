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
        'Authorization: Basic ' . env('PALPLUSS_KEY')
    ];
    $data = [
        "amount" => (float) $amount,
        "phone" => $account,
        "reference" => $trackingID,
        "callbackUrl" => callback_url('palpluss_b2c_callback.php'),
        "description" => "BusinessPayment"
    ];
    $inititate = $api->request(env('PALPLUSS_B2C_URL'), 'POST', $data, $headers);

    // An unreachable provider makes Curl::request() return null, and reading
    // ['success'] off it warned and then fell through to the generic failure.
    // Treat "no answer" as an explicit failure so the caller refunds.
    if (!is_array($inititate)) {
        error_log('[withdraw] payout provider returned no parseable response');

        return [
            'status' => 'Failed',
            'message' => 'Payment provider is unreachable. Please try again shortly.',
        ];
    }

  if (!empty($inititate['success'])) {
        $res = [
            'status' => 'Success',
            'message' => 'Mpesa transaction initiated successfully',
        ];
    }elseif(isset($inititate['error'])){
        $res = [
            'status' => 'Failed',
            'message' => is_array($inititate['error']) ? ($inititate['error']['message'] ?? 'Payout rejected') : (string) $inititate['error'],
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

        // Phase 3.6 -- validate before anything touches a balance.
        if (!is_valid_amount($amount)) {
            $fileGetContent->send_content([
                'status' => 'Failed',
                'message' => 'Please enter a valid amount.',
            ]);
            exit;
        }
        $amount = money($amount);

        //get transaction controls from database
        $controls = $query->select('controls');
        $control = $controls[0];
        $min = $control['minWith'];

        //get user wallet details
        $user_wallet = $query->select('wallets', '*', ['userID' => $userID]);
        $user_wallet = $user_wallet[0] ?? null;

        if ($user_wallet === null) {
            $fileGetContent->send_content([
                'status' => 'Error',
                'message' => 'Wallet not found.'
            ]);
            exit;
        }

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
        
        // check withdrawal pin (Phase 2.3 -- stored as a hash now)
        if($withdrawal_pin !== '' && password_verify((string) $pin, $withdrawal_pin)){

            //check if the amount is greater than the minimum withdrawal
            if ($amount < money($min)) {
                $fileGetContent->send_content([
                    'status' => 'Failed',
                    'message' => 'Minimum withdrawal is kes '.$min,
                ]);
                exit;
            }

            $trackingID = create_tracking_ID();
            $fees = $amount * money($control['withFee']) / 100;
            $send_amount = $amount - $fees;

            /**
             * Phase 3.3 / 3.4 -- reserve, attempt, compensate.
             *
             * Step 1 (this transaction): lock the wallet, re-check the balance
             * under the lock, debit it, and record the withdrawal as Pending.
             * Committing the debit *before* calling the payment provider is
             * deliberate -- it is what stops two simultaneous withdrawals from
             * both passing the balance check and spending the same funds.
             *
             * Step 2 (after commit): call the provider. Network calls must not
             * happen inside a transaction; a slow provider would hold the row
             * lock for its entire timeout.
             *
             * Step 3: on failure, refund in a second transaction. Previously
             * the balance was debited and, if the provider call failed, simply
             * stayed debited -- the user lost the money and no payout existed.
             */
            $pdo->beginTransaction();

            try {
                $lockedWallet = wallet_for_update($pdo, $userID);
                $balance = money($lockedWallet['balance'] ?? 0);

                if ($lockedWallet === null || $balance < $amount) {
                    $pdo->rollBack();

                    $fileGetContent->send_content([
                        'status' => 'Failed',
                        'message' => 'Insuficient balance to perform this transaction!',
                    ]);
                    exit;
                }

                $query->update('wallets', ['balance' => money_str($balance - $amount)], ['userID' => $userID]);
                $query->insert('transactions', ['userID' => $userID, 'amount' => money_str($amount), 'fees' => money_str($fees), 'account' => $account, 'trackingID' => $trackingID, 'type' => 'Withdraw', 'status' => 'Pending', 'method' => $method ]);

                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('[withdraw] reserve failed: ' . $e->getMessage());

                $fileGetContent->send_content([
                    'status' => 'Failed',
                    'message' => 'Could not start the withdrawal. Please try again.',
                ]);
                exit;
            }

            // Funds are now reserved. Attempt the payout.
            $initiate = mpesa_auto($query, $userID, $send_amount, $account, $curl, $trackingID);

            if (isset($initiate['status']) && $initiate['status'] === 'Success') {
                // Provider accepted it. The b2c callback settles or refunds.
                $query->update('transactions', ['status' => 'Processing'], ['trackingID' => $trackingID]);

                $body = pending_withdrawal_template($user_details[0]['name'], money_str($amount), money_str($fees));
                $email_res = send_email($user_details[0]['email'], 'Withdrawal Submitted Succesfully', $body);

                $response = [
                    'status' => 'Success',
                    'message' => 'Withdrawal made succesfully, fee charge Kes '.money_str($fees).' amount to recieve kes '.money_str($send_amount).'. Expect to recieve your funds within 3hours. If not, contact our support team.'
                ];
            } else {
                /**
                 * Provider refused it. Give the money back.
                 *
                 * The old code reported Success here regardless -- and then a
                 * trailing `if ($initiate)` overwrote the response with Success
                 * a second time, because $initiate is always a non-empty array.
                 * The failure branch was unreachable.
                 */
                $pdo->beginTransaction();

                try {
                    $refundWallet = wallet_for_update($pdo, $userID);
                    $query->update('wallets', ['balance' => money_str(money($refundWallet['balance']) + $amount)], ['userID' => $userID]);
                    $query->update('transactions', ['status' => 'Failed', 'description' => 'Payout rejected; amount refunded'], ['trackingID' => $trackingID]);
                    $pdo->commit();

                    error_log("[withdraw] payout refused for {$trackingID}; refunded {$amount}");
                } catch (\Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    // The reservation stands but the refund failed. Say so
                    // loudly -- this is the one state that needs a human.
                    error_log("[withdraw] REFUND FAILED for {$trackingID} user {$userID} amount {$amount}: " . $e->getMessage());
                }

                $response = [
                    'status' => 'Failed',
                    'message' => $initiate['message'] ?? 'Withdrawal could not be processed. Your balance has not been charged.',
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