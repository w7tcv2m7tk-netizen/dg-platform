<?php
/**
 * Commercial module — listings, tenants, lease pipeline.
 *
 * @package DG_Platform
 * @version 10.11.0
 */

if (!defined('ABSPATH')) exit;

class DG_Module_Commercial {
    private static $instance = null;

    public static function get_instance($platform = null) {
        if (null === self::$instance) self::$instance = new self($platform);
        return self::$instance;
    }

    private function __construct($platform) {
        if ($platform) $platform->register_module('commercial', $this);
        foreach (['class-com-permissions.php','class-com-listings.php','class-com-pipeline.php','class-com-reports.php','class-com-admin-views.php'] as $f) {
            require_once __DIR__ . '/includes/' . $f;
        }
        add_action('init', [$this, 'init'], 5);
        add_action('dg_platform_register_menus', [$this, 'register_menus'], 15);
        add_action('admin_post_dg_com_add_tenancy', [$this, 'handle_add']);
        add_action('save_post_dg_commercial', [$this, 'save_listing_meta']);
        add_action('add_meta_boxes', [$this, 'listing_meta_box']);
        add_filter('dg_platform_dashboard_widgets', [$this, 'dashboard_widgets']);
        add_filter('dg_platform_capabilities', function ($caps) {
            return array_merge($caps, ['dg_com_view_listings', 'dg_com_manage_listings']);
        });
        add_filter('dg_analytics_pro/metrics', [$this, 'analytics_metrics']);
    }

    public function init() {
        $this->create_tables();
        DG_Com_Listings::register_post_type();
    }

    public function create_tables() {
        global $wpdb;
        $table = $wpdb->prefix . 'dg_com_tenancies';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) return;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT, contact_id bigint(20) NOT NULL,
            listing_id bigint(20) DEFAULT NULL, business_name varchar(200) DEFAULT NULL,
            stage varchar(50) DEFAULT 'inquiry', status varchar(20) DEFAULT 'active',
            rent_pcm decimal(15,2) DEFAULT 0, sqm decimal(10,2) DEFAULT 0,
            lease_start date DEFAULT NULL, lease_end date DEFAULT NULL,
            notes text, owner_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id), KEY stage (stage)
        ) {$wpdb->get_charset_collate()};");
    }

    public function listing_meta_box() {
        add_meta_box('dg_com_details', 'Listing details', function ($post) {
            wp_nonce_field('dg_com_listing_meta', 'dg_com_listing_meta_nonce');
            ?>
            <p><label>Address <input type="text" name="dg_com_address" class="large-text" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_com_address', true)); ?>"></label></p>
            <p><label>Type <select name="dg_com_type"><?php foreach (DG_Com_Pipeline::property_types() as $t) : ?><option <?php selected(get_post_meta($post->ID, 'dg_com_type', true), $t); ?>><?php echo esc_html($t); ?></option><?php endforeach; ?></select></label></p>
            <p><label>Sqm <input type="number" name="dg_com_sqm" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_com_sqm', true)); ?>"></label></p>
            <p><label>Rent ($/month) <input type="number" name="dg_com_rent_pcm" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_com_rent_pcm', true)); ?>"></label></p>
            <p><label>Status <select name="dg_com_status"><option value="available">Available</option><option value="under_offer" <?php selected(get_post_meta($post->ID, 'dg_com_status', true), 'under_offer'); ?>>Under offer</option><option value="leased" <?php selected(get_post_meta($post->ID, 'dg_com_status', true), 'leased'); ?>>Leased</option></select></label></p>
            <?php
        }, 'dg_commercial', 'normal', 'high');
    }

    public function save_listing_meta($post_id) {
        if (!isset($_POST['dg_com_listing_meta_nonce']) || !wp_verify_nonce($_POST['dg_com_listing_meta_nonce'], 'dg_com_listing_meta')) return;
        foreach (['address','type','sqm','rent_pcm','status'] as $k) {
            $field = 'dg_com_' . $k;
            if (isset($_POST[$field])) update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
        }
        $addr = get_post_meta($post_id, 'dg_com_address', true);
        if ($addr && get_the_title($post_id) === 'Auto Draft') {
            wp_update_post(['ID' => $post_id, 'post_title' => $addr]);
        }
    }

    public function register_menus() {
        if (!DG_Com_Permissions::can_view()) return;
        add_submenu_page('dg-platform', 'Commercial', '🏢 Commercial', DG_Com_Permissions::menu_cap(), 'dg-com-dashboard', ['DG_Com_Admin_Views', 'render_dashboard']);
        if (DG_Com_Permissions::can_manage()) {
            add_submenu_page('dg-platform', 'New Tenancy', '➕ Tenancy Lead', DG_Com_Permissions::menu_cap(), 'dg-com-add', ['DG_Com_Admin_Views', 'render_add']);
        }
    }

    public function handle_add() {
        if (!DG_Com_Permissions::can_manage() || !check_admin_referer('dg_com_add_tenancy')) wp_die('Unauthorized');
        DG_Com_Pipeline::create($_POST);
        wp_safe_redirect(admin_url('admin.php?page=dg-com-dashboard&added=1'));
        exit;
    }

    public function dashboard_widgets($widgets) {
        if (!DG_Com_Permissions::can_view()) return $widgets;
        $s = DG_Com_Reports::summary();
        $widgets[] = ['id' => 'com_listings', 'label' => 'Commercial listings', 'value' => $s['listings'], 'color' => '#6366F1'];
        return $widgets;
    }

    public function analytics_metrics($metrics) {
        if (!DG_Com_Permissions::can_view()) return $metrics;
        $s = DG_Com_Reports::summary();
        $metrics['com_listings'] = ['value' => (float) $s['listings'], 'module' => 'commercial'];
        $metrics['com_rent_roll'] = ['value' => (float) $s['rent_roll'], 'module' => 'commercial'];
        return $metrics;
    }
}
