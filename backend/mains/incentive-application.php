<?php
include '../includes/initiate.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

// Get posted json data
$data = $fileGetContent->get_content();

// Process coupon
if (isset($data['userID'])) {
    try {
        
        if (!defined('JWT_SECRET') || !defined('JWT_ALGO')) {
          $fileGetContent->send_content([
            'status' => 'Error',
            'message' => 'JWT config missing'
          ]);
          exit;
        }
        $decoded = JWT::decode($data['userID'], new Key(JWT_SECRET, JWT_ALGO));
        $userID = $decoded->userID ?? $decoded->sub ?? null;

        if (!$userID) {
            $fileGetContent->send_content([
                'status' => 'Error',
                'message' => 'Invalid token'
            ]);
            exit;
        }
        $name = $data['name'];
        $phone_number = $data['phone'];
        $id_number = $data['idNumber'];
        $incentiveID = $data['incentiveID'];

        // Check if application already exists
        $existing_application = $query->select('incentives_requests', '*', ['userID' => $userID, 'incentiveID' => $incentiveID]);

        if (count($existing_application) > 0) {
            $response = [
                'status' => 'Failed',
                'message' => 'Application already submitted for this incentive'
            ];
            $fileGetContent->send_content($response);
            exit;
        }

        $insert_application = $query->insert('incentives_requests', [
            'userID' => $userID,
            'incentiveID' => $incentiveID,
            'name' => $name,
            'phone' => $phone_number,
            'personal_ID' => $id_number,
            'status' => 'Pending'
        ]);
        $response = [
            'status' => 'Success',
            'message' => 'Incentive application submitted successfully'
        ];

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
    } catch (\Throwable $e) {
    // Phase 5.6 -- this returned $e->getMessage() to the client as a 'debug'
    // field. A PDO exception message carries the failing SQL, and the token
    // messages describe the signing setup; neither belongs in a response.
    error_log('[incentive-application] ' . $e->getMessage());

    $fileGetContent->send_content([
        'status' => 'Error',
        'message' => 'Could not submit the application. Please try again.'
    ]);
}



}else{
    $response = [
            'status' => 'Failed',
            'message' => 'No token provided'
        ];
    $fileGetContent->send_content($response);
}