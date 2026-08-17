<?php

if (!function_exists('sanitizeKenyanPhone')) {
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

    // Case 3: Starts with 2547XXXXXXXX
    if (preg_match('/^2547\d{8}$/', $phone)) {
        return $phone;
    }

    // Case 4: Starts with 25407XXXXXXXX (rare but happens)
    if (preg_match('/^25407\d{8}$/', $phone)) {
        return '254' . substr($phone, 3);
    }

    // Invalid Kenyan mobile number
    return false;
}
}

// A commented-out Umeskia Softwares integration used to sit here. It was dead
// code carrying live bearer tokens and endpoint URLs in the clear, which
// is a credential in the repository whether or not anything executes it.
// The working implementation is sendSMS() below, which uses HostPinnacle.


// Function to send sms using hostpinnacle
if (!function_exists('sendSMS')) {
function sendSMS($phoneNumber, $query, $curl, $message){
    
    $apiUrl = env('SMS_URL');

    // Credentials
    $userid   = env('SMS_USERID');
    $password = env('SMS_PASSWORD');
    $apiKey   = env('SMS_API_KEY');

    // Sender ID
    $senderID = env('SMS_SENDER_ID');


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
        // $query->insert('verification_codes', [
        //     "phone"  => $phoneNumber,
        //     "code"   => $sms_code,
        //     "expiry" => "5",
        //     "type"   => $type,
        // ]);

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
}