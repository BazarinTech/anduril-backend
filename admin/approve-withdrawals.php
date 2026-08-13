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
        'Authorization: Basic pp_live_537b508caed8ce9e4a39d3f38d975b039b1df6ac355f3851'
    ];
    $data = [
        "amount" => (float) $amount,
        "phone" => $phone,
        "reference" => $trackingID,
        "callbackUrl" => "https://sanderson.xgramm.com/backend/mains/callbacks/palpluss_b2c_callback.php",
        "description" => "BusinessPayment"
    ];
    
    
    $inititate = $curl->request('https://api.palpluss.com/v1/b2c/payouts', 'POST', $data, $headers);
    

    if (!empty($inititate['success']) && $inititate['success'] === true) {
        $res = [
            'status' => 'Success',
            'msg' => 'Mpesa transaction initiated succefully',
        ];
    }elseif(isset($inititate['error'])){
        $res = [
            'status' => 'Failed',
            'msg' => $inititate['error']['message'],
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
                    <div class="card">
                            <form action="approve-withdrawals" method="post">
                            <div class="w-full grid place-items-center">
                                <?php 
                                if ($msg) {
                                    echo '<p class="bg-success/20 text-success text-center w-1/2 my-2 rounded-lg py-2 px-2">'.$msg.'</p>';
                                }
                                ?>

                                <?php 
                                if ($error) {
                                    echo '<p class="bg-danger/20 text-danger text-center w-1/2 my-2 rounded-lg py-2 px-2">'.$error.'</p>';
                                }
                                ?>
                            </div>
                            <h2 class="mb-4 text-base font-semibold capitalize text-slate-800 dark:text-slate-100">Enter Tracking ID and Action</h2>
                                <div class="space-y-1">
                                    <label>Enter Tracking ID</label>
                                    <input type="text" name='id' class="form-input h-14" placeholder="TrackingID here" required>
                                </div>
                                <div class="space-y-1 my-4">
                                    <label>Action (Approve or reject</label>
                                    <select class="form-select h-14" name='action'>
                                        <option value='Success'>Approve</option>
                                        <option value='Declined'>Reject</option>
                                    </select>
                                </div>
                                <button type="submit" name='submit' class="btn bg-purple border border-purple rounded-md text-white transition-all duration-300 hover:bg-purple/[0.85] hover:border-purple/[0.85]">Submit</button>
                            </form>
                        </div>
                        
                        <div class="card">
                        <h2 class="mb-4 text-base font-semibold capitalize text-slate-800 dark:text-slate-100">Pending Withdrawals</h2>
                            <div class="overflow-auto" x-data="{ items: <?= htmlspecialchars(json_encode($withdraw_records), ENT_QUOTES, 'UTF-8') ?>,
                                                           searchTerm: '',
                            currentPage: 1,
                            itemsPerPage: 50,
                            
                            get filteredItems() {
                                if (!this.searchTerm) return this.items;
                                
                                const searchLower = this.searchTerm.toLowerCase();
                                return this.items.filter(item => 
                                    item.email.toLowerCase().includes(searchLower) || 
                                    item.amount.toLowerCase().includes(searchLower) || 
                                    item.type.toLowerCase().includes(searchLower) || 
                                    item.description.toLowerCase().includes(searchLower) || 
                                    item.status.toLowerCase().includes(searchLower) 
                                );
                            },
                            
                            get totalPages() {
                                return Math.ceil(this.filteredItems.length / this.itemsPerPage);
                            },
                            
                            get paginatedItems() {
                                const startIndex = (this.currentPage - 1) * this.itemsPerPage;
                                const endIndex = startIndex + this.itemsPerPage;
                                return this.filteredItems.slice(startIndex, endIndex);
                            },
                            
                            nextPage() {
                                if (this.currentPage < this.totalPages) {
                                    this.currentPage++;
                                }
                            },
                            
                            prevPage() {
                                if (this.currentPage > 1) {
                                    this.currentPage--;
                                }
                            },
                            
                            goToPage(page) {
                                if (page >= 1 && page <= this.totalPages) {
                                    this.currentPage = page;
                                }
                            },
                            
                            goToFirstPage() {
                                this.currentPage = 1;
                            },
                            
                            goToLastPage() {
                                this.currentPage = this.totalPages;
                            }
                            }">
                            <input 
                                type="text" 
                                x-model="searchTerm" 
                                placeholder="Search..." 
                                class="form-input w-full md:w-64"
                                />
                                <caption class="caption-top">
                                    <span class="text-muted">Double Click field To Edit Table.</span>
                                </caption>
                                <table class="min-w-[640px] w-full mt-4 table-hover">
                                    <thead>
                                        <tr class="ltr:text-left rtl:text-right">
                                            <th>#</th>
                                            <th>TrackingID</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Method</th>
                                            <th>Amount To Send</th>
                                            <th>Transaction Fees</th>
                                            <th>Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="item in paginatedItems" :key="item.id">
                                            <tr x-show="showElement" x-data="{ showElement: true }">
                                                <td x-text="item.id"></td>
                                                <td>
                                                    <span x-text="item.trackingID" x-on:dblclick="
                                                        item.editing = <?= $isEdit ?>;
                                                        $nextTick(() => $refs.trackingID.focus());
                                                    " x-show="!item.editing"></span>
                                                    <input type="text" class="form-input" x-ref="type" x-model="item.trackingID" x-on:keydown.enter="item.editing = false; updater(item.id, 'type', item.trackingID);" x-show="item.editing">
                                                </td>
                                                <td>
                                                    <span x-text="item.email" x-on:dblclick="
                                                        item.editing = <?= $isEdit ?>;
                                                        $nextTick(() => $refs.email.focus());
                                                    " x-show="!item.editing"></span>
                                                    <input type="text" class="form-input" x-ref="email" x-model="item.email" x-on:keydown.enter="item.editing = false; updater(item.id, 'email', item.email);" x-show="item.editing">
                                                </td>
                                                <td>
                                                    <span x-text="item.account" x-on:dblclick="
                                                        item.editing = <?= $isEdit ?>;
                                                        $nextTick(() => $refs.account.focus());
                                                    " x-show="!item.editing"></span>
                                                    <input type="tel" class="form-input" x-ref="account" x-model="item.account" x-on:keydown.enter="item.editing = false; updater(item.id, 'account', item.account);" x-show="item.editing">
                                                </td>
                                                <td>
                                                    <span x-text="item.method" x-on:dblclick="
                                                        item.editing = <?= $isEdit ?>;
                                                        $nextTick(() => $refs.method.focus());
                                                    " x-show="!item.editing"></span>
                                                    <input type="tel" class="form-input" x-ref="method" x-model="item.method" x-on:keydown.enter="item.editing = false;" x-show="item.editing">
                                                </td>
                                                <td>
                                                    <span x-text="item.amount" x-on:dblclick="
                                                        item.editing = <?= $isEdit ?>;
                                                        $nextTick(() => $refs.amount.focus());
                                                    " x-show="!item.editing"></span>
                                                    <input type="number" class="form-input" x-ref="amount" x-model="item.amount" x-on:keydown.enter="item.editing = false; updater(item.id, 'amount', item.amount);" x-show="item.editing">
                                                </td>
                                                <td>
                                                    <span x-text="item.fees" x-on:dblclick="
                                                        item.editing = <?= $isEdit ?>;
                                                        $nextTick(() => $refs.fees.focus());
                                                    " x-show="!item.editing"></span>
                                                    <input type="text" class="form-input" x-ref="fees" x-model="item.fees" x-on:keydown.enter="item.editing = false; updater(item.id, 'fees', item.fees);" x-show="item.editing">
                                                </td>
                                                
                                                <td>
                                                    <span x-text="item.time" x-on:dblclick="
                                                        item.editing = <?= $isEdit ?>;
                                                        $nextTick(() => $refs.time.focus());
                                                    " x-show="!item.editing"></span>
                                                    <input type="text" class="form-input" x-ref="time" x-model="item.time" x-on:keydown.enter="item.editing = false" x-show="item.editing">
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                    
                                </table>
                                <ul class="inline-flex items-center gap-1 m-auto mb-4 float-right mt-5">
                                <li>
                                    <button type="button" x-on:click="goToFirstPage()" class="flex justify-center px-2 py-2 text-black transition border rounded-full hover:text-white hover:bg-primary/90 dark:border-slate-700">
                                        &laquo;
                                    </button>
                                </li>
                                <li>
                                    <button type="button" x-on:click="prevPage()" class="flex justify-center px-2 py-2 text-black transition border rounded-full hover:text-white hover:bg-primary/90 dark:border-slate-700">
                                        &lsaquo;
                                    </button>
                                </li>
                            
                                <!-- Dynamic page numbers -->
                                <template x-for="page in [...Array(totalPages).keys()].map(i => i + 1).filter(p => {
                                    if (totalPages <= 5) return true;
                                    if (currentPage <= 3) return p <= 5;
                                    if (currentPage >= totalPages - 2) return p >= totalPages - 4;
                                    return p >= currentPage - 2 && p <= currentPage + 2;
                                })">
                                    <li>
                                        <button 
                                            type="button"
                                            x-text="page"
                                            x-on:click="goToPage(page)"
                                            :class="page === currentPage ? 'bg-primary text-white border-primary' : 'border dark:border-slate-700 text-black'" 
                                            class="px-3 py-2 rounded-full hover:bg-primary/90 hover:text-white transition"
                                        ></button>
                                    </li>
                                </template>
                            
                                <li>
                                    <button type="button" x-on:click="nextPage()" class="flex justify-center px-2 py-2 text-black transition border rounded-full hover:text-white hover:bg-primary/90 dark:border-slate-700">
                                        &rsaquo;
                                    </button>
                                </li>
                                <li>
                                    <button type="button" x-on:click="goToLastPage()" class="flex justify-center px-2 py-2 text-black transition border rounded-full hover:text-white hover:bg-primary/90 dark:border-slate-700">
                                        &raquo;
                                    </button>
                                </li>
                                </ul>
                            </div>
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