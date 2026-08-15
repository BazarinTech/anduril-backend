<?php include 'includes/main.php'?>
<!DOCTYPE html>
<html lang="en"  :dir="$store.app.direction" x-data="{ direction: $store.app.direction || 'ltr' }" x-bind:dir="direction" class="group/item" :data-mode="$store.app.mode" :data-sidebar="$store.app.sidebarMode">


<!-- Mirrored from srbthemes.kcubeinfotech.com/sliced-pro/html/blank.html by HTTrack Website Copier/3.x [XR&CO'2014], Mon, 10 Mar 2025 01:28:49 GMT -->
<head>

    <meta charset="utf-8">
    <title>Transactions</title>
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
                        <li class="text-xl font-semibold text-slate-800 dark:text-slate-100">Transactions</li>
                    </ul>
                </nav>
                <!-- Start All Card -->
                <div class="flex flex-col gap-4 min-h-[calc(100vh-212px)]">
                    <div class="grid grid-cols-1 gap-4">
                        <div class="card">
                        <h2 class="mb-4 text-base font-semibold capitalize text-slate-800 dark:text-slate-100">Transaction Records</h2>
                            <?php
                            data_table([
                                'id'         => 'transactions',
                                'label'      => 'transaction',
                                'rows'       => $transactions_records,
                                'key'        => 'id',
                                'update'     => 'actions/update_transactions.php',
                                'resource'   => 'transactions',
                                'can_edit'   => $isEdit,
                                'can_delete' => $isEdit,
                                'search'     => ['email', 'account', 'type', 'status', 'description'],
                                'empty'      => 'No transactions recorded yet.',
                                'columns'    => [
                                    ['label' => '#',     'field' => 'id'],
                                    ['label' => 'Email', 'field' => 'email', 'wide' => true,
                                     'hint'  => "The owning user's address, joined in from users. Change it on the Users page."],
                                    ['label' => 'Phone', 'field' => 'account', 'edit' => 'account'],
                                    /*
                                     * 'amount' is rendered as "Kes 500"; the editor reads
                                     * amount_raw instead. Posting the formatted string into
                                     * a DECIMAL column is what the inline editor used to do.
                                     */
                                    ['label' => 'Amount', 'field' => 'amount', 'value' => 'amount_raw', 'edit' => 'amount',
                                     'type'  => 'number', 'numeric' => true],
                                    ['label' => 'Type transaction', 'field' => 'type', 'edit' => 'type'],
                                    ['label' => 'Status', 'field' => 'status', 'edit' => 'status',
                                     'type'  => 'select',
                                     'options' => ['Success' => 'Success', 'Pending' => 'Pending', 'Processing' => 'Processing', 'Failed' => 'Failed', 'Declined' => 'Declined'],
                                     'badge' => ['Success' => 'success', 'Pending' => 'warning', 'Processing' => 'info', '*' => 'danger'],
                                     'hint'  => 'Editing this only relabels the row. It does not move money -- use Approve Deposits or Approve Withdrawals for that.'],
                                    ['label' => 'Description', 'field' => 'description', 'edit' => 'description', 'wide' => true],
                                    ['label' => 'Fee Charged', 'field' => 'fee', 'edit' => 'fees', 'type' => 'number', 'numeric' => true],
                                    ['label' => 'Time', 'field' => 'time',
                                     'hint'  => 'Recorded when the transaction was created.'],
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