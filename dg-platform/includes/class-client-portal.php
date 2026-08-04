<?php

/**

 * DigitalGate client portal — role, page access, login routing.

 *

 * @package DG_Platform

 */



if (!defined('ABSPATH')) {

    exit;

}



class DG_Client_Portal {



    const ROLE = 'dg_client';

    const CAP = 'dg_client_portal';



    /** @return array<string,mixed>|null */
    public static function config() {
        return class_exists('DG_Site_Portal_Config') ? DG_Site_Portal_Config::current() : null;
    }

    public static function portal_id() {
        $config = self::config();
        return $config ? (string) ($config['id'] ?? 'client') : 'client';
    }

    public static function role() {
        $config = self::config();
        return $config ? (string) ($config['role'] ?? self::ROLE) : self::ROLE;
    }

    public static function cap() {
        $config = self::config();
        return $config ? (string) ($config['cap'] ?? self::CAP) : self::CAP;
    }

    /** @return string[] */
    private static function protected_slugs() {
        $config = self::config();
        if ($config && !empty($config['protected_slugs']) && is_array($config['protected_slugs'])) {
            return $config['protected_slugs'];
        }
        return [
            'client-dashboard',
            'client-account',
            'client-reports',
            'customer-account',
            'reports',
        ];
    }



    public static function init() {

        add_action('init', [__CLASS__, 'register_role']);
        add_action('init', [__CLASS__, 'maybe_sync_portal_users'], 20);

        add_action('template_redirect', [__CLASS__, 'redirect_legacy_portal_urls'], 2);

        add_action('template_redirect', [__CLASS__, 'handle_portal_login_route'], 3);

        add_action('template_redirect', [__CLASS__, 'handle_portal_dashboard_route'], 3);

        add_action('template_redirect', [__CLASS__, 'redirect_dashboard_to_platform'], 4);

        add_action('admin_init', [__CLASS__, 'restrict_admin_access'], 1);

        add_action('admin_menu', [__CLASS__, 'trim_admin_menu'], 99999);

        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_client_admin_shell']);

        add_action('in_admin_header', [__CLASS__, 'render_admin_shell_header']);

        add_filter('admin_body_class', [__CLASS__, 'admin_body_class']);

        add_filter('login_redirect', [__CLASS__, 'login_redirect'], 10, 3);

        add_action('admin_bar_menu', [__CLASS__, 'add_admin_bar_menu'], 85);



        if (!self::enabled()) {

            return;

        }



        add_action('template_redirect', [__CLASS__, 'guard_portal_pages']);

        add_action('wp_login_failed', [__CLASS__, 'login_failed_redirect']);

        add_filter('show_admin_bar', [__CLASS__, 'hide_admin_bar']);

    }



    public static function enabled() {
        $config = self::config();
        return $config && !empty($config['enabled']);
    }



    public static function login_url() {
        $config = self::config();
        $slug = $config ? (string) ($config['login_slug'] ?? 'client-portal') : 'client-portal';
        return self::page_url($slug);
    }



    public static function dashboard_url() {
        $config = self::config();
        $slug = $config ? (string) ($config['dashboard_slug'] ?? 'client-dashboard') : 'client-dashboard';
        return self::page_url($slug);
    }



    public static function account_url() {
        $config = self::config();
        $slug = $config ? ($config['account_slug'] ?? 'client-account') : 'client-account';
        return $slug ? self::page_url((string) $slug) : self::dashboard_url();
    }

    public static function reports_url() {
        $config = self::config();
        $slug = $config ? ($config['reports_slug'] ?? 'client-reports') : 'client-reports';
        return $slug ? self::page_url((string) $slug) : self::dashboard_url();
    }



    /** @return array<string,string> Legacy URL path (no slashes) => canonical slug */
    public static function legacy_path_redirects() {
        $config = self::config();
        if ($config && !empty($config['legacy_redirects']) && is_array($config['legacy_redirects'])) {
            return $config['legacy_redirects'];
        }
        return [
            'system-pages/client-portal' => 'client-portal',
            'system-pages/client-dashboard' => 'client-dashboard',
            'system-pages/customer-account' => 'client-account',
            'system-pages/client-account' => 'client-account',
            'system-pages/client-reports' => 'client-reports',
            'customer-account' => 'client-account',
        ];
    }



    /** @return string[] */
    public static function canonical_slugs() {
        $config = self::config();
        if ($config && !empty($config['canonical_slugs']) && is_array($config['canonical_slugs'])) {
            return $config['canonical_slugs'];
        }
        return ['client-portal', 'client-dashboard', 'client-account', 'client-reports'];
    }



    public static function page_url($slug) {
        $slug = trim((string) $slug, '/');
        return home_url('/' . $slug . '/');
    }



    /** 301 redirect old /system-pages/* client URLs after pages move to root. */
    public static function redirect_legacy_portal_urls() {
        $request = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        $path = trim((string) parse_url($request, PHP_URL_PATH), '/');

        foreach (self::legacy_path_redirects() as $legacy => $canonical) {
            if ($path === $legacy) {
                wp_safe_redirect(self::page_url($canonical), 301);
                exit;
            }
        }

        if (is_page('customer-account')) {
            wp_safe_redirect(self::account_url(), 301);
            exit;
        }

        if (!is_page()) {
            return;
        }

        $post = get_queried_object();
        if (!$post instanceof WP_Post) {
            return;
        }

        $parent = (int) $post->post_parent;
        if (!$parent || get_post_field('post_name', $parent) !== 'system-pages') {
            return;
        }

        $canonical = $post->post_name === 'customer-account' ? 'client-account' : $post->post_name;
        if (!in_array($canonical, self::canonical_slugs(), true)) {
            return;
        }

        wp_safe_redirect(self::page_url($canonical), 301);
        exit;
    }



    public static function platform_admin_url() {

        return admin_url('admin.php?page=dg-platform');

    }



    /** @return array<string,string> */
    public static function app_group_labels() {
        return apply_filters('dg_client_portal_app_group_labels', [
            'core' => 'Core Apps',
            'industry' => 'Industry Apps',
            'premium' => 'Premium Apps',
            'addons' => 'Add-ons',
            'platform' => 'Platform Apps',
        ]);
    }



    /** @return array<string,array<int,array{title:string,url:string,group?:string}>> */
    public static function grouped_launcher_apps() {
        if (!self::can_access_portal()) {
            return [];
        }

        $apps = [];
        try {
            if (is_admin()) {
                self::bootstrap_admin_menu();
                if (class_exists('DG_Admin_Menu') && method_exists('DG_Admin_Menu', 'launcher_apps')) {
                    $apps = DG_Admin_Menu::launcher_apps();
                }
            }
        } catch (Throwable $e) {
            $apps = [];
        }

        if (!$apps) {
            $apps = self::fallback_launcher_apps();
        }

        $apps = apply_filters('dg_client_portal_launcher_apps', $apps);
        $apps = self::enrich_launcher_apps($apps);
        return self::sort_app_groups($apps);
    }

    /** @param array<int,array<string,mixed>> $apps */
    private static function enrich_launcher_apps(array $apps) {
        foreach ($apps as &$app) {
            if (empty($app['icon'])) {
                $app['icon'] = self::resolve_app_icon($app);
            }
        }
        unset($app);

        return $apps;
    }

    /** @param array<string,mixed> $app */
    public static function resolve_app_icon(array $app) {
        if (!empty($app['icon'])) {
            $icon = (string) $app['icon'];
            return strpos($icon, 'fa-') === 0 ? $icon : 'fa-' . $icon;
        }

        $slug = !empty($app['slug']) ? (string) $app['slug'] : '';
        if ($slug === '' && !empty($app['url'])) {
            if (preg_match('/[?&]page=([^&]+)/', (string) $app['url'], $matches)) {
                $slug = $matches[1];
            }
        }

        $map = self::app_icon_map();
        if ($slug !== '' && isset($map[$slug])) {
            return $map[$slug];
        }

        $title = strtolower((string) ($app['title'] ?? ''));
        if (strpos($title, 'contact') !== false) {
            return 'fa-address-book';
        }
        if (strpos($title, 'task') !== false) {
            return 'fa-tasks';
        }
        if (strpos($title, 'calendar') !== false) {
            return 'fa-calendar-alt';
        }
        if (strpos($title, 'search') !== false) {
            return 'fa-search';
        }
        if (strpos($title, 'report') !== false || strpos($title, 'growth') !== false) {
            return 'fa-chart-line';
        }
        if (strpos($title, 'real estate') !== false || strpos($title, 'property') !== false) {
            return 'fa-home';
        }
        if (strpos($title, 'accommodation') !== false || strpos($title, 'booking') !== false) {
            return 'fa-bed';
        }
        if (strpos($title, 'marketing') !== false) {
            return 'fa-bullhorn';
        }
        if (strpos($title, 'finance') !== false) {
            return 'fa-coins';
        }

        return 'fa-cube';
    }

    /** @return array<string,string> Font Awesome icon classes keyed by admin page slug. */
    public static function app_icon_map() {
        return apply_filters('dg_client_portal_app_icon_map', [
            'dg-platform-contacts' => 'fa-address-book',
            'dg-platform-tasks' => 'fa-tasks',
            'dg-platform-calendar' => 'fa-calendar-alt',
            'dg-platform-search' => 'fa-search',
            'dg-platform-activity' => 'fa-stream',
            'dg-platform-reports' => 'fa-chart-line',
            'dg-marketing-dashboard' => 'fa-bullhorn',
            'dg-marketing-clients' => 'fa-users',
            'dg-marketing-audits' => 'fa-search-plus',
            'dg-re-dashboard' => 'fa-home',
            'dg-re-properties' => 'fa-building',
            'dg-re-leads' => 'fa-user-plus',
            'dg-acc-dashboard' => 'fa-bed',
            'dg-acc-bookings' => 'fa-calendar-check',
            'dg-fin-dashboard' => 'fa-coins',
            'dg-svc-dashboard' => 'fa-wrench',
            'dg-dealer-dashboard' => 'fa-car',
            'dg-com-dashboard' => 'fa-city',
            'dg-creator-dashboard' => 'fa-video',
        ]);
    }



    /** Safe template context for Oxygen client dashboard (avoids fatals in builder/frontend). */
    public static function dashboard_template_context() {
        try {
            return self::build_dashboard_template_context();
        } catch (Throwable $e) {
            return [
                'client_name' => 'there',
                'client_email' => '',
                'payment_done' => false,
                'onboarding_done' => false,
                'setup_live' => false,
                'logout_url' => function_exists('wp_logout_url') ? wp_logout_url(self::login_url()) : '/wp-login.php?action=logout',
                'account_url' => self::account_url(),
                'reports_url' => self::reports_url(),
                'onboarding_url' => self::onboarding_url(),
                'strategy_session_url' => self::strategy_session_url(),
                'app_groups' => [],
                'app_labels' => self::app_group_labels(),
                'is_builder' => self::is_oxygen_builder(),
                'show_wp_admin' => current_user_can('manage_options'),
            ];
        }
    }

    /** Safe template context for Oxygen client account page (avoids fatals in builder/frontend). */
    public static function account_template_context() {
        try {
            return self::build_account_template_context();
        } catch (Throwable $e) {
            return self::fallback_account_template_context();
        }
    }

    /** Safe template context for Oxygen client reports page. */
    public static function reports_template_context() {
        if (class_exists('DG_Client_Reports')) {
            return DG_Client_Reports::template_context();
        }
        return [
            'client_name' => 'Client',
            'dashboard_url' => self::dashboard_url(),
            'generated_label' => wp_date('j M Y, g:i a'),
            'setup_steps' => [],
            'setup_percent' => 0,
            'summary_cards' => [],
            'sections' => [],
            'premium_apps' => [],
            'trend_charts' => [],
            'charts' => [],
            'pdf_filename' => 'digitalgate-progress-report.pdf',
            'is_builder' => self::is_oxygen_builder(),
            'has_data' => false,
        ];
    }

    /** @return array<string,mixed> */
    private static function fallback_account_template_context() {
        return [
            'name' => 'Client',
            'email' => '',
            'org_name' => '',
            'purchase_label' => '',
            'dashboard_url' => home_url('/client-dashboard/'),
            'onboarding_url' => home_url('/onboarding/'),
            'password_url' => home_url('/wp-login.php?action=lostpassword'),
            'logout_url' => home_url('/client-portal/'),
            'is_builder' => false,
            'show_wp_admin' => false,
        ];
    }

    /** @return array<string,mixed> */
    private static function build_account_template_context() {
        $is_builder = self::is_oxygen_builder();
        $name = 'Client';
        $email = '';
        $org_name = '';
        $purchase_label = '';

        if ($is_builder) {
            $name = 'Preview Client';
            $email = 'client@example.com';
            $org_name = 'Sample Business';
            $purchase_label = 'DG Platform';
        } elseif (function_exists('wp_get_current_user')) {
            $user = wp_get_current_user();
            if ($user && $user->ID) {
                $name = $user->display_name ?: $user->first_name ?: explode('@', $user->user_email)[0];
                $email = $user->user_email;
                $contact_id = (int) get_user_meta($user->ID, 'dg_contact_id', true);
                if ($contact_id && class_exists('DG_Contacts')) {
                    $contact = DG_Contacts::get($contact_id);
                    if ($contact && class_exists('DG_Entity_Meta')) {
                        $meta = DG_Entity_Meta::get('contact', $contact_id);
                        $stripe = is_array($meta['stripe_purchase'] ?? null) ? $meta['stripe_purchase'] : [];
                        if (!empty($stripe['purchase_label'])) {
                            $purchase_label = (string) $stripe['purchase_label'];
                        }
                    }
                    if ($contact && !empty($contact->organisation_id) && class_exists('DG_Organisations')) {
                        $org = DG_Organisations::get((int) $contact->organisation_id);
                        if ($org && !empty($org->name)) {
                            $org_name = (string) $org->name;
                        }
                    }
                }
            }
        }

        $login = self::login_url();

        return [
            'name' => $name,
            'email' => $email,
            'org_name' => $org_name,
            'purchase_label' => $purchase_label,
            'dashboard_url' => self::dashboard_url(),
            'onboarding_url' => self::onboarding_url(),
            'password_url' => function_exists('wp_lostpassword_url') ? wp_lostpassword_url($login) : home_url('/wp-login.php?action=lostpassword'),
            'logout_url' => function_exists('wp_logout_url') ? wp_logout_url($login) : '/wp-login.php?action=logout',
            'is_builder' => $is_builder,
            'show_wp_admin' => !$is_builder && current_user_can('manage_options'),
        ];
    }

    /** Safe template context for Oxygen client login page (avoids fatals in builder/frontend). */
    public static function portal_login_context() {
        try {
            return self::build_portal_login_context();
        } catch (Throwable $e) {
            return self::fallback_portal_login_context();
        }
    }

    /** @return array<string,mixed> */
    private static function build_portal_login_context() {
        $requested = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : '';
        $redirect_url = self::login_redirect_target($requested);
        $is_builder = self::is_oxygen_builder();
        $config = self::config();

        return [
            'redirect_url' => $redirect_url,
            'login_error' => isset($_GET['login']) && sanitize_key(wp_unslash($_GET['login'])) === 'failed',
            'access_denied' => isset($_GET['access']) && sanitize_key(wp_unslash($_GET['access'])) === 'denied',
            'logged_in_no_access' => is_user_logged_in()
                && !(current_user_can('manage_options') || self::is_portal_user()),
            'login_url' => self::login_url(),
            'onboarding_url' => self::onboarding_url(),
            'is_builder' => $is_builder,
            'site_label' => $config['site_label'] ?? 'Portal',
            'portal_label' => $config['label'] ?? __('Sign In', 'dg-platform'),
            'login_tagline' => $config['login_tagline'] ?? '',
            'access_denied_message' => $config['access_denied_message'] ?? '',
            'support_email' => $config['support_email'] ?? '',
            'show_onboarding_link' => !empty($config['show_onboarding_link']),
            'theme' => $config['theme'] ?? 'digitalgate',
            'login_icon' => $config['login_icon'] ?? 'fa-layer-group',
            'login_icon_color' => $config['login_icon_color'] ?? '#3B82F6',
        ];
    }

    /** @return array<string,mixed> */
    private static function fallback_portal_login_context() {
        $requested = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : '';
        $redirect_url = home_url('/client-dashboard/');
        if ($requested !== '' && strpos($requested, home_url()) === 0) {
            $redirect_url = $requested;
        }

        return [
            'redirect_url' => $redirect_url,
            'login_error' => isset($_GET['login']) && sanitize_key(wp_unslash($_GET['login'])) === 'failed',
            'access_denied' => isset($_GET['access']) && sanitize_key(wp_unslash($_GET['access'])) === 'denied',
            'logged_in_no_access' => false,
            'login_url' => home_url('/client-portal/'),
            'onboarding_url' => home_url('/onboarding/'),
            'is_builder' => false,
        ];
    }

    public static function is_oxygen_builder() {
        if (defined('OXYGEN_IFRAME') && OXYGEN_IFRAME) {
            return true;
        }
        if (isset($_GET['oxygen']) && sanitize_text_field(wp_unslash($_GET['oxygen'])) === 'true') {
            return true;
        }
        $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
        if ($action !== '' && strpos($action, 'oxy_') === 0) {
            return true;
        }

        return (bool) apply_filters('dg_client_portal_is_oxygen_builder', false);
    }



    /** @return array<string,mixed> */
    private static function build_dashboard_template_context() {
        $client_name = 'there';
        $client_email = '';
        $payment_done = false;
        $onboarding_done = false;
        $setup_live = false;

        if (function_exists('wp_get_current_user')) {
            $user = wp_get_current_user();
            if ($user && $user->ID) {
                $client_name = class_exists('DG_Email_Names')
                    ? DG_Email_Names::first_name($user)
                    : ($user->first_name ?: $user->display_name ?: explode('@', $user->user_email)[0]);
                $client_email = $user->user_email;
                $contact_id = (int) get_user_meta($user->ID, 'dg_contact_id', true);
                if ($contact_id && class_exists('DG_Contacts')) {
                    $contact = DG_Contacts::get($contact_id);
                    if ($contact) {
                        $tags = is_string($contact->tags ?? null) ? $contact->tags : '';
                        $payment_done = stripos($tags, 'Payment Received') !== false;
                        $onboarding_done = stripos($tags, 'Onboarding Complete') !== false;
                        $setup_live = stripos($tags, 'Platform Live') !== false;
                    }
                } elseif ($user->ID) {
                    $payment_done = true;
                }
            }
        }

        $login = self::login_url();
        $is_builder = self::is_oxygen_builder();
        return [
            'client_name' => $is_builder ? 'Preview' : $client_name,
            'client_email' => $is_builder ? 'client@example.com' : $client_email,
            'payment_done' => $is_builder ? true : $payment_done,
            'onboarding_done' => $is_builder ? false : $onboarding_done,
            'setup_live' => $is_builder ? false : $setup_live,
            'logout_url' => function_exists('wp_logout_url') ? wp_logout_url($login) : '/wp-login.php?action=logout',
            'account_url' => self::account_url(),
            'reports_url' => self::reports_url(),
            'onboarding_url' => self::onboarding_url(),
            'strategy_session_url' => self::strategy_session_url(),
            'app_groups' => $is_builder ? self::preview_launcher_app_groups() : self::grouped_launcher_apps(),
            'app_labels' => self::app_group_labels(),
            'is_builder' => $is_builder,
            'show_wp_admin' => !$is_builder && current_user_can('manage_options'),
        ];
    }



    /** @param array<int,array{title:string,url:string,group?:string}> $apps */
    private static function sort_app_groups(array $apps) {
        $grouped = [];
        foreach ($apps as $app) {
            $group = isset($app['group']) ? (string) $app['group'] : 'core';
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $grouped[$group][] = $app;
        }

        $order = ['core', 'industry', 'premium', 'addons', 'platform'];
        $sorted = [];
        foreach ($order as $key) {
            if (!empty($grouped[$key])) {
                $sorted[$key] = $grouped[$key];
            }
        }
        foreach ($grouped as $key => $items) {
            if (!isset($sorted[$key])) {
                $sorted[$key] = $items;
            }
        }

        return $sorted;
    }



    /** Sample apps for Oxygen builder preview. */
    private static function preview_launcher_app_groups() {
        return [
            'core' => [
                ['title' => 'Contacts', 'url' => '#', 'icon' => 'fa-address-book'],
                ['title' => 'Tasks', 'url' => '#', 'icon' => 'fa-tasks'],
                ['title' => 'Calendar', 'url' => '#', 'icon' => 'fa-calendar-alt'],
            ],
            'industry' => [
                ['title' => 'Marketing', 'url' => '#', 'icon' => 'fa-bullhorn'],
            ],
        ];
    }

    /** Capability-based app list — safe on frontend without bootstrapping wp-admin menus. */
    private static function fallback_launcher_apps() {
        $defs = [
            ['cap' => 'dg_view_contacts', 'title' => 'Contacts', 'slug' => 'dg-platform-contacts', 'group' => 'core'],
            ['cap' => 'dg_view_tasks', 'title' => 'Tasks', 'slug' => 'dg-platform-tasks', 'group' => 'core'],
            ['cap' => 'dg_view_calendar', 'title' => 'Calendar', 'slug' => 'dg-platform-calendar', 'group' => 'core'],
            ['cap' => 'dg_view_contacts', 'title' => 'Search', 'slug' => 'dg-platform-search', 'group' => 'core'],
            ['cap' => 'dg_view_activities', 'title' => 'Activity', 'slug' => 'dg-platform-activity', 'group' => 'core'],
            ['cap' => 'dg_view_reports', 'title' => 'Growth Intelligence', 'slug' => 'dg-platform-reports', 'group' => 'core'],
            ['cap' => 'dg_re_view_leads', 'title' => 'Real Estate', 'slug' => 'dg-re-dashboard', 'group' => 'industry'],
            ['cap' => 'dg_acc_view_bookings', 'title' => 'Accommodation', 'slug' => 'dg-acc-dashboard', 'group' => 'industry'],
            ['cap' => 'dg_marketing_view_clients', 'title' => 'Marketing', 'slug' => 'dg-marketing-dashboard', 'group' => 'industry'],
            ['cap' => 'dg_fin_view_loans', 'title' => 'Finance', 'slug' => 'dg-fin-dashboard', 'group' => 'industry'],
            ['cap' => 'dg_svc_view_jobs', 'title' => 'Services', 'slug' => 'dg-svc-dashboard', 'group' => 'industry'],
            ['cap' => 'dg_dealer_view_inventory', 'title' => 'Automotive', 'slug' => 'dg-dealer-dashboard', 'group' => 'industry'],
            ['cap' => 'dg_com_view_listings', 'title' => 'Commercial', 'slug' => 'dg-com-dashboard', 'group' => 'industry'],
            ['cap' => 'dg_creator_view_content', 'title' => 'Creator', 'slug' => 'dg-creator-dashboard', 'group' => 'industry'],
        ];

        $apps = [];
        foreach ($defs as $def) {
            if (!self::user_has_app_cap($def['cap'])) {
                continue;
            }
            $slug = $def['slug'];
            $apps[] = [
                'title' => $def['title'],
                'url' => admin_url('admin.php?page=' . $slug),
                'group' => $def['group'],
                'slug' => $slug,
                'icon' => self::resolve_app_icon(['slug' => $slug, 'title' => $def['title']]),
            ];
        }

        return $apps;
    }



    private static function user_has_app_cap($cap) {
        if (current_user_can('manage_options')) {
            return true;
        }
        return current_user_can($cap);
    }



    private static function bootstrap_admin_menu() {
        global $submenu;

        if (!is_admin() || (!empty($submenu['dg-platform']) && is_array($submenu['dg-platform']))) {
            return;
        }

        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        do_action('admin_menu');
    }



    public static function onboarding_url() {
        $config = self::config();
        $slug = $config ? ($config['onboarding_slug'] ?? 'onboarding') : 'onboarding';
        return $slug ? self::page_url((string) $slug) : '';
    }

    public static function strategy_session_url() {
        return home_url('/strategy-session/');
    }



    /** @return array<string,bool> */

    public static function client_capabilities() {
        if (self::portal_id() === 'guest') {
            return apply_filters('dg_guest_portal_capabilities', [
                'read' => true,
                self::cap() => true,
            ]);
        }

        if (self::portal_id() === 'owner') {
            return apply_filters('dg_owner_portal_capabilities', [
                'read' => true,
                self::cap() => true,
                'dg_re_view_appraisals' => true,
                'dg_re_view_listings' => true,
                'dg_re_view_sales' => true,
            ]);
        }

        if (self::portal_id() === 'creator') {
            return apply_filters('dg_creator_portal_capabilities', [
                'read' => true,
                self::cap() => true,
                'dg_access_platform' => true,
                'dg_creator_view_content' => true,
                'dg_creator_manage_content' => true,
                'dg_creator_view_audience' => true,
                'dg_creator_manage_audience' => true,
            ]);
        }

        $caps = [

            'read' => true,

            self::cap() => true,

            'dg_access_platform' => true,

            'dg_view_contacts' => true,

            'dg_manage_contacts' => true,

            'dg_view_organisations' => true,

            'dg_manage_organisations' => true,

            'dg_view_tasks' => true,

            'dg_manage_tasks' => true,

            'dg_view_calendar' => true,

            'dg_manage_calendar' => true,

            'dg_view_activities' => true,

            'dg_manage_activities' => true,

            'dg_view_documents' => true,

            'dg_manage_documents' => true,

        ];



        $caps = array_merge($caps, self::module_capabilities());



        return apply_filters('dg_client_portal_capabilities', $caps);

    }



    /** Industry module caps matching this site's vertical. */

    private static function module_capabilities() {

        if (!class_exists('DG_Site_Profile')) {

            return [];

        }



        switch (DG_Site_Profile::primary_module()) {

            case 'real-estate':

                return [

                    'dg_re_view_leads' => true,

                    'dg_re_manage_leads' => true,

                    'dg_re_view_appraisals' => true,

                    'dg_re_manage_appraisals' => true,

                    'dg_re_view_listings' => true,

                    'dg_re_manage_listings' => true,

                    'dg_re_view_buyers' => true,

                    'dg_re_manage_buyers' => true,

                    'dg_re_view_sales' => true,

                    'dg_re_manage_sales' => true,

                    'dg_re_view_agents' => true,

                ];

            case 'accommodation':

                return [

                    'dg_acc_view_bookings' => true,

                    'dg_acc_manage_bookings' => true,

                    'dg_acc_view_guests' => true,

                    'dg_acc_manage_guests' => true,

                ];

            case 'creator':

                return [

                    'dg_creator_view_content' => true,

                    'dg_creator_manage_content' => true,

                    'dg_creator_view_audience' => true,

                    'dg_creator_manage_audience' => true,

                ];

            case 'finance':

                return [

                    'dg_fin_view_loans' => true,

                    'dg_fin_manage_loans' => true,

                ];

            case 'services':

                return [

                    'dg_svc_view_jobs' => true,

                    'dg_svc_manage_jobs' => true,

                ];

            default:

                return [];

        }

    }



    public static function register_role() {

        $caps = self::client_capabilities();
        $role_key = self::role();
        $config = self::config();
        $role_label = $config ? (string) ($config['role_label'] ?? 'Portal User') : 'DG Client';
        $role = get_role($role_key);



        if (!$role) {

            add_role($role_key, $role_label, $caps);

            return;

        }



        foreach ($caps as $cap => $grant) {

            if ($grant) {

                $role->add_cap($cap);

            }

        }

    }



    /** Upgrade existing portal users when plugin version changes. */

    public static function maybe_sync_portal_users() {

        $key = 'dg_portal_caps_version';

        if (get_option($key) === DG_PLATFORM_VERSION) {

            return;

        }



        self::register_role();

        $users = get_users(['role' => self::role(), 'fields' => 'ID']);

        foreach ($users as $user_id) {

            self::sync_portal_capabilities((int) $user_id);

        }

        update_option($key, DG_PLATFORM_VERSION);

    }

    /** @deprecated Use maybe_sync_portal_users() */
    public static function maybe_sync_client_users() {
        self::maybe_sync_portal_users();
    }



    /** @param int|WP_User $user */
    public static function sync_portal_capabilities($user) {

        self::register_role();

        $user = self::resolve_user($user);

        if (!$user || !self::is_portal_user($user)) {

            return;

        }



        foreach (self::client_capabilities() as $cap => $grant) {

            if ($grant) {

                $user->add_cap($cap);

            }

        }

    }

    /** @param int|WP_User $user */
    public static function sync_client_capabilities($user) {
        self::sync_portal_capabilities($user);
    }



    public static function is_portal_user($user = null) {

        $user = self::resolve_user($user);

        if (!$user) {

            return false;

        }

        return in_array(self::role(), (array) $user->roles, true)

            || user_can($user, self::cap());

    }

    public static function is_client_user($user = null) {
        return self::is_portal_user($user);
    }



    /**

     * @return array{user_id:int,created:bool,error?:string}

     */

    public static function ensure_user($email, $display_name, $contact_id = 0, $org_id = 0) {

        self::register_role();



        $email = sanitize_email($email);

        if ($email === '') {

            return ['user_id' => 0, 'created' => false, 'error' => 'Invalid email'];

        }



        $existing = email_exists($email);

        if ($existing) {

            $user = get_userdata($existing);

            if ($user && !self::is_portal_user($user)) {

                $user->add_role(self::role());

            }

            self::sync_portal_capabilities((int) $existing);

            self::link_user_meta((int) $existing, $contact_id, $org_id);

            return ['user_id' => (int) $existing, 'created' => false];

        }



        $password = wp_generate_password(16, true);

        $user_id = wp_create_user($email, $password, $email);

        if (is_wp_error($user_id)) {

            return ['user_id' => 0, 'created' => false, 'error' => $user_id->get_error_message()];

        }



        wp_update_user([

            'ID' => $user_id,

            'display_name' => $display_name,

            'first_name' => $display_name,

            'role' => self::role(),

        ]);



        self::sync_portal_capabilities((int) $user_id);

        self::link_user_meta((int) $user_id, $contact_id, $org_id);



        return ['user_id' => (int) $user_id, 'created' => true];

    }



    public static function password_set_link($user_id, $email) {

        $reset_key = get_password_reset_key(get_userdata($user_id));

        if (is_wp_error($reset_key)) {

            return self::login_url();

        }



        return network_site_url(

            'wp-login.php?action=rp&key=' . rawurlencode($reset_key) . '&login=' . rawurlencode($email)

        );

    }



    /** Serve login on the portal slug regardless of Oxygen page body; redirect signed-in users to dashboard. */
    public static function handle_portal_login_route() {
        if (!self::is_portal_login_page()) {
            return;
        }
        if (self::is_oxygen_builder()) {
            return;
        }

        $preview_login = isset($_GET['preview']) && sanitize_key(wp_unslash($_GET['preview'])) === 'login';

        if (is_user_logged_in() && !$preview_login && self::can_access_portal()) {
            wp_safe_redirect(self::dashboard_url());
            exit;
        }

        self::render_login_page();
        exit;
    }

    /** @deprecated Use handle_portal_login_route() */
    public static function handle_client_portal_route() {
        self::handle_portal_login_route();
    }

    /** Plugin-rendered dashboards for guest / placeholder portals. */
    public static function handle_portal_dashboard_route() {
        if (!self::enabled() || !self::is_portal_dashboard_page()) {
            return;
        }
        if (self::is_oxygen_builder()) {
            return;
        }

        $config = self::config();
        $renderer = $config ? (string) ($config['dashboard_renderer'] ?? 'oxygen') : 'oxygen';
        if ($renderer === 'oxygen') {
            return;
        }

        if (!is_user_logged_in() || !self::can_access_portal()) {
            $login = add_query_arg(
                'redirect_to',
                rawurlencode(self::dashboard_url()),
                self::login_url()
            );
            wp_safe_redirect($login);
            exit;
        }

        if ($renderer === 'plugin' && self::portal_id() === 'guest') {
            self::render_guest_dashboard();
            exit;
        }

        if ($renderer === 'placeholder') {
            self::render_placeholder_dashboard();
            exit;
        }
    }

    public static function is_portal_login_page() {
        $config = self::config();
        $slug = $config ? (string) ($config['login_slug'] ?? 'client-portal') : 'client-portal';

        if (is_page($slug)) {
            return true;
        }

        $request = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        $path = trim((string) parse_url($request, PHP_URL_PATH), '/');

        return $path === $slug;
    }

    public static function is_portal_dashboard_page() {
        $config = self::config();
        $slug = $config ? (string) ($config['dashboard_slug'] ?? 'client-dashboard') : 'client-dashboard';

        if (is_page($slug)) {
            return true;
        }

        $request = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        $path = trim((string) parse_url($request, PHP_URL_PATH), '/');

        return $path === $slug;
    }

    public static function render_login_page() {
        $ctx = self::portal_login_context();
        status_header(200);
        nocache_headers();
        include DG_PLATFORM_PATH . 'templates/frontend/site-portal-login.php';
    }

    public static function render_guest_dashboard() {
        $ctx = class_exists('DG_Site_Portal_Guest')
            ? DG_Site_Portal_Guest::dashboard_context()
            : [];
        status_header(200);
        nocache_headers();
        include DG_PLATFORM_PATH . 'templates/frontend/guest-portal-dashboard.php';
    }

    public static function render_placeholder_dashboard() {
        $config = self::config();
        $user_name = 'there';
        if (function_exists('wp_get_current_user')) {
            $user = wp_get_current_user();
            if ($user && $user->ID) {
                $user_name = class_exists('DG_Email_Names')
                    ? DG_Email_Names::first_name($user)
                    : ($user->first_name ?: $user->display_name ?: explode('@', $user->user_email)[0]);
            }
        }

        $messages = [
            'owner' => __('Your owner dashboard with property reports and documents is coming soon.', 'dg-platform'),
            'creator' => __('Creator Studio is being set up. Your content and audience tools will appear here.', 'dg-platform'),
        ];

        $ctx = [
            'user_name' => $user_name,
            'portal_label' => $config['label'] ?? 'Portal',
            'site_label' => $config['site_label'] ?? '',
            'logout_url' => function_exists('wp_logout_url') ? wp_logout_url(self::login_url()) : '/wp-login.php?action=logout',
            'message' => $messages[self::portal_id()] ?? __('Your dashboard is being prepared.', 'dg-platform'),
            'theme' => $config['theme'] ?? 'digitalgate',
        ];

        status_header(200);
        nocache_headers();
        include DG_PLATFORM_PATH . 'templates/frontend/site-portal-placeholder-dashboard.php';
    }

    public static function can_access_portal($user = null) {
        $user = self::resolve_user($user);
        if (!$user) {
            return false;
        }
        return self::is_portal_user($user) || user_can($user, 'manage_options');
    }

    /** Redirect legacy Oxygen client dashboard to DG Platform admin. */

    public static function redirect_dashboard_to_platform() {

        if (!is_page() || !is_user_logged_in() || !self::is_portal_user()) {

            return;

        }

        if (self::portal_id() !== 'client') {
            return;
        }



        $post = get_queried_object();

        if (!$post instanceof WP_Post || $post->post_name !== 'client-dashboard') {

            return;

        }



        if (!apply_filters('dg_client_portal_use_platform_dashboard', false)) {

            return;

        }



        wp_safe_redirect(self::platform_admin_url());

        exit;

    }



    public static function login_failed_redirect() {
        $redirect = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : '';
        if ($redirect && (strpos($redirect, admin_url()) === 0 || self::is_portal_login_context($redirect))) {
            wp_safe_redirect(add_query_arg('login', 'failed', self::login_url()));
            exit;
        }
    }

    public static function guard_portal_pages() {

        if (!is_page()) {

            return;

        }



        $post = get_queried_object();

        if (!$post instanceof WP_Post) {

            return;

        }



        if (!self::is_protected_page($post)) {

            return;

        }



        if (is_user_logged_in() && (self::is_portal_user() || current_user_can('manage_options'))) {

            return;

        }



        if (!is_user_logged_in()) {
            $login = add_query_arg(
                'redirect_to',
                rawurlencode(get_permalink($post)),
                self::login_url()
            );
            wp_safe_redirect($login);
            exit;
        }



        wp_safe_redirect(self::login_url() . '?access=denied');

        exit;

    }



    public static function login_redirect($redirect_to, $requested_redirect_to, $user) {

        if (!$user instanceof WP_User || is_wp_error($user)) {

            return $redirect_to;

        }

        if (self::is_portal_user($user)) {
            if ($requested_redirect_to && self::is_safe_redirect($requested_redirect_to)) {
                return $requested_redirect_to;
            }
            return self::dashboard_url();
        }

        if (user_can($user, 'manage_options')) {
            if ($requested_redirect_to && self::is_safe_redirect($requested_redirect_to)) {
                return $requested_redirect_to;
            }
            if (self::is_portal_login_context($redirect_to) || self::is_portal_login_context($requested_redirect_to)) {
                return self::dashboard_url();
            }
        }

        return $redirect_to;
    }

    private static function is_portal_login_context($url) {
        if (!is_string($url) || $url === '') {
            return false;
        }
        $config = self::config();
        $login_slug = $config ? (string) ($config['login_slug'] ?? 'client-portal') : 'client-portal';
        $portal = trailingslashit(self::login_url());
        return strpos($url, $portal) === 0 || strpos($url, $login_slug) !== false;
    }

    public static function restrict_admin_access() {

        if (!self::is_portal_user() || current_user_can('manage_options')) {

            return;

        }

        $config = self::config();
        if ($config && empty($config['allow_wp_admin'])) {
            wp_safe_redirect(self::dashboard_url());
            exit;
        }

        if (wp_doing_ajax() || wp_doing_cron()) {

            return;

        }



        global $pagenow;

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';



        if (self::is_allowed_admin_screen($pagenow, $page)) {

            return;

        }



        wp_safe_redirect(self::dashboard_url());

        exit;

    }



    public static function trim_admin_menu() {

        if (!self::is_portal_user() || current_user_can('manage_options')) {

            return;

        }



        global $menu;

        if (!is_array($menu)) {

            return;

        }



        $keep = ['toplevel_page_dg-platform'];

        foreach ($menu as $index => $item) {

            if (!is_array($item) || empty($item[2])) {

                continue;

            }

            $slug = (string) $item[2];

            if ($slug === 'profile.php') {

                continue;

            }

            if (!in_array($slug, $keep, true) && strpos($slug, 'dg-') !== 0 && strpos($slug, 'dg_') !== 0) {

                remove_menu_page($slug);

            }

        }

    }



    public static function hide_admin_bar($show) {

        if (current_user_can('manage_options')) {

            return $show;

        }

        if (self::is_portal_user()) {
            $config = self::config();
            if ($config && empty($config['allow_wp_admin'])) {
                return false;
            }
            // Keep admin bar in wp-admin so portal users can reach their dashboard from the toolbar.
            return is_admin();

        }

        return $show;

    }

    public static function add_admin_bar_menu($wp_admin_bar) {
        if (!self::enabled() || !is_user_logged_in() || !self::can_access_portal()) {
            return;
        }

        if (!is_admin_bar_showing()) {
            return;
        }

        $config = self::config();
        $toolbar_label = $config ? (string) ($config['toolbar_label'] ?? 'Dashboard') : 'Client Dashboard';
        $portal_label = $config ? (string) ($config['label'] ?? 'Portal') : 'Client Portal';

        $wp_admin_bar->add_node([
            'id' => 'dg-site-portal-dashboard',
            'title' => $toolbar_label,
            'href' => self::dashboard_url(),
            'meta' => [
                'class' => 'dg-site-portal-toolbar',
                'title' => sprintf(__('Open %s', 'dg-platform'), $portal_label),
            ],
        ]);
    }



    private static function is_allowed_admin_screen($pagenow, $page) {

        $allowed = ['profile.php', 'admin-post.php', 'async-upload.php', 'media-upload.php', 'upload.php', 'media-new.php'];

        if (in_array($pagenow, $allowed, true)) {

            return true;

        }



        if ($pagenow === 'admin.php') {

            return $page === '' || strpos($page, 'dg-') === 0;

        }



        if (in_array($pagenow, ['edit.php', 'post.php', 'post-new.php'], true)) {

            $post_type = self::current_admin_post_type($pagenow);

            return $post_type && self::is_allowed_post_type($post_type);

        }



        return false;

    }



    private static function current_admin_post_type($pagenow) {

        if (!empty($_GET['post_type'])) {

            return sanitize_key(wp_unslash($_GET['post_type']));

        }

        if ($pagenow === 'post.php' && !empty($_GET['post'])) {

            $post_id = (int) $_GET['post'];

            return $post_id ? get_post_type($post_id) : '';

        }

        if ($pagenow === 'post-new.php' && !empty($_GET['post_type'])) {

            return sanitize_key(wp_unslash($_GET['post_type']));

        }

        return '';

    }



    private static function is_allowed_post_type($post_type) {

        if (strpos($post_type, 'dg_') === 0) {

            return true;

        }

        return in_array($post_type, ['property', 'agent'], true);

    }



    public static function login_redirect_target($requested = '') {
        $requested = is_string($requested) ? esc_url_raw($requested) : '';
        if ($requested && self::is_safe_redirect($requested)) {
            return $requested;
        }
        return self::dashboard_url();
    }

    public static function is_safe_redirect($url) {

        $url = wp_validate_redirect($url, '');

        if ($url === '') {

            return false;

        }

        return strpos($url, admin_url()) === 0 || strpos($url, home_url()) === 0;

    }



    /** @param WP_Post|int|null $post */

    private static function is_protected_page($post) {

        $post = get_post($post);

        if (!$post) {

            return false;

        }



        if (in_array($post->post_name, self::protected_slugs(), true)) {

            return true;

        }



        $parent = (int) $post->post_parent;

        if ($parent && get_post_field('post_name', $parent) === 'system-pages') {

            return in_array($post->post_name, self::protected_slugs(), true);

        }



        return false;

    }



    private static function link_user_meta($user_id, $contact_id, $org_id) {

        if ($contact_id) {

            update_user_meta($user_id, 'dg_contact_id', (int) $contact_id);

        }

        if ($org_id) {

            update_user_meta($user_id, 'dg_organisation_id', (int) $org_id);

        }

    }



    public static function is_app_admin_context() {
        if (!is_admin() || !self::is_portal_user() || current_user_can('manage_options')) {
            return false;
        }
        global $pagenow;
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        return self::is_allowed_admin_screen($pagenow, $page);
    }



    public static function enqueue_client_admin_shell($hook) {
        if (!self::is_app_admin_context()) {
            return;
        }

        wp_enqueue_style(
            'dg-client-admin-shell',
            DG_PLATFORM_URL . 'assets/css/client-admin-shell.css',
            [],
            DG_PLATFORM_VERSION
        );
    }



    public static function render_admin_shell_header() {
        if (!self::is_app_admin_context()) {
            return;
        }

        $dashboard = self::dashboard_url();
        $config = self::config();
        $brand = $config ? (string) ($config['site_label'] ?? 'Platform') : 'DigitalGate Platform';
        ?>
        <div class="dg-client-admin-shell-bar">
            <a class="dg-client-admin-shell-back" href="<?php echo esc_url($dashboard); ?>">
                <span aria-hidden="true">←</span> Back to dashboard
            </a>
            <span class="dg-client-admin-shell-brand"><?php echo esc_html($brand); ?></span>
            <a class="dg-client-admin-shell-support dg-support-open" href="#">Support</a>
        </div>
        <?php
    }



    public static function admin_body_class($classes) {
        if (self::is_portal_user() && !current_user_can('manage_options')) {
            $classes .= ' dg-client-admin-shell ';
        }
        return $classes;
    }



    /** @param int|WP_User|null $user */

    private static function resolve_user($user) {

        if ($user instanceof WP_User) {

            return $user;

        }

        if (is_numeric($user)) {

            return get_userdata((int) $user) ?: null;

        }

        if (!is_user_logged_in()) {

            return null;

        }

        return wp_get_current_user();

    }

}



/** Shared site portal engine (alias for backward-compatible DG_Client_Portal). */
class DG_Site_Portal extends DG_Client_Portal {}



add_action('plugins_loaded', ['DG_Client_Portal', 'init'], 11);


