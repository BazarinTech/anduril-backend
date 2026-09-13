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
        $decoded = JWT::decode(request_token($data), new Key(JWT_SECRET, JWT_ALGO));
        $userID = $decoded->userID ?? $decoded->sub ?? null;

        if (!$userID) {
            $fileGetContent->send_content([
                'status' => 'Error',
                'message' => 'Invalid token'
            ]);
            exit;
        }
        $name = request_str($data, 'name');
        $phone_number = request_str($data, 'phone');
        $id_number = request_str($data, 'idNumber');
        $incentiveID = request_str($data, 'incentiveID');

        /**
         * All four land in NOT NULL columns of incentives_requests. Missing
         * ones used to arrive as NULL and fail the INSERT -- after the
         * duplicate check had already run -- with a database error instead of
         * a message the applicant could act on. Lengths are checked against
         * the columns for the same reason: strict mode rejects an overlong
         * value rather than truncating it.
         */
        $limits = [
            'your full name'   => [$name, 50],
            'your phone number' => [$phone_number, 15],
            'your ID number'   => [$id_number, 10],
            'the incentive'    => [$incentiveID, 12],
        ];

        foreach ($limits as $label => [$value, $max]) {
            if ($value === '') {
                $fileGetContent->send_content([
                    'status'  => 'Failed',
                    'message' => 'Please provide ' . $label . '.',
                ]);
                exit;
            }

            if (strlen($value) > $max) {
                $fileGetContent->send_content([
                    'status'  => 'Failed',
                    'message' => ucfirst($label) . ' is too long (maximum ' . $max . ' characters).',
                ]);
                exit;
            }
        }

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
    } catch (\UnexpectedValueException | \DomainException $e) {
        // A malformed or unreadable token. php-jwt raises UnexpectedValueException
        // for structural problems (expired and bad-signature are its
        // subclasses, caught above) and DomainException when a segment is not
        // valid base64/JSON. Without this catch both fell into the catch-all
        // below and were reported as a failed submission.
        $fileGetContent->send_content([
            'status' => 'Error',
            'message' => 'Invalid token'
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