<?php
include 'includes/main.php';

/**
 * PLATFORM CONTROL
 * ================
 * Was "Transaction Control". Renamed because it now governs more than
 * transactions: the daily claim window lives here too.
 *
 * The claim window is the setting that used to be a button on this very page
 * labelled "Update User Rolls". Claiming no longer depends on anyone pressing
 * anything -- an investment is claimable when the clock is inside the window
 * and the order has claims left for the day. See bootstrap/claims.php.
 */
$msg = '';
$error = '';

if (isset($_POST['update'])) {
    // These settings decide commission rates, fees and when money can be
    // claimed. Before this, any signed-in admin could rewrite all of it --
    // there was no permission check on this page at all.
    if (!admin_can($query, 'edit')) {
        $error = "Your admin account does not have the 'edit' permission.";
    } else {
        /**
         * Percentages and floors, clamped rather than trusted. A negative
         * withdrawal fee pays people to withdraw; a commission over 100%
         * pays out more than was deposited.
         */
        $percent = function ($value, $fallback) {
            $value = is_numeric($value) ? (float) $value : $fallback;

            return (string) max(0, min(100, $value));
        };

        $amount = function ($value, $fallback) {
            $value = is_numeric($value) ? (float) $value : $fallback;

            return (string) max(0, $value);
        };

        $settings = [
            'minDep'      => $amount($_POST['minDep'] ?? null, $min_dep),
            'minWith'     => $amount($_POST['minWith'] ?? null, $min_with),
            'withFee'     => $percent($_POST['withFee'] ?? null, $with_fee),
            'tranFee'     => $percent($_POST['tranFee'] ?? null, $tran_fee),
            'minTransfer' => $amount($_POST['minTransfer'] ?? null, $min_transfer),
            'level1'      => $percent($_POST['level1'] ?? null, $level1),
            'level2'      => $percent($_POST['level2'] ?? null, $level2),
            // level3 was collected from the form and then left out of the
            // UPDATE, so the third-level commission rate could not be changed
            // from this page at all.
            'level3'      => $percent($_POST['level3'] ?? null, $level3),

            // -- Claim window ----------------------------------------------
            'claimWindowOn' => isset($_POST['claimWindowOn']) ? '1' : '0',
            'claimOpensAt'  => claim_normalise_time($_POST['claimOpensAt'] ?? '', '07:00:00'),
            'claimsPerDay'  => max(1, (int) ($_POST['claimsPerDay'] ?? 1)),
        ];

        // An empty close time means "open until it next opens", which is a
        // different thing from midnight and has to survive as NULL.
        $closes = trim((string) ($_POST['claimClosesAt'] ?? ''));
        $settings['claimClosesAt'] = $closes === '' ? null : claim_normalise_time($closes, null);

        // The controls table holds exactly one row, but an UPDATE with no
        // WHERE is a habit worth not having.
        $query->update('controls', $settings, ['ID' => $transaction_controls['ID']]);

        // Re-read so the form redisplays what was actually stored rather than
        // what was typed.
        $transaction_controls = $query->select('controls')[0];
        $min_dep      = $transaction_controls['minDep'];
        $min_with     = $transaction_controls['minWith'];
        $with_fee     = $transaction_controls['withFee'];
        $tran_fee     = $transaction_controls['tranFee'];
        $min_transfer = $transaction_controls['minTransfer'];
        $level1       = $transaction_controls['level1'];
        $level2       = $transaction_controls['level2'];
        $level3       = $transaction_controls['level3'];

        $msg = 'Settings saved.';
    }
}

/**
 * PLATFORM RESET
 * --------------
 * Removes every non-admin user with their wallets (withdrawal accounts
 * included), transactions, orders and incentive applications. What is removed
 * and kept is documented in lib/platform-reset.php.
 *
 * It cannot be undone, so it asks for three things a stray click or a forged
 * request cannot supply:
 *
 *   permissions   both 'edit' and 'finance' -- it rewrites money records
 *   password      the signed-in admin's own, re-entered. This is also what
 *                 makes the form safe from cross-site submission: a page that
 *                 tricks the browser into posting here does not know it.
 *   phrase        typed out, so the button cannot be reached by habit
 */
require_once __DIR__ . '/../lib/platform-reset.php';

const PLATFORM_RESET_PHRASE = 'RESET PLATFORM';

$reset_error   = '';
$reset_removed = null;

if (isset($_POST['reset_platform'])) {
    $phrase   = (string) ($_POST['confirm_phrase'] ?? '');
    $password = (string) ($_POST['admin_password'] ?? '');

    if (!admin_can($query, 'edit') || !admin_can($query, 'finance')) {
        // Shown in the page banner: this admin never sees the dialog.
        $error = "Resetting the platform needs both the 'edit' and 'finance' permissions.";
    } elseif (trim($phrase) !== PLATFORM_RESET_PHRASE) {
        $reset_error = 'Type ' . PLATFORM_RESET_PHRASE . ' exactly to confirm.';
    } elseif ($password === '' || !password_verify($password, (string) $admin_pass)) {
        $reset_error = 'That password is not correct.';
    } else {
        try {
            $reset_removed = platform_reset_run($pdo, $adminID);

            $summary = [];
            foreach ($reset_removed as $table => $step) {
                $summary[] = $table . '=' . $step['count'];
            }

            // There is no undo, so leave a record of who did it and what went.
            error_log('[platform-reset] admin userID ' . $adminID . ' (' . $admin_email . ') reset the platform: ' . implode(', ', $summary));

            $msg = 'Platform reset. Removed ' . number_format($reset_removed['users']['count']) . ' user accounts and their records.';
        } catch (\Throwable $e) {
            error_log('[platform-reset] failed for admin userID ' . $adminID . ': ' . $e->getMessage());
            $reset_error = 'The reset failed and nothing was removed. Please try again.';
        }
    }
}

// Counted after any reset above, so the card shows what is left.
$reset_preview = platform_reset_preview($pdo);
$reset_allowed = admin_can($query, 'edit') && admin_can($query, 'finance');

$claim_settings = claim_settings($query);
$claim_state    = claim_window($claim_settings);
?>
<!DOCTYPE html>
<html lang="en"  :dir="$store.app.direction" x-data="{ direction: $store.app.direction || 'ltr' }" x-bind:dir="direction" class="group/item" :data-mode="$store.app.mode" :data-sidebar="$store.app.sidebarMode">


<!-- Mirrored from srbthemes.kcubeinfotech.com/sliced-pro/html/blank.html by HTTrack Website Copier/3.x [XR&CO'2014], Mon, 10 Mar 2025 01:28:49 GMT -->
<head>

    <meta charset="utf-8">
    <title>Platform Control</title>
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
  <!-- Modal, field and button styles for the reset dialog. -->
  <link rel="stylesheet" href="assets/css/data-table.css">
  <style>
      [x-cloak] { display: none !important; }

      .pc-danger {
          border: 1px solid rgb(239 68 68 / 0.45);
      }
      .pc-danger__head {
          display: flex;
          flex-wrap: wrap;
          align-items: flex-start;
          justify-content: space-between;
          gap: 1rem;
      }
      .pc-danger__title {
          font-size: 1rem;
          font-weight: 600;
          color: var(--dt-danger);
      }
      .pc-danger__text {
          margin-top: 0.25rem;
          max-width: 42rem;
          font-size: 0.875rem;
          color: var(--dt-text-muted);
      }
      .pc-counts {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(9.5rem, 1fr));
          gap: 0.75rem;
          margin-top: 1rem;
      }
      .pc-count {
          padding: 0.75rem;
          border: 1px dashed var(--dt-border);
          border-radius: 0.375rem;
      }
      .pc-count__value {
          font-size: 1.25rem;
          font-weight: 600;
          color: var(--dt-text);
          font-variant-numeric: tabular-nums;
      }
      .pc-count__label {
          font-size: 0.75rem;
          color: var(--dt-text-muted);
      }
      .pc-kept {
          margin-top: 0.75rem;
          font-size: 0.8125rem;
          color: var(--dt-text-muted);
      }
      .dt-btn--danger {
          background-color: var(--dt-danger);
          border-color: var(--dt-danger);
          color: #ffffff;
      }
      .dt-btn--danger:hover { background-color: rgb(239 68 68 / 0.85); border-color: rgb(239 68 68 / 0.85); }
      .dt-btn--danger:disabled { opacity: 0.5; cursor: not-allowed; }
      .pc-reset-body { grid-template-columns: 1fr !important; }
      .pc-reset-list {
          margin: 0.25rem 0 0;
          padding-left: 1.1rem;
          list-style: disc;
          font-size: 0.875rem;
          color: var(--dt-text);
      }
      .pc-reset-list li + li { margin-top: 0.2rem; }
      .pc-phrase {
          font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
          font-weight: 600;
          letter-spacing: 0.04em;
      }
  </style>
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
                        <li class="text-xs dark:text-white/80">management</li>
                        <li class="text-xl font-semibold text-slate-800 dark:text-slate-100">Platform Control</li>
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
                        <form action="platform-control" method='post'>

                            <!-- ============ Daily claim window ============ -->
                            <h2 class="mb-1 text-base font-semibold text-slate-800 dark:text-slate-100">Daily claim window</h2>
                            <p class="mb-4 text-sm text-muted">
                                When investors can claim their daily return. This replaces the old
                                &ldquo;Update User Rolls&rdquo; button &mdash; nothing has to be pressed or
                                scheduled for tomorrow&rsquo;s claims to work.
                            </p>

                            <div class="p-3 mb-4 border border-dashed rounded-md border-slate-200 dark:border-darkborder">
                                <p class="text-sm">
                                    Right now:
                                    <span class="font-semibold text-<?= $claim_state['open'] ? 'success' : 'warning' ?>">
                                        <?= $claim_state['open'] ? 'OPEN' : 'CLOSED' ?>
                                    </span>
                                    &middot; <?= htmlspecialchars($claim_state['message'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php if (!$claim_state['open']): ?>
                                        Next opens <?= htmlspecialchars($claim_state['next_open'], ENT_QUOTES, 'UTF-8') ?>.
                                    <?php endif; ?>
                                </p>
                                <p class="mt-1 text-xs text-muted">
                                    Server time <?= date('Y-m-d H:i:s') ?> (<?= htmlspecialchars(date_default_timezone_get(), ENT_QUOTES, 'UTF-8') ?>).
                                    All times below are in this zone.
                                </p>
                            </div>

                            <div class="space-y-1 my-4">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name='claimWindowOn' class="form-checkbox" value='1' <?= $claim_settings['enabled'] ? 'checked' : '' ?>>
                                    <span>Enforce the claim window</span>
                                </label>
                                <p class="text-xs text-muted">
                                    Unticked, claims are accepted at any hour &mdash; still once per day,
                                    just with no opening time.
                                </p>
                            </div>
                            <div class="space-y-1 my-4">
                                <label>Claiming opens at</label>
                                <input type="time" name='claimOpensAt' step="60" class="form-input h-14" value='<?= htmlspecialchars(substr($claim_settings['opens'], 0, 5), ENT_QUOTES, 'UTF-8') ?>' required>
                            </div>
                            <div class="space-y-1 my-4">
                                <label>Claiming closes at <span class="text-muted">(optional)</span></label>
                                <input type="time" name='claimClosesAt' step="60" class="form-input h-14" value='<?= $claim_settings['closes'] ? htmlspecialchars(substr($claim_settings['closes'], 0, 5), ENT_QUOTES, 'UTF-8') : '' ?>'>
                                <p class="text-xs text-muted">
                                    Leave blank to stay open until it opens again the next day. A closing
                                    time earlier than the opening time runs the window through midnight.
                                </p>
                            </div>
                            <div class="space-y-1 my-4">
                                <label>Claims allowed per investment per day</label>
                                <input type="number" name='claimsPerDay' min="1" max="24" class="form-input h-14" value='<?= (int) $claim_settings['per_day'] ?>' required>
                            </div>

                            <!-- ============ Transactions ============ -->
                            <h2 class="mt-8 mb-4 text-base font-semibold text-slate-800 dark:text-slate-100">Deposits, withdrawals and transfers</h2>
                                <div class="space-y-1">
                                    <label>Minimum Withdrawal(kes)</label>
                                    <input type="number" name='minWith' class="form-input h-14" placeholder="eg 500" value='<?=$min_with?>' required>
                                </div>
                                <?php /* The "Maximum Withdrawal" field that sat here was hardcoded to
                                         1000000 and had no column behind it, so it displayed a number
                                         nobody had set and discarded whatever was typed. Removed rather
                                         than left looking functional. */ ?>
                                
                                <div class="space-y-1 my-4">
                                    <label>Withdrawal Fee (%)</label>
                                    <input type="number" name='withFee' class="form-input h-14" placeholder="eg 500" value='<?=$with_fee?>' required>
                                </div>
                                <div class="space-y-1 my-4">
                                    <label>Transfer fee (%)</label>
                                    <input type="number" name='tranFee' class="form-input h-14" placeholder="eg 500" value='<?=$tran_fee?>' required>
                                </div>
                                <div class="space-y-1 my-4">
                                    <label>Minimum Transfer (Kes)</label>
                                    <input type="number" name='minTransfer' class="form-input h-14" placeholder="eg 500" value='<?=$min_transfer?>' required>
                                </div>
                                <div class="space-y-1 my-4">
                                    <label>Level 1 commission (%)</label>
                                    <input type="number" name='level1' class="form-input h-14" placeholder="eg 500" value='<?=$level1?>' required>
                                </div>
                                <div class="space-y-1 my-4">
                                    <label>Level 2 commission (%)</label>
                                    <input type="number" name='level2' class="form-input h-14" placeholder="eg 500" value='<?=$level2?>' required>
                                </div>
                                <div class="space-y-1 my-4">
                                    <label>Level 3 commission (%)</label>
                                    <input type="number" name='level3' class="form-input h-14" placeholder="eg 500" value='<?=$level3?>' required>
                                </div>
                                
                                
                                <div class="space-y-1 my-4">
                                    <label>Minimum Deposit(Kes)</label>
                                    <input type="number" name='minDep' class="form-input h-14" placeholder="eg 500" value='<?=$min_dep?>' required>
                                </div>
                                <button  name='update' type='submit' class="btn bg-purple border border-purple rounded-md text-white transition-all duration-300 hover:bg-purple/[0.85] hover:border-purple/[0.85]">Save</button>
                            </form>
                            <?php /* "Update User Rolls" is gone. It posted a `rolls` field this page
                                     never handled -- the handler had been commented out -- so the button
                                     did nothing at all. What it was meant to do is now the claim window
                                     above, which needs no button. */ ?>
                        </div>

                        <!-- ============ Danger zone: platform reset ============ -->
                        <?php
                            $reset_count = function ($table) use ($reset_preview) {
                                return number_format($reset_preview['counts'][$table]['count'] ?? 0);
                            };
                        ?>
                        <div class="card pc-danger"
                             x-data="{ open: <?= $reset_error ? 'true' : 'false' ?>, phrase: '', phraseRequired: <?= htmlspecialchars(json_encode(PLATFORM_RESET_PHRASE), ENT_QUOTES, 'UTF-8') ?> }">
                            <div class="pc-danger__head">
                                <div>
                                    <h2 class="pc-danger__title">Reset platform</h2>
                                    <p class="pc-danger__text">
                                        Permanently removes every user account except admins, together with
                                        their wallets, withdrawal accounts, transactions, orders and incentive
                                        applications. Products, bonuses, coupons, incentives and these settings
                                        are kept. This cannot be undone &mdash; take a database backup first.
                                    </p>
                                </div>
                                <?php if ($reset_preview !== null): ?>
                                    <button type="button" class="dt-btn dt-btn--danger" @click="open = true; phrase = ''"
                                            <?= $reset_allowed ? '' : 'disabled title="Needs the edit and finance permissions"' ?>>
                                        Reset platform&hellip;
                                    </button>
                                <?php endif; ?>
                            </div>

                            <?php if ($reset_preview === null): ?>
                                <p class="pc-kept">No admin accounts were found, so a reset is not available.</p>
                            <?php else: ?>
                                <div class="pc-counts">
                                    <div class="pc-count">
                                        <div class="pc-count__value"><?= $reset_count('users') ?></div>
                                        <div class="pc-count__label">User accounts</div>
                                    </div>
                                    <div class="pc-count">
                                        <div class="pc-count__value"><?= $reset_count('wallets') ?></div>
                                        <div class="pc-count__label">Wallets</div>
                                    </div>
                                    <div class="pc-count">
                                        <div class="pc-count__value"><?= number_format($reset_preview['withdrawal_accounts']) ?></div>
                                        <div class="pc-count__label">Withdrawal accounts</div>
                                    </div>
                                    <div class="pc-count">
                                        <div class="pc-count__value"><?= $reset_count('transactions') ?></div>
                                        <div class="pc-count__label">Transactions</div>
                                    </div>
                                    <div class="pc-count">
                                        <div class="pc-count__value"><?= $reset_count('orders') ?></div>
                                        <div class="pc-count__label">Orders</div>
                                    </div>
                                    <div class="pc-count">
                                        <div class="pc-count__value"><?= $reset_count('incentives_requests') ?></div>
                                        <div class="pc-count__label">Incentive applications</div>
                                    </div>
                                </div>
                                <p class="pc-kept">
                                    Kept: <?= number_format($reset_preview['admins_kept']) ?> admin
                                    account<?= $reset_preview['admins_kept'] === 1 ? '' : 's' ?>, including their own wallets and history.
                                </p>

                                <?php if ($reset_allowed): ?>
                                <div class="dt-modal" x-show="open" x-cloak @keydown.escape.window="open = false" @click.self="open = false"
                                     role="dialog" aria-modal="true" aria-labelledby="pc-reset-title">
                                    <div class="dt-modal__panel">
                                        <div class="dt-modal__head">
                                            <div>
                                                <div class="dt-modal__title" id="pc-reset-title">Reset the platform?</div>
                                                <div class="dt-modal__subtitle">This permanently deletes data and cannot be undone.</div>
                                            </div>
                                            <button type="button" class="dt-modal__close" @click="open = false" aria-label="Close">&times;</button>
                                        </div>

                                        <form action="platform-control" method="post" autocomplete="off">
                                            <div class="dt-modal__body pc-reset-body">
                                                <?php if ($reset_error): ?>
                                                    <div class="dt-alert dt-alert--danger"><?= htmlspecialchars($reset_error, ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php endif; ?>

                                                <div>
                                                    <div class="dt-field"><label>This will delete</label></div>
                                                    <ul class="pc-reset-list">
                                                        <li><?= $reset_count('users') ?> user accounts</li>
                                                        <li><?= $reset_count('wallets') ?> wallets, with <?= number_format($reset_preview['withdrawal_accounts']) ?> withdrawal accounts</li>
                                                        <li><?= $reset_count('transactions') ?> transactions, including pending deposits and withdrawals</li>
                                                        <li><?= $reset_count('orders') ?> orders</li>
                                                        <li><?= $reset_count('incentives_requests') ?> incentive applications</li>
                                                    </ul>
                                                </div>

                                                <div class="dt-field">
                                                    <label for="pc-reset-phrase">Type <span class="pc-phrase"><?= htmlspecialchars(PLATFORM_RESET_PHRASE, ENT_QUOTES, 'UTF-8') ?></span> to confirm</label>
                                                    <input id="pc-reset-phrase" type="text" name="confirm_phrase" x-model="phrase" spellcheck="false" required>
                                                </div>

                                                <div class="dt-field">
                                                    <label for="pc-reset-password">Your admin password</label>
                                                    <input id="pc-reset-password" type="password" name="admin_password" autocomplete="current-password" required>
                                                </div>
                                            </div>

                                            <div class="dt-modal__foot">
                                                <button type="button" class="dt-btn" @click="open = false">Cancel</button>
                                                <button type="submit" name="reset_platform" value="1" class="dt-btn dt-btn--danger"
                                                        :disabled="phrase.trim() !== phraseRequired">
                                                    Delete everything and reset
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                <?php endif; ?>
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