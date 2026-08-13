<?php 
include 'includes/main.php';
//payment wallet
$payment_wallet = 0;

//service wallet
$response = $curl->request('https://api.palpluss.com/v1/wallets/service/balance', 'GET');
$service_wallet = $response['data']['ledgerBalance'];
?>
<!DOCTYPE html>
<html lang="en"  :dir="$store.app.direction" x-data="{ direction: $store.app.direction || 'ltr' }" x-bind:dir="direction" class="group/item" :data-mode="$store.app.mode" :data-sidebar="$store.app.sidebarMode">


<!-- Mirrored from srbthemes.kcubeinfotech.com/sliced-pro/html/crm-dashboard.html by HTTrack Website Copier/3.x [XR&CO'2014], Mon, 10 Mar 2025 01:28:12 GMT -->
<head>

    <meta charset="utf-8">
    <title>Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Tailwind CSS Admin & Dashboard Template" name="description">
    <link href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" rel="stylesheet">
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

        <!-- Start Side bar -->
         <?php require('includes/sidebar.php')?>
         <!-- End Side bar -->
          
        <!-- Start Content Area -->
        <div class="flex-1 main-content">

            <!-- Start Topbar -->
            <?php require('includes/topbar.php')?>
            <!-- End Topbar -->

             <!-- Start Content -->
            <div class="h-[calc(100vh-60px)] relative overflow-y-auto overflow-x-hidden p-4 space-y-4 detached-content">
                <nav class="w-full">
                    <ul class="space-y-2 detached-breadcrumb">
                        <li class="text-xs dark:text-white/80">Dashboard</li>
                        <li class="text-xl font-semibold text-slate-800 dark:text-slate-100">Welcome, <?=$admin_uname?></li>
                    </ul>
                </nav>                <!-- Start All Card -->
                <div class="flex flex-col gap-4 min-h-[calc(100vh-212px)]">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 xl:grid-cols-5">
                        <div class="card">
                            <p class="flex items-center gap-2 text-base dark:text-gray-300"><i data-feather="dollar-sign" class="size-4"></i> Payment Wallet</p>
                            <h4 class="flex items-center gap-4 mt-3 text-2xl font-semibold text-slate-800 dark:text-slate-100">Kes <?=number_format($payment_wallet, 2)?>
                            </h4>
                            <p class="mt-2 text-muted"><span class="font-semibold text-success">+$141k</span> than last month</p>
                        </div>
                        <div class="card">
                            <p class="flex items-center gap-2 text-base dark:text-gray-300"><i data-feather="activity" class="size-4"></i> Service Wallet</p>
                            <h4 class="flex items-center gap-4 mt-3 text-2xl font-semibold text-slate-800 dark:text-slate-100">Kes <?=number_format($service_wallet, 2)?>
                            </h4>
                            <p class="mt-2 text-muted"><span class="font-semibold text-success">2%</span> than last month</p>
                        </div>
                        <div class="card">
                            <p class="flex items-center gap-2 text-base dark:text-gray-300"><i data-feather="truck" class="size-4"></i> Total Balance</p>
                            <h4 class="flex items-center gap-4 mt-3 text-2xl font-semibold text-slate-800 dark:text-slate-100">Kes <?=number_format($total_balance, 2)?>
                            </h4>
                            <p class="mt-2 text-muted"><span class="font-semibold text-success">+1.5k</span> than last month</p>
                        </div>
                        <div class="card">
                            <p class="flex items-center gap-2 text-base dark:text-gray-300"><i data-feather="stop-circle" class="size-4"></i> Total Deposits</p>
                            <h4 class="flex items-center gap-4 mt-3 text-2xl font-semibold text-slate-800 dark:text-slate-100">Kes <?=number_format($total_deposits, 2)?>
                            </h4>
                            <p class="mt-2 text-muted"><span class="font-semibold text-danger">548</span> than last month</p>
                        </div>
                        <div class="card">
                            <p class="flex items-center gap-2 text-base dark:text-gray-300"><i data-feather="shopping-bag" class="size-4"></i> Total Withdrawals</p>
                            <h4 class="flex items-center gap-4 mt-3 text-2xl font-semibold text-slate-800 dark:text-slate-100">Kes <?=number_format($total_withdrawals, 2)?>
                            </h4>
                            <p class="mt-2 text-muted"><span class="font-semibold text-success">+1.7k</span> than last month</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-12 gap-4">
                        <div class="col-span-12 gap-6 card xl:col-span-6">
                            <div class="flex items-center justify-between gap-4 mb-4">
                                <h2 class="text-base font-semibold text-slate-800 dark:text-slate-100">Users</h2>
                                <div>
                                    <div x-data="{ dropdown: false}" class="ltr:ml-auto rtl:mr-auto dropdown">
                                        <a href="#!" class="text-black dark:text-white" @click="dropdown = !dropdown" @keydown.escape="dropdown = false">
                                            <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M9 12.75C9.69036 12.75 10.25 13.3096 10.25 14C10.25 14.6904 9.69036 15.25 9 15.25C8.30964 15.25 7.75 14.6904 7.75 14C7.75 13.3096 8.30964 12.75 9 12.75Z" fill="currentColor"></path>
                                                <path d="M14 12.75C14.6904 12.75 15.25 13.3096 15.25 14C15.25 14.6904 14.6904 15.25 14 15.25C13.3096 15.25 12.75 14.6904 12.75 14C12.75 13.3096 13.3096 12.75 14 12.75Z" fill="currentColor"></path>
                                                <path d="M20.25 14C20.25 13.3096 19.6904 12.75 19 12.75C18.3096 12.75 17.75 13.3096 17.75 14C17.75 14.6904 18.3096 15.25 19 15.25C19.6904 15.25 20.25 14.6904 20.25 14Z" fill="currentColor"></path>
                                            </svg>
                                        </a>
                                        <ul x-show="dropdown" @click.away="dropdown = false" x-transition="" x-transition.duration.300ms="" class=" whitespace-nowrap ltr:right-0 rtl:left-0">
                                            <li><a href="#!">Weekly</a></li>
                                            <li><a href="#!">Monthly</a></li>
                                            <li><a href="#!">Yearly</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-3 divide-x md:grid-cols-4 divide-slate-200 dark:divide-darkborder divide-dashed rtl:divide-x-reverse">
                                <div class="px-4 ltr:first:pl-0 rtl:first:pr-0">
                                    <h6 class="text-base font-semibold text-slate-800 dark:text-slate-100"><?=$total_users?></h6>
                                    <p>Totals Users</p>
                                </div>
                                <div class="px-4 ltr:first:pl-0 rtl:first:pr-0">
                                    <h6 class="text-base font-semibold text-slate-800 dark:text-slate-100"><?=$total_active_users?></h6>
                                    <p>Active Users</p>
                                </div>
                                <div class="px-4 ltr:first:pl-0 rtl:first:pr-0">
                                    <h6 class="text-base font-semibold text-slate-800 dark:text-slate-100"><?=$users_joined_today?></h6>
                                    <p>Joined Today</p>
                                </div>
                            </div>
                            <div>
                                <div id="customerActivitiesChart" dir="ltr" class="!min-h-[auto]"></div>
                            </div>
                        </div>
                        <div class="col-span-12 card xl:col-span-3">
                            <div class="flex items-center gap-3 mb-4">
                                <h2 class="text-base font-semibold capitalize grow text-slate-800 dark:text-slate-100">Transactions</h2>
                                <a href="#!" class="transition-all hover:text-purple">View All <i class="align-middle ri-arrow-right-line"></i></a>
                            </div>
                            <div id="salesChart" dir="ltr"></div>
                            <div class="mt-4 space-y-3">
                                <div class="flex items-center gap-3 p-3 border border-dashed shadow-none card">
                                    <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAACXBIWXMAAAsTAAALEwEAmpwYAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAMpSURBVHgBjZZvTiJBEMWHURMTTZb9pvFPxhOs3gBOoJ4APAF4AvEE4AnEE4gngBsIJ3A2auI3ZxNNjFHZ94MqMg7DxE7abqq769V7Vd1jKShoGxsb0crKSuPr6+uoVCpFMiXqw/F4fPX4+NgNftBKixZ2d3drctzRtKw+EEAsx2X1fQPrfXx8nK6urgaJtR8DbG9vVzT05Sz+/PysPj09xZn1aw1HaZv2DjVcZJnlAuzs7Nwp+iDrfGtri+jbmlaCqVxXNsLs0Jm9vLyc5DKSg7qi66uPNW+6vawm0DZ29Wf1FrZgnnnLzra/LbDZHad6hTXlomFOOXhJ4tOMckA66fMhf9bX1/tG+0JUT7Gpeu4YqSByoWlV+p4gGSAEJElukRPmDrC8vNyy6SRHoS0SyfnDw0PTNM22C60NjOmZnAA+EvAJuRLQJYAAx3GcWEDRBFCLNQyKrhUUNKSSM/aULRjf30V7jQ2AFXBXPtnzyyWqyDAqck7lqKJgd0zU6jWk2dzcnMgAmO7EARdQe+sWxMgBcPBcBKCDXTHcQybqnLlsN0tLS9eeeHIje132Ax1J5PMQSUPkEfW5anh/f/+d+jnKAR3a5SqZNJc4FMjQmEYqnmYoJGjte1mpenpG9ZZDRneRdIlHjTRra2su2UDDkPyGQu9Y1qG7TxVwSJruBdObDrs/WedhGM5syk+Sw5Aqi0IcCumGSD3qtKayHWvtyOsdpsx1uB4UNJ2bVVFgESZeBWJ1S72zcH9/37OksoZk18rZ7EIWAMB84ACJ62nSkKiWR807pN8Anush29O+TpFzgkMegnKAvxioAqRRkqrpW8o3AGDqnVcSmWRv5MliwbSoMEraAXr8URWc+eZUvR8A6G+QVRZvFxGSO96fyI5RMFzIwevrazVLy1/Bs2xk/gb5q8re9HOtZ6Rp9n764Zuw8kkURWXJQHR8f2NYKZp/0LaKmXw6g+k7NPBz9t3uIyeMs8HNfdGIwPTN3u6eHB+nDe6c/OlnNQ28EMBb2drb2xsat50ZyaPizGklmFbgqcq5m+dnIcACZjVjxjc45oJK1k72n4J0+w+aFx6vuG6LdAAAAABJRU5ErkJggg==" alt="" class="size-7">
                                    <div>
                                        <h5 class="text-slate-800 dark:text-slate-100">Deposits</h5>
                                        <p class="text-muted dark:text-darkmuted">+2.2% yesterday</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 p-3 border border-dashed shadow-none card">
                                    <img src="data:image/png;base64,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" alt="" class="size-7">
                                    <div>
                                        <h5 class="text-slate-800 dark:text-slate-100">Withdrawals</h5>
                                        <p class="text-muted dark:text-darkmuted">-1.5% yesterday</p>
                                    </div>
                                </div>
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

<script src="assets/libs/apexcharts/apexcharts.min.js"></script>
<script src="assets/js/pages/crm-dashboard.init.js"></script>

</body>


<!-- Mirrored from srbthemes.kcubeinfotech.com/sliced-pro/html/crm-dashboard.html by HTTrack Website Copier/3.x [XR&CO'2014], Mon, 10 Mar 2025 01:28:13 GMT -->
</html>