<?php 
include 'includes/main.php';
if (isset($_POST['update'])) {
    $min_dep = $_POST['minDep'];
    $min_with = $_POST['minWith'];
    $with_fee = $_POST['withFee'];
    $tran_fee = $_POST['tranFee'];
    $min_transfer = $_POST['minTransfer'];
    $level1 = $_POST['level1'];
    $level2 = $_POST['level2'];
    $level3 = $_POST['level3'];

    $update = $query->update('controls', ['minWith' => $min_with, 'minDep' => $min_dep, 'withFee' => $with_fee, 'tranFee' => $tran_fee, 'minTransfer' => $min_transfer, 'level1' => $level1, 'level2' => $level2]);
}
// if (isset($_POST['rolls'])) {
//     $update_rolls = $query->update('orders', ['rolls' => 1]);
// }
?>
<!DOCTYPE html>
<html lang="en"  :dir="$store.app.direction" x-data="{ direction: $store.app.direction || 'ltr' }" x-bind:dir="direction" class="group/item" :data-mode="$store.app.mode" :data-sidebar="$store.app.sidebarMode">


<!-- Mirrored from srbthemes.kcubeinfotech.com/sliced-pro/html/blank.html by HTTrack Website Copier/3.x [XR&CO'2014], Mon, 10 Mar 2025 01:28:49 GMT -->
<head>

    <meta charset="utf-8">
    <title>Transaction Control</title>
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
                        <li class="text-xs dark:text-white/80">transaction and payments</li>
                        <li class="text-xl font-semibold text-slate-800 dark:text-slate-100">Transaction Control</li>
                    </ul>
                </nav>
                <!-- Start All Card -->
                <div class="flex flex-col gap-4 min-h-[calc(100vh-212px)]">
                    <div class="grid grid-cols-1 gap-4">
                        <div class="card">
                        <form action="transaction-control" method='post'>
                            <h2 class="mb-4 text-base font-semibold capitalize text-slate-800 dark:text-slate-100"></h2>
                                <div class="space-y-1">
                                    <label>Minimum Withdrawal(kes)</label>
                                    <input type="number" name='minWith' class="form-input h-14" placeholder="eg 500" value='<?=$min_with?>' required>
                                </div>
                                <div class="space-y-1 my-4">
                                    <label>Maximum Withdrawal(kes)</label>
                                    <input type="number" name='maxWith' class="form-input h-14" placeholder="eg 500" value='1000000' required>
                                </div>
                                
                                
                                <div class="space-y-1 my-4">
                                    <label>Withdrawal Fee (%)</label>
                                    <input type="number" name='withFee' class="form-input h-14" placeholder="eg 500" value='<?=$with_fee?>' required>
                                </div>
                                <div class="space-y-1 my-4">
                                    <label>Transfer fee (%)</label>
                                    <input type="number" name='tranFee' class="form-input h-14" placeholder="eg 500" value='<?=$tran_fee?>' required>
                                </div>
                                <div class="space-y-1 my-4">
                                    <label>Minimum Transfer (Kes)</label>
                                    <input type="number" name='minTransfer' class="form-input h-14" placeholder="eg 500" value='<?=$min_transfer?>' required>
                                </div>
                                <div class="space-y-1 my-4">
                                    <label>Level 1 commission (%)</label>
                                    <input type="number" name='level1' class="form-input h-14" placeholder="eg 500" value='<?=$level1?>' required>
                                </div>
                                <div class="space-y-1 my-4">
                                    <label>Level 2 commission (%)</label>
                                    <input type="number" name='level2' class="form-input h-14" placeholder="eg 500" value='<?=$level2?>' required>
                                </div>
                                <div class="space-y-1 my-4">
                                    <label>Level 3 commission (%)</label>
                                    <input type="number" name='level3' class="form-input h-14" placeholder="eg 500" value='<?=$level3?>' required>
                                </div>
                                
                                
                                <div class="space-y-1 my-4">
                                    <label>Minimum Deposit(Kes)</label>
                                    <input type="number" name='minDep' class="form-input h-14" placeholder="eg 500" value='<?=$min_dep?>' required>
                                </div>
                                <button  name='update' type='submit' class="btn bg-purple border border-purple rounded-md text-white transition-all duration-300 hover:bg-purple/[0.85] hover:border-purple/[0.85]">Save</button>
                            </form>
                            <form action="transaction-control" method='post'>
                            <button  name='rolls' type='submit' class="btn <?= $isAdd ? '' : 'hidden' ?> mt-5 bg-purple border border-purple rounded-md text-white transition-all duration-300 hover:bg-purple/[0.85] hover:border-purple/[0.85]">Update User Rolls</button>
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