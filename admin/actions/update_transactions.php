<?php
include 'initiate.php';
$data = $fileGetContent->get_content();

$id = intval($data['id']);
$field = $data['field'];
$value = $data['value'];

// Sanitize field names to prevent SQL injection
// Phase 4.11 -- `email` is the owning user's address, joined in for display.
// It is not a column here, and the field is now read-only in the UI.
$allowedFields = ["status", "type", "fees", "description", "account", "amount", "method"];
if (!in_array($field, $allowedFields, true)) {
    http_response_code(400);
    $fileGetContent->send_content(["success" => false, "message" => "Invalid field"]);
    exit;
}

// Update query
$update = $query->update('transactions', [$field => $value], ['ID' => $id]);

$response = ["success" => true, "message" => 'updated succefully'];

$fileGetContent->send_content($response);