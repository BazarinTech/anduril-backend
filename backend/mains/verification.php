<?php
include '../includes/initiate.php';
include 'sms-code.php';
include 'code-helpers.php';

// Get posted json data
$data = $fileGetContent->get_content();

/**
 * Phase 2.4 -- verification code lifecycle.
 *
 * The `expiry` column (minutes) was written on every code and then never read,
 * so a code stayed valid forever. Codes were also never consumed: verifying
 * one flipped it to 'Verified' and left it there, which is exactly the state
 * forgot-password.php accepted, so a single old SMS could reset a password
 * again and again.
 *
 * The lifecycle is now Pending -> Verified -> Used, with an age check at every
 * step, and 'Used' is terminal.
 */

// code_expired() and sms_rate_limited() live in code-helpers.php, shared with
// forgot-password.php.

// Process user phone verification
if (isset($data)) {

    // Check type of verification action
    $type = $data['type'] ?? '';

    if ($type === 'RequestPhoneVerification') {
        $phoneNumber = $data['phone'] ?? '';

        if ($phoneNumber === '') {
            $response = [
                'status' => 'Failed',
                'message' => 'A phone number is required.'
            ];
        } elseif (sms_rate_limited($query, $phoneNumber)) {
            $response = [
                'status' => 'Failed',
                'message' => 'Too many verification requests. Please wait a few minutes and try again.'
            ];
        } else {
            $response = sendSMS($phoneNumber, $query, $curl, 'PhoneVerification');
        }

    } elseif ($type === 'VerifyPhone') {
        $code = $data['code'] ?? '';
        $phoneNumber = $data['phone'] ?? '';

        // Only a Pending code for this number can be verified. An already
        // verified or already used code is not a second ticket.
        $existingCode = $query->select('verification_codes', '*', [
            'code'   => $code,
            'phone'  => $phoneNumber,
            'type'   => 'PhoneVerification',
            'status' => 'Pending'
        ]);

        if (!empty($existingCode) && !code_expired($existingCode[0])) {
            // Update code status to Verified
            $updateCode = $query->update('verification_codes', ['status' => 'Verified'], ['ID' => $existingCode[0]['ID']]);

            $response = [
                'status' => 'Success',
                'message' => 'Phone number verified successfully.'
            ];
        } elseif (!empty($existingCode)) {
            // Expire it explicitly so it cannot be retried.
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
