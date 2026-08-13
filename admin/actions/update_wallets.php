<?php
include 'initiate.php';
$data = $fileGetContent->get_content();

$id = intval($data['id']);
$field = $data['field'];
$value = $data['value'];

// Sanitize field names to prevent SQL injection
$allowedFields = ["email", "balance", "rolls", "total_income", "invite_income", "bonus_income"];
if (!in_array($field, $allowedFields)) {
    $response =["success" => false, "message" => "Invalid field"];
    exit;
}

// Update query
$update = $query->update('wallets', [$field => $value], ['ID' => $id]);

$response = ["success" => true, "message" => 'updated succefully'];

$fileGetContent->send_content($response);