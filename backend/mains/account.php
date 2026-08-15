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

        if ($type == 'account') {
            /**
             * Phase 4.8. Two problems here.
             *
             * The endpoint accepted an `email` and silently ignored it -- only
             * the phone was ever written, so a user editing their email saw
             * "updated successfully" and nothing changed.
             *
             * And QueryBuilder::update() returns the affected row count, so
             * saving the same phone number again returns 0 and reported
             * "Account update failed!" for a request that worked perfectly.
             * Zero rows changed is success, not failure.
             */
            $email = trim((string) ($data['email'] ?? ''));
            $phone = trim((string) ($data['phone'] ?? ''));

            $changes = [];

            if ($phone !== '') {
                $changes['phone'] = $phone;
            }

            if ($email !== '') {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $fileGetContent->send_content([
                        'status' => 'Failed',
                        'message' => 'That email address does not look valid.'
                    ]);
                    exit;
                }

                // Someone else must not already hold it.
                $taken = $query->select('users', '*', ['email' => $email]);

                if (!empty($taken) && (string) $taken[0]['ID'] !== (string) $userID) {
                    $fileGetContent->send_content([
                        'status' => 'Failed',
                        'message' => 'That email address is already in use.'
                    ]);
                    exit;
                }

                $changes['email'] = $email;
            }

            if (empty($changes)) {
                $response = [
                    'status' => 'Failed',
                    'message' => 'Nothing to update.'
                ];
            } else {
                $query->update('users', $changes, ['ID' => $userID]);

                $response = [
                    'status' => 'Success',
                    'message' => 'Account updated succefully!'
                ];
            }
        }

        if ($type == 'password') {
            $old_password = $data['oldPassword'];
            $new_password = $data['newPassword'];
            $con_password = $data['confirmPassword'];
            $user = $query->select('users', '*', ['ID' => $userID]);
            $password = $user[0]['passwrd'] ?? '';
            if (password_verify($old_password, $password)) {
                if ($new_password == $con_password) {
                    if (strlen($new_password) >= 6) {
                        $update_password = $query->update('users', ['passwrd' => password_hash($new_password, PASSWORD_DEFAULT)], ['ID' => $userID]);
                        $response = [
                            'status' => 'Success',
                            'message' => 'Password updated succesfully!'
                        ];
                    }else{
                        $response = [
                            'status' => 'Failed',
                            'message' => 'Password must be at least 6 characters long!'
                        ];
                    }
                }else{
                    $response = [
                        'status' => 'Failed',
                        'message' => 'New and confirm password do not match!'
                    ];
                }
            }else{
                $response = [
                    'status' => 'Failed',
                    'message' => 'Wrong old password!'
                ];
            }
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
            'message' => 'No token provided'
        ];
    $fileGetContent->send_content($response);
}
