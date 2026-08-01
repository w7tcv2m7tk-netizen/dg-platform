<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap dg-platform-wrap">
    <h1>🔑 API Settings</h1>
    <?php if (isset($_GET['saved'])) : ?><div class="notice notice-success"><p>API settings saved.</p></div><?php endif; ?>
    <?php if (isset($_GET['dev_key_regenerated'])) : ?><div class="notice notice-success"><p>Dev API key regenerated. Update your Cursor MCP config with the new key.</p></div><?php endif; ?>
    <div class="dg-panel" style="margin-bottom:20px;">
        <h2>Cursor / Dev API</h2>
        <p style="color:#666;">Use this key so Cursor MCP can query live CRM data while you build. Never commit it to git or expose it on the frontend. If this key was ever shared, click <strong>Regenerate key</strong> and update <code>~/.cursor/mcp.json</code>.</p>
        <?php
        $dev_key = class_exists('DG_Dev_API') ? DG_Dev_API::get_or_create_key() : '';
        $regenerate_url = wp_nonce_url(admin_url('admin-post.php?action=dg_regenerate_dev_api_key'), 'dg_regenerate_dev_api_key');
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
                    </ul>
                    <?php if (class_exists('DG_Site_Profile')) : ?>
                        <p class="description">This site: <strong><?php echo esc_html(DG_Site_Profile::label()); ?></strong></p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Cursor MCP</th>
                <td>
                    <p class="description">In the repo: run <code>npm install</code> in <code>mcp-server/</code>, paste this key into <code>.cursor/mcp.json</code>, then reload Cursor.</p>
                    <p><a href="<?php echo esc_url($regenerate_url); ?>" class="button" onclick="return confirm('Regenerate key? Update Cursor MCP config afterwards.');">Regenerate key</a></p>
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
                <tr><th colspan="2"><h3>Marketing Integrations</h3></th></tr>
                <tr><th>Rank Math API Key</th><td><input type="text" name="rankmath" value="<?php echo esc_attr(DG_Integrations::get_api_key('rankmath')); ?>" class="regular-text"></td></tr>
                <tr><th>Google Search Console</th><td><input type="text" name="gsc" value="<?php echo esc_attr(DG_Integrations::get_api_key('gsc')); ?>" class="regular-text"></td></tr>
                <tr><th>Google Business Profile</th><td><input type="text" name="gbp" value="<?php echo esc_attr(DG_Integrations::get_api_key('gbp')); ?>" class="regular-text"></td></tr>
                <tr><th>FluentCRM API Key</th><td><input type="text" name="fluentcrm" value="<?php echo esc_attr(DG_Integrations::get_api_key('fluentcrm')); ?>" class="regular-text"></td></tr>
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
