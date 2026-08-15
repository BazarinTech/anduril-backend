<?php 
include 'includes/main.php';
include 'includes/send-email.php';
$error = '';
$msg = '';

$isEdit = false;


if (isset($_POST['submit'])) {
    $userID = $_POST['id'];
    $amount = $_POST['amount'];

    /**
     * Phase 4.13 -- this inserted the string 'REWARD' into `orders.prodID`,
     * an INT column. Under STRICT_TRANS_TABLES that is error 1366 and the
     * whole reward failed; without strict mode it silently stored 0. The row
     * is already distinguished by `type = 'reward'`, so prodID carries no
     * information here and 0 is the honest value.
     *
     * Phase 3 also applies: the balance is read, modified and written, so it
     * gets the same lock as every other credit.
     */
    if (!is_valid_amount($amount)) {
        $error = 'Please enter a valid reward amount.';
    } else {
        $pdo->beginTransaction();

        try {
            $wallet = wallet_for_update($pdo, $userID);

            if ($wallet === null) {
                $pdo->rollBack();
                $error = 'No wallet found for that user.';
            } else {
                // process reward
                $query->update('wallets', ['balance' => money_str(money($wallet['balance']) + money($amount))], ['userID' => $userID]);
                $insert_order = $query->insert('orders', ['userID' => $userID, 'prodID' => 0, 'type' => 'reward', 'amount' => money_str(money($amount))]);

                $insert_transaction = $query->insert('transactions', [
                            'userID'      => $userID,
                            'type'        => 'Bonus',
                            'amount'      => money_str(money($amount)),
                            'description' => 'Reward have been awarded to your account.',
                            'status'      => 'Completed'
                            ]);

                $pdo->commit();
                $msg = "Reward have been awarded";
            }
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('[user-reward] ' . $e->getMessage());
            $error = 'Could not award the reward. Please try again.';
        }
    }

}
?>
<!DOCTYPE html>
<html lang="en"  :dir="$store.app.direction" x-data="{ direction: $store.app.direction || 'ltr' }" x-bind:dir="direction" class="group/item" :data-mode="$store.app.mode" :data-sidebar="$store.app.sidebarMode">


<!-- Mirrored from srbthemes.kcubeinfotech.com/sliced-pro/html/blank.html by HTTrack Website Copier/3.x [XR&CO'2014], Mon, 10 Mar 2025 01:28:49 GMT -->
<head>

    <meta charset="utf-8">
    <title>Coupon</title>
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
                        <li class="text-xs dark:text-white/80">commodities controll</li>
                        <li class="text-xl font-semibold text-slate-800 dark:text-slate-100">User Reward</li>
                    </ul>
                </nav>
                <!-- Start All Card -->
                <div class="flex flex-col gap-4 min-h-[calc(100vh-212px)]">
                    <div class="grid grid-cols-1 gap-4">
                    <div class="card">
                            <form action="user-reward" method="post">
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
                            <h2 class="mb-4 text-base font-semibold capitalize text-slate-800 dark:text-slate-100">Reward a user</h2>
                                <div class="space-y-1">
                                    <label>User ID</label>
                                    <input type="text" name='id' class="form-input h-14" placeholder="User ID" required>
                                </div>
                                <div class="space-y-1">
                                    <label>Amount</label>
                                    <input type="number" name='amount' class="form-input h-14" placeholder="Amount" required>
                                </div>
 
                                <button type="submit" name='submit' class="btn bg-purple border border-purple rounded-md text-white transition-all duration-300 hover:bg-purple/[0.85] hover:border-purple/[0.85]">Submit</button>
                            </form>
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