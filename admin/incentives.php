<?php 
include 'includes/main.php';
require_once __DIR__ . '/includes/field-rules.php';

$error = '';
$msg = '';

// What the admin typed, so a rejected form comes back filled in rather than
// empty.
$form = ['name' => '', 'salary' => '', 'referrals' => '', 'level' => ''];

if (isset($_POST['submit'])) {
    foreach (array_keys($form) as $field) {
        $form[$field] = admin_form_input($field);
    }

    // The Add button is hidden without 'add', but the form handler never
    // checked it, so the permission was cosmetic.
    if (!$isAdd) {
        $error = "Your admin account does not have the 'add' permission.";
    } else {
        /**
         * Validated before the INSERT. The columns are narrow and MySQL is in
         * strict mode, so a name or level longer than its column used to throw
         * straight out of the insert and the page answered with a 500 -- which
         * is why a short first incentive saved and a longer second one did not.
         */
        [$values, $problem] = admin_validate_form($query, 'incentives', $form);
        $error = (string) $problem;

        if ($error === '') {
            try {
                $query->insert('incentives', $values);

                /**
                 * Redirect rather than render. main.php loaded $incentives before
                 * this insert ran, so rendering now would leave the new record out
                 * of the table -- and refreshing a POST response submits the form
                 * again, creating a duplicate.
                 */
                header('Location: incentives?added=1');
                exit;
            } catch (\Throwable $e) {
                error_log('[admin/incentives] insert failed: ' . $e->getMessage());
                $error = 'Could not save the incentive. Please try again.';
            }
        }
    }
}

if ($error === '' && isset($_GET['added'])) {
    $msg = 'Incentive added.';
}

$esc = function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
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
                    <div x-data="modals(<?= $error !== '' && isset($_POST['submit']) && $isAdd ? 'true' : 'false' ?>)">
                        <div class="flex items-center justify-center">
                            <button type="button" class="btn <?= $isAdd ? '' : 'hidden' ?> bg-purple border border-purple rounded-md text-white transition-all duration-300 hover:bg-purple/[0.85] hover:border-purple/[0.85]" @click="toggle">Add new</button>
                        </div>
                        <form action="incentives" method="POST" class="fixed inset-0 bg-black/80 z-[99999] hidden overflow-y-auto dark:bg-dark/90" :class="open && '!block'">
                            <div class="flex items-start justify-center min-h-screen px-4" @click.self="open = false">
                                <div x-show="open" x-transition x-transition.duration.300 class="relative w-full max-w-lg p-0 my-8 overflow-hidden bg-white border rounded-lg border-slate-200 dark:bg-darklight dark:border-darkborder">
                                    <div class="flex items-center justify-between px-5 py-3 bg-white border-b border-slate-200 dark:bg-darklight dark:border-darkborder">
                                        <h5 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Add Incentive</h5>
                                        <button type="button" class="text-muted hover:text-black dark:hover:text-white" @click="toggle" x-on:click="open = false">
                                            ✖
                                        </button>
                                    </div>
                                    <div class="p-5 space-y-4">
                                        <?php if ($error !== '' && isset($_POST['submit'])): ?>
                                            <p class="bg-danger/20 text-danger text-center rounded-lg py-2 px-2"><?= $esc($error) ?></p>
                                        <?php endif; ?>
                                        <div class="space-y-1">
                                            <label>Incentive Name</label>
                                            <input type="text" name="name" class="form-input h-14" placeholder="e.g. Silver Ambassador" maxlength="100" value="<?= $esc($form['name']) ?>" required>
                                        </div>
                                        <div class="space-y-1 my-4">
                                            <label>Salary</label>
                                            <input name="salary" type="number" min="0" step="0.01" class="form-input h-14" placeholder="Salary" value="<?= $esc($form['salary']) ?>" required>
                                        </div>
                                        <div class="space-y-1 my-4">
                                            <label>Referrals</label>
                                            <input name="referrals" type="number" min="0" max="1000000" step="1" class="form-input h-14" placeholder="Referrals" value="<?= $esc($form['referrals']) ?>" required>
                                        </div>
                                        <div class="space-y-1 my-4">
                                            <label>Level</label>
                                            <input name="level" type="text" class="form-input h-14" placeholder="e.g. lvl2" maxlength="50" value="<?= $esc($form['level']) ?>" required>
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
                        <?php if ($msg || ($error && !$isAdd)): ?>
                            <div class="card">
                                <p class="<?= $msg ? 'bg-success/20 text-success' : 'bg-danger/20 text-danger' ?> text-center rounded-lg py-2 px-2"><?= $esc($msg ?: $error) ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="card">
                        <h2 class="mb-4 text-base font-semibold capitalize text-slate-800 dark:text-slate-100">Incentives Records</h2>
                        
                            <?php
                            data_table([
                                'id'         => 'incentives',
                                'label'      => 'incentive',
                                'rows'       => $incentives,
                                'key'        => 'ID',
                                'update'     => 'actions/update_incentives.php',
                                'resource'   => 'incentives',
                                'can_edit'   => $isEdit,
                                'can_delete' => $isEdit,
                                'search'     => ['name', 'level', 'status'],
                                'empty'      => 'No incentives configured yet.',
                                'columns'    => [
                                    ['label' => '#',         'field' => 'ID'],
                                    ['label' => 'Incentive Name', 'field' => 'name',      'edit' => 'name'],
                                    ['label' => 'Salary',    'field' => 'salary',    'edit' => 'salary',    'type' => 'number', 'numeric' => true],
                                    ['label' => 'Referrals', 'field' => 'referrals', 'edit' => 'referrals', 'type' => 'number', 'numeric' => true,
                                     'hint'  => 'Referral count a user must reach before they can apply.'],
                                    ['label' => 'Level',     'field' => 'level',     'edit' => 'level'],
                                    ['label' => 'Status',    'field' => 'status',    'edit' => 'status',
                                     'type'  => 'select',    'options' => ['Active' => 'Active', 'Inactive' => 'Inactive'],
                                     'badge' => ['Active' => 'success', '*' => 'danger']],
                                    ['label' => 'Date Created', 'field' => 'date',
                                     'hint'  => 'Recorded when the incentive was created.'],
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