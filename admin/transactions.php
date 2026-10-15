<?php
include 'includes/main.php';
require_once __DIR__ . '/includes/transaction-search.php';

/**
 * The table below shows the most recent ADMIN_TABLE_LIMIT transactions, which
 * is what an admin needs most days. When they need an older one -- "the
 * customer says they deposited in March" -- this searches the whole table
 * instead, using only indexed columns and a capped, keyset-paged query. See
 * admin/includes/transaction-search.php for why each lookup is cheap.
 */
$tx_filters = [
    'q'      => $_GET['q'] ?? '',
    'type'   => $_GET['tx_type'] ?? '',
    'status' => $_GET['tx_status'] ?? '',
    'from'   => $_GET['from'] ?? '',
    'to'     => $_GET['to'] ?? '',
    'before' => $_GET['before'] ?? 0,
];

$tx_searching = transaction_search_active($tx_filters) || (int) $tx_filters['before'] > 0;
$tx_result    = null;

if ($tx_searching) {
    try {
        $tx_result = transaction_search($pdo, $tx_filters);
        $transactions_records = $tx_result['rows'];
    } catch (\Throwable $e) {
        error_log('[transactions] search failed: ' . $e->getMessage());
        $tx_result = ['rows' => [], 'has_more' => false, 'next_cursor' => null, 'criteria' => '', 'error' => 'The search could not be run. Please try again.'];
        $transactions_records = [];
    }
}

/** Rebuild the current query string with one value changed. */
$tx_url = function (array $changes = []) {
    $params = array_filter(array_merge([
        'q'         => $_GET['q'] ?? '',
        'tx_type'   => $_GET['tx_type'] ?? '',
        'tx_status' => $_GET['tx_status'] ?? '',
        'from'      => $_GET['from'] ?? '',
        'to'        => $_GET['to'] ?? '',
    ], $changes), static function ($v) { return $v !== '' && $v !== null; });

    return 'transactions' . ($params ? '?' . http_build_query($params) : '');
};

$esc = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
?>
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
                            <h2 class="mb-1 text-base font-semibold capitalize text-slate-800 dark:text-slate-100">Find a transaction</h2>
                            <p class="mb-4 text-sm text-muted">
                                Searches every transaction on record, not only the recent ones loaded below.
                                Look one up by M-Pesa/provider reference, our own reference, email, phone,
                                transaction ID or user ID &mdash; and narrow it by type, status or date.
                            </p>
                            <form method="get" action="transactions" class="dt-filters">
                                <input type="search" name="q" value="<?= $esc($_GET['q'] ?? '') ?>"
                                       placeholder="Reference, email, phone or ID" aria-label="Search transactions">
                                <select name="tx_type" aria-label="Type">
                                    <option value="">Any type</option>
                                    <?php foreach (['Deposit','Withdraw','Transfer','Product Income','Commission','Bonus','Incentive'] as $t): ?>
                                        <option value="<?= $esc($t) ?>" <?= ($_GET['tx_type'] ?? '') === $t ? 'selected' : '' ?>><?= $esc($t) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="tx_status" aria-label="Status">
                                    <option value="">Any status</option>
                                    <?php foreach (['Success','Completed','Pending','Processing','Failed','Declined'] as $st): ?>
                                        <option value="<?= $esc($st) ?>" <?= ($_GET['tx_status'] ?? '') === $st ? 'selected' : '' ?>><?= $esc($st) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label class="dt-filters__date">From <input type="date" name="from" value="<?= $esc($_GET['from'] ?? '') ?>"></label>
                                <label class="dt-filters__date">To <input type="date" name="to" value="<?= $esc($_GET['to'] ?? '') ?>"></label>
                                <button type="submit" class="dt-btn dt-btn--primary">Search</button>
                                <?php if ($tx_searching): ?>
                                    <a class="dt-btn" href="transactions">Clear</a>
                                <?php endif; ?>
                            </form>

                            <?php if ($tx_searching && $tx_result['error']): ?>
                                <p class="mt-3 bg-danger/20 text-danger text-center rounded-lg py-2 px-2"><?= $esc($tx_result['error']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="card">
                        <h2 class="mb-4 text-base font-semibold capitalize text-slate-800 dark:text-slate-100">
                            <?= $tx_searching ? 'Search results' : 'Transaction Records' ?>
                        </h2>
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
                                'note'       => $tx_searching
                                    ? 'Matching ' . $tx_result['criteria'] . ' -- ' . number_format(count($transactions_records)) . ' shown'
                                        . ($tx_result['has_more'] ? ', more available (use Older).' : '.')
                                    : ($transactions_total > count($transactions_records) ? 'Showing the most recent ' . number_format(count($transactions_records)) . ' of ' . number_format($transactions_total) . ' transactions. Use the search above for older ones.' : ''),
                                'empty'      => $tx_searching ? 'No transaction matches that search.' : 'No transactions recorded yet.',
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

                            <?php if ($tx_searching): ?>
                                <div class="dt-pager">
                                    <a class="dt-btn" href="<?= $esc($tx_url()) ?>">&laquo; Newest</a>
                                    <?php if ($tx_result['has_more'] && $tx_result['next_cursor']): ?>
                                        <a class="dt-btn" href="<?= $esc($tx_url(['before' => $tx_result['next_cursor']])) ?>">Older &raquo;</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
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