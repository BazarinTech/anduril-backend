<?php 
include 'includes/main.php';
$error = '';
$msg = '';
if (isset($_POST['submit'])) {
    $name= $_POST['name'];
    $salary = $_POST['salary'];
    $referrals = $_POST['referrals'];
    $level = $_POST['level'];
    

    $insert = $query->insert('incentives', ['name' => $name, 'salary' => $salary, 'referrals' => $referrals, 'level' => $level]);


}
?>
<!DOCTYPE html>
<html lang="en"  :dir="$store.app.direction" x-data="{ direction: $store.app.direction || 'ltr' }" x-bind:dir="direction" class="group/item" :data-mode="$store.app.mode" :data-sidebar="$store.app.sidebarMode">


<!-- Mirrored from srbthemes.kcubeinfotech.com/sliced-pro/html/blank.html by HTTrack Website Copier/3.x [XR&CO'2014], Mon, 10 Mar 2025 01:28:49 GMT -->
<head>

    <meta charset="utf-8">
    <title>Incentives</title>
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
                <nav class="w-full flex justify-between items-center mb-4">
                    <ul class="space-y-2 detached-breadcrumb">
                        <li class="text-xs dark:text-white/80">commodities controll</li>
                        <li class="text-xl font-semibold text-slate-800 dark:text-slate-100">Incentives</li>
                    </ul>
                    <div x-data="modals">
                        <div class="flex items-center justify-center">
                            <button type="button" class="btn <?= $isAdd ? '' : 'hidden' ?> bg-purple border border-purple rounded-md text-white transition-all duration-300 hover:bg-purple/[0.85] hover:border-purple/[0.85]" @click="toggle">Add new</button>
                        </div>
                        <form action="incentives.php" method="POST" enctype="multipart/form-data" class="fixed inset-0 bg-black/80 z-[99999] hidden overflow-y-auto dark:bg-dark/90" :class="open && '!block'">
                            <div class="flex items-start justify-center min-h-screen px-4" @click.self="open = false">
                                <div x-show="open" x-transition x-transition.duration.300 class="relative w-full max-w-lg p-0 my-8 overflow-hidden bg-white border rounded-lg border-slate-200 dark:bg-darklight dark:border-darkborder">
                                    <div class="flex items-center justify-between px-5 py-3 bg-white border-b border-slate-200 dark:bg-darklight dark:border-darkborder">
                                        <h5 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Add Products</h5>
                                        <button type="button" class="text-muted hover:text-black dark:hover:text-white" @click="toggle" x-on:click="open = false">
                                            ✖
                                        </button>
                                    </div>
                                    <div class="p-5 space-y-4">
                                        <div class="space-y-1">
                                            <label>Incentive Name</label>
                                            <input type="text" name="name" class="form-input h-14" placeholder="name" required>
                                        </div>
                                        <div class="space-y-1 my-4">
                                            <label>Salary</label>
                                            <input name="salary" type="number" class="form-input h-14" placeholder="Salary" required>
                                        </div>
                                        <div class="space-y-1 my-4">
                                            <label>Referrals</label>
                                            <input name="referrals" type="number" class="form-input h-14" placeholder="Referrals" required>
                                        </div>
                                        <div class="space-y-1 my-4">
                                            <label>Level</label>
                                            <input name="level" type="text" class="form-input h-14" placeholder="Level" required>
                                        </div>
                                        <div class="flex items-center justify-end gap-4">
                                            <button type="button" class="btn text-danger border-danger hover:bg-danger hover:text-white" @click="toggle">Discard</button>
                                            <button type="submit" name="submit" class="btn text-purple border-purple hover:bg-purple hover:text-white">Save</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </nav>
                
                <!-- Start All Card -->
                <div class="flex flex-col gap-4 min-h-[calc(100vh-212px)]">
                    <div class="grid grid-cols-1 gap-4">
                        
                        <div class="card">
                        <h2 class="mb-4 text-base font-semibold capitalize text-slate-800 dark:text-slate-100">Incentives Records</h2>
                        
                            <div class="overflow-auto" x-data="{ items: <?= htmlspecialchars(json_encode($incentives), ENT_QUOTES, 'UTF-8') ?>,
                                            searchTerm: '',
                            currentPage: 1,
                            itemsPerPage: 10,
                            
                            get filteredItems() {
                                if (!this.searchTerm) return this.items;
                                
                                const searchLower = this.searchTerm.toLowerCase();
                                return this.items.filter(item => 
                                    item.name.toLowerCase().includes(searchLower) || 
                                    item.referrals.toLowerCase().includes(searchLower) || 
                                    item.salary.toLowerCase().includes(searchLower) || 
                                    item.date.toLowerCase().includes(searchLower) || 
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
                                            <th>Incentive Name</th>
                                            <th>Salary</th>
                                            <th>Referrals</th>
                                            <th>Level</th>
                                            <th>Status</th>
                                            <th>Date Created</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="item in paginatedItems" :key="item.ID">
                                            <tr x-show="showElement" x-data="{ showElement: true }">
                                                <td x-text="item.ID"></td>
                                                <td>
                                                    <span x-text="item.name" x-on:dblclick="
                                                        item.editing = <?= $isEdit ?>;
                                                        $nextTick(() => $refs.name.focus());
                                                    " x-show="!item.editing"></span>
                                                    <input type="text" class="form-input" x-ref="name" x-model="item.name" x-on:keydown.enter="item.editing = false; updater(item.ID, 'name', item.name);" x-show="item.editing">
                                                </td>
                                                <td>
                                                    <span x-text="item.salary" x-on:dblclick="
                                                        item.editing = <?= $isEdit ?>;
                                                        $nextTick(() => $refs.salary.focus());
                                                    " x-show="!item.editing"></span>
                                                    <input type="number" class="form-input" x-ref="salary" x-model="item.salary" x-on:keydown.enter="item.editing = false; updater(item.ID, 'salary', item.salary);" x-show="item.editing">
                                                </td>
                                                <td>
                                                    <span x-text="item.referrals" x-on:dblclick="
                                                        item.editing = <?= $isEdit ?>;
                                                        $nextTick(() => $refs.referrals.focus());
                                                    " x-show="!item.editing"></span>
                                                    <input type="number" class="form-input" x-ref="referrals" x-model="item.referrals" x-on:keydown.enter="item.editing = false; updater(item.ID, 'referrals', item.referrals);" x-show="item.editing">
                                                </td>
                                                <td>
                                                    <span x-text="item.level" x-on:dblclick="
                                                        item.editing = <?= $isEdit ?>;
                                                        $nextTick(() => $refs.level.focus());
                                                    " x-show="!item.editing"></span>
                                                    <input type="number" class="form-input" x-ref="level" x-model="item.level" x-on:keydown.enter="item.editing = false; updater(item.ID, 'level', item.level);" x-show="item.editing">
                                                </td>
            
                                                <td>
                                                    <span x-text="item.status" x-bind:class="item.status === 'Active' ? 'bg-success/20 text-success' : 'bg-danger/20 text-danger'" x-on:dblclick="
                                                            item.editing = <?= $isEdit ?>;
                                                            $nextTick(() => $refs.status.focus());
                                                        " x-show="!item.editing" class='px-2 rounded py-1'></span>
                                                    <select class="form-select" x-ref="status" x-model="item.status" x-on:keydown.enter="item.editing = false; updater(item.id, 'status', item.status);" x-show="item.editing">
                                                        <option value='Active'>Active</option>
                                                        <option value='Inactive'>Inactive</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <span x-text="item.date" x-on:dblclick="
                                                        item.editing = <?= $isEdit ?>;
                                                        $nextTick(() => $refs.date.focus());
                                                    " x-show="!item.editing"></span>
                                                    <input type="text" class="form-input" x-ref="date" x-model="item.date" x-on:keydown.enter="item.editing = false" x-show="item.editing">
                                                </td>
                                                <td>
                                                    <button class="text-danger <?= $isEdit ? '' : 'hidden' ?> ltr:ml-2 rtl:mr-2" x-on:click="showElement = false; deleteItem('incentives', item.ID);">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="inline-block w-5 h-5">
                                                            <path fill="currentColor" d="M17 6H22V8H20V21C20 21.5523 19.5523 22 19 22H5C4.44772 22 4 21.5523 4 21V8H2V6H7V3C7 2.44772 7.44772 2 8 2H16C16.5523 2 17 2.44772 17 3V6ZM18 8H6V20H18V8ZM9 11H11V17H9V11ZM13 11H15V17H13V11ZM9 4V6H15V4H9Z"></path>
                                                        </svg>
                                                    </button>
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
<script>
function updater(id, field, value) {
    fetch('actions/update_incentives.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, field: field, value: value })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('User updated successfully');
        } else {
            console.error('Update failed:', data.message);
        }
    })
    .catch(error => console.error('Error:', error));
}
function deleteItem(table, id) {
    fetch('actions/delete_record.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, table })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Record deleted successfully');
        } else {
            console.error('Deletion failed:', data.message);
        }
    })
    .catch(error => console.error('Error:', error));
}
</script>

</body>


<!-- Mirrored from srbthemes.kcubeinfotech.com/sliced-pro/html/blank.html by HTTrack Website Copier/3.x [XR&CO'2014], Mon, 10 Mar 2025 01:28:49 GMT -->
</html>