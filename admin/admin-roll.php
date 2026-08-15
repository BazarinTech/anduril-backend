<?php
include 'includes/main.php';

/**
 * Phase 5.4 -- the daily reset is a cron job now: bin/daily-reset.php.
 *
 * This page remains as a manual override for the day the cron does not fire,
 * but it no longer contains its own copy of the logic. The old version set
 * `rolls = 1` on *every* orders row including expired ones, handing a fresh
 * claim to investments that had already finished.
 *
 * It needs the 'finance' permission: re-arming claims is a money operation,
 * not a routine edit.
 */
$msg = '';
$error = '';

if(isset($_POST['update'])){
    if (!admin_can($query, 'finance')) {
        $error = "Your admin account does not have the 'finance' permission.";
    } else {
        $descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(
            PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/../bin/daily-reset.php'),
            $descriptor,
            $pipes
        );

        if (is_resource($process)) {
            $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $msg = proc_close($process) === 0
                ? 'Daily reset completed.'
                : 'Daily reset failed -- see the error log.';
            $msg .= ' ' . trim(preg_replace('/\s+/', ' ', $out));
        } else {
            $error = 'Could not start the reset job.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
  <title>Admin</title>
</head>
<body class="w3-light-grey">

    <div class="w3-container w3-margin">
      <h3>Daily reset</h3>
      <p class="w3-text-grey">
        This runs automatically every night via <code>bin/daily-reset.php</code>.
        Use this button only to catch up a day the cron missed.
      </p>

      <?php if ($msg): ?><p class="w3-panel w3-pale-green w3-leftbar w3-border-green"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
      <?php if ($error): ?><p class="w3-panel w3-pale-red w3-leftbar w3-border-red"><?= htmlspecialchars($error) ?></p><?php endif; ?>

      <form action="admin-roll" method="post">
        <button name="update" class="w3-btn w3-blue w3-round w3-margin">Run daily reset now</button>
      </form>
    </div>
</body>
</html>