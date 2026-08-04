<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap dg-platform-wrap">
    <h1>🔑 API Settings</h1>
    <?php if (isset($_GET['stripe_replay'])) : ?>
        <?php if ($_GET['stripe_replay'] === '1') : ?>
            <div class="notice notice-success"><p>Stripe checkout session processed — contact updated.</p></div>
        <?php elseif ($_GET['stripe_replay'] === 'error') : ?>
            <div class="notice notice-error"><p>Stripe replay failed: <?php echo esc_html(sanitize_text_field(wp_unslash($_GET['msg'] ?? 'Unknown error'))); ?></p></div>
        <?php elseif ($_GET['stripe_replay'] === 'failed') : ?>
            <div class="notice notice-warning"><p><strong>Replay did not create a contact.</strong> Session <?php echo esc_html(sanitize_text_field(wp_unslash($_GET['session_id'] ?? ''))); ?> — email: <?php echo esc_html(sanitize_text_field(wp_unslash($_GET['email'] ?? 'none'))); ?><?php if (!empty($_GET['msg'])) : ?> — <?php echo esc_html(sanitize_text_field(wp_unslash($_GET['msg']))); ?><?php endif; ?>. Check webhook log below.</p></div>
        <?php endif; ?>
    <?php endif; ?>
    <?php if (isset($_GET['saved'])) : ?><div class="notice notice-success"><p>API settings saved.</p></div><?php endif; ?>
    <?php if (isset($_GET['dev_key_regenerated'])) : ?><div class="notice notice-success"><p>Dev API key regenerated. Update your Cursor MCP config with the new key.</p></div><?php endif; ?>
    <div class="dg-panel" style="margin-bottom:20px;">
        <h2>Cursor MCP (Dev API)</h2>
        <p style="color:#666;max-width:820px;">This key lets Cursor query live CRM data from any of your DG Platform sites while you build.</p>
        <ol style="color:#666;max-width:820px;line-height:1.7;">
            <li>In WordPress: <strong>DG Platform → API Settings</strong> (this page) — copy the Dev API Key below.</li>
            <li>On your Mac: open <code>~/.cursor/mcp.json</code> and set <code>DG_PLATFORM_URL</code> + <code>DG_DEV_API_KEY</code> per site.</li>
            <li>Restart Cursor, then ask the agent to run site tools: <code>get_marketing_summary</code> (DigitalGate), <code>get_pipeline_summary</code> (Roe Realty), <code>get_accommodation_summary</code> (CVH), <code>get_creator_summary</code> (Aetherra).</li>
        </ol>
        <?php
        $dev_key = class_exists('DG_Dev_API') ? DG_Dev_API::get_or_create_key() : '';
        $regenerate_url = wp_nonce_url(admin_url('admin-post.php?action=dg_regenerate_dev_api_key'), 'dg_regenerate_dev_api_key');
        $mcp_example = wp_json_encode([
            'mcpServers' => [
                'dg-platform-digitalgate' => [
                    'command' => 'node',
                    'args' => ['/path/to/dg-platform/mcp-server/index.js'],
                    'env' => [
                        'DG_PLATFORM_URL' => home_url(),
                        'DG_DEV_API_KEY' => $dev_key !== '' ? $dev_key : 'paste-key-here',
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        ?>
        <table class="form-table">
            <tr>
                <th>Dev API Key</th>
                <td>
                    <input type="text" readonly value="<?php echo esc_attr($dev_key); ?>" class="large-text code" onclick="this.select();">
                    <p class="description">Send as <code>X-API-Key</code> header. Examples:</p>
                    <ul style="margin:0 0 0 1em;color:#666;">
                        <li>DigitalGate: <code><?php echo esc_html(rest_url(DG_REST_NAMESPACE . '/marketing/summary')); ?></code></li>
                        <li>Roe Realty: <code><?php echo esc_html(rest_url(DG_REST_NAMESPACE . '/leads/summary')); ?></code></li>
                        <li>CVH: <code><?php echo esc_html(rest_url(DG_REST_NAMESPACE . '/accommodation/summary')); ?></code></li>
                        <li>Aetherra: <code><?php echo esc_html(rest_url(DG_REST_NAMESPACE . '/creator/summary')); ?></code></li>
                        <li>Agency audit webhook: <code><?php echo esc_html(rest_url('dg/v1/audit-webhook')); ?></code></li>
                        <li>Client onboarding: <code><?php echo esc_html(class_exists('DG_Client_Onboarding') ? DG_Client_Onboarding::rest_url() : rest_url(DG_REST_NAMESPACE . '/onboarding')); ?></code></li>
                        <?php if (class_exists('DG_Client_Onboarding') && DG_Client_Onboarding::enabled()) : ?>
                            <li>Onboarding form action: <code><?php echo esc_html(DG_Client_Onboarding::form_action_url()); ?></code> — hidden field <code>action=dg_submit_onboarding</code></li>
                        <?php endif; ?>
                        <?php if (class_exists('DG_Stripe_Billing') && DG_Stripe_Billing::enabled()) : ?>
                            <li>Stripe billing webhook: <code><?php echo esc_html(DG_Stripe_Billing::webhook_url()); ?></code> — event <code>checkout.session.completed</code></li>
                        <?php endif; ?>
                    </ul>
                    <?php if (class_exists('DG_Site_Profile')) : ?>
                        <p class="description">This site: <strong><?php echo esc_html(DG_Site_Profile::label()); ?></strong></p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>MCP config snippet</th>
                <td>
                    <textarea readonly class="large-text code" rows="12" onclick="this.select();"><?php echo esc_textarea($mcp_example); ?></textarea>
                    <p class="description">Repo path on your Mac: <code>/Users/aetherra/Documents/dg-platform/mcp-server/index.js</code></p>
                    <p><a href="<?php echo esc_url($regenerate_url); ?>" class="button" onclick="return confirm('Regenerate key? Update ~/.cursor/mcp.json afterwards.');">Regenerate key</a></p>
                </td>
            </tr>
        </table>
    </div>
    <div class="dg-panel" style="margin-bottom:20px;">
        <h2>Property Import API</h2>
        <?php
        $import_key = get_option('roe_realty_api_key', '');
        if ($import_key === '' && function_exists('dg_re_get_import_api_key')) {
            $import_key = dg_re_get_import_api_key();
        }
        ?>
        <table class="form-table">
            <tr>
                <th>Import API Key</th>
                <td>
                    <input type="text" readonly value="<?php echo esc_attr($import_key); ?>" class="large-text code" onclick="this.select();">
                    <p class="description">Required for <code>POST <?php echo esc_html(rest_url('roerealty/v1/import')); ?></code>. Send as <code>X-API-Key</code> header. Import is blocked if this key is not set.</p>
                </td>
            </tr>
        </table>
    </div>
    <div class="dg-panel">
        <form method="post">
            <?php wp_nonce_field('dg_api_settings'); ?>
            <h2>Admin appearance</h2>
            <?php $dark_default = class_exists('DG_Admin_Dark_Mode') ? DG_Admin_Dark_Mode::site_default() : 'off'; ?>
            <table class="form-table">
                <tr>
                    <th>Default dark mode</th>
                    <td>
                        <select name="dg_admin_dark_default">
                            <option value="off" <?php selected($dark_default, 'off'); ?>>Off (light) — users opt in via admin bar</option>
                            <option value="on" <?php selected($dark_default, 'on'); ?>>On — dark by default for new users</option>
                            <option value="system" <?php selected($dark_default, 'system'); ?>>System — match OS light/dark preference</option>
                        </select>
                        <p class="description">Admins can always toggle with the 🌙 button in the top admin bar. Per-user choice is remembered.</p>
                    </td>
                </tr>
            </table>
            <hr>
            <table class="form-table">
                <tr><th colspan="2"><h3>AI & Analytics</h3></th></tr>
                <tr><th>Google PageSpeed API Key</th><td><input type="text" name="pagespeed" value="<?php echo esc_attr(DG_Integrations::get_api_key('pagespeed')); ?>" class="regular-text"></td></tr>
                <tr><th>OpenAI API Key</th><td><input type="text" name="openai" value="<?php echo esc_attr(DG_Integrations::get_api_key('openai')); ?>" class="regular-text"></td></tr>
                <tr><th>Google Gemini API Key</th><td><input type="text" name="gemini" value="<?php echo esc_attr(DG_Integrations::get_api_key('gemini')); ?>" class="regular-text"></td></tr>
                <tr><th colspan="2"><h3>Google Integrations</h3></th></tr>
                <tr><th>Google Search Console</th><td><input type="text" name="gsc" value="<?php echo esc_attr(DG_Integrations::get_api_key('gsc')); ?>" class="regular-text"></td></tr>
                <tr><th>Google Business Profile</th><td><input type="text" name="gbp" value="<?php echo esc_attr(DG_Integrations::get_api_key('gbp')); ?>" class="regular-text"></td></tr>
                <tr><th colspan="2"><h3>Communication & Payments</h3></th></tr>
                <tr><th>Twilio SID</th><td><input type="text" name="twilio_sid" value="<?php echo esc_attr(DG_Integrations::get_api_key('twilio_sid')); ?>" class="regular-text"></td></tr>
                <tr><th>Twilio Token</th><td><input type="text" name="twilio_token" value="<?php echo esc_attr(DG_Integrations::get_api_key('twilio_token')); ?>" class="regular-text"></td></tr>
                <tr><th>Twilio From Number</th><td><input type="text" name="twilio_from" value="<?php echo esc_attr(DG_Integrations::get_api_key('twilio_from')); ?>" class="regular-text"></td></tr>
                <tr><th>Stripe Secret Key</th><td><input type="text" name="stripe_secret" value="<?php echo esc_attr(DG_Integrations::get_api_key('stripe_secret')); ?>" class="regular-text" placeholder="sk_test_… or sk_live_…"><?php $stripe_key = DG_Integrations::get_api_key('stripe_secret'); if ($stripe_key !== '') : ?><p class="description" style="color:#059669;">✓ Stripe secret key saved<?php echo strpos($stripe_key, 'sk_test_') === 0 ? ' (test mode)' : (strpos($stripe_key, 'sk_live_') === 0 ? ' (live mode)' : ''); ?>.</p><?php endif; ?><p class="description">Use <strong>sk_test_</strong> when replaying test checkout sessions. Must match Test/Live mode in Stripe.</p></td></tr>
                <?php if (class_exists('DG_Stripe_Billing') && DG_Stripe_Billing::enabled()) : ?>
                <tr><th colspan="2"><h3>Stripe billing webhook (DigitalGate sales)</h3></th></tr>
                <tr>
                    <th>Webhook signing secret</th>
                    <td>
                        <input type="text" name="dg_stripe_billing_webhook_secret" value="<?php echo esc_attr(get_option('dg_stripe_billing_webhook_secret', '')); ?>" class="large-text code" placeholder="whsec_..." autocomplete="off">
                        <p class="description">From Stripe → Developers → Webhooks → your endpoint → <strong>Signing secret</strong>. Use the secret from the endpoint in the <strong>same mode</strong> as your payment (Test payments → Test mode webhook → Test <code>whsec_</code>). Live and Test secrets are different.</p>
                        <p><code><?php echo esc_html(DG_Stripe_Billing::webhook_url()); ?></code></p>
                        <p class="description">Event: <code>checkout.session.completed</code> — creates CRM contact, portal user, and sends onboarding email.</p>
                        <?php
                        $webhook_logs = class_exists('DG_Stripe_Billing') ? DG_Stripe_Billing::recent_logs(8) : [];
                        if ($webhook_logs) : ?>
                            <h4 style="margin-top:16px;">Recent webhook deliveries (plugin log)</h4>
                            <table class="widefat striped" style="max-width:900px;">
                                <thead><tr><th>Time</th><th>Event</th><th>Result</th></tr></thead>
                                <tbody>
                                <?php foreach ($webhook_logs as $row) : ?>
                                    <tr>
                                        <td><?php echo esc_html($row['at'] ?? ''); ?></td>
                                        <td><code><?php echo esc_html($row['type'] ?? ''); ?></code></td>
                                        <td><?php echo esc_html($row['message'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p class="description">Also check Stripe → Developers → Webhooks → <strong>Event deliveries</strong> (Test vs Live mode must match your payment).</p>
                        <?php else : ?>
                            <p class="description">No webhook hits logged yet. After a test payment, refresh this page.</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
            <p class="submit"><button type="submit" name="save_api_settings" class="button button-primary">Save API Keys</button></p>
        </form>
    </div>
    <?php if (class_exists('DG_Stripe_Billing') && DG_Stripe_Billing::enabled()) : ?>
    <div class="dg-panel" style="margin-top:20px;">
        <h2>Stripe manual replay</h2>
        <p style="color:#666;max-width:720px;">Saving the Stripe secret key alone does <strong>not</strong> create contacts. After a test payment, paste the Checkout Session ID here and click Process.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;max-width:720px;">
            <?php wp_nonce_field('dg_stripe_replay_session'); ?>
            <input type="hidden" name="action" value="dg_stripe_replay_session">
            <input type="text" name="stripe_session_id" class="large-text code" placeholder="cs_test_…" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_GET['session_id'] ?? ''))); ?>" style="flex:1;min-width:280px;">
            <button type="submit" class="button button-secondary">Process checkout session</button>
        </form>
    </div>
    <?php endif; ?>
</div>
