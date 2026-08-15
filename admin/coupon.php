<?php 
include 'includes/main.php';
include 'includes/send-email.php';
$error = '';
$msg = '';

$isEdit = false;


if (isset($_POST['submit'])) {
    $code = $_POST['code'];
    $expiry = $_POST['expiry'];
    $amount = $_POST['amount'];
    
    
    // Check if coupon with this tracking code exists
    if(count($query->select('coupons', '*', ['code' => $code])) == 0){
        
        $query->insert('coupons', ['code' => $code, 'expiry' => $expiry, 'amount' => $amount]);
        $msg = "Code added succesfully";
            
    }else{
        $error = 'That code exist, please try another one!';
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
                        <li class="text-xl font-semibold text-slate-800 dark:text-slate-100">Coupons</li>
                    </ul>
                </nav>
                <!-- Start All Card -->
                <div class="flex flex-col gap-4 min-h-[calc(100vh-212px)]">
                    <div class="grid grid-cols-1 gap-4">
                    <div class="card">
                            <form action="coupon" method="post">
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
                            <h2 class="mb-4 text-base font-semibold capitalize text-slate-800 dark:text-slate-100">Create Coupons</h2>
                                <div class="space-y-1">
                                    <label>Coupon Code</label>
                                    <input type="text" name='code' class="form-input h-14" placeholder="Code" required>
                                </div>
                                <div class="space-y-1">
                                    <label>Amount</label>
                                    <input type="number" name='amount' class="form-input h-14" placeholder="Amount" required>
                                </div>
                                <div class="space-y-1">
                                    <label>Expiry(minutes)</label>
                                    <input type="text" name='expiry' class="form-input h-14" placeholder="Expiry" required>
                                </div>
 
                                <button type="submit" name='submit' class="btn bg-purple border border-purple rounded-md text-white transition-all duration-300 hover:bg-purple/[0.85] hover:border-purple/[0.85]">Submit</button>
                            </form>
                        </div>
                        
                        <div class="card">
                        <h2 class="mb-4 text-base font-semibold capitalize text-slate-800 dark:text-slate-100">Coupons List</h2>
                            <div class="overflow-auto" x-data="{ items: <?= htmlspecialchars(json_encode($coupons), ENT_QUOTES, 'UTF-8') ?>,
                                                           searchTerm: '',
                            currentPage: 1,
                            itemsPerPage: 50,
                            
                            get filteredItems() {
                                if (!this.searchTerm) return this.items;
                                
                                const searchLower = this.searchTerm.toLowerCase();
                                return this.items.filter(item => 
                                    item.code.toLowerCase().includes(searchLower) || 
                                    item.expiry.toLowerCase().includes(searchLower) || 
                                    item.amount.toLowerCase().includes(searchLower) || 
                                    item.time_created.toLowerCase().includes(searchLower) || 
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
                                            <th>Coupon ID</th>
                                            <th>Code</th>
                                            <th>Amount</th>
                                            <th>Expiry</th>
                                            <th>Date Created</th>
                                            <th>Status</th>

                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="item in paginatedItems" :key="item.ID">
                                            <tr x-show="showElement" x-data="{ showElement: true }">
                                                <td x-text="item.ID"></td>
                                                <td>
                                                    <span x-text="item.code"></span>
                                                </td>
                                                <td>
                                                    <span x-text="item.amount"></span>
                                                </td>
                                                <td>
                                                    <span x-text="item.expiry"></span>
                                                </td>
                                                
                                                <td>
                                                    <span x-text="item.time_created" x-on:dblclick="
                                                        item.editing = <?= $isEdit ?>;
                                                        $nextTick(() => $refs.time_created.focus());
                                                    " x-show="!item.editing"></span>
                                                    <input type="text" class="form-input" x-ref="time_created" x-model="item.time_created" x-on:keydown.enter="item.editing = false" x-show="item.editing">
                                                </td>
                                                <td>
                                                    <?php /* Phase 4.16 -- read-only. This page has no updater()
                                                             and no request to actions/, so the edit never did
                                                             anything but raise a console error. */ ?>
                                                    <span x-text="item.status" x-bind:class="item.status === 'Active' ? 'bg-success/20 text-success' : 'bg-danger/20 text-danger'" class='px-2 rounded py-1'></span>
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