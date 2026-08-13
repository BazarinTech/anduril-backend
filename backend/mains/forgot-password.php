<?php
include '../includes/initiate.php';

// Get posted json data
$data = $fileGetContent->get_content();

// Process forgot password
if (isset($data['code']) && isset($data['phone']) && isset($data['newPassword'])) {
    
    $code = $data['code'];
    $phone = $data['phone'];
    $newPassword = $data['newPassword'];

    // Verify the code
    $existingCode = $query->select('verification_codes', '*', ['code' => $code, 'phone' => $phone, 'status' => 'Verified']);

    if (!empty($existingCode)) {
        // Verify if user exits
        $existingUser = $query->select('users', '*', ['phone' => $phone]);
    
        if (!empty($existingUser)) {
            // Now allow user to reset password (this part can be expanded as needed)
            $changePassword = $query->update('users', ['passwrd' => $newPassword], ['phone' => $phone]);
    
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