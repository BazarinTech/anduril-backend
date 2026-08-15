<?php
include '../includes/initiate.php';
include 'code-helpers.php';

// Get posted json data
$data = $fileGetContent->get_content();

/**
 * Phase 2.5 -- password reset.
 *
 * Previously this accepted any `verification_codes` row for the phone number
 * whose status was 'Verified', with no age check and no consumption. Because
 * verification never consumed codes either, one old SMS was a permanent
 * password-reset key for that account.
 *
 * A reset now requires a code that is Verified, unexpired, and issued for this
 * purpose -- and the code is burned on use.
 */

if (isset($data['code']) && isset($data['phone']) && isset($data['newPassword'])) {

    $code = $data['code'];
    $phone = $data['phone'];
    $newPassword = $data['newPassword'];

    if (strlen($newPassword) < 6) {
        $fileGetContent->send_content([
            'status' => 'Failed',
            'message' => 'Password must be at least 6 characters long.'
        ]);
        exit;
    }

    // Verify the code
    $existingCode = $query->select('verification_codes', '*', ['code' => $code, 'phone' => $phone, 'status' => 'Verified']);

    if (!empty($existingCode) && !code_expired($existingCode[0])) {
        // Verify if user exits
        $existingUser = $query->select('users', '*', ['phone' => $phone]);

        if (!empty($existingUser)) {
            $changePassword = $query->update('users', ['passwrd' => password_hash($newPassword, PASSWORD_DEFAULT)], ['phone' => $phone]);

            // Burn the code. 'Used' is terminal, so this reset cannot be
            // replayed with the same SMS.
            $query->update('verification_codes', ['status' => 'Used'], ['ID' => $existingCode[0]['ID']]);

            $response = [
                'status' => 'Success',
                'message' => 'Password reset successfully.'
            ];
        } else {
            $response = [
                'status' => 'Failed',
                'message' => 'User with that phone number does not seem exist.'
            ];
        }
    } elseif (!empty($existingCode)) {
        $query->update('verification_codes', ['status' => 'Expired'], ['ID' => $existingCode[0]['ID']]);

        $response = [
            'status' => 'Failed',
            'message' => 'That code has expired. Please request a new one.'
        ];
    } else {
        $response = [
            'status' => 'Failed',
            'message' => 'Invalid code or phone number.'
        ];
    }
    $fileGetContent->send_content($response);
}else{
    $response = [
            'status' => 'Failed',
            'message' => 'Somefields are missing!'
        ];
    $fileGetContent->send_content($response);
}
