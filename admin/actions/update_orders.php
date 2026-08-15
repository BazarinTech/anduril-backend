<?php
include 'initiate.php';
$data = $fileGetContent->get_content();

$id = intval($data['id']);
$field = $data['field'];
$value = $data['value'];

// Sanitize field names to prevent SQL injection
// Phase 4.11 -- `email` is joined in from users for display, not a column
// here. Status is the only thing an admin can meaningfully change on an order.
$allowedFields = ["status"];
if (!in_array($field, $allowedFields, true)) {
    http_response_code(400);
    $fileGetContent->send_content(["success" => false, "message" => "Invalid field"]);
    exit;
}

// Update query
$update = $query->update('orders', [$field => $value], ['ID' => $id]);

$response = ["success" => true, "message" => 'updated succefully'];

$fileGetContent->send_content($response);