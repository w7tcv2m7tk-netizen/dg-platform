<?php
/**
 * Admin dark mode — assets, per-user preference, site profile accents.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Admin_Dark_Mode {

    const USER_META = 'dg_admin_dark_mode';
    const COOKIE = 'dg_admin_dark';
    const OPTION_DEFAULT = 'dg_admin_dark_default';

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_head', [$this, 'prevent_fouc'], 0);
        add_action('admin_enqueue_scripts', [$this, 'enqueue'], 5);
        add_action('admin_bar_menu', [$this, 'add_toolbar_toggle'], 999);
        add_action('wp_ajax_dg_toggle_dark_mode', [$this, 'ajax_toggle']);
    }

    /** @return string off|on|system */
    public static function site_default() {
        $value = get_option(self::OPTION_DEFAULT, 'off');
        return in_array($value, ['off', 'on', 'system'], true) ? $value : 'off';
    }

    public static function user_prefers_dark($user_id = null) {
        if (isset($_COOKIE[self::COOKIE])) {
            return $_COOKIE[self::COOKIE] === '1';
        }

        if ($user_id === null && is_user_logged_in()) {
            $user_id = get_current_user_id();
        }

        if ($user_id) {
            $meta = get_user_meta($user_id, self::USER_META, true);
            if ($meta === '1') {
                return true;
            }
            if ($meta === '0') {
                return false;
            }
        }

        $default = self::site_default();
        if ($default === 'on') {
            return true;
        }

        return false;
    }

    public function prevent_fouc() {
        if (!is_admin()) {
            return;
        }
        $dark = self::user_prefers_dark();
        $default = self::site_default();
        ?>
<script id="dg-dark-mode-boot">
(function(){
    var html = document.documentElement;
    var cookie = document.cookie.match(/<?php echo esc_js(self::COOKIE); ?>=([01])/);
    if (cookie) {
        if (cookie[1] === '1') html.classList.add('admin-dark-mode');
        else html.classList.remove('admin-dark-mode');
        return;
    }
    <?php if ($dark) : ?>
    html.classList.add('admin-dark-mode');
    <?php else : ?>
    html.classList.remove('admin-dark-mode');
    <?php endif; ?>
    if (!html.classList.contains('admin-dark-mode') && <?php echo wp_json_encode($default === 'system'); ?> && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        html.classList.add('admin-dark-mode');
    }
})();
</script>
        <?php
    }

    public function enqueue() {
        wp_enqueue_style(
            'dg-platform-admin',
            DG_PLATFORM_URL . 'assets/css/admin.css',
            [],
            DG_PLATFORM_VERSION
        );
        wp_enqueue_style(
            'dg-admin-dark-mode',
            DG_PLATFORM_URL . 'assets/css/admin-dark-mode.css',
            ['dg-platform-admin'],
            DG_PLATFORM_VERSION
        );
        wp_add_inline_style('dg-admin-dark-mode', self::theme_css_vars());

        wp_enqueue_script(
            'dg-admin-dark-mode',
            DG_PLATFORM_URL . 'assets/js/admin-dark-mode.js',
            [],
            DG_PLATFORM_VERSION,
            true
        );
        wp_localize_script('dg-admin-dark-mode', 'dgDarkMode', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dg_dark_mode'),
            'cookie' => self::COOKIE,
            'defaultMode' => self::site_default(),
            'isDark' => self::user_prefers_dark(),
            'canToggle' => current_user_can('manage_options'),
        ]);
    }

    public static function theme_css_vars() {
        if (class_exists('DG_Site_Profile') && DG_Site_Profile::is_digitalgate()) {
            return ':root,.admin-dark-mode{--dg-accent:#3B82F6;--dg-accent-muted:#60A5FA;--dg-adminbar-icon:#60A5FA;--dg-bg-base:#0A0F1A;--dg-bg-panel:#141B2B;--dg-bg-elevated:#1E293B;--dg-border:#334155;--dg-text:#E2E8F0;--dg-text-muted:#94A3B8;}';
        }
        if (class_exists('DG_Site_Profile') && DG_Site_Profile::is_roe_realty()) {
            return ':root,.admin-dark-mode{--dg-accent:#C9A46C;--dg-accent-muted:#E0D6CC;--dg-adminbar-icon:#B9A48A;--dg-bg-base:#1c1c1e;--dg-bg-panel:#2c2c2e;--dg-bg-elevated:#3a3a3c;--dg-border:#48484a;--dg-text:#e6edf3;--dg-text-muted:#a1a1aa;}';
        }
        return ':root,.admin-dark-mode{--dg-accent:#3B82F6;--dg-accent-muted:#60A5FA;--dg-adminbar-icon:#B9A48A;--dg-bg-base:#1c1c1e;--dg-bg-panel:#2c2c2e;--dg-bg-elevated:#3a3a3c;--dg-border:#48484a;--dg-text:#e6edf3;--dg-text-muted:#94A3B8;}';
    }

    public function add_toolbar_toggle($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }
        $is_dark = self::user_prefers_dark();
        $wp_admin_bar->add_node([
            'id' => 'dg-dark-toggle',
            'title' => ($is_dark ? '☀️ Light Mode' : '🌙 Dark Mode'),
            'href' => '#',
            'meta' => [
                'class' => 'dg-dark-toggle-btn',
                'title' => 'Toggle admin dark mode',
            ],
        ]);
    }

    public function ajax_toggle() {
        check_ajax_referer('dg_dark_mode', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Forbidden', 403);
        }
        $is_dark = !empty($_POST['is_dark']) && $_POST['is_dark'] === '1';
        update_user_meta(get_current_user_id(), self::USER_META, $is_dark ? '1' : '0');
        wp_send_json_success(['is_dark' => $is_dark]);
    }
}
