<?php

// fucntion to generate random sms code that is 6 digits long and unique
function generateSMSCode($query){
    $isUnique = false;
    $smsCode = ''; 
    while (!$isUnique) {
        $smsCode = strval(rand(100000, 999999)); 

        $existingCode = $query->select('verification_codes', '*' , ['code' => $smsCode]);

        if (empty($existingCode)) {
            $isUnique = true;
        }
    }
    return $smsCode;
}

// Sanitize phone numbers

function sanitizeKenyanPhone($input){
    
    $phone = preg_replace('/[^0-9]/', '', $input);

    // Case 1: Starts with 07XXXXXXXX
    if (preg_match('/^07\d{8}$/', $phone)) {
        return '254' . substr($phone, 1);
    }

    // Case 2: Starts with 7XXXXXXXX
    if (preg_match('/^7\d{8}$/', $phone)) {
        return '254' . $phone;
    }
    
    // Case 3: Starts with 1XXXXXXXX
    if (preg_match('/^1\d{8}$/', $phone)) {
        return '254' . $phone;
    }
    
    // Case 4: Starts with 01XXXXXXXX
    if (preg_match('/^01\d{8}$/', $phone)) {
        return '254' . substr($phone, 1);
    }

    // Case 5: Starts with 2547XXXXXXXX
    if (preg_match('/^2547\d{8}$/', $phone)) {
        return $phone;
    }

    // Case 6: Starts with 25407XXXXXXXX (rare but happens)
    if (preg_match('/^25407\d{8}$/', $phone)) {
        return '254' . substr($phone, 3);
    }

    // Invalid Kenyan mobile number
    return false;
}

// Function to send sms using umeskia softwares
// function sendSMS($phoneNumber, $query, $curl, $type){
    
//     // Your SMS gateway API credentials
//     $apiUrl = "https://comms.umeskiasoftwares.com/api/v1/sms/send";
//     $apiKey = "7ac3d0e4b136d662d74c267e6bfe6974"; 
//     $senderID = "UMS_SMS"; 
//     $appID = "UMSC246701";
//     $sms_code = generateSMSCode($query);
//     $message = "Your verification code is: " . $sms_code ." . It is valid for 5 minutes.";
//     $data = [
//         "api_key" => $apiKey,
//         "app_id" => $appID,
//         "sender_id" => $senderID,
//         "message" => $message,
//         "phone" => $phoneNumber
//     ];
//     $inititate = $curl->request($apiUrl, 'POST', $data);

//     if ($inititate['status'] === 'complete') {
//         $insert_code = $query->insert('verification_codes', [
//             'phone' => $phoneNumber,
//             'code' => $sms_code,
//             'expiry' => '5',
//             'phone' => $phoneNumber,
//             'type' => $type 
//         ]);
//         $res = [
//             'status' => 'Success',
//             'message' => 'SMS sent successfully.',
//         ];
//     } else {
//         $res = [
//             'status' => 'Failed',
//             'message' => 'Failed to send SMS. Please try again later.',
//         ];
//     }

//     return $res;

// }


// Function to send sms using hostpinnacle
function sendSMS($phoneNumber, $query, $curl, $type){
    
    $apiUrl = "https://smsportal.hostpinnacle.co.ke/SMSApi/send";

    // Credentials
    $userid   = "bazarin";
    $password = "xxxxx";
    $apiKey   = "7ac3d0e4b136d662d74c267e6bfe6974";

    // Sender ID
    $senderID = "GROVER";

    // OTP message
    $sms_code = generateSMSCode($query);
    $message  = "Your verification code is: {$sms_code}. It is valid for 5 minutes.";

    // HostPinnacle expects form-urlencoded
    $postFields = http_build_query([
        "userid"         => $userid,
        "password"       => $password,
        "mobile"         => sanitizeKenyanPhone($phoneNumber),
        "msg"            => $message,
        "senderid"       => $senderID,
        "msgType"        => "text",
        "duplicatecheck" => "true",
        "output"         => "json",
        "sendMethod"     => "quick",
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CUSTOMREQUEST  => "POST",
        CURLOPT_POSTFIELDS     => $postFields,
        CURLOPT_HTTPHEADER     => [
            "apikey: " . $apiKey,
            "cache-control: no-cache",
            "content-type: application/x-www-form-urlencoded"
        ],
    ]);

    $response = curl_exec($ch);
    $err      = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Transport-level error
    if ($err) {
        return [
            "status"  => "Failed",
            "message" => "cURL Error: " . $err,
        ];
    }

    $decoded = json_decode($response, true);

    // Non-JSON response (unexpected)
    if (!is_array($decoded)) {
        return [
            "status"   => "Failed",
            "message"  => "Gateway returned non-JSON response.",
            "raw"      => $response,
            "httpCode" => $httpCode,
        ];
    }

    // EXACT success logic based on your sample response
    $status     = strtolower($decoded["status"] ?? "");
    $statusCode = (string)($decoded["statusCode"] ?? "");
    $invalid    = trim((string)($decoded["invalidMobile"] ?? ""));

    $isSuccess = ($status === "success") && ($statusCode === "200") && ($invalid === "");

    if ($isSuccess) {
        $query->insert('verification_codes', [
            "phone"  => $phoneNumber,
            "code"   => $sms_code,
            "expiry" => "5",
            "type"   => $type,
        ]);

        return [
            "status"  => "Success",
            "message" => "SMS sent successfully.",
            "gateway" => $decoded,
        ];
    }

    // Gateway-level failure
    $reason = $decoded["reason"] ?? "Unknown gateway error";

    return [
        "status"   => "Failed",
        "message"  => "Failed to send SMS: " . $reason,
        "gateway"  => $decoded,
        "httpCode" => $httpCode,
    ];
}