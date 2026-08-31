<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="wrap dg-platform-wrap">
  <h1>Founding 10 offers</h1>
  <p>Issue a written-offer accept link. This is <strong>not</strong> the public application form. Do not enable the live <code>/onboarding/</code> alias until setup returns 200 and a trial proof has passed.</p>

  <?php if (!empty($_GET['created'])) : ?>
    <div class="notice notice-success"><p>Offer created. Accept URL: <code><?php echo esc_html(DG_Founding_Offers::accept_url(sanitize_text_field(wp_unslash($_GET['created'])))); ?></code></p></div>
  <?php endif; ?>

  <div class="dg-panel" style="margin-bottom:20px;padding:16px;background:#fff;border:1px solid #ccd0d4;">
    <h2>Create test / customer offer</h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field('dg_founding_create_offer'); ?>
      <input type="hidden" name="action" value="dg_founding_create_offer">
      <table class="form-table">
        <tr><th>Email</th><td><input class="regular-text" type="email" name="email" required></td></tr>
        <tr><th>Name</th><td><input class="regular-text" type="text" name="name" required></td></tr>
        <tr><th>Business</th><td><input class="regular-text" type="text" name="business_name" required></td></tr>
        <tr>
          <th>Plan</th>
          <td>
            <select name="platform_tier">
              <option value="starter">Starter</option>
              <option value="professional">Growth</option>
              <option value="business">Scale</option>
            </select>
            <select name="billing_interval">
              <option value="month">Monthly</option>
              <option value="year">Yearly</option>
            </select>
          </td>
        </tr>
        <tr>
          <th>Apps</th>
          <td>
            <label><input type="checkbox" name="apps[]" value="services"> Services</label>
            <label><input type="checkbox" name="apps[]" value="accommodation"> Accommodation</label>
            <label><input type="checkbox" name="apps[]" value="real-estate"> Real Estate</label>
          </td>
        </tr>
      </table>
      <?php submit_button('Create accept link'); ?>
    </form>
  </div>

  <div class="dg-panel" style="margin-bottom:20px;padding:16px;background:#fff;border:1px solid #ccd0d4;">
    <h2>Stripe 14-day trial proof (test mode)</h2>
    <p>Creates real test-mode subscriptions (monthly and yearly) with a test card. Expects status <code>trialing</code> and $0 due. Does <strong>not</strong> use Payment Links.</p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field('dg_founding_prove_trial'); ?>
      <input type="hidden" name="action" value="dg_founding_prove_trial">
      <?php submit_button('Prove monthly + yearly trial'); ?>
    </form>
    <?php if (is_array($proof)) : ?>
      <pre style="background:#111;color:#eee;padding:12px;overflow:auto;"><?php echo esc_html(wp_json_encode($proof, JSON_PRETTY_PRINT)); ?></pre>
    <?php endif; ?>
  </div>

  <div class="dg-panel" style="margin-bottom:20px;padding:16px;background:#fff;border:1px solid #ccd0d4;">
    <h2>Live /onboarding/ alias</h2>
    <p>Currently <strong><?php echo $ready ? 'ON' : 'OFF'; ?></strong>. Leave OFF until <code>/founding/setup/</code> returns 200 and you are ready to switch the live funnel.</p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field('dg_founding_toggle_setup_ready'); ?>
      <input type="hidden" name="action" value="dg_founding_toggle_setup_ready">
      <label><input type="checkbox" name="setup_ready" value="1" <?php checked($ready); ?>> Point <code>/onboarding/</code> at Founding setup</label>
      <?php submit_button('Save alias setting'); ?>
    </form>
    <p>
      <a href="<?php echo esc_url(home_url('/founding-customers-preview/')); ?>">Preview invite/explore page</a> ·
      <a href="<?php echo esc_url(home_url('/founding/setup/')); ?>">Setup route</a> ·
      <a href="<?php echo esc_url(rest_url(DG_REST_NAMESPACE . '/founding/health')); ?>">Health JSON</a>
    </p>
  </div>

  <table class="widefat striped">
    <thead><tr><th>Business</th><th>Email</th><th>Status</th><th>Accept URL</th></tr></thead>
    <tbody>
      <?php if (!$offers) : ?>
        <tr><td colspan="4">No offers yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($offers as $row) : ?>
        <tr>
          <td><?php echo esc_html($row['business_name'] ?? ''); ?></td>
          <td><?php echo esc_html($row['email'] ?? ''); ?></td>
          <td><?php echo esc_html($row['status'] ?? ''); ?></td>
          <td><code><?php echo esc_html(DG_Founding_Offers::accept_url($row['token'] ?? '')); ?></code></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
