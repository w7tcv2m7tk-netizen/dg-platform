<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap dg-platform-wrap">
    <h1>🔑 API Settings</h1>
    <?php if (isset($_GET['saved'])) : ?><div class="notice notice-success"><p>API settings saved.</p></div><?php endif; ?>
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
