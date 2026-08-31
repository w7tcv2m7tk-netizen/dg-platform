<?php
if (!defined('ABSPATH')) {
    exit;
}
$offer = $offer ?? null;
$status = is_array($offer) ? (string) ($offer['stripe_status'] ?: $offer['status']) : '';
$is_trial = $status === 'trialing';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Trial started | DigitalGate</title>
  <link rel="stylesheet" href="<?php echo esc_url(DG_PLATFORM_URL . 'assets/css/founding-journey.css'); ?>?v=<?php echo esc_attr(DG_PLATFORM_VERSION); ?>">
</head>
<body class="dg-f10">
  <div class="dg-f10-wrap">
    <div class="dg-f10-kicker">Founding 10 · Trial</div>
    <h1><?php echo $is_trial ? 'Your 14-day trial is active' : 'Checkout received'; ?></h1>
    <div class="dg-f10-ok">
      <?php if ($is_trial) : ?>
        Your card is on file. Stripe status is <strong>trialing</strong>. You have not been charged. Billing starts when the 14-day trial ends.
      <?php else : ?>
        If Stripe is still confirming the session, refresh this page. You should see status <strong>trialing</strong> and $0 due now.
      <?php endif; ?>
    </div>
    <div class="dg-f10-card">
      <h2>What happens next</h2>
      <p class="lead">DigitalGate implements the Apps and connectors from your setup. You then go live. This is not a second contract step.</p>
      <div class="dg-f10-actions">
        <a class="dg-f10-btn" href="<?php echo esc_url(home_url('/founding/setup/')); ?>">Review setup</a>
      </div>
    </div>
  </div>
</body>
</html>
