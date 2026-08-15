<?php 
include 'includes/main.php';
include 'includes/send-email.php';
$error = '';
$msg = '';

$isEdit = false;

// The two-level refferal_algo() copy that lived here is gone. It disagreed
// with the three-level version in the deposit callback, so the same deposit
// paid different commission depending on whether it settled automatically or
// an admin approved it (finding 4.3). Both now call referral_commission()
// from bootstrap/referrals.php.

if (isset($_POST['submit'])) {
    $trackingID= $_POST['id'];
    $action = $_POST['action'];
    
    // Check if transaction with this tracking ID exists
    if(count($query->select('transactions', '*', ['trackingID' => $trackingID])) > 0){
        if ($action == 'Success') {
            
            //get transaction details
            $transactions = $query->select('transactions', '*', ['trackingID' => $trackingID]);
            $transaction = $transactions[0];
            $userID = $transaction['userID'];
            $amount = $transaction['amount'];
            $status = $transaction['status'];
            
            // get user details
            $user_details = $query->select('users', '*', ['ID' => $userID]);
            
            if($status == 'Success'){
                $error = 'Transaction is already approved!';
            }else{
                /**
                 * Phase 3.2 -- same atomic credit as the automatic callback.
                 * The status check above and the credit below were separate
                 * statements, so two admins clicking Approve on the same
                 * pending deposit could both pass the check and credit it
                 * twice. The UPDATE now only matches a row that is still
                 * unapproved, and the wallet is locked for the arithmetic.
                 */
                $pdo->beginTransaction();

                try {
                    // Same claim primitive the payment callbacks use, so a
                    // double-clicked Approve cannot credit twice.
                    if (!claim_transaction($pdo, $trackingID, $status, 'Success')) {
                        $pdo->rollBack();
                        $error = 'Transaction is already approved!';
                    } else {
                        $wallet = wallet_for_update($pdo, $userID);

                        if ($wallet === null) {
                            $pdo->rollBack();
                            $error = 'No wallet found for that user!';
                        } else {
                            $query->update('wallets', ['balance' => money_str(money($wallet['balance']) + money($amount))], ['userID' => $userID]);

                            //refferal income
                            referral_commission($pdo, $query, $userID, $amount);

                            $pdo->commit();

                            // Email after the commit -- SMTP must not hold a row lock.
                            $body = deposit_template($user_details[0]['name'], money_str(money($amount)), $transaction['method']);
                            $email_res = send_email($user_details[0]['email'], 'Deposit Recieved Successfully', $body);

                            $msg = "Transaction approved successfully!";
                        }
                    }
                } catch (\Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    error_log('[approve-deposits] ' . $e->getMessage());
                    $error = 'Could not approve the transaction. Please try again.';
                }
            }
            
        }else{
            //update transaction status
            $query->update('transactions', ['status' => $action], ['trackingID' => $trackingID]);
            $msg = "Transaction Rejected successfully!";
            
        }
    }else{
        $error = 'There is no existence of transaction with that trackingID!';
    }
    


    // main.php built the queue before this handler ran, so the list below
    // would still show the record just actioned. Rebuild it.
    $deposits_records = pending_deposit_records($query);
}
?>
<!DOCTYPE html>
<html lang="en"  :dir="$store.app.direction" x-data="{ direction: $store.app.direction || 'ltr' }" x-bind:dir="direction" class="group/item" :data-mode="$store.app.mode" :data-sidebar="$store.app.sidebarMode">


<!-- Mirrored from srbthemes.kcubeinfotech.com/sliced-pro/html/blank.html by HTTrack Website Copier/3.x [XR&CO'2014], Mon, 10 Mar 2025 01:28:49 GMT -->
<head>

    <meta charset="utf-8">
    <title>Approve Deposits</title>
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
                        <li class="text-xl font-semibold text-slate-800 dark:text-slate-100">Approve Deposits</li>
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
                        <h2 class="mb-4 text-base font-semibold capitalize text-slate-800 dark:text-slate-100">Pending Deposits</h2>
                            <?php
                            /*
                             * Approve / Reject sit on the row they act on. They replace a
                             * standalone form that asked the admin to copy a tracking ID out
                             * of this table and type it back into a text box -- one
                             * transposed character away from approving the wrong deposit,
                             * and it accepted IDs for records not even listed here.
                             *
                             * The buttons post the same fields to the same handler, so the
                             * locking and crediting logic at the top of this file is
                             * untouched.
                             */
                            data_table([
                                'id'       => 'pending-deposits',
                                'label'    => 'deposit',
                                'rows'     => $deposits_records,
                                'key'      => 'id',
                                'search'   => ['trackingID', 'email', 'account', 'description'],
                                'empty'    => 'No deposits are waiting for approval.',
                                'columns'  => [
                                    ['label' => '#',          'field' => 'id'],
                                    ['label' => 'TrackingID', 'field' => 'trackingID'],
                                    ['label' => 'Email',      'field' => 'email'],
                                    ['label' => 'Phone',      'field' => 'account'],
                                    ['label' => 'Amount',     'field' => 'amount', 'numeric' => true],
                                    ['label' => 'Transaction Message', 'field' => 'description'],
                                    ['label' => 'Time',       'field' => 'time'],
                                ],
                                'actions'  => [
                                    ['label' => 'Approve', 'style' => 'success', 'field' => 'trackingID',
                                     'post'  => ['action' => 'Success'],
                                     'confirm' => 'Approve deposit {value}? This credits the wallet and pays referral commission.'],
                                    ['label' => 'Reject',  'style' => 'danger',  'field' => 'trackingID',
                                     'post'  => ['action' => 'Declined'],
                                     'confirm' => 'Reject deposit {value}?'],
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