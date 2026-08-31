<?php
if (!defined('ABSPATH')) {
    exit;
}
$offer = $offer ?? [];
$public = DG_Founding_Journey::public_offer($offer);
$rest = rest_url(DG_REST_NAMESPACE . '/founding/accept');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Accept Founding 10 | DigitalGate</title>
  <link rel="stylesheet" href="<?php echo esc_url(DG_PLATFORM_URL . 'assets/css/founding-journey.css'); ?>?v=<?php echo esc_attr(DG_PLATFORM_VERSION); ?>">
</head>
<body class="dg-f10">
  <div class="dg-f10-wrap">
    <div class="dg-f10-kicker">Founding 10 · Formal acceptance</div>
    <h1>Accept your Founding 10 place</h1>
    <p class="lead">DigitalGate has offered <?php echo esc_html($public['business_name'] !== '' ? $public['business_name'] : 'your business'); ?> a Founding 10 place. This is the agreement step — not an application, and not a payment.</p>

    <div class="dg-f10-card">
      <h2>Your offer</h2>
      <div class="dg-f10-meta">
        <div><span>Business</span><?php echo esc_html($public['business_name'] ?: '—'); ?></div>
        <div><span>Contact</span><?php echo esc_html($public['name'] . ' · ' . $public['email']); ?></div>
        <div><span>Plan</span><?php echo esc_html(ucfirst($public['platform_tier'])); ?></div>
        <div><span>Billing</span><?php echo esc_html($public['billing_interval'] === 'year' ? 'Yearly (≈ 10 months)' : 'Monthly'); ?> · 14-day trial after onboarding</div>
      </div>
    </div>

    <div class="dg-f10-card">
      <h2>Founding Customer Terms</h2>
      <p class="lead" style="margin-bottom:1rem;">Read the programme terms. You only agree here because DigitalGate has offered you a place and you have decided to proceed.</p>
      <p><a href="https://digitalgate.com.au/founding-customer-terms/" target="_blank" rel="noopener">Open Founding Customer Terms &amp; Conditions</a></p>
      <p><a href="https://digitalgate.com.au/terms-conditions/" target="_blank" rel="noopener">DigitalGate Terms &amp; Conditions</a> · <a href="https://digitalgate.com.au/privacy-policy/" target="_blank" rel="noopener">Privacy Policy</a></p>
    </div>

    <div id="f10err" class="dg-f10-err"></div>
    <form id="f10accept" class="dg-f10-card">
      <label class="dg-f10-check">
        <input type="checkbox" name="agree_founding_terms" value="1" required>
        <span>I have read and agree to the <a href="https://digitalgate.com.au/founding-customer-terms/" target="_blank" rel="noopener">Founding Customer Terms &amp; Conditions</a>.</span>
      </label>
      <div class="dg-f10-actions">
        <button type="submit" class="dg-f10-btn">Accept Founding 10 place</button>
      </div>
    </form>
  </div>
  <script>
  (function () {
    var form = document.getElementById('f10accept');
    var err = document.getElementById('f10err');
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      err.style.display = 'none';
      fetch(<?php echo wp_json_encode($rest); ?>, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          token: <?php echo wp_json_encode($public['token']); ?>,
          agree_founding_terms: form.agree_founding_terms.checked
        })
      }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (res) {
          if (!res.ok) throw new Error(res.j.message || 'Could not accept this offer.');
          window.location.href = res.j.redirect;
        })
        .catch(function (e) {
          err.textContent = e.message;
          err.style.display = 'block';
        });
    });
  })();
  </script>
</body>
</html>
