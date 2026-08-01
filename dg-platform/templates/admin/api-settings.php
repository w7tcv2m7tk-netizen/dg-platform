<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap dg-platform-wrap">
    <h1>🔑 API Settings</h1>
    <?php if (isset($_GET['saved'])) : ?><div class="notice notice-success"><p>API settings saved.</p></div><?php endif; ?>
    <?php if (isset($_GET['dev_key_regenerated'])) : ?><div class="notice notice-success"><p>Dev API key regenerated. Update your Cursor MCP config with the new key.</p></div><?php endif; ?>
    <div class="dg-panel" style="margin-bottom:20px;">
        <h2>Cursor MCP (Dev API)</h2>
        <p style="color:#666;max-width:820px;">Cursor is your code editor on your Mac — not inside WordPress. This key lets Cursor query live CRM data from DigitalGate or Roe Realty while you build.</p>
        <ol style="color:#666;max-width:820px;line-height:1.7;">
            <li>In WordPress: <strong>DG Platform → API Settings</strong> (this page) — copy the Dev API Key below.</li>
            <li>On your Mac: open <code>~/.cursor/mcp.json</code> and paste the key into <code>DG_DEV_API_KEY</code> for the site you want (<code>https://digitalgate.com.au</code> or <code>https://roerealty.com.au</code>).</li>
            <li>Restart Cursor, then ask the agent to run tools like <code>get_marketing_summary</code> (DigitalGate) or <code>get_pipeline_summary</code> (Roe Realty).</li>
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
                        <li>Roe Realty: <code><?php echo esc_html(rest_url(DG_REST_NAMESPACE . '/leads/summary')); ?></code></li>
                        <li>DigitalGate: <code><?php echo esc_html(rest_url(DG_REST_NAMESPACE . '/marketing/summary')); ?></code></li>
                        <li>Agency audit webhook: <code><?php echo esc_html(rest_url('dg/v1/audit-webhook')); ?></code></li>
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
                <tr><th>Stripe Secret Key</th><td><input type="text" name="stripe_secret" value="<?php echo esc_attr(DG_Integrations::get_api_key('stripe_secret')); ?>" class="regular-text"></td></tr>
            </table>
            <p class="submit"><button type="submit" name="save_api_settings" class="button button-primary">Save API Keys</button></p>
        </form>
    </div>
</div>
