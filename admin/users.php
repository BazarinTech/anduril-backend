<?php
require_once __DIR__ . '/includes/main.php';

?>

<?php
echo "<!-- users_records type: " . gettype($users_records) . " -->";
if (is_array($users_records)) {
    echo "<!-- users_records count: " . count($users_records) . " -->";
} elseif (is_object($users_records)) {
    echo "<!-- users_records class: " . get_class($users_records) . " -->";
}
?>


<!DOCTYPE html>
<html lang="en"  :dir="$store.app.direction" x-data="{ direction: $store.app.direction || 'ltr' }" x-bind:dir="direction" class="group/item" :data-mode="$store.app.mode" :data-sidebar="$store.app.sidebarMode">


<!-- Mirrored from srbthemes.kcubeinfotech.com/sliced-pro/html/blank.html by HTTrack Website Copier/3.x [XR&CO'2014], Mon, 10 Mar 2025 01:28:49 GMT -->
<head>

    <meta charset="utf-8">
    <title>Users</title>
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
                        <li class="text-xs dark:text-white/80">generals</li>
                        <li class="text-xl font-semibold text-slate-800 dark:text-slate-100">Users</li>
                    </ul>
                </nav>
                <!-- Start All Card -->
                <div class="flex flex-col gap-4 min-h-[calc(100vh-212px)]">
                    <div class="grid grid-cols-1 gap-4">
                        <div class="card">
                        <h2 class="mb-4 text-base font-semibold capitalize text-slate-800 dark:text-slate-100">Users Records</h2>
                        <?php
$usersJson = json_encode(
    $users_records ?? [],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
);

if ($usersJson === false) {
    echo "<!-- json_encode error: " . json_last_error_msg() . " -->";
    $usersJson = '[]';
}
?>

                        
                        <div class="overflow-auto"
     data-users='<?= htmlspecialchars($usersJson, ENT_QUOTES, "UTF-8") ?>'
     x-data="{
        items: [],
        searchTerm: '',
        currentPage: 1,
        itemsPerPage: 50,

        init() {
          console.log('dataset.users raw:', this.$el.dataset.users);
          this.items = JSON.parse(this.$el.dataset.users || '[]');
          console.log('parsed items len:', this.items.length);
        },

        get filteredItems() {
          if (!this.searchTerm) return this.items;
          const s = this.searchTerm.toLowerCase();
          return this.items.filter(item =>
            (item.email ?? '').toLowerCase().includes(s) ||
            (item.phone ?? '').toLowerCase().includes(s) ||
            (item.date ?? '').toLowerCase().includes(s) ||
            (item.upline ?? '').toLowerCase().includes(s) ||
            (item.status ?? '').toLowerCase().includes(s)
          );
        },

        get totalPages() {
          return Math.ceil(this.filteredItems.length / this.itemsPerPage) || 1;
        },

        get paginatedItems() {
          const start = (this.currentPage - 1) * this.itemsPerPage;
          return this.filteredItems.slice(start, start + this.itemsPerPage);
        },

        nextPage() { if (this.currentPage < this.totalPages) this.currentPage++; },
        prevPage() { if (this.currentPage > 1) this.currentPage--; },
        goToPage(page) { if (page >= 1 && page <= this.totalPages) this.currentPage = page; },
        goToFirstPage() { this.currentPage = 1; },
        goToLastPage() { this.currentPage = this.totalPages; }
     }">

                            <input 
                                type="text" 
                                x-model="searchTerm" 
                                placeholder="Search..." 
                                class="form-input w-full md:w-64"
                                />
                                <caption class="caption-top">
                                    <span class="text-muted">Double Click To Edit Table.</span>
                                </caption>
                                <div class="text-xs mt-2">
  Loaded: <span x-text="Array.isArray(items) ? items.length : 'not-array'"></span> users
  | Showing: <span x-text="paginatedItems.length"></span>
</div>

                    <?php
                    echo "<!-- users_records type: " . gettype($users_records) . " -->\n";
                    
                    if (is_array($users_records)) {
                    echo "<!-- users_records count: " . count($users_records) . " -->\n";
                    if (count($users_records) > 0) {
                    echo "<!-- first row keys: " . implode(',', array_keys($users_records[0])) . " -->\n";
                    }
                    } else {
                    echo "<!-- users_records not array -->\n";
                    }
                    
                    echo "<!-- usersJson len: " . strlen($usersJson) . " -->\n";
                    echo "<!-- usersJson preview: " . substr($usersJson, 0, 120) . " -->\n";
                    ?>


                                <table class="min-w-[640px] w-full mt-4 table-hover">
                                    <thead>
                                        <tr class="ltr:text-left rtl:text-right">
                                            <th>#</th>
                                            <th>Name</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Upline</th>
                                            <th>Date Joined</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(item, idx) in paginatedItems" :key="item.id ?? idx">
                                            <tr x-data="{ showElement: true }" x-show="showElement">
                                                <td x-text="item.id"></td>
                                                <td>
                                                    <span x-text="item.name" x-on:dblclick="
                                                        item.editing = <?= $isEdit ?>;
                                                        $nextTick(() => $refs.name.focus());
                                                    " x-show="!item.editing"></span>
                                                    <input type="text" class="form-input" x-ref="name" x-model="item.name" x-on:keydown.enter="item.editing = false" x-show="item.editing">
                                                </td>
                                                <td>
                                                    <span x-text="item.username" x-on:dblclick="
                                                        item.editing = <?= $isEdit ?>;
                                                        $nextTick(() => $refs.username.focus());
                                                    " x-show="!item.editing"></span>
                                                    <input type="text" class="form-input" x-ref="username" x-model="item.username" x-on:keydown.enter="item.editing = false" x-show="item.editing">
                                                </td>
                                                <td>
                                                    <span x-text="item.email" x-on:dblclick="
                                                        item.editing = <?= $isEdit ?>;
                                                        $nextTick(() => $refs.email.focus());
                                                    " x-show="!item.editing"></span>
                                                    <input type="text" class="form-input" x-ref="email" x-model="item.email" x-on:keydown.enter="item.editing = false" x-show="item.editing">
                                                </td>
                                                <td>
                                                    <span x-text="item.phone" x-on:dblclick="
                                                        item.editing = <?= $isEdit ?>;
                                                        $nextTick(() => $refs.phone.focus());
                                                    " x-show="!item.editing"></span>
                                                    <input type="text" class="form-input" x-ref="phone" x-model="item.phone" x-on:keydown.enter="item.editing = false; updateUser(item.id, 'phone', item.phone);" x-show="item.editing">
                                                </td>
                                                <td>
                                                    <span x-text="item.roles" x-bind:class="item.roles === 'agent' ? 'bg-success/20 text-success' : 'bg-info/20 text-info'" x-on:dblclick="
                                                            item.editing = <?= $isEdit ?>;
                                                            $nextTick(() => $refs.roles.focus());
                                                        " x-show="!item.editing" class='px-2 rounded py-1'></span>
                                                    <select class="form-select" x-ref="roles" x-model="item.roles" x-on:keydown.enter="item.editing = false; 
                                                    updateUser(item.id, 'role', item.roles);" x-show="item.editing">
                                                        <option value='user'>User</option>
                                                        <option value='agent'>Agent</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <span x-text="item.status" x-bind:class="item.status === 'Active' ? 'bg-success/20 text-success' : 'bg-danger/20 text-danger'" x-on:dblclick="
                                                            item.editing = <?= $isEdit ?>;
                                                            $nextTick(() => $refs.status.focus());
                                                        " x-show="!item.editing" class='px-2 rounded py-1'></span>
                                                    <select class="form-select" x-ref="status" x-model="item.status" x-on:keydown.enter="item.editing = false; 
                                                    updateUser(item.id, 'status', item.status);" x-show="item.editing">
                                                        <option value='Active'>Activate</option>
                                                        <option value='Inactive'>Deactivate</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <span x-text="item.upline" x-on:dblclick="
                                                        item.editing = <?= $isEdit ?>;
                                                        $nextTick(() => $refs.upline.focus());
                                                    " x-show="!item.editing"></span>
                                                    <input type="text" class="form-input" x-ref="upline" x-model="item.upline" x-on:keydown.enter="item.editing = false" x-show="item.editing">
                                                </td>
                                                <td>
                                                    <span x-text="item.date" x-on:dblclick="
                                                        item.editing = <?= $isEdit ?>;
                                                        $nextTick(() => $refs.date.focus());
                                                    " x-show="!item.editing"></span>
                                                    <input type="text" class="form-input" x-ref="date" x-model="item.date" x-on:keydown.enter="item.editing = false" x-show="item.editing">
                                                </td>
                                                <?php /* Phase 2.2 -- the password column is gone. Passwords are
                                                         now one-way hashes, so there is nothing readable to show,
                                                         and shipping the digest to the browser would only hand an
                                                         attacker something to crack offline. Resetting a user's
                                                         password is a reset flow, not an inline edit. */ ?>
                                                <td>
                                                    <button class="text-danger <?= $isEdit ? '' : 'hidden' ?> ltr:ml-2 rtl:mr-2" x-on:click="showElement = false; deleteItem('users', item.id);">
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
function updateUser(userId, field, value) {
    fetch('actions/update_user.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: userId, field: field, value: value })
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