<?php
/**
 * Warn when legacy Roe plugins duplicate DG Platform functionality.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Legacy_Notices {

    private static $legacy_plugins = [
        'roe-realty-automation.php' => [
            'label' => 'Roe Realty Follow-up Automation',
            'reason' => 'Email follow-ups are built into DG Platform (v10.0.5+). Running both sends duplicate emails.',
        ],
        'fluent-snippets/fluent-snippets.php' => [
            'label' => 'Fluent Snippets',
            'reason' => 'Use DG Platform → Site Tools → Snippets, or migrate logic into DG modules. Deactivate once migrated.',
        ],
        'fluent-smtp/fluent-smtp.php' => [
            'label' => 'Fluent SMTP',
            'reason' => 'SMTP is built into DG Platform → Site Tools → Email. Running both may conflict.',
        ],
        'wp-smushit/wp-smush.php' => [
            'label' => 'Smush',
            'reason' => 'Image compression is built into DG Platform → Site Tools → Images.',
        ],
        'google-site-kit/google-site-kit.php' => [
            'label' => 'Google Site Kit',
            'reason' => 'PageSpeed, SEO, and Analytics Pro cover most Site Kit features. Use Site Tools → Analytics.',
        ],
    ];

    public static function init() {
        add_action('admin_notices', [__CLASS__, 'maybe_show_notices']);
        add_action('admin_post_dg_deactivate_legacy_plugin', [__CLASS__, 'handle_deactivate']);
    }

    public static function maybe_show_notices() {
        if (!current_user_can('manage_options')) {
            return;
        }

        foreach (self::$legacy_plugins as $plugin => $info) {
            if (!self::is_plugin_active($plugin)) {
                continue;
            }
            $deactivate_url = wp_nonce_url(
                admin_url('admin-post.php?action=dg_deactivate_legacy_plugin&plugin=' . rawurlencode($plugin)),
                'dg_deactivate_legacy_' . md5($plugin)
            );
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong>DG Platform:</strong>
                    <?php echo esc_html($info['label']); ?> —
                    <?php echo esc_html($info['reason']); ?>
                    <a href="<?php echo esc_url($deactivate_url); ?>" class="button button-small" style="margin-left:8px;">Deactivate now</a>
                    <a href="<?php echo esc_url(admin_url('plugins.php')); ?>">View all plugins</a>
                </p>
            </div>
            <?php
        }

        if (self::is_plugin_active('fluent-booking/fluent-booking.php')) {
            ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <strong>DG Platform:</strong>
                    Fluent Booking is active. Property appraisal bookings are handled by DG Platform at
                    <code>/property-appraisal/</code> — you can deactivate Fluent Booking if it is no longer needed.
                </p>
            </div>
            <?php
        }
    }

    public static function handle_deactivate() {
        if (!current_user_can('activate_plugins')) {
            wp_die('Unauthorized');
        }

        $plugin = sanitize_text_field(wp_unslash($_GET['plugin'] ?? ''));
        if (!$plugin || !isset(self::$legacy_plugins[$plugin])) {
            wp_die('Invalid plugin.');
        }

        check_admin_referer('dg_deactivate_legacy_' . md5($plugin));

        if (!function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        deactivate_plugins($plugin);
        wp_safe_redirect(admin_url('plugins.php?deactivated=true'));
        exit;
    }

    private static function is_plugin_active($plugin) {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active($plugin);
    }
}

DG_Legacy_Notices::init();
