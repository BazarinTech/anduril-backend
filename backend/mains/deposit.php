<?php
//get initiator
include '../includes/initiate.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

//get data posted remotely
$data = $fileGetContent->get_content();

/**
 * Our own reference for this attempt.
 *
 * Two jobs now. It is sent as `accountReference` so the deposit is
 * identifiable in the provider's dashboard, and it is sent as the
 * `Idempotency-Key`, which is what stops a retried or double-submitted
 * request from charging the customer twice.
 *
 * That second job is why this is no longer 'INV-' . date('His') . rand(100,999):
 * two requests in the same second had a 1-in-900 chance of colliding, and a
 * colliding idempotency key makes the provider return the *first* transaction
 * instead of starting a new one -- so the second customer's deposit would be
 * silently attributed to the first.
 */
function create_tracking_ID() {
    return 'INV-' . date('YmdHis') . '-' . bin2hex(random_bytes(6));
};

/**
 * Start an M-Pesa STK push against Palpluss.
 *
 * Request and response follow the wallet top-up API:
 *
 *   POST {base}/v1/wallets/b2c/topups
 *   Authorization: Basic <key>
 *   Idempotency-Key: <our reference>
 *   { amount, phone, accountReference, transactionDesc, callbackUrl }
 *
 * The response carries a `transactionId` UUID, and that UUID is what we now
 * store as the transaction's reference -- the provider quotes it in the
 * callback and in support conversations, so matching on it is more reliable
 * than matching on a string we invented. Our own reference is kept alongside
 * it in `local_ref` so a deposit can still be traced from either side.
 */
function mpesa_auto($query, $userID, $amount, $account, $api, $trackingID){
    $headers = [
        'Authorization: Basic ' . env('PALPLUSS_KEY'),
        'Idempotency-Key: ' . $trackingID,
    ];

    $data = [
        "amount" => (float) $amount,
        "phone" => $account,
        "accountReference" => $trackingID,
        "transactionDesc" => "Deposit",
        "callbackUrl" => callback_url('palpluss_deposit_callback.php'),
    ];

    $inititate = $api->request(env('PALPLUSS_TOPUP_URL'), 'POST', $data, $headers);

    // An unreachable provider makes Curl::request() return null.
    if (!is_array($inititate)) {
        error_log('[deposit] payment provider returned no parseable response');

        return [
            'status' => 'Failed',
            'message' => 'Payment provider is unreachable. Please try again shortly.',
        ];
    }

    /**
     * Success is a transactionId plus resultCode "0". This endpoint reports
     * neither the `success` boolean the previous one did, nor an HTTP-level
     * error -- an accepted push comes back as status PENDING, and the real
     * outcome arrives later on the callback.
     */
    $transactionId = $inititate['transactionId'] ?? null;
    $resultCode    = (string) ($inititate['resultCode'] ?? '');
    $status        = strtoupper((string) ($inititate['status'] ?? ''));

    $accepted = $transactionId
        && ($resultCode === '0' || in_array($status, ['PENDING', 'SUCCESS', 'PROCESSING'], true));

    if ($accepted) {
        $query->insert('transactions', [
            'userID'     => $userID,
            'amount'     => money_str(money($amount)),
            'account'    => $account,
            'trackingID' => $transactionId,
            'local_ref'  => $trackingID,
            'type'       => 'Deposit',
        ]);

        error_log("[deposit] STK push accepted for user {$userID}: {$transactionId} (ref {$trackingID})");

        $res = [
            'status' => 'Success',
            'message' => 'Mpesa transaction initiated successfully. Kindly confirm with mpesa pin to complete',
            'trackingID' => $transactionId
        ];
    }else{
        // Error shape is not documented for this endpoint, so read the
        // plausible spellings rather than assuming one and warning on the rest.
        $reason = $inititate['resultDescription']
            ?? $inititate['message']
            ?? (is_array($inititate['error'] ?? null) ? ($inititate['error']['message'] ?? null) : ($inititate['error'] ?? null))
            ?? 'Transaction could not be initiated. Please try again.';

        error_log('[deposit] STK push refused: ' . json_encode($inititate));

        $res = [
            'status' => 'Failed',
            'message' => $reason,
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
        
        // Phase 3.6 -- validate before anything else looks at the amount.
        if (!is_valid_amount($amount)) {
            $fileGetContent->send_content([
                'status' => 'Failed',
                'message' => 'Please enter a valid amount.',
            ]);
            exit;
        }
        $amount = money($amount);

        /**
         * Phase 4.5 -- a first-time depositor has no previous transaction, and
         * reading [0] off the empty result produced the "Undefined array key 0"
         * warnings in backend/mains/error_log on every debut deposit.
         *
         * The throttle itself is left commented out as it was found; with the
         * timezone fix in place it would now actually work, so enabling it is a
         * product decision rather than a repair.
         */
        $user_transactions = $query->select('transactions', '*', ['userID' => $userID, 'type' => 'Deposit'], ['direction' => 'desc', 'column' => 'ID']);
        $last_deposit = $user_transactions[0] ?? null;

        // Seconds since the previous deposit attempt; null when this is the first.
        $time_diff = $last_deposit === null ? null : time() - strtotime($last_deposit['time']);
        // if($time_diff !== null && $time_diff < 10){
        //     $response = [
        //         'status' => 'Failed',
        //         'message' => 'Next transaction should be initated after 10 seconds',
        //     ];
        //     $fileGetContent->send_content($response);
        //     exit;
        // }

        // check if the amount is greater than the minimum deposit
        if ($amount >= money($min)) {
            
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





