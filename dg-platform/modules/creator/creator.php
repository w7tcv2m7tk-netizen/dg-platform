<?php
/**
 * Creator module — Aetherra content & audience CRM.
 *
 * @package DG_Platform
 * @version 10.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Module_Creator {

    private static $instance = null;
    private $platform = null;
    private $module_key = 'creator';

    public static function get_instance($platform = null) {
        if (null === self::$instance) {
            self::$instance = new self($platform);
        }
        return self::$instance;
    }

    private function __construct($platform) {
        $this->platform = $platform;
        if ($platform) {
            $platform->register_module($this->module_key, $this);
        }
        $this->load_includes();
        $this->init_hooks();
    }

    private function load_includes() {
        $dir = __DIR__ . '/includes/';
        foreach ([
            'class-creator-permissions.php',
            'class-creator-reports.php',
            'class-creator-admin-views.php',
            'class-creator-dev-api.php',
        ] as $file) {
            $path = $dir . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }

    private function init_hooks() {
        add_action('dg_platform_register_menus', [$this, 'register_platform_menus'], 15);
        add_action('dg_platform_quick_actions', [$this, 'quick_actions']);
        add_filter('dg_platform_dashboard_widgets', [$this, 'dashboard_widgets']);
    }

    public function register_platform_menus() {
        if (!DG_Creator_Permissions::can_view()) {
            return;
        }
        add_submenu_page(
            'dg-platform',
            'Creator',
            '✨ Creator',
            DG_Creator_Permissions::menu_cap(),
            'dg-creator-dashboard',
            ['DG_Creator_Admin_Views', 'render_dashboard']
        );
    }

    public function quick_actions($actions) {
        if (!DG_Creator_Permissions::can_view()) {
            return $actions;
        }
        $actions[] = [
            'label' => 'New post',
            'url' => admin_url('post-new.php'),
            'icon' => '✏️',
        ];
        return $actions;
    }

    public function dashboard_widgets($widgets) {
        if (!class_exists('DG_Creator_Reports') || !DG_Creator_Permissions::can_view()) {
            return $widgets;
        }
        $summary = DG_Creator_Reports::summary();
        $widgets[] = [
            'id' => 'creator_posts',
            'label' => 'Published posts',
            'value' => $summary['published_posts'],
            'color' => '#A78BFA',
        ];
        return $widgets;
    }
}
