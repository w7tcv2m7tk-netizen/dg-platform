<?php
/**
 * Admin menu grouping — Core, Industry, Premium, Add-ons, Platform.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Admin_Menu {

    /** @var array<string,string> */
    private static $slug_groups = [];

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'organize_submenu'], 999);
        add_action('admin_menu', [__CLASS__, 'ensure_dashboard_first'], 99999);
        add_action('admin_init', [__CLASS__, 'redirect_separator_pages'], 1);
        add_action('admin_head', [__CLASS__, 'separator_styles']);
        add_filter('parent_file', [__CLASS__, 'keep_platform_parent']);
        add_filter('submenu_file', [__CLASS__, 'keep_platform_submenu']);
    }

    /**
     * Register a submenu slug into a menu group (for modules).
     *
     * @param string $slug  Submenu slug (3rd param to add_submenu_page).
     * @param string $group core|industry|premium|addons|platform
     */
    public static function register_slug($slug, $group) {
        self::$slug_groups[$slug] = $group;
    }

    /** @return array<string,array{label:string,order:int}> */
    private static function groups() {
        return [
            'core' => ['label' => 'Core', 'order' => 10],
            'industry' => ['label' => 'Industry', 'order' => 20],
            'premium' => ['label' => 'Premium', 'order' => 30],
            'addons' => ['label' => 'Add-ons', 'order' => 40],
            'platform' => ['label' => 'Platform', 'order' => 50],
        ];
    }

    /** @return array<string,string> */
    private static function default_slug_map() {
        return array_merge([
            // Core
            'dg-platform' => 'core',
            'dg-platform-contacts' => 'core',
            'dg-platform-tasks' => 'core',
            'dg-platform-calendar' => 'core',
            'dg-platform-search' => 'core',
            'dg-platform-activity' => 'core',
            'dg-platform-reviews' => 'core',
            'dg-platform-automations' => 'core',
            'dg-platform-custom-fields' => 'core',
            'dg-platform-reports' => 'core',
            'dg-platform-documents' => 'core',
            // Premium
            'dg-platform-seo' => 'premium',
            'dg-platform-ai-visibility' => 'premium',
            'dg-platform-automation-pro' => 'premium',
            'dg-platform-analytics-pro' => 'premium',
            'dg-platform-social-pro' => 'premium',
            // Add-ons
            'dg-platform-site-tools' => 'addons',
            'dg-platform-voice' => 'addons',
            'dg-platform-onboarding' => 'addons',
            // Platform
            'dg-platform-modules' => 'platform',
            'dg-platform-roles' => 'platform',
            'dg-platform-audit-log' => 'platform',
            'dg-platform-api' => 'platform',
            // Real Estate industry
            'dg-re-dashboard' => 'industry',
            'edit.php?post_type=property' => 'industry',
            'edit.php?post_type=agent' => 'industry',
            'dg-re-contacts' => 'industry',
            'dg-re-vendor-leads' => 'industry',
            'dg-re-vendor-pipeline' => 'industry',
            'dg-re-buyer-leads' => 'industry',
            'dg-re-buyer-pipeline' => 'industry',
            'dg-re-pipeline-reports' => 'industry',
            'dg-re-bookings' => 'industry',
            'dg-re-booking-settings' => 'industry',
            'dg-re-email-templates' => 'industry',
            'dg-re-import' => 'industry',
            'dg-re-property-files' => 'industry',
            // Marketing industry (DigitalGate)
            'dg-marketing-dashboard' => 'industry',
            'dg-platform-clients' => 'industry',
            'dg-marketing-client-pipeline' => 'industry',
            'dg-marketing-pipeline-reports' => 'industry',
            'dg-marketing-import' => 'industry',
            'dg-platform-audits' => 'industry',
            'dg-platform-ai' => 'industry',
            'dg-marketing-email-templates' => 'industry',
            // Accommodation
            'dg-acc-dashboard' => 'industry',
            'edit.php?post_type=dg_accommodation' => 'industry',
            'edit.php?post_type=dg_booking' => 'industry',
            'dg-admin-calendar' => 'industry',
            'edit.php?post_type=dg_guest' => 'industry',
            'edit-tags.php?taxonomy=dg_accommodation_type&post_type=dg_accommodation' => 'industry',
            'dg-acc-housekeeping' => 'industry',
            'edit.php?post_type=dg_cleaning_report' => 'industry',
            'dg-booking-settings' => 'industry',
            'dg-stripe-settings' => 'industry',
            'dg-force-sync-all' => 'industry',
            // Preview industry modules
            'dg-fin-dashboard' => 'industry',
            'dg-fin-add' => 'industry',
            'dg-svc-dashboard' => 'industry',
            'dg-svc-add' => 'industry',
            'dg-dealer-dashboard' => 'industry',
            'dg-dealer-add' => 'industry',
            'dg-com-dashboard' => 'industry',
            'dg-com-add' => 'industry',
            'dg-creator-dashboard' => 'industry',
        ], self::$slug_groups);
    }

    /** @return array<int,array{title:string,url:string,group?:string}> */
    public static function launcher_apps() {
        global $submenu;
        $apps = [];

        if (empty($submenu['dg-platform']) || !is_array($submenu['dg-platform'])) {
            return apply_filters('dg_platform_launcher_apps', $apps);
        }

        $map = self::default_slug_map();
        $current_group = 'core';

        foreach ($submenu['dg-platform'] as $item) {
            if (!is_array($item) || empty($item[2])) {
                continue;
            }

            $slug = (string) $item[2];
            if ($slug === 'dg-platform') {
                continue;
            }

            if (strpos($slug, 'dg-sep-') === 0) {
                $current_group = str_replace('dg-sep-', '', $slug);
                continue;
            }

            if (!self::slug_allowed($slug)) {
                continue;
            }

            $cap = isset($item[1]) ? (string) $item[1] : 'read';
            if ($cap !== '' && !current_user_can($cap)) {
                continue;
            }

            $url = (strpos($slug, '.php') !== false || strpos($slug, '?') !== false)
                ? admin_url($slug)
                : admin_url('admin.php?page=' . $slug);

            $apps[] = [
                'title' => wp_strip_all_tags((string) ($item[0] ?? 'App')),
                'url' => $url,
                'group' => $map[$slug] ?? $current_group,
                'slug' => $slug,
            ];
        }

        return apply_filters('dg_platform_launcher_apps', $apps);
    }

    public static function organize_submenu() {
        global $submenu;
        if (empty($submenu['dg-platform']) || !is_array($submenu['dg-platform'])) {
            return;
        }

        $map = apply_filters('dg_platform_admin_menu_groups', self::default_slug_map());
        $groups = self::groups();
        $hidden = ['dg-re-vendor-lead', 'dg-re-buyer-lead'];
        $bucket = [];
        foreach ($groups as $key => $meta) {
            $bucket[$key] = [];
        }
        $unknown = [];

        foreach ($submenu['dg-platform'] as $item) {
            $slug = isset($item[2]) ? (string) $item[2] : '';
            if ($slug === '' || in_array($slug, $hidden, true)) {
                continue;
            }
            if (!self::slug_allowed($slug)) {
                continue;
            }
            $group = $map[$slug] ?? null;
            if ($group && isset($bucket[$group])) {
                $bucket[$group][] = $item;
            } else {
                $unknown[] = $item;
            }
        }

        $ordered = [];
        uasort($groups, function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        foreach ($groups as $key => $meta) {
            if (empty($bucket[$key])) {
                continue;
            }
            $ordered[] = self::separator($meta['label'], $key);
            foreach ($bucket[$key] as $item) {
                $ordered[] = $item;
            }
        }

        foreach ($unknown as $item) {
            $ordered[] = $item;
        }

        $ordered = self::pin_dashboard_first($ordered);
        $submenu['dg-platform'] = $ordered;
    }

    /**
     * Top-level "DG Platform" menu link uses the first submenu slug — keep Dashboard first.
     *
     * @param array<int,mixed> $items
     * @return array<int,mixed>
     */
    private static function pin_dashboard_first($items) {
        $dashboard = null;
        $others = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                $others[] = $item;
                continue;
            }
            $slug = isset($item[2]) ? (string) $item[2] : '';
            if ($slug === 'dg-platform') {
                $dashboard = $item;
            } else {
                $others[] = $item;
            }
        }

        if ($dashboard) {
            return array_merge([$dashboard], $others);
        }

        return $items;
    }

    /** Redirect invalid separator slugs to the app launcher dashboard. */
    public static function redirect_separator_pages() {
        if (!is_admin() || empty($_GET['page'])) {
            return;
        }

        $page = sanitize_key(wp_unslash($_GET['page']));
        if (strpos($page, 'dg-sep-') === 0) {
            wp_safe_redirect(admin_url('admin.php?page=dg-platform'));
            exit;
        }
    }

    /** Safety net after all modules register submenu items. */
    public static function ensure_dashboard_first() {
        global $submenu;

        if (empty($submenu['dg-platform']) || !is_array($submenu['dg-platform'])) {
            return;
        }

        $submenu['dg-platform'] = self::pin_dashboard_first($submenu['dg-platform']);
    }

    /**
     * Keep DG Platform menu expanded when viewing industry CPT/taxonomy screens linked from submenus.
     */
    public static function keep_platform_parent($parent_file) {
        return self::detect_platform_submenu() ? 'dg-platform' : $parent_file;
    }

    public static function keep_platform_submenu($submenu_file) {
        $detected = self::detect_platform_submenu();
        return $detected ?: $submenu_file;
    }

    /** @return string|null Submenu slug under dg-platform */
    private static function detect_platform_submenu() {
        global $current_screen;

        if (!$current_screen) {
            return null;
        }

        $map = self::default_slug_map();

        if (!empty($current_screen->taxonomy)) {
            $candidates = [
                'edit-tags.php?taxonomy=' . $current_screen->taxonomy,
            ];
            if (!empty($current_screen->post_type)) {
                $candidates[] = 'edit-tags.php?taxonomy=' . $current_screen->taxonomy . '&post_type=' . $current_screen->post_type;
            }
            foreach ($candidates as $slug) {
                if (isset($map[$slug])) {
                    return $slug;
                }
            }
        }

        if (!empty($current_screen->post_type)) {
            $list_slug = 'edit.php?post_type=' . $current_screen->post_type;
            if (isset($map[$list_slug])) {
                return $list_slug;
            }
        }

        return null;
    }

    /** Hide premium app menus unless selected in Platform Plan. */
    private static function slug_allowed($slug) {
        $premium_slugs = [
            'dg-platform-seo' => 'seo_pro',
            'dg-platform-ai-visibility' => 'ai_visibility_pro',
            'dg-platform-automation-pro' => 'automation_pro',
            'dg-platform-analytics-pro' => 'analytics_pro',
            'dg-platform-social-pro' => 'social_pro',
        ];
        if (!isset($premium_slugs[$slug]) || !class_exists('DG_Plan_Registry')) {
            return true;
        }
        return DG_Plan_Registry::has_premium_app($premium_slugs[$slug]);
    }

    /** @return array<int,mixed> */
    private static function separator($label, $key) {
        return [
            '<span class="dg-menu-group-label">' . esc_html($label) . '</span>',
            'read',
            'dg-sep-' . $key,
            '',
            'wp-not-current-submenu dg-menu-separator',
        ];
    }

    public static function separator_styles() {
        ?>
        <style>
            #toplevel_page_dg-platform .wp-submenu li.dg-menu-separator {
                pointer-events: none;
                cursor: default;
                margin-top: 6px;
                padding: 6px 12px 2px;
                min-height: 0;
            }
            #toplevel_page_dg-platform .wp-submenu li.dg-menu-separator .dg-menu-group-label,
            #toplevel_page_dg-platform .wp-submenu li.dg-menu-separator a {
                font-size: 10px;
                font-weight: 700;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: #94A3B8 !important;
                padding: 0 !important;
                background: none !important;
            }
            .admin-dark-mode #toplevel_page_dg-platform .wp-submenu li.dg-menu-separator .dg-menu-group-label,
            .admin-dark-mode #toplevel_page_dg-platform .wp-submenu li.dg-menu-separator a {
                color: var(--dg-text-muted, #94A3B8) !important;
            }
        </style>
        <?php
    }
}

DG_Admin_Menu::init();
