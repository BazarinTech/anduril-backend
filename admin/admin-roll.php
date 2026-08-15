<?php
include 'includes/main.php';

/**
 * This page used to re-arm everyone's daily claim -- first as a button an
 * admin had to remember to press, later as a trigger for the nightly cron.
 *
 * Neither exists any more. Claiming is decided by the clock: an investment can
 * be claimed while the window is open and the order has claims left for the
 * day. There is nothing to re-arm, so there is nothing for this page to do.
 *
 * It is kept as a signpost rather than deleted, because an admin who has this
 * URL in their history and finds a 404 will reasonably conclude that claiming
 * is broken.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
  <title>Daily reset (retired)</title>
</head>
<body class="w3-light-grey">

    <div class="w3-container w3-margin">
      <h3>Nothing to reset</h3>

      <p class="w3-panel w3-pale-blue w3-leftbar w3-border-blue">
        Claims are no longer re-armed by hand or by a nightly job. An investment
        is claimable whenever the daily window is open and that order has not
        used up its claims for the day.
      </p>

      <p class="w3-text-grey">
        Set the opening time, the optional closing time, and how many claims each
        investment gets per day on
        <a href="platform-control"><strong>Platform Control</strong></a>.
      </p>

      <p class="w3-text-grey">
        If a cron entry still calls <code>bin/daily-reset.php</code>, it is
        harmless &mdash; but you can remove it.
      </p>
    </div>
</body>
</html>
