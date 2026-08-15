<?php
include 'includes/main.php';
require_once __DIR__ . '/../lib/palpluss.php';

/**
 * Provider float balances.
 *
 * The payment wallet is the B2C float that withdrawals are paid out of --
 * when it runs dry, payouts fail with "Insufficient B2C wallet balance", so
 * this number is the early warning for that.
 *
 * Both are live reads with a short timeout and a 30-second cache, and both
 * report failure distinctly rather than rendering as zero. An empty float and
 * an unreachable provider mean opposite things to whoever is looking at this.
 */
$payment_wallet = palpluss_wallet_balance('b2c');
$service_wallet = palpluss_wallet_balance('service');

/**
 * CHART DATA
 * ==========
 * Both charts shipped with the theme's demo numbers -- an eleven-month series
 * dated 2003 and a flat 44/55 donut split. They are computed from the database
 * here instead.
 *
 * All of it is one pass over twelve months, three aggregate queries total, so
 * this costs a fixed amount no matter how much history accumulates.
 */

$months = [];
$cursor = new DateTime('first day of this month 00:00:00');
$cursor->modify('-11 months');

for ($i = 0; $i < 12; $i++) {
    $months[$cursor->format('Y-m')] = [
        'label'    => $cursor->format('M Y'),
        'iso'      => $cursor->format('Y-m-01'),
        'signups'  => 0,
        'active'   => 0,
        'deposits' => 0.0,
        'withdrawals' => 0.0,
    ];
    $cursor->modify('+1 month');
}

$windowStart = array_key_first($months) . '-01 00:00:00';

// Sign-ups per month.
$stmt = $pdo->prepare(
    "SELECT DATE_FORMAT(date_created, '%Y-%m') AS ym, COUNT(*) AS n
       FROM users
      WHERE date_created >= ?
   GROUP BY ym"
);
$stmt->execute([$windowStart]);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (isset($months[$row['ym']])) {
        $months[$row['ym']]['signups'] = (int) $row['n'];
    }
}

/**
 * Active users per month.
 *
 * Deliberately *not* `users.status`: that column holds each account's status
 * right now, with no history, so plotting it over time would draw today's
 * figure across every month. What is actually recorded is activity -- a
 * settled transaction or an order placed -- so that is what "active" means
 * here: distinct people who did something that month.
 */
$stmt = $pdo->prepare(
    "SELECT ym, COUNT(DISTINCT userID) AS n FROM (
         SELECT DATE_FORMAT(time, '%Y-%m') AS ym, userID
           FROM transactions
          WHERE status = 'Success' AND time >= ?
          UNION
         SELECT DATE_FORMAT(time, '%Y-%m') AS ym, userID
           FROM orders
          WHERE time >= ?
     ) activity
     GROUP BY ym"
);
$stmt->execute([$windowStart, $windowStart]);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (isset($months[$row['ym']])) {
        $months[$row['ym']]['active'] = (int) $row['n'];
    }
}

// Settled deposit and withdrawal volume per month, for the month-on-month
// figures under the headline totals.
$stmt = $pdo->prepare(
    "SELECT DATE_FORMAT(time, '%Y-%m') AS ym, type, SUM(amount) AS total
       FROM transactions
      WHERE status = 'Success' AND type IN ('Deposit', 'Withdraw') AND time >= ?
   GROUP BY ym, type"
);
$stmt->execute([$windowStart]);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (!isset($months[$row['ym']])) {
        continue;
    }

    $key = $row['type'] === 'Deposit' ? 'deposits' : 'withdrawals';
    $months[$row['ym']][$key] = (float) $row['total'];
}

$chart_labels   = array_column($months, 'iso');
$chart_signups  = array_column($months, 'signups');
$chart_active   = array_column($months, 'active');

// The donut is the settled split between money in and money out. Both totals
// already exist -- main.php computes them for the cards above.
$chart_deposits_total    = (float) $total_deposits;
$chart_withdrawals_total = (float) $total_withdrawals;

/**
 * "than last month" under each headline figure. These were hardcoded strings
 * (+1.5k, 548, +1.7k) sitting under numbers that were real, which is the worst
 * of both -- it reads as measured.
 */
$this_month = array_key_last($months);
$last_month = array_slice(array_keys($months), -2, 1)[0];

/**
 * Returns the coloured figure and the grey sentence around it separately, so
 * the phrasing still reads when there is nothing to compare against. A new
 * install has no previous month, and "+0 (0%) than last month" is a worse
 * answer than saying so.
 *
 * @param string $noun   What is being counted, e.g. 'sign-ups'. Empty for money.
 * @param bool   $money  Prefix the figure with the currency.
 * @param bool   $invert Swap the colours. Money leaving the business is not
 *                       good news because it went up, so withdrawals read red
 *                       when rising and green when falling.
 */
function month_delta($current, $previous, $noun = '', $money = false, $invert = false)
{
    $format = function ($value) use ($money) {
        return ($money ? 'Kes ' : '') . number_format($value, 0);
    };

    $subject = $noun !== '' ? ' ' . $noun : '';

    if ($previous <= 0) {
        if ($current > 0) {
            return [
                'lead' => $format($current),
                'tail' => $subject . ' this month, the first on record',
                'tone' => $invert ? 'muted' : 'success',
            ];
        }

        return [
            'lead' => 'None',
            'tail' => $subject . ' this month or last',
            'tone' => 'muted',
        ];
    }

    $change  = $current - $previous;
    $percent = number_format(abs($change / $previous) * 100, 1);

    $rising = $change >= 0;

    return [
        'lead' => ($rising ? '+' : '-') . $format(abs($change)),
        'tail' => $subject . ' than last month (' . $percent . '%)',
        'tone' => ($invert ? !$rising : $rising) ? 'success' : 'danger',
    ];
}

// Same shape, different sentence: the two rows under the donut compare
// yesterday with the day before, not this month with last.
function day_delta($current, $previous, $invert = false)
{
    $delta = month_delta($current, $previous, '', true, $invert);

    $delta['tail'] = str_replace(
        [' than last month', ' this month, the first on record', ' this month or last'],
        [' on the day before', ' yesterday, the first on record', ' settled yesterday or the day before'],
        $delta['tail']
    );

    return $delta;
}

$deposits_delta    = month_delta($months[$this_month]['deposits'], $months[$last_month]['deposits'], '', true);
$withdrawals_delta = month_delta($months[$this_month]['withdrawals'], $months[$last_month]['withdrawals'], '', true, true);
$signups_delta     = month_delta($months[$this_month]['signups'], $months[$last_month]['signups'], 'sign-ups');

/**
 * Total Balance is what everyone holds right now. No history of it is stored,
 * so there is nothing to compare it against month on month. Net settled
 * movement this month is derivable and explains which way it is going.
 */
$net_flow = $months[$this_month]['deposits'] - $months[$this_month]['withdrawals'];
$balance_note = [
    'lead' => ($net_flow >= 0 ? '+' : '-') . 'Kes ' . number_format(abs($net_flow), 0),
    'tail' => ' net movement this month',
    'tone' => $net_flow >= 0 ? 'success' : 'danger',
];

// Yesterday against the day before, for the two rows under the donut.
$stmt = $pdo->prepare(
    "SELECT type, DATE(time) AS d, SUM(amount) AS total
       FROM transactions
      WHERE status = 'Success' AND type IN ('Deposit', 'Withdraw')
        AND DATE(time) IN (CURDATE() - INTERVAL 1 DAY, CURDATE() - INTERVAL 2 DAY)
   GROUP BY type, d"
);
$stmt->execute();

$daily = ['Deposit' => [], 'Withdraw' => []];
$yesterday   = (new DateTime('yesterday'))->format('Y-m-d');
$day_before  = (new DateTime('-2 days'))->format('Y-m-d');

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $daily[$row['type']][$row['d']] = (float) $row['total'];
}

$deposits_daily = day_delta(
    $daily['Deposit'][$yesterday] ?? 0.0,
    $daily['Deposit'][$day_before] ?? 0.0
);
$withdrawals_daily = day_delta(
    $daily['Withdraw'][$yesterday] ?? 0.0,
    $daily['Withdraw'][$day_before] ?? 0.0,
    true
);
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
                            <h4 class="flex items-center gap-4 mt-3 text-2xl font-semibold text-slate-800 dark:text-slate-100">
                                <?php if ($payment_wallet['ok']): ?>
                                    <?=htmlspecialchars($payment_wallet['currency'])?> <?=number_format($payment_wallet['balance'], 2)?>
                                <?php else: ?>
                                    <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
                            </h4>
                            <?php /* Never render an unavailable balance as 0.00 -- an empty payout
                                     float and an unreachable provider look identical that way, and
                                     they call for opposite responses. */ ?>
                            <?php if ($payment_wallet['ok']): ?>
                                <p class="mt-2 text-muted">Payout float<?= $payment_wallet['cached'] ? ' &middot; cached' : '' ?></p>
                            <?php else: ?>
                                <p class="mt-2 text-muted"><span class="font-semibold text-danger">Unavailable</span> &mdash; <?=htmlspecialchars($payment_wallet['error'])?></p>
                            <?php endif; ?>
                        </div>
                        <div class="card">
                            <p class="flex items-center gap-2 text-base dark:text-gray-300"><i data-feather="activity" class="size-4"></i> Service Wallet</p>
                            <h4 class="flex items-center gap-4 mt-3 text-2xl font-semibold text-slate-800 dark:text-slate-100">
                                <?php if ($service_wallet['ok']): ?>
                                    <?=htmlspecialchars($service_wallet['currency'])?> <?=number_format($service_wallet['balance'], 2)?>
                                <?php else: ?>
                                    <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
                            </h4>
                            <?php if ($service_wallet['ok']): ?>
                                <p class="mt-2 text-muted">Service balance<?= $service_wallet['cached'] ? ' &middot; cached' : '' ?></p>
                            <?php else: ?>
                                <p class="mt-2 text-muted"><span class="font-semibold text-danger">Unavailable</span> &mdash; <?=htmlspecialchars($service_wallet['error'])?></p>
                            <?php endif; ?>
                        </div>
                        <div class="card">
                            <p class="flex items-center gap-2 text-base dark:text-gray-300"><i data-feather="truck" class="size-4"></i> Total Balance</p>
                            <h4 class="flex items-center gap-4 mt-3 text-2xl font-semibold text-slate-800 dark:text-slate-100">Kes <?=number_format($total_balance, 2)?>
                            </h4>
                            <p class="mt-2 text-muted"><span class="font-semibold text-<?=$balance_note['tone']?>"><?=$balance_note['lead']?></span><?=$balance_note['tail']?></p>
                        </div>
                        <div class="card">
                            <p class="flex items-center gap-2 text-base dark:text-gray-300"><i data-feather="stop-circle" class="size-4"></i> Total Deposits</p>
                            <h4 class="flex items-center gap-4 mt-3 text-2xl font-semibold text-slate-800 dark:text-slate-100">Kes <?=number_format($total_deposits, 2)?>
                            </h4>
                            <p class="mt-2 text-muted"><span class="font-semibold text-<?=$deposits_delta['tone']?>"><?=$deposits_delta['lead']?></span><?=$deposits_delta['tail']?></p>
                        </div>
                        <div class="card">
                            <p class="flex items-center gap-2 text-base dark:text-gray-300"><i data-feather="shopping-bag" class="size-4"></i> Total Withdrawals</p>
                            <h4 class="flex items-center gap-4 mt-3 text-2xl font-semibold text-slate-800 dark:text-slate-100">Kes <?=number_format($total_withdrawals, 2)?>
                            </h4>
                            <p class="mt-2 text-muted"><span class="font-semibold text-<?=$withdrawals_delta['tone']?>"><?=$withdrawals_delta['lead']?></span><?=$withdrawals_delta['tail']?></p>
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
                                    <span class="grid rounded-full shrink-0 size-9 place-items-center bg-success/15 text-success"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm1 10h3l-4 4-4-4h3V7h2v5z"></path></svg></span>
                                    <div>
                                        <h5 class="text-slate-800 dark:text-slate-100">Deposits</h5>
                                        <p class="text-muted dark:text-darkmuted"><span class="font-medium text-<?=$deposits_daily['tone']?>"><?=$deposits_daily['lead']?></span><?=$deposits_daily['tail']?></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 p-3 border border-dashed shadow-none card">
                                    <span class="grid rounded-full shrink-0 size-9 place-items-center bg-danger/15 text-danger"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm1 10v5h-2v-5H8l4-4 4 4h-3z"></path></svg></span>
                                    <div>
                                        <h5 class="text-slate-800 dark:text-slate-100">Withdrawals</h5>
                                        <p class="text-muted dark:text-darkmuted"><span class="font-medium text-<?=$withdrawals_daily['tone']?>"><?=$withdrawals_daily['lead']?></span><?=$withdrawals_daily['tail']?></p>
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
<?php
/**
 * Chart data, handed to the init script rather than baked into it.
 *
 * crm-dashboard.init.js is a static asset shared with the theme; keeping the
 * numbers here means the chart file stays a chart file and PHP stays the only
 * thing that talks to the database.
 */
?>
<script>
window.dashboardData = <?= json_encode([
    'labels'      => $chart_labels,
    'signups'     => $chart_signups,
    'active'      => $chart_active,
    'deposits'    => round($chart_deposits_total, 2),
    'withdrawals' => round($chart_withdrawals_total, 2),
    'currency'    => 'Kes',
], JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="assets/js/pages/crm-dashboard.init.js"></script>

</body>


<!-- Mirrored from srbthemes.kcubeinfotech.com/sliced-pro/html/crm-dashboard.html by HTTrack Website Copier/3.x [XR&CO'2014], Mon, 10 Mar 2025 01:28:13 GMT -->
</html>