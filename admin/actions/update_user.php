<?php
include 'initiate.php';
$data = $fileGetContent->get_content();

$userId = intval($data['id']);
$field = $data['field'];
$value = $data['value'];

// Sanitize field names to prevent SQL injection
// Phase 4.11 -- the join date column is `date_created`, not `date_joined`.
// It is not editable from the panel, so it is simply dropped rather than
// renamed.
$allowedFields = ["name", "email", "phone", "status", "upline", "role"];
if (!in_array($field, $allowedFields, true)) {
    http_response_code(400);
    $fileGetContent->send_content(["success" => false, "message" => "Invalid field"]);
    exit;
}

// Update query
$update = $query->update('users', [$field => $value], ['ID' => $userId]);

$response = ["success" => true, "message" => 'updated succefully'];

$fileGetContent->send_content($response);