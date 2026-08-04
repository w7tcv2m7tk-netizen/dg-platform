<?php
/**
 * Automotive / Dealership module — inventory, test drives, sales pipeline.
 *
 * @package DG_Platform
 * @version 10.11.0
 */

if (!defined('ABSPATH')) exit;

class DG_Module_Dealership {
    private static $instance = null;

    public static function get_instance($platform = null) {
        if (null === self::$instance) self::$instance = new self($platform);
        return self::$instance;
    }

    private function __construct($platform) {
        if ($platform) $platform->register_module('dealership', $this);
        foreach (['class-dealer-permissions.php','class-dealer-pipeline.php','class-dealer-inventory.php','class-dealer-reports.php','class-dealer-admin-views.php'] as $f) {
            require_once __DIR__ . '/includes/' . $f;
        }
        add_action('init', [$this, 'init'], 5);
        add_action('dg_platform_register_menus', [$this, 'register_menus'], 15);
        add_action('admin_post_dg_dealer_add_lead', [$this, 'handle_add_lead']);
        add_action('save_post_dg_vehicle', [$this, 'save_vehicle_meta']);
        add_action('add_meta_boxes', [$this, 'vehicle_meta_box']);
        add_filter('dg_platform_dashboard_widgets', [$this, 'dashboard_widgets']);
        add_filter('dg_platform_capabilities', function ($caps) {
            return array_merge($caps, ['dg_dealer_view_inventory', 'dg_dealer_manage_inventory']);
        });
        add_filter('dg_analytics_pro/metrics', [$this, 'analytics_metrics']);
    }

    public function init() {
        $this->create_tables();
        DG_Dealer_Inventory::register_post_type();
    }

    public function create_tables() {
        global $wpdb;
        $table = $wpdb->prefix . 'dg_dealer_leads';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) return;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT, contact_id bigint(20) NOT NULL,
            vehicle_id bigint(20) DEFAULT NULL, interest_type varchar(50) DEFAULT 'Test drive',
            stage varchar(50) DEFAULT 'inquiry', status varchar(20) DEFAULT 'active',
            scheduled_at datetime DEFAULT NULL, notes text, owner_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id), KEY stage (stage)
        ) {$wpdb->get_charset_collate()};");
    }

    public function vehicle_meta_box() {
        add_meta_box('dg_vehicle_details', 'Vehicle details', function ($post) {
            wp_nonce_field('dg_vehicle_meta', 'dg_vehicle_meta_nonce');
            ?>
            <p><label>Make <input type="text" name="dg_vehicle_make" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_vehicle_make', true)); ?>"></label></p>
            <p><label>Model <input type="text" name="dg_vehicle_model" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_vehicle_model', true)); ?>"></label></p>
            <p><label>Year <input type="number" name="dg_vehicle_year" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_vehicle_year', true)); ?>"></label></p>
            <p><label>Price ($) <input type="number" name="dg_vehicle_price" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_vehicle_price', true)); ?>"></label></p>
            <p><label>Stock # <input type="text" name="dg_vehicle_stock" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_vehicle_stock', true)); ?>"></label></p>
            <p><label>Status <select name="dg_vehicle_status"><option value="available">Available</option><option value="reserved" <?php selected(get_post_meta($post->ID, 'dg_vehicle_status', true), 'reserved'); ?>>Reserved</option><option value="sold" <?php selected(get_post_meta($post->ID, 'dg_vehicle_status', true), 'sold'); ?>>Sold</option></select></label></p>
            <?php
        }, 'dg_vehicle', 'normal', 'high');
    }

    public function save_vehicle_meta($post_id) {
        if (!isset($_POST['dg_vehicle_meta_nonce']) || !wp_verify_nonce($_POST['dg_vehicle_meta_nonce'], 'dg_vehicle_meta')) return;
        foreach (['make','model','year','price','stock','status'] as $k) {
            $field = 'dg_vehicle_' . $k;
            if (isset($_POST[$field])) update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
        }
        $make = get_post_meta($post_id, 'dg_vehicle_make', true);
        $model = get_post_meta($post_id, 'dg_vehicle_model', true);
        $year = get_post_meta($post_id, 'dg_vehicle_year', true);
        if ($make || $model) {
            wp_update_post(['ID' => $post_id, 'post_title' => trim("$year $make $model")]);
        }
    }

    public function register_menus() {
        if (!DG_Dealer_Permissions::can_view()) return;
        add_submenu_page('dg-platform', 'Automotive', '🚗 Automotive', DG_Dealer_Permissions::menu_cap(), 'dg-dealer-dashboard', ['DG_Dealer_Admin_Views', 'render_dashboard']);
        if (DG_Dealer_Permissions::can_manage()) {
            add_submenu_page('dg-platform', 'New Lead', '➕ Auto Lead', DG_Dealer_Permissions::menu_cap(), 'dg-dealer-add', ['DG_Dealer_Admin_Views', 'render_add']);
        }
    }

    public function handle_add_lead() {
        if (!DG_Dealer_Permissions::can_manage() || !check_admin_referer('dg_dealer_add_lead')) wp_die('Unauthorized');
        if (!empty($_POST['scheduled_at'])) $_POST['scheduled_at'] = str_replace('T', ' ', sanitize_text_field($_POST['scheduled_at'])) . ':00';
        DG_Dealer_Pipeline::create($_POST);
        wp_safe_redirect(admin_url('admin.php?page=dg-dealer-dashboard&added=1'));
        exit;
    }

    public function dashboard_widgets($widgets) {
        if (!DG_Dealer_Permissions::can_view()) return $widgets;
        $s = DG_Dealer_Reports::summary();
        $widgets[] = ['id' => 'dealer_stock', 'label' => 'Vehicles in stock', 'value' => $s['vehicles'], 'color' => '#3B82F6'];
        return $widgets;
    }

    public function analytics_metrics($metrics) {
        if (!DG_Dealer_Permissions::can_view()) return $metrics;
        $s = DG_Dealer_Reports::summary();
        $metrics['dealer_leads'] = ['value' => (float) $s['leads'], 'module' => 'dealership'];
        $metrics['dealer_stock'] = ['value' => (float) $s['vehicles'], 'module' => 'dealership'];
        return $metrics;
    }
}
