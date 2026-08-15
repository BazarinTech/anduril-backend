<?php 
include 'includes/main.php';
include 'includes/send-email.php';
include 'includes/transaction-sms.php';
$error = '';
$msg = '';

$isEdit = false;

// payment method auto mpesa function for payhero
// function mpesa_auto($query, $curl, $phone, $amount, $trackingID){
//      $data = [
//         "amount" => (float) $amount,
//         "phone_number" => $phone,
//         "channel_id" => 1549, 
//         "external_reference" => $trackingID,
//         "callback_url" => "https://grover.xgramm.com/backend/mains/callbacks/payhero_b2c_callback.php",
//         "network_code" => "63902",
//         "channel" => "mobile",
//         "payment_service" => "b2c" 
//     ];
//     $initiate = $curl->request('https://backend.payhero.co.ke/api/v2/withdraw', 'POST', $data);
    

//     if (isset($initiate['error_code'])) {
//         $msg = $initiate['error_message'];
//         $status = 'Error';
//     }else{
//         $msg = "Transaction approved successfully!";
//         $status = 'Success';
//     }
    
    
//     return [
//             'status' => $status,
//             'msg' => $msg
//         ];
// }

//  payment method auto mpesa function for swiftwallet
function mpesa_auto($query, $curl, $phone, $amount, $trackingID){
    $headers = [
        'Authorization: Basic ' . env('PALPLUSS_KEY')
    ];
    $data = [
        "amount" => (float) $amount,
        "phone" => $phone,
        "reference" => $trackingID,
        "callbackUrl" => callback_url('palpluss_b2c_callback.php'),
        "description" => "BusinessPayment"
    ];


    $inititate = $curl->request(env('PALPLUSS_B2C_URL'), 'POST', $data, $headers);

    // An unreachable provider returns null. Treat "no answer" as an explicit
    // failure rather than reading array keys off it (same guard as
    // backend/mains/withdraw.php).
    if (!is_array($inititate)) {
        error_log('[approve-withdrawals] payout provider returned no parseable response');

        return [
            'status' => 'Failed',
            'msg' => 'Payment provider is unreachable. Please try again shortly.',
        ];
    }

    if (!empty($inititate['success']) && $inititate['success'] === true) {
        $res = [
            'status' => 'Success',
            'msg' => 'Mpesa transaction initiated succefully',
        ];
    }elseif(isset($inititate['error'])){
        $res = [
            'status' => 'Failed',
            'msg' => is_array($inititate['error']) ? ($inititate['error']['message'] ?? 'Payout rejected') : (string) $inititate['error'],
        ];
    }else{
        $res = [
            'status' => 'Failed',
            'msg' => 'Transaction Failed. Kindly reach our customer service for quick assistance',
        ];
    }

    return $res;
}


if (isset($_POST['submit'])) {
    $trackingID= $_POST['id'];
    $action = $_POST['action'];
    
    // Check if transaction with this tracking ID exists
    if(count($query->select('transactions', '*', ['trackingID' => $trackingID])) > 0){
        
         //get transaction details
            $transactions = $query->select('transactions', '*', ['trackingID' => $trackingID]);
            $transaction = $transactions[0];
            $userID = $transaction['userID'];
            $amount = $transaction['amount'] - $transaction['fees'];
            $status = $transaction['status'];
            $method = $transaction['method'];
            $account = $transaction['account'];
            
            // get user details
            $user_details = $query->select('users', '*', ['ID' => $userID]);
        if ($action == 'Success') {
            
            
            if($status == 'Success' || $status == 'Processing'){
                $error = 'Transaction is already approved!';
            }else{
                if($method == 'mpesa'){
                    $initiate = mpesa_auto($query, $api, $account, $amount, $trackingID);
                    if($initiate['status'] == 'Success'){
                        //update transaction status
                        $query->update('transactions', ['status' => 'Processing'], ['trackingID' => $trackingID]);
                        $msg = $initiate['msg'];
                        $body = success_withdrawal_template($user_details[0]['name'], $amount, $trackingID);
                        $email_res = send_email($user_details[0]['email'], 'Withdrawal Processed Successfully', $body);
                    }else{
                        $error = $initiate['msg'];
                    }
                }else{
                    
                    //update transaction status
                    $query->update('transactions', ['status' => 'Processing'], ['trackingID' => $trackingID]);
                    $msg = "Transaction approved successfully!";
                }
                
                
            }
            
        }else{
            //update transaction status
            $query->update('transactions', ['status' => $action], ['trackingID' => $trackingID]);
            $msg = "Transaction Rejected successfully!";

            $body = declined_withdrawal_template($user_details[0]['name'], $amount, 'Due to uknown issue that should be consulted from customer support');
            $email_res = send_email($user_details[0]['email'], 'We are unable to process your withdrawal request', $body);
            
        }
    }else{
        $error = 'There is no existence of transaction with that trackingID!';
    }
    


    // main.php built the queue before this handler ran, so the list below
    // would still show the record just actioned. Rebuild it.
    $withdraw_records = pending_withdrawal_records($query);
}
?>
<!DOCTYPE html>
<html lang="en"  :dir="$store.app.direction" x-data="{ direction: $store.app.direction || 'ltr' }" x-bind:dir="direction" class="group/item" :data-mode="$store.app.mode" :data-sidebar="$store.app.sidebarMode">


<!-- Mirrored from srbthemes.kcubeinfotech.com/sliced-pro/html/blank.html by HTTrack Website Copier/3.x [XR&CO'2014], Mon, 10 Mar 2025 01:28:49 GMT -->
<head>

    <meta charset="utf-8">
    <title>Payment Wallet</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Tailwind CSS Admin & Dashboard Template" name="description">
    <meta content="SRBThemes" name="author">
    <!-- favicon -->
    <link rel="shortcut icon" href="assets/images/favicon.ico">
    <!-- plugins CSS -->
    
    <!-- Icons CSS -->
    
    <!-- Tailwind CSS -->
    
    
    
    

  <script type="module" crossorigin src="assets/main-0ff05731.js"></script>
  <link rel="stylesheet" href="assets/css/main.css">
</head>

<body x-data="main" x-init="$store.app.hasCreative = window.location.href.includes('creative.html') , $store.app.hasdetached = window.location.href.includes('detached.html')" :class="[ $store.app.sidebar ? 'toggle-sidebar' : '', $store.app.fullscreen ? 'full' : '' , $store.app.hasCreative ? 'detached ' : '' , $store.app.hasdetached ? 'detached detached-simple ' : '' , $store.app.layout  ]" class="relative overflow-x-hidden text-[15px] antialiased font-normal text-black font-primary dark:text-white vertical " x-data="modals">

<!-- Start Layout -->
<div class="bg-slate-50 dark:bg-dark">

    <!-- Start detached bg -->
    <div class="bg-[url('../images/bg-main.png')] bg-slate-800 group-data-[sidebar=dark]/item:bg-darklight group-data-[sidebar=brand]/item:bg-sky-500 min-h-[220px] sm:min-h-[250px] bg-bottom fixed hidden w-full -z-50 detached-img"></div>
    <!-- End detached bg -->

    <!-- Start Menu Sidebar Olverlay -->
    <div x-cloak class="fixed inset-0 z-10 bg-black/60 dark:bg-dark/90 lg:hidden" :class="{'hidden' : !$store.app.sidebar}" @click="$store.app.toggleSidebar()"></div>
    <!-- End Menu Sidebar Olverlay -->

    <!-- Start Main Content -->
    <div class="flex mx-auto main-container">

        <!-- Start Sidebar -->
        <?php require('includes/sidebar.php')?>
        <!-- End sidebar -->

        <!-- Start Content Area -->
        <div class="flex-1 main-content">

            <!-- Start Topbar -->
            <?php require('includes/topbar.php')?>
            <!-- End Topbar --> 

            <!-- Start Content -->
            <div class="h-[calc(100vh-60px)] relative overflow-y-auto overflow-x-hidden p-4 space-y-4 detached-content">
                <nav class="w-full">
                    <ul class="space-y-2 detached-breadcrumb">
                        <li class="text-xs dark:text-white/80">transactions and payments</li>
                        <li class="text-xl font-semibold text-slate-800 dark:text-slate-100">Approve Withdrawals</li>
                    </ul>
                </nav>
                <!-- Start All Card -->
                <div class="flex flex-col gap-4 min-h-[calc(100vh-212px)]">
                    <div class="grid grid-cols-1 gap-4">
                    <?php if ($msg || $error): ?>
                        <div class="card">
                            <p class="<?= $msg ? 'bg-success/20 text-success' : 'bg-danger/20 text-danger' ?> text-center rounded-lg py-2 px-2">
                                <?= htmlspecialchars($msg ?: $error, ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </div>
                    <?php endif; ?>
                        
                        <div class="card">
                        <h2 class="mb-4 text-base font-semibold capitalize text-slate-800 dark:text-slate-100">Pending Withdrawals</h2>
                            <?php
                            /*
                             * As on Approve Deposits: the action buttons sit on the row and
                             * post exactly the fields the old type-in-a-tracking-ID form
                             * posted, so the payout logic above is unchanged.
                             */
                            data_table([
                                'id'       => 'pending-withdrawals',
                                'label'    => 'withdrawal',
                                'rows'     => $withdraw_records,
                                'key'      => 'id',
                                'search'   => ['trackingID', 'email', 'account', 'method'],
                                'empty'    => 'No withdrawals are waiting for approval.',
                                'columns'  => [
                                    ['label' => '#',          'field' => 'id'],
                                    ['label' => 'TrackingID', 'field' => 'trackingID'],
                                    ['label' => 'Email',      'field' => 'email'],
                                    ['label' => 'Phone',      'field' => 'account'],
                                    ['label' => 'Method',     'field' => 'method'],
                                    ['label' => 'Amount To Send',  'field' => 'amount', 'numeric' => true],
                                    ['label' => 'Transaction Fees', 'field' => 'fees',  'numeric' => true],
                                    ['label' => 'Time',       'field' => 'time'],
                                ],
                                'actions'  => [
                                    ['label' => 'Approve', 'style' => 'success', 'field' => 'trackingID',
                                     'post'  => ['action' => 'Success'],
                                     'confirm' => 'Approve withdrawal {value}? This sends the payout.'],
                                    ['label' => 'Reject',  'style' => 'danger',  'field' => 'trackingID',
                                     'post'  => ['action' => 'Declined'],
                                     'confirm' => 'Reject withdrawal {value}? The held amount goes back to the wallet.'],
                                ],
                            ]);
                            ?>
                        </div>
                    </div>
                </div>
                <!-- End All Card -->

                <!-- Start Footer -->
                <?php require('includes/footer.php')?>
                <!-- End Footer -->  

                </div>
        </div>
    </div>
</div>

<script  src="assets/libs/%40alpinejs/persist/cdn.min.js"></script>
<script  src="assets/libs/%40alpinejs/collapse/cdn.min.js"></script>
<script  src="assets/libs/feather-icons/feather.min.js"></script>


</body>


<!-- Mirrored from srbthemes.kcubeinfotech.com/sliced-pro/html/blank.html by HTTrack Website Copier/3.x [XR&CO'2014], Mon, 10 Mar 2025 01:28:49 GMT -->
</html>