<?php
include 'includes/main.php';

// Process roll update
if(isset($_POST['update'])){
    $update_rolls = $query->update('orders', ['rolls' => 1]);
    $update_wallets = $query->update('wallets', ['today_income' => 0]);
    
    echo 'Updated succesfull';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
  <title>Admin</title>
</head>
<body class="w3-light-grey">

        <form action="admin-roll" method="post">
      <button name="update" class="w3-btn w3-blue w3-round w3-margin">Update Rolls</button>
    </form>
</body>
</html>