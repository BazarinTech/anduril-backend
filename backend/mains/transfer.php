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
        $decoded = JWT::decode(request_token($data), new Key(JWT_SECRET, JWT_ALGO));
        $userID = $decoded->userID ?? $decoded->sub ?? null;

        if (!$userID) {
            $fileGetContent->send_content([
                'status' => 'Error',
                'message' => 'Invalid token'
            ]);
            exit;
        }
        /**
         * Missing fields are a malformed request, not a crash.
         *
         * These were read directly, so a request without `method` raised an
         * "Undefined array key" warning and then passed NULL into a NOT NULL
         * column -- the transfer died at the INSERT, after the balances had
         * already been adjusted inside the transaction. The rollback caught
         * it and no money moved, but the caller got "Could not complete the
         * transfer" for what is really "you left a field out".
         */
        $amount    = request_str($data, 'amount');
        $recipient = request_str($data, 'recipient');

        /**
         * `method` records how the money moved. A transfer is wallet to
         * wallet, so that is the only accurate value and the request does not
         * need to supply it -- the column is VARCHAR(10), and 'wallet' fits.
         * A caller may still name something else; anything longer is trimmed
         * rather than allowed to fail the insert.
         */
        $method = substr(request_str($data, 'method'), 0, 10);

        if ($method === '') {
            $method = 'wallet';
        }

        if ($recipient === '') {
            $fileGetContent->send_content([
                'status'  => 'Failed',
                'message' => 'Please enter the recipient username or email.',
            ]);
            exit;
        }

        // Phase 3.6 -- a negative amount here used to move money the wrong way:
        // the sender's balance went up and the recipient's down.
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
        $min = $control['minTransfer'];

        // Declare fees and total amount to be charges
        $fees = $amount * money($control['tranFee']) / 100;
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

        $recipientID = $recipient_details[0]['ID'];

        //check if the amount is greater than the minimum transfer
        /**
         * Same fail-closed reading as withdraw.php: an unparseable
         * `minTransfer` must not silently mean "no minimum".
         */
        $minTransfer = money_limit($min);

        if ($minTransfer === null) {
            error_log('[transfer] refusing: controls.minTransfer is not a usable number (' . var_export($min, true) . ')');

            $fileGetContent->send_content([
                'status' => 'Failed',
                'message' => 'Transfers are temporarily unavailable. Please try again shortly.',
            ]);
            exit;
        }

        if ($amount < $minTransfer) {
            $fileGetContent->send_content([
                'status' => 'Failed',
                'message' => 'Minimum transfer is kes '.$min,
            ]);
            exit;
        }

        /**
         * Phase 3.2 -- both wallets move, so both are locked, and always in
         * ascending userID order. Locking "sender then recipient" would
         * deadlock against a simultaneous transfer running the other way;
         * a fixed order makes the second request queue instead.
         *
         * The balance check now happens after the lock. Before, sender and
         * recipient balances were both read before any write, so two
         * concurrent transfers out of one account could each see enough funds,
         * and two transfers *into* one account would overwrite each other --
         * losing one of the credits outright.
         */
        $pdo->beginTransaction();

        try {
            [$senderWallet, $recipientWallet] = wallets_for_update($pdo, $userID, $recipientID);

            if ($senderWallet === null || $recipientWallet === null) {
                $pdo->rollBack();

                $fileGetContent->send_content([
                    'status' => 'Failed',
                    'message' => 'Wallet not found for one of the accounts.',
                ]);
                exit;
            }

            $balance = money($senderWallet['balance']);

            //check if user account balance is sufficient
            if($balance >= $total_charge){

                // Initiate transfer tracking ID
                $trackingID = create_tracking_ID();

                // Process transfer
                $balance -= $total_charge;
                $query->update('wallets', ['balance' => money_str($balance)], ['userID' => $userID]);
                $query->update('wallets', ['balance' => money_str(money($recipientWallet['balance']) + $amount)], ['userID' => $recipientID]);

                // Save to database
                $initiate1 = $query->insert('transactions', ['userID' => $userID, 'amount' => money_str($total_charge), 'fees' => money_str($fees), 'account' => $recipient, 'trackingID' => $trackingID, 'type' => 'Transfer', 'status' => 'Success', 'method'=> $method]);
                $initiate2 = $query->insert('transactions', ['userID' => $recipientID, 'amount' => money_str($amount), 'fees' => 0, 'account' => $userID, 'trackingID' => $trackingID, 'type' => 'Transfer', 'status' => 'Success', 'method'=> $method]);

                $pdo->commit();

                $response = [
                    'status' => 'Success',
                    'message' => 'Transfer made successfully, fee charged Kes '.money_str($fees)
                ];

            }else{
                $pdo->rollBack();

                $response = [
                    'status' => 'Failed',
                    'message' => 'Insuficient balance to perform this transaction!',
                ];
            }
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('[transfer] ' . $e->getMessage());

            $response = [
                'status' => 'Failed',
                'message' => 'Could not complete the transfer. Please try again.',
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