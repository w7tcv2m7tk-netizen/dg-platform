<?php
if (!defined('ABSPATH')) {
    exit;
}
$offer = $offer ?? null;
$public = $offer ? DG_Founding_Journey::public_offer($offer) : null;
$setup = is_array($public['setup'] ?? null) ? $public['setup'] : [];
$catalog = DG_Founding_Checkout::add_on_catalog();
$plans = DG_Founding_Checkout::platform_catalog();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Founding 10 onboarding | DigitalGate</title>
  <link rel="stylesheet" href="<?php echo esc_url(DG_PLATFORM_URL . 'assets/css/founding-journey.css'); ?>?v=<?php echo esc_attr(DG_PLATFORM_VERSION); ?>">
</head>
<body class="dg-f10">
  <div class="dg-f10-wrap">
    <div class="dg-f10-kicker">Founding 10 · Onboarding</div>
    <h1>Configure your DigitalGate organisation</h1>
    <p class="lead">This is implementation setup — not the agreement (already accepted) and not a payment. After you confirm plan and Apps, we collect a card and start a 14-day trial. You are not charged during the trial.</p>

    <?php if (!$public) : ?>
      <div class="dg-f10-card">
        <p>Open the secure accept link from your Founding 10 offer email first. Setup is only available after you accept the offer.</p>
        <p><a href="<?php echo esc_url(home_url('/founding-customers-preview/')); ?>">Explore Founding 10</a></p>
      </div>
    <?php else : ?>
    <div id="f10err" class="dg-f10-err"></div>
    <div class="dg-f10-steps" id="f10steps"></div>
    <form id="f10setup">
      <input type="hidden" name="token" value="<?php echo esc_attr($public['token']); ?>">

      <div class="dg-f10-panel is-on" data-step="0">
        <div class="dg-f10-card">
          <h2>1. Business profile</h2>
          <div class="dg-f10-grid">
            <div class="full"><label>Business name</label><input name="business_name" value="<?php echo esc_attr($setup['business_name'] ?? $public['business_name']); ?>" required></div>
            <div><label>ABN</label><input name="abn" value="<?php echo esc_attr($setup['abn'] ?? ''); ?>"></div>
            <div><label>Phone</label><input name="phone" value="<?php echo esc_attr($setup['phone'] ?? ''); ?>"></div>
            <div class="full"><label>Street address</label><input name="street_address" value="<?php echo esc_attr($setup['street_address'] ?? ''); ?>"></div>
            <div><label>City</label><input name="city" value="<?php echo esc_attr($setup['city'] ?? ''); ?>"></div>
            <div><label>State</label><input name="state" value="<?php echo esc_attr($setup['state'] ?? ''); ?>"></div>
            <div><label>Postcode</label><input name="postcode" value="<?php echo esc_attr($setup['postcode'] ?? ''); ?>"></div>
            <div><label>Business email</label><input type="email" name="business_email" value="<?php echo esc_attr($setup['business_email'] ?? $public['email']); ?>"></div>
            <div><label>Primary contact</label><input name="contact_name" value="<?php echo esc_attr($setup['contact_name'] ?? $public['name']); ?>"></div>
            <div><label>Role</label><input name="position" value="<?php echo esc_attr($setup['position'] ?? ''); ?>"></div>
            <div><label>Contact phone</label><input name="contact_phone" value="<?php echo esc_attr($setup['contact_phone'] ?? ''); ?>"></div>
            <div><label>Contact email</label><input type="email" name="contact_email" value="<?php echo esc_attr($setup['contact_email'] ?? $public['email']); ?>"></div>
          </div>
        </div>
      </div>

      <div class="dg-f10-panel" data-step="1">
        <div class="dg-f10-card">
          <h2>2. About &amp; goals</h2>
          <div class="dg-f10-grid">
            <div class="full"><label>What does the business do?</label><textarea name="about_business"><?php echo esc_textarea($setup['about_business'] ?? ''); ?></textarea></div>
            <div class="full"><label>Goals for DigitalGate</label><textarea name="goals"><?php echo esc_textarea($setup['goals'] ?? ''); ?></textarea></div>
          </div>
        </div>
      </div>

      <div class="dg-f10-panel" data-step="2">
        <div class="dg-f10-card">
          <h2>3. Team</h2>
          <label>Who needs access? (name, email, role)</label>
          <textarea name="team_members"><?php echo esc_textarea($setup['team_members'] ?? ''); ?></textarea>
        </div>
      </div>

      <div class="dg-f10-panel" data-step="3">
        <div class="dg-f10-card">
          <h2>4. Brand &amp; website</h2>
          <div class="dg-f10-grid">
            <div class="full"><label>Website URL</label><input name="website_url" value="<?php echo esc_attr($setup['website_url'] ?? ''); ?>"></div>
            <div class="full"><label>Brand colours</label><input name="brand_colours" value="<?php echo esc_attr($setup['brand_colours'] ?? ''); ?>" placeholder="#0A0E17, #3B82F6"></div>
          </div>
        </div>
      </div>

      <div class="dg-f10-panel" data-step="4">
        <div class="dg-f10-card">
          <h2>5. Existing systems</h2>
          <label>What do you use today?</label>
          <textarea name="systems" placeholder="Xero, Airbnb, current CRM…"><?php echo esc_textarea($setup['systems'] ?? ''); ?></textarea>
        </div>
      </div>

      <div class="dg-f10-panel" data-step="5">
        <div class="dg-f10-card">
          <h2>6. Plan &amp; Apps</h2>
          <p class="lead">Pre-filled from your accepted offer. Change only if the offer needs an adjustment.</p>
          <div class="dg-f10-grid">
            <div>
              <label>Platform plan</label>
              <select name="platform_tier">
                <?php foreach ($plans as $key => $plan) : ?>
                  <option value="<?php echo esc_attr($key); ?>" <?php selected($public['platform_tier'], $key); ?>><?php echo esc_html($plan['label'] . ' — $' . ($plan['monthly'] / 100) . '/mo'); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label>Billing after trial</label>
              <select name="billing_interval">
                <option value="month" <?php selected($public['billing_interval'], 'month'); ?>>Monthly</option>
                <option value="year" <?php selected($public['billing_interval'], 'year'); ?>>Yearly (≈ 10 months)</option>
              </select>
            </div>
            <div class="full">
              <label>Apps</label>
              <?php foreach ($catalog as $key => $item) :
                  $selected = in_array($key, array_merge($public['apps'], $public['premium'], $public['addons']), true);
                  $field = $item['group'] === 'premium' ? 'premium[]' : ($item['group'] === 'addons' ? 'addons[]' : 'apps[]');
                  ?>
                <label class="dg-f10-check"><input type="checkbox" name="<?php echo esc_attr($field); ?>" value="<?php echo esc_attr($key); ?>" <?php checked($selected); ?>><span><?php echo esc_html($item['label'] . ' — $' . ($item['monthly'] / 100) . '/mo'); ?></span></label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="dg-f10-panel" data-step="6">
        <div class="dg-f10-card">
          <h2>7. Implementation</h2>
          <label>What should DigitalGate configure first?</label>
          <textarea name="implementation"><?php echo esc_textarea($setup['implementation'] ?? ''); ?></textarea>
        </div>
      </div>

      <div class="dg-f10-panel" data-step="7">
        <div class="dg-f10-card">
          <h2>8. Start 14-day trial</h2>
          <p class="lead">We collect your card via Stripe Checkout (not a Payment Link). Status will be <strong>trialing</strong>. You pay $0 for 14 days. Billing starts when the trial ends.</p>
          <ul class="dg-f10-lines" id="f10lines">
            <?php foreach ($public['lines'] as $line) : ?>
              <li><span><?php echo esc_html($line['name']); ?></span><span>$<?php echo esc_html(number_format($line['amount'] / 100, 2)); ?></span></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>

      <div class="dg-f10-actions">
        <button type="button" class="dg-f10-btn dg-f10-btn-ghost" id="f10prev">Back</button>
        <button type="button" class="dg-f10-btn" id="f10next">Save &amp; continue</button>
        <button type="button" class="dg-f10-btn" id="f10pay" style="display:none">Start 14-day trial</button>
      </div>
    </form>
    <script>
    window.dgFoundingSetup = {
      restSetup: <?php echo wp_json_encode(rest_url(DG_REST_NAMESPACE . '/founding/setup')); ?>,
      restCheckout: <?php echo wp_json_encode(rest_url(DG_REST_NAMESPACE . '/founding/checkout')); ?>,
      labels: <?php echo wp_json_encode(['Business', 'Goals', 'Team', 'Brand', 'Systems', 'Plan', 'Implement', 'Trial']); ?>
    };
    </script>
    <script src="<?php echo esc_url(DG_PLATFORM_URL . 'assets/js/founding-setup.js'); ?>?v=<?php echo esc_attr(DG_PLATFORM_VERSION); ?>"></script>
    <?php endif; ?>
  </div>
</body>
</html>
