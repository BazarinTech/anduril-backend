<?php
include 'initiate.php';
$data = $fileGetContent->get_content();

$id = intval($data['id']);
$field = $data['field'];
$value = $data['value'];

// Sanitize field names to prevent SQL injection
// Phase 4.11 -- `returns` is a products column, not an incentives one.
$allowedFields = ["name", "status", "salary", "level", "referrals", "bonusItem"];
if (!in_array($field, $allowedFields, true)) {
    http_response_code(400);
    $fileGetContent->send_content(["success" => false, "message" => "Invalid field"]);
    exit;
}

// Update query
$update = $query->update('incentives', [$field => $value], ['ID' => $id]);

$response = ["success" => true, "message" => 'updated succefully'];

$fileGetContent->send_content($response);