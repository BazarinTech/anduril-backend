<?php
include 'initiate.php';
$data = $fileGetContent->get_content();

$id = intval($data['id']);
$field = $data['field'];
$value = $data['value'];

// Sanitize field names to prevent SQL injection
// Phase 4.11 -- four of the six names here were not columns on `wallets`:
// `email` lives on users, `rolls` on orders, and `total_income` /
// `bonus_income` do not exist at all. Editing any of them threw
// "Unknown column" (visible in admin/actions/error_log) rather than saving.
// The UI's "total income" field maps to the `income` column.
$allowedFields = ["balance", "income", "invite_income", "today_income", "level", "status"];
if (!in_array($field, $allowedFields, true)) {
    http_response_code(400);
    $fileGetContent->send_content(["success" => false, "message" => "Invalid field"]);
    exit;
}

// Update query
$update = $query->update('wallets', [$field => $value], ['ID' => $id]);

$response = ["success" => true, "message" => 'updated succefully'];

$fileGetContent->send_content($response);