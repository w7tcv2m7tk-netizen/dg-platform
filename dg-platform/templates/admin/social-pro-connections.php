<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="dg-panel" style="margin-top:20px;">
    <h2>Platform connections</h2>
    <p class="description">Connect each platform once. OAuth redirect URL for all apps: <code><?php echo esc_html(DG_Social_Pro_Settings::oauth_redirect_uri()); ?></code></p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('dg_social_pro_settings'); ?>
        <input type="hidden" name="action" value="dg_save_social_pro_settings">

        <h3>App credentials</h3>
        <table class="form-table">
            <tr><th colspan="2"><strong>Meta (Facebook + Instagram)</strong></th></tr>
            <tr>
                <th>App ID</th>
                <td><input type="text" name="facebook_app_id" class="regular-text" value="<?php echo esc_attr($settings['facebook_app_id']); ?>"></td>
            </tr>
            <tr>
                <th>App Secret</th>
                <td><input type="password" name="facebook_app_secret" class="regular-text" value="<?php echo esc_attr($settings['facebook_app_secret']); ?>" autocomplete="new-password"></td>
            </tr>
            <tr><th colspan="2"><strong>LinkedIn</strong></th></tr>
            <tr>
                <th>Client ID</th>
                <td><input type="text" name="linkedin_client_id" class="regular-text" value="<?php echo esc_attr($settings['linkedin_client_id']); ?>"></td>
            </tr>
            <tr>
                <th>Client Secret</th>
                <td><input type="password" name="linkedin_client_secret" class="regular-text" value="<?php echo esc_attr($settings['linkedin_client_secret']); ?>" autocomplete="new-password"></td>
            </tr>
            <tr><th colspan="2"><strong>X (Twitter)</strong></th></tr>
            <tr>
                <th>Client ID</th>
                <td><input type="text" name="x_client_id" class="regular-text" value="<?php echo esc_attr($settings['x_client_id']); ?>"></td>
            </tr>
            <tr>
                <th>Client Secret</th>
                <td><input type="password" name="x_client_secret" class="regular-text" value="<?php echo esc_attr($settings['x_client_secret']); ?>" autocomplete="new-password"></td>
            </tr>
            <tr><th colspan="2"><strong>Pinterest</strong></th></tr>
            <tr>
                <th>App ID</th>
                <td><input type="text" name="pinterest_app_id" class="regular-text" value="<?php echo esc_attr($settings['pinterest_app_id']); ?>"></td>
            </tr>
            <tr>
                <th>App Secret</th>
                <td><input type="password" name="pinterest_app_secret" class="regular-text" value="<?php echo esc_attr($settings['pinterest_app_secret']); ?>" autocomplete="new-password"></td>
            </tr>
            <tr><th colspan="2"><strong>Defaults</strong></th></tr>
            <tr>
                <th>Default link URL</th>
                <td><input type="url" name="default_link" class="large-text" value="<?php echo esc_attr($settings['default_link'] ?: home_url('/')); ?>"></td>
            </tr>
        </table>
        <p><button type="submit" class="button button-primary">Save credentials</button></p>
    </form>
</div>

<div class="dg-panel dg-social-connections-grid">
    <?php foreach ($platforms as $key => $def) :
        $conn = $connections[$key] ?? [];
        $connected = !empty($conn['access_token']) || ($key === 'instagram' && !empty($connections['facebook']['access_token']));
        if ($key === 'instagram') {
            $conn = $connections['instagram'] ?? [];
            $connected = !empty($conn['instagram_account_id']);
        }
        $oauth_url = DG_Social_Pro_OAuth::authorize_url($key);
        ?>
        <div class="dg-social-connection-card" style="--platform-color:<?php echo esc_attr($def['color']); ?>">
            <div class="dg-social-connection-header">
                <span class="dg-social-platform-icon"><?php echo esc_html($def['icon']); ?></span>
                <h3><?php echo esc_html($def['label']); ?></h3>
                <span class="dg-social-connection-badge <?php echo $connected ? 'is-on' : 'is-off'; ?>">
                    <?php echo $connected ? 'Connected' : 'Not connected'; ?>
                </span>
            </div>
            <p class="description"><?php echo esc_html($def['help']); ?></p>
            <?php if ($connected) : ?>
                <p><strong><?php echo esc_html($conn['account_name'] ?? 'Connected'); ?></strong></p>
                <?php if (!empty($conn['board_name'])) : ?>
                    <p class="description">Board: <?php echo esc_html($conn['board_name']); ?></p>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                    <?php wp_nonce_field('dg_disconnect_social_platform'); ?>
                    <input type="hidden" name="action" value="dg_disconnect_social_platform">
                    <input type="hidden" name="platform" value="<?php echo esc_attr($key); ?>">
                    <button type="submit" class="button" onclick="return confirm('Disconnect <?php echo esc_js($def['label']); ?>?');">Disconnect</button>
                </form>
            <?php elseif ($oauth_url) : ?>
                <a href="<?php echo esc_url($oauth_url); ?>" class="button button-primary">Connect with OAuth</a>
            <?php else : ?>
                <p class="description">Connect via Facebook first.</p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="dg-panel">
    <h3>Manual token entry (advanced)</h3>
    <p class="description">If OAuth is not available, paste a long-lived access token from your developer console.</p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('dg_social_pro_settings'); ?>
        <input type="hidden" name="action" value="dg_save_social_pro_settings">
        <table class="form-table">
            <tr>
                <th>Platform</th>
                <td>
                    <select name="manual_platform">
                        <?php foreach ($platforms as $key => $def) : ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($def['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th>Access token</th>
                <td><input type="text" name="manual_access_token" class="large-text"></td>
            </tr>
            <tr>
                <th>Account name</th>
                <td><input type="text" name="manual_account_name" class="regular-text" placeholder="Display name"></td>
            </tr>
            <tr>
                <th>Page ID (Facebook)</th>
                <td><input type="text" name="manual_page_id" class="regular-text"></td>
            </tr>
            <tr>
                <th>Page token (Facebook)</th>
                <td><input type="text" name="manual_page_access_token" class="large-text"></td>
            </tr>
            <tr>
                <th>IG account ID</th>
                <td><input type="text" name="manual_instagram_account_id" class="regular-text"></td>
            </tr>
            <tr>
                <th>Author URN (LinkedIn)</th>
                <td><input type="text" name="manual_author_urn" class="regular-text" placeholder="urn:li:person:… or urn:li:organization:…"></td>
            </tr>
            <tr>
                <th>Board ID (Pinterest)</th>
                <td><input type="text" name="manual_board_id" class="regular-text"></td>
            </tr>
        </table>
        <p><button type="submit" class="button">Save manual connection</button></p>
    </form>
</div>
