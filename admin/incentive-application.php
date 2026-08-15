<?php 
include 'includes/main.php';
include 'includes/send-email.php';
$error = '';
$msg = '';

$isEdit = false;


if (isset($_POST['submit'])) {
    $applicationID = $_POST['id'];
    $action = $_POST['action'];
    $request_details = $query->select('incentives_requests', '*', ['ID' => $applicationID]);
    $incentive_details = $query->select('incentives', '*', ['ID' => $request_details[0]['incentiveID']]);
    
    // Check if transaction with this tracking ID exists
    if(count($request_details) > 0){
        if ($action == 'Approved') {
            
            $query->update('incentives_requests', ['status' => $action], ['ID' => $applicationID]);
            $query->update('wallets', ['level' => $incentive_details[0]['level']], ['userID' => $request_details[0]['userID']]);
            $msg = "Application Approved successfully!";
            
        }else{
            //update application status
            $query->update('incentives_requests', ['status' => $action], ['ID' => $applicationID]);
            $msg = "Application Rejected successfully!";
            
        }
    }else{
        $error = 'There is no existence of application with that application ID!';
    }
    


    // main.php built the queue before this handler ran, so the list below
    // would still show the record just actioned. Rebuild it.
    $incentive_applications = incentive_application_records($query);
}
?>
<!DOCTYPE html>
<html lang="en"  :dir="$store.app.direction" x-data="{ direction: $store.app.direction || 'ltr' }" x-bind:dir="direction" class="group/item" :data-mode="$store.app.mode" :data-sidebar="$store.app.sidebarMode">


<!-- Mirrored from srbthemes.kcubeinfotech.com/sliced-pro/html/blank.html by HTTrack Website Copier/3.x [XR&CO'2014], Mon, 10 Mar 2025 01:28:49 GMT -->
<head>

    <meta charset="utf-8">
    <title>Incentives Applications</title>
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
                        <li class="text-xl font-semibold text-slate-800 dark:text-slate-100">Incentives Applications</li>
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
                        <h2 class="mb-4 text-base font-semibold capitalize text-slate-800 dark:text-slate-100">Pending Applications</h2>
                            <?php
                            data_table([
                                'id'       => 'incentive-applications',
                                'label'    => 'application',
                                'rows'     => $incentive_applications,
                                'key'      => 'ID',
                                'search'   => ['incentive', 'email', 'name', 'phone', 'personal_ID'],
                                'empty'    => 'No incentive applications are waiting for review.',
                                'columns'  => [
                                    ['label' => 'Application ID', 'field' => 'ID'],
                                    ['label' => 'Incentive Name', 'field' => 'incentive'],
                                    ['label' => 'Email',          'field' => 'email'],
                                    ['label' => 'Personal ID',    'field' => 'personal_ID'],
                                    ['label' => 'Applicant Name', 'field' => 'name'],
                                    ['label' => 'Phone',          'field' => 'phone'],
                                    ['label' => 'Date',           'field' => 'date'],
                                ],
                                'actions'  => [
                                    ['label' => 'Approve', 'style' => 'success',
                                     'post'  => ['action' => 'Approved'],
                                     'confirm' => 'Approve application #{value}?'],
                                    ['label' => 'Reject',  'style' => 'danger',
                                     'post'  => ['action' => 'Declined'],
                                     'confirm' => 'Reject application #{value}?'],
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