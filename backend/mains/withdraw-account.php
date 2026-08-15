<?php
include '../includes/initiate.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

// Get posted json data
$data = $fileGetContent->get_content();

// Process account data
if (isset($data['userID']) && isset($data['type'])) {
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
        $type = $data['type'];

        /**
         * Phase 2.3. The PIN is what stands between a stolen token and a
         * payout, and all three branches here used to undermine it:
         *
         *   create      overwrote the account and PIN unconditionally, so an
         *               attacker could simply re-run it and own the payout
         *               destination without knowing the existing PIN.
         *   update      checked the PIN but compared it in plaintext.
         *   update_pin  set a brand new PIN without asking for the old one,
         *               which made the check in `update` pointless.
         *
         * The PIN is now a hash, every branch that changes payout details
         * proves knowledge of the current PIN, and `create` only applies to an
         * account that has not been set up yet.
         */
        $wallets = $query->select('wallets', '*', ['userID' => $userID]);
        $wallet  = $wallets[0] ?? null;

        if ($wallet === null) {
            $fileGetContent->send_content([
                'status' => 'Failed',
                'message' => 'No wallet found for this account.'
            ]);
            exit;
        }

        $currentPin = $wallet['withdrawal_pin'] ?? '';
        $hasPin     = $currentPin !== '';

        if ($type == 'create') {
            $phone = $data['phone'];
            $account_name = $data['accountName'];
            $pin = (string) $data['pin'];

            if ($hasPin) {
                // Already configured -- this is an update, and updates must
                // present the current PIN.
                $response = [
                    'status' => 'Failed',
                    'message' => 'A withdrawal account already exists. Use update and supply your current PIN.'
                ];
            } elseif (strlen($pin) < 4) {
                $response = [
                    'status' => 'Failed',
                    'message' => 'Withdrawal PIN must be at least 4 digits.'
                ];
            } else {
                $query->update('wallets', [
                    'withdrawal_account' => $phone,
                    'withdrawal_name' => $account_name,
                    'withdrawal_pin' => password_hash($pin, PASSWORD_DEFAULT)
                ], ['userID' => $userID]);

                $response = [
                    'status' => 'Success',
                    'message' => 'Withdrawal account created successfully!'
                ];
            }
        }elseif($type == 'update'){
            $phone = $data['phone'];
            $account_name = $data['accountName'];
            $pin = (string) $data['pin'];

            if ($hasPin && password_verify($pin, $currentPin)) {
                $update_withdraw = $query->update('wallets', [
                'withdrawal_account' => $phone,
                'withdrawal_name' => $account_name,
                ],[
                    'userID' => $userID
                ]);

                $response = [
                    'status' => 'Success',
                    'message' => 'Withdrawal account updated successfully!'
                ];
            }else{
                $response = [
                    'status' => 'Failed',
                    'message' => 'Incorrect withdrawal pin!'
                ];
            }

        }elseif($type == 'update_pin'){
            $pin    = (string) $data['pin'];
            $oldPin = (string) ($data['oldPin'] ?? '');

            if (!$hasPin) {
                $response = [
                    'status' => 'Failed',
                    'message' => 'No withdrawal PIN is set yet. Create your withdrawal account first.'
                ];
            } elseif (!password_verify($oldPin, $currentPin)) {
                $response = [
                    'status' => 'Failed',
                    'message' => 'Incorrect current withdrawal pin!'
                ];
            } elseif (strlen($pin) < 4) {
                $response = [
                    'status' => 'Failed',
                    'message' => 'Withdrawal PIN must be at least 4 digits.'
                ];
            } else {
                $update_withdraw = $query->update('wallets', [
                    'withdrawal_pin' => password_hash($pin, PASSWORD_DEFAULT)
                ],[
                    'userID' => $userID
                ]);

                $response = [
                    'status' => 'Success',
                    'message' => 'Withdrawal PIN updated successfully!'
                ];
            }
        }else{
            $response = [
                'status' => 'Failed',
                'message' => 'Unknown request type'
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
    $response = [
            'status' => 'Failed',
            'message' => 'Some fields are empty'
        ];
    $fileGetContent->send_content($response);
}