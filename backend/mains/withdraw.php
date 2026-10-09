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

 
// A commented-out PayHero and SwiftWallet integration used to sit here. It was dead
// code carrying live bearer tokens and endpoint URLs in the clear, which
// is a credential in the repository whether or not anything executes it.
//
// The payout itself lives in lib/payouts.php, which sends on whichever rail
// the admin has selected on Platform Control -- Palpluss or Safaricom Daraja.
// This file used to carry its own copy of the Palpluss call, as did
// admin/approve-withdrawals.php, and the two had already drifted apart.
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
        $amount = request_str($data, 'amount');
        // PINs are compared exactly, so they are not trimmed.
        $pin = request_str($data, 'pin', '', false);

        /**
         * The method recorded is the rail the payout actually uses.
         *
         * This used to store whatever the client sent -- or NULL when it sent
         * nothing, which failed the NOT NULL column. But every withdrawal is
         * paid out over M-Pesa by payout_send(), whatever was asked for, so
         * a client-supplied value could only ever make the ledger say
         * something untrue. It is no longer read from the request.
         */
        $method = 'mpesa';

        /**
          * One line per withdrawal attempt, before anything can refuse it.
          *
          * Every branch below ends the request with a message the customer
          * sees and nothing in the log, so "withdrawals are failing" could not
          * be traced to which check was rejecting them. Each refusal now says
          * so, keyed by user, and the payout itself logs the provider's own
          * answer (lib/payouts.php).
          */
        error_log("[withdraw] request from user {$userID}: amount=" . var_export($amount, true));

        // Phase 3.6 -- validate before anything touches a balance.
        if (!is_valid_amount($amount)) {
            error_log("[withdraw] user {$userID} refused: amount is not a valid number");

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
            error_log("[withdraw] user {$userID} refused: no wallet row");

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
            error_log("[withdraw] user {$userID} refused: withdrawal account not set");

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

            /**
             * The minimum, enforced fail-closed.
             *
             * This used to be `$amount < money($min)`, and money() answers 0.0
             * for anything it cannot parse. A blank or malformed `minWith`
             * therefore became "minimum is 0" and let every amount through,
             * with nothing said anywhere -- the rule looked enforced and was
             * not. An unreadable limit now stops the withdrawal instead.
             */
            $minWithdrawal = money_limit($min);

            if ($minWithdrawal === null) {
                error_log('[withdraw] refusing: controls.minWith is not a usable number (' . var_export($min, true) . ')');

                $fileGetContent->send_content([
                    'status' => 'Failed',
                    'message' => 'Withdrawals are temporarily unavailable. Please try again shortly.',
                ]);
                exit;
            }

            if ($amount < $minWithdrawal) {
                error_log("[withdraw] user {$userID} refused: {$amount} is below the minimum {$minWithdrawal}");

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

                    error_log("[withdraw] user {$userID} refused: balance {$balance} is less than {$amount}");

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

            error_log("[withdraw] {$trackingID} reserved {$amount} from user {$userID} "
                . "(fee {$fees}, sending {$send_amount} to " . payout_mask_phone($account) . ')');

            // Funds are now reserved. Attempt the payout.
            $initiate = payout_send($query, $curl, $account, $send_amount, $trackingID);

            error_log("[withdraw] {$trackingID} payout via " . ($initiate['provider'] ?? '?')
                . ': ' . ($initiate['status'] ?? '?') . ' - ' . ($initiate['message'] ?? ''));

            if (isset($initiate['status']) && $initiate['status'] === 'Success') {
                // Provider accepted it. The b2c callback settles it, or
                // queues it for an admin if the payout itself fails.
                $query->update('transactions', ['status' => 'Processing'], ['trackingID' => $trackingID]);

                error_log("[withdraw] {$trackingID} accepted by " . ($initiate['provider'] ?? '?') . '; now Processing');

                $body = pending_withdrawal_template($user_details[0]['name'], money_str($amount), money_str($fees));
                $email_res = send_email($user_details[0]['email'], 'Withdrawal Submitted Succesfully', $body);

                $response = [
                    'status' => 'Success',
                    'message' => 'Withdrawal made succesfully, fee charge Kes '.money_str($fees).' amount to recieve kes '.money_str($send_amount).'. Expect to recieve your funds within 3hours. If not, contact our support team.'
                ];
            } else {
                /**
                 * The provider refused it -- most often an empty B2C float on
                 * our side, which has nothing to do with this customer.
                 *
                 * The money STAYS reserved and the withdrawal goes to
                 * 'Pending', which is the queue on
                 * admin/approve-withdrawals.php. An admin tops up the float
                 * and approves to retry, or rejects to return the funds. The
                 * provider's own reason is stored on the row so the admin can
                 * see why it did not go out.
                 *
                 * The customer is told the same thing as a successful
                 * request. From their side nothing has gone wrong: the money
                 * has left their balance and is on its way, which is exactly
                 * what the normal three-hour wording already says.
                 */
                $reason = (string) ($initiate['message'] ?? 'Payout provider refused the request');

                $query->update(
                    'transactions',
                    [
                        'status'      => 'Pending',
                        // Kept short: the column is VARCHAR(255) and strict
                        // mode rejects anything longer.
                        'description' => substr('Awaiting admin approval - ' . $reason, 0, 255),
                    ],
                    ['trackingID' => $trackingID]
                );

                error_log("[withdraw] payout refused for {$trackingID} (user {$userID}, "
                    . "amount {$send_amount}, provider " . ($initiate['provider'] ?? '?') . "): {$reason}"
                    . ' -- funds stay reserved, queued for admin approval');

                $body = pending_withdrawal_template($user_details[0]['name'], money_str($amount), money_str($fees));
                $email_res = send_email($user_details[0]['email'], 'Withdrawal Submitted Succesfully', $body);

                $response = [
                    'status' => 'Success',
                    'message' => 'Withdrawal made succesfully, fee charge Kes '.money_str($fees).' amount to recieve kes '.money_str($send_amount).'. Expect to recieve your funds within 3hours. If not, contact our support team.'
                ];
            }

        }else{
            error_log("[withdraw] user {$userID} refused: incorrect withdrawal pin");

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