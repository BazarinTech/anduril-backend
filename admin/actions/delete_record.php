<?php
include 'initiate.php';
$data = $fileGetContent->get_content();

$id = intval($data['id']);
$table = $data['table'];

// Update query
if ($table == 'users') {
    $user = $query->select('users', '*', ['ID' => $id]);
    
    $delete = $query->delete('wallets', ['userID' => $id]);

    $delete = $query->delete($table, ['ID' => $id]);

}else{
    $delete = $query->delete($table, ['ID' => $id]);
}


$response = ["success" => true, "message" => 'Deleted succefully'];

$fileGetContent->send_content($response);