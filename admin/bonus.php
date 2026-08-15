<?php 
include 'includes/main.php';
$error = '';
$msg = '';
if (isset($_POST['submit'])) {
    $name= $_POST['name'];
    $target = $_POST['target'];
    $reward = $_POST['reward'];
    $type = $_POST['type'];
    $target_type = $_POST['target_type'];
    $insert = $query->insert('bonus', ['name' => $name, 'target' => $target, 'reward' => $reward, 'reward_type' => $type, 'type' => $target_type]);
}
?>
<!DOCTYPE html>
<html lang="en"  :dir="$store.app.direction" x-data="{ direction: $store.app.direction || 'ltr' }" x-bind:dir="direction" class="group/item" :data-mode="$store.app.mode" :data-sidebar="$store.app.sidebarMode">


<!-- Mirrored from srbthemes.kcubeinfotech.com/sliced-pro/html/blank.html by HTTrack Website Copier/3.x [XR&CO'2014], Mon, 10 Mar 2025 01:28:49 GMT -->
<head>

    <meta charset="utf-8">
    <title>Bonus</title>
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
                        <li class="text-xl font-semibold text-slate-800 dark:text-slate-100">Bonus</li>
                    </ul>
                    <div x-data="modals ">
                        <div class="flex items-center justify-center">
                            <button type="button" class="btn <?= $isAdd ? '' : 'hidden' ?> bg-purple border border-purple rounded-md text-white transition-all duration-300 hover:bg-purple/[0.85] hover:border-purple/[0.85]" @click="toggle">Add new</button>
                        </div>
                        <div class="fixed inset-0 bg-black/80 z-[99999] hidden overflow-y-auto dark:bg-dark/90" :class="open && '!block'">
                            <div class="flex items-start justify-center min-h-screen px-4" @click.self="open = false">
                                <div x-show="open" x-transition x-transition.duration.300 class="relative w-full max-w-lg p-0 my-8 overflow-hidden bg-white border rounded-lg border-slate-200 dark:bg-darklight dark:border-darkborder">
                                    <div class="flex items-center justify-between px-5 py-3 bg-white border-b border-slate-200 dark:bg-darklight dark:border-darkborder">
                                        <h5 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Add Products</h5>
                                        <button type="button" class="text-muted hover:text-black dark:hover:text-white" @click="toggle" x-on:click="open = false">
                                            <svg class="w-5 h-5" width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M24.2929 6.29289L6.29289 24.2929C6.10536 24.4804 6 24.7348 6 25C6 25.2652 6.10536 25.5196 6.29289 25.7071C6.48043 25.8946 6.73478 26 7 26C7.26522 26 7.51957 25.8946 7.70711 25.7071L25.7071 7.70711C25.8946 7.51957 26 7.26522 26 7C26 6.73478 25.8946 6.48043 25.7071 6.29289C25.5196 6.10536 25.2652 6 25 6C24.7348 6 24.4804 6.10536 24.2929 6.29289Z" fill="currentcolor" />
                                                <path d="M7.70711 6.29289C7.51957 6.10536 7.26522 6 7 6C6.73478 6 6.48043 6.10536 6.29289 6.29289C6.10536 6.48043 6 6.73478 6 7C6 7.26522 6.10536 7.51957 6.29289 7.70711L24.2929 25.7071C24.4804 25.8946 24.7348 26 25 26C25.2652 26 25.5196 25.8946 25.7071 25.7071C25.8946 25.5196 26 25.2652 26 25C26 24.7348 25.8946 24.4804 25.7071 24.2929L7.70711 6.29289Z" fill="currentcolor" />
                                            </svg>
                                        </button>
                                    </div>
                                    <form action='bonus' method='post' class="p-5 space-y-4">
                                        <div class="text-black dark:text-muted">
                                            <div class="space-y-1">
                                                <label>Bonus Title</label>
                                                <input type="text" name='name' class="form-input h-14" placeholder="name" required>
                                            </div>
                                            <div class="space-y-1 my-4">
                                                <label>Bonus Target</label>
                                                <input type="number" name='target' class="form-input h-14" placeholder="target" required>
                                            </div>
                                            <div class="space-y-1 my-4">
                                                <label>Bonus Reward</label>
                                                <input type="number" name='reward' class="form-input h-14" placeholder="reward" required>
                                            </div>
                                            <div class="space-y-1 my-4">
                                                <label>Reward Type</label>
                                                <select name="type" id="" class="form-input h-14">
                                                    <option value="products">Products</option>
                                                    <option value="money">Money</option>
                                                </select>
                                            </div>
                                            <div class="space-y-1 my-4">
                                                <label>Target Type</label>
                                                <select name="target_type" id="" class="form-input h-14">
                                                    <option value="users">Users</option>
                                                    <option value="actives">Active</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-end gap-4">
                                            <button type="button" class="transition-all duration-300 border rounded-md btn text-danger border-danger hover:bg-danger hover:text-white" @click="toggle">Discard</button>
                                            <button name='submit' class="transition-all duration-300 border rounded-md btn text-purple border-purple hover:bg-purple hover:text-white">Save</button>
                                        </div>
                                      </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </nav>
                
                <!-- Start All Card -->
                <div class="flex flex-col gap-4 min-h-[calc(100vh-212px)]">
                    <div class="grid grid-cols-1 gap-4">
                        
                        <div class="card">
                        <h2 class="mb-4 text-base font-semibold capitalize text-slate-800 dark:text-slate-100">Bonus Records</h2>
                        
  <?php
  /*
   * Two columns here read one DB column and write another, which is how the
   * original inline editors were wired: the "Target Type" cell shows bonus.type
   * and the "Bonus Type" cell shows bonus.reward_type. The mapping is preserved
   * rather than corrected, so nothing about the data changes.
   */
  data_table([
      'id'         => 'bonus',
      'label'      => 'bonus',
      'rows'       => $bonus_records,
      'key'        => 'id',
      'update'     => 'actions/update_bonus.php',
      'resource'   => 'bonus',
      'can_edit'   => $isEdit,
      'can_delete' => $isEdit,
      'search'     => ['name', 'type', 'target_type', 'status'],
      'empty'      => 'No bonuses configured yet.',
      'columns'    => [
          ['label' => '#',            'field' => 'id'],
          ['label' => 'Bonus Name',   'field' => 'name',        'edit' => 'name'],
          ['label' => 'Bonus Target', 'field' => 'target',      'edit' => 'target',      'type' => 'number', 'numeric' => true],
          ['label' => 'Target Type',  'field' => 'target_type', 'edit' => 'type'],
          ['label' => 'Bonus Type',   'field' => 'type',        'edit' => 'reward_type'],
          ['label' => 'Bonus Income', 'field' => 'income',      'edit' => 'reward',      'type' => 'number', 'numeric' => true],
          ['label' => 'Status',       'field' => 'status',      'edit' => 'status',
           'type'  => 'select',       'options' => ['Active' => 'Active', 'Inactive' => 'Inactive'],
           'badge' => ['Active' => 'success', '*' => 'danger']],
          ['label' => 'Date Created', 'field' => 'time',
           'hint'  => 'Recorded when the bonus was created.'],
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