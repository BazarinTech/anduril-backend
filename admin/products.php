<?php
include 'includes/main.php';
require_once __DIR__ . '/../lib/storage.php';
require_once __DIR__ . '/includes/field-rules.php';
require_once __DIR__ . '/includes/product-image.php';

/**
 * Product images no longer go to `uploads/` on this machine's disk. They go
 * through lib/storage.php, which writes to the S3-compatible bucket when one
 * is configured and to disk otherwise. See that file for why.
 *
 * The database still stores the bare filename, exactly as before -- only where
 * the bytes live and how a URL is built have changed.
 */

$error = '';
$msg = '';

$form = ['name' => '', 'max' => '', 'return' => '', 'description' => '', 'riskLevel' => '', 'duration' => '', 'tier' => '', 'limit' => ''];

if (isset($_POST['submit'])) {
    foreach (array_keys($form) as $field) {
        $form[$field] = admin_form_input($field);
    }

    if (!$isAdd) {
        $error = "Your admin account does not have the 'add' permission.";
        goto render_page;
    }

    /**
     * Validated before the image is touched. The columns are narrow (tier is
     * 20 characters, prices 10) and MySQL is in strict mode, so a bad value
     * used to throw out of the INSERT with a 500 -- after the image had
     * already been resized and uploaded to the bucket, leaving it orphaned.
     */
    [$values, $problem] = admin_validate_form($query, 'products', [
        'name'        => $form['name'],
        'max'         => $form['max'],
        'min'         => '0',
        'returns'     => $form['return'],
        'description' => $form['description'],
        'riskLevel'   => $form['riskLevel'],
        'duration'    => $form['duration'],
        'tier'        => $form['tier'],
        'order_limit' => $form['limit'],
    ]);

    if ($problem !== null) {
        $error = $problem;
        goto render_page;
    }

    $name        = $values['name'];
    $max         = $values['max'];
    $min         = $values['min'];
    $return      = $values['returns'];
    $description = $values['description'];
    $riskLevel   = $values['riskLevel'];
    $duration    = $values['duration'];
    $tier        = $values['tier'];
    $limit       = $values['order_limit'];

    /**
     * The image, through the shared pipeline the Edit modal also uses
     * (admin/includes/product-image.php). This file previously carried ~120
     * lines of inline finfo/GD handling, duplicated the moment a second place
     * needed to accept an image.
     */
    $stored = store_uploaded_product_image($_FILES['image'] ?? null);

    if (!$stored['ok']) {
        $error = $stored['error'];
        goto render_page;
    }

    try {
        $query->insert('products', [
            'name'        => $name,
            'max'         => $max,
            'min'         => $min,
            'description' => $description,
            'returns'     => $return,
            'riskLevel'   => $riskLevel,
            'duration'    => $duration,
            'tier'        => $tier,
            'image'       => $stored['name'],
            'order_limit' => $limit,
        ]);

        $msg = 'Product created successfully.';
        $form = array_map(static fn () => '', $form);
    } catch (\Throwable $e) {
        error_log('[admin/products] insert failed: ' . $e->getMessage());
        $error = 'Could not save the product. Please try again.';
    }
}

render_page:
?>

<!DOCTYPE html>
<html lang="en"  :dir="$store.app.direction" x-data="{ direction: $store.app.direction || 'ltr' }" x-bind:dir="direction" class="group/item" :data-mode="$store.app.mode" :data-sidebar="$store.app.sidebarMode">


<!-- Mirrored from srbthemes.kcubeinfotech.com/sliced-pro/html/blank.html by HTTrack Website Copier/3.x [XR&CO'2014], Mon, 10 Mar 2025 01:28:49 GMT -->
<head>

    <meta charset="utf-8">
    <title>Products</title>
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
                        <li class="text-xl font-semibold text-slate-800 dark:text-slate-100">Products</li>
                    </ul>
                    <div x-data="modals(<?= $error !== '' && isset($_POST['submit']) && $isAdd ? 'true' : 'false' ?>)">
                        <div class="flex items-center justify-center">
                            <button type="button" class="btn <?= $isAdd ? '' : 'hidden' ?> bg-purple border border-purple rounded-md text-white transition-all duration-300 hover:bg-purple/[0.85] hover:border-purple/[0.85]" @click="toggle">Add new</button>
                        </div>
                        <form action="products" method="POST" enctype="multipart/form-data" class="fixed inset-0 bg-black/80 z-[99999] hidden overflow-y-auto dark:bg-dark/90" :class="open && '!block'">
                            <div class="flex items-start justify-center min-h-screen px-4" @click.self="open = false">
                                <div x-show="open" x-transition x-transition.duration.300 class="relative w-full max-w-lg p-0 my-8 overflow-hidden bg-white border rounded-lg border-slate-200 dark:bg-darklight dark:border-darkborder">
                                    <div class="flex items-center justify-between px-5 py-3 bg-white border-b border-slate-200 dark:bg-darklight dark:border-darkborder">
                                        <h5 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Add Product</h5>
                                        <button type="button" class="text-muted hover:text-black dark:hover:text-white" @click="toggle" x-on:click="open = false">
                                            ✖
                                        </button>
                                    </div>
                                    <div class="p-5 space-y-4">
                                        <?php if ($error !== '' && isset($_POST['submit']) && $isAdd): ?>
                                            <p class="bg-danger/20 text-danger text-center rounded-lg py-2 px-2"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?> Choose the image again before saving.</p>
                                        <?php endif; ?>
                                        <div class="space-y-1">
                                            <label>Product Name</label>
                                            <input type="text" name="name" class="form-input h-14" placeholder="name" maxlength="255" value="<?= htmlspecialchars($form['name'], ENT_QUOTES, 'UTF-8') ?>" required>
                                        </div>
                                        <div class="space-y-1 my-4">
                                            <label>Product Price</label>
                                            <input name="max" type="number" min="0" step="0.01" class="form-input h-14" placeholder="Price" value="<?= htmlspecialchars($form['max'], ENT_QUOTES, 'UTF-8') ?>" required>
                                        </div>
                                        <div class="space-y-1 my-4">
                                            <label>Risk Level</label>
                                            <input name="riskLevel" type="number" min="0" max="100" step="1" class="form-input h-14" placeholder="Risk Level" value="<?= htmlspecialchars($form['riskLevel'], ENT_QUOTES, 'UTF-8') ?>" required>
                                        </div>
                                        <div class="space-y-1 my-4">
                                            <label>Product Return(%)</label>
                                            <input name="return" type="number" min="0" step="0.01" class="form-input h-14" placeholder="Return" value="<?= htmlspecialchars($form['return'], ENT_QUOTES, 'UTF-8') ?>" required>
                                        </div>
                                        <div class="space-y-1 my-4">
                                            <label>Duration(days)</label>
                                            <input name="duration" type="number" min="1" step="1" class="form-input h-14" placeholder="Duration" value="<?= htmlspecialchars($form['duration'], ENT_QUOTES, 'UTF-8') ?>" required>
                                        </div>
                                        <div class="space-y-1 my-4">
                                            <label>Tier</label>
                                            <input name="tier" type="text" class="form-input h-14" placeholder="Tier" maxlength="20" value="<?= htmlspecialchars($form['tier'], ENT_QUOTES, 'UTF-8') ?>" required>
                                        </div>
                                        <div class="space-y-1 my-4">
                                            <label>Order Limit</label>
                                            <input name="limit" type="number" min="0" step="1" class="form-input h-14" placeholder="Order limit" value="<?= htmlspecialchars($form['limit'], ENT_QUOTES, 'UTF-8') ?>" required>
                                        </div>
                                        <div class="space-y-1 my-4">
                                            <label>Product image</label>
                                            <input type="file" name="image" accept="image/*" required>
                                        </div>
                                        <div class="space-y-1 my-4">
                                            <label>Description</label>
                                            <textarea name="description" class="form-input h-14" placeholder="Describe here..." maxlength="255" required><?= htmlspecialchars($form['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
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

                        <?php if ($msg || $error): ?>
                            <div class="card">
                                <p class="<?= $msg ? 'bg-success/20 text-success' : 'bg-danger/20 text-danger' ?> text-center rounded-lg py-2 px-2">
                                    <?= htmlspecialchars($msg ?: $error, ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <?php
                        /*
                         * Where images are going right now. An upload that
                         * silently lands on a container's disk and disappears
                         * on the next deploy is the failure this is here to
                         * make visible.
                         */
                        $storage_state = storage_health();
                        ?>
                        <?php if (!$storage_state['ok']): ?>
                            <div class="card">
                                <p class="bg-warning/20 text-warning rounded-lg py-2 px-3 text-sm">
                                    <strong>Image storage problem.</strong>
                                    <?= htmlspecialchars($storage_state['detail'], ENT_QUOTES, 'UTF-8') ?>.
                                    Uploads will fail until this is fixed.
                                </p>
                            </div>
                        <?php elseif ($storage_state['driver'] === 'local'): ?>
                            <div class="card">
                                <p class="bg-info/20 text-info rounded-lg py-2 px-3 text-sm">
                                    Images are being written to this server's disk. That is fine for
                                    development, but on a container host they are lost at the next deploy
                                    &mdash; configure the bucket before going live.
                                </p>
                            </div>
                        <?php endif; ?>

                        <div class="card">
                        <h2 class="mb-4 text-base font-semibold capitalize text-slate-800 dark:text-slate-100">Products Records</h2>
                        
                            <?php
                            data_table([
                                'id'         => 'products',
                                'label'      => 'product',
                                'rows'       => $products_records,
                                'key'        => 'id',
                                'update'     => 'actions/update_products.php',
                                'resource'   => 'products',
                                'can_edit'   => $isEdit,
                                'can_delete' => $isEdit,
                                'search'     => ['name', 'tier', 'status'],
                                'empty'      => 'No products configured yet.',
                                'image'      => [
                                    'field'  => 'image_url',
                                    'upload' => 'actions/update_product_image.php',
                                    'label'  => 'Product image',
                                    'hint'   => 'PNG, JPG, GIF, WEBP or SVG, under 1000KB. Resized to 500x500 and shown to users in the app.',
                                ],
                                'columns'    => [
                                    ['label' => '#',     'field' => 'id'],
                                    ['label' => 'Image', 'field' => 'image_url', 'type' => 'avatar'],
                                    ['label' => 'Product Name', 'field' => 'name', 'edit' => 'name'],
                                    ['label' => 'Price', 'field' => 'max', 'edit' => 'max', 'type' => 'number', 'numeric' => true,
                                     'hint'  => 'Stored in products.max -- the amount a user pays for one unit.'],
                                    ['label' => 'Return(Kes)', 'field' => 'return', 'edit' => 'returns', 'type' => 'number', 'numeric' => true,
                                     'hint'  => 'Paid out per roll.'],
                                    ['label' => 'Tier',  'field' => 'tier', 'edit' => 'tier'],
                                    ['label' => 'Expiry (days)', 'field' => 'duration', 'edit' => 'duration', 'type' => 'number', 'numeric' => true,
                                     'hint'  => 'How many days a new order runs for. Orders already placed keep the duration they were bought with.'],
                                    ['label' => 'Order Limit', 'field' => 'order_limit', 'edit' => 'order_limit', 'type' => 'number', 'numeric' => true],
                                    ['label' => 'Status', 'field' => 'status', 'edit' => 'status',
                                     'type'  => 'select', 'options' => ['Active' => 'Active', 'Inactive' => 'Inactive'],
                                     'badge' => ['Active' => 'success', '*' => 'danger']],
                                    ['label' => 'Date Created', 'field' => 'time',
                                     'hint'  => 'Recorded when the product was created.'],
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