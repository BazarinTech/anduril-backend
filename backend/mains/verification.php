<?php
include '../includes/initiate.php';
include 'sms-code.php';

// Get posted json data
$data = $fileGetContent->get_content();

// Process user phone verification
if (isset($data)) {
    

    // Check type of verification action
    $type = $data['type'];
    if ($type === 'RequestPhoneVerification') {
        $phoneNumber = $data['phone'];
        $response = sendSMS($phoneNumber, $query, $curl, 'PhoneVerification');
    } elseif ($type === 'VerifyPhone') {
        $code = $data['code'];
        $phoneNumber = $data['phone'];
        // Verify the code
        $existingCode = $query->select('verification_codes', '*', ['code' => $code, 'phone' => $phoneNumber, 'type' => 'PhoneVerification', 'status' => 'Pending']);
        if (!empty($existingCode)) {
            // Update code status to Verified
            $updateCode = $query->update('verification_codes', ['status' => 'Verified'], ['ID' => $existingCode[0]['ID']]);

            $response = [
                'status' => 'Success',
                'message' => 'Phone number verified successfully.'
            ];
        } else {
            $response = [
                'status' => 'Failed',
                'message' => 'Invalid code or phone number.'
            ];
        }
    }else{
        $response = [
            'status' => 'Error',
            'message' => 'Invalid verification type'
        ];
    }

    $fileGetContent->send_content($response);
    
}else{
    $fileGetContent->send_content([
        'status' => 'Error',
        'message' => 'Some fields are empty'
    ]);
}
