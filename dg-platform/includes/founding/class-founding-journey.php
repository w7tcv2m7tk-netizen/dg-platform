<?php
/**
 * Founding 10 public routes: invite preview, accept, setup, trial started.
 *
 * These WordPress routes are the working Founding journey until
 * app.digitalgate.com.au/founding/* is implemented in dg-platform-web.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Founding_Journey {

    const REWRITE_VERSION = 2;
    const OPTION_SETUP_READY = 'dg_founding_setup_ready';
    const OPTION_REWRITE = 'dg_founding_rewrite_version';

    public static function init() {
        add_action('init', [__CLASS__, 'register_rewrites']);
        add_action('init', [__CLASS__, 'maybe_flush_rewrites'], 99);
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_action('template_redirect', [__CLASS__, 'render'], 1);
        add_action('template_redirect', [__CLASS__, 'maybe_redirect_onboarding'], 2);
        add_action('dg_platform_register_rest_routes', [__CLASS__, 'register_rest']);
        add_action('dg_platform_register_menus', [__CLASS__, 'register_admin_menu']);
    }

    public static function register_rewrites() {
        add_rewrite_rule('^founding/accept/([^/]+)/?$', 'index.php?dg_founding=accept&dg_founding_token=$matches[1]', 'top');
        add_rewrite_rule('^founding/setup/?$', 'index.php?dg_founding=setup', 'top');
        add_rewrite_rule('^founding/trial-started/?$', 'index.php?dg_founding=trial', 'top');
        add_rewrite_rule('^founding-customers-preview/?$', 'index.php?dg_founding=invite', 'top');
    }

    public static function maybe_flush_rewrites() {
        if ((int) get_option(self::OPTION_REWRITE, 0) === self::REWRITE_VERSION) {
            return;
        }
        self::register_rewrites();
        flush_rewrite_rules(false);
        update_option(self::OPTION_REWRITE, self::REWRITE_VERSION);
    }

    public static function query_vars($vars) {
        $vars[] = 'dg_founding';
        $vars[] = 'dg_founding_token';
        return $vars;
    }

    public static function setup_is_ready() {
        return (bool) get_option(self::OPTION_SETUP_READY, false);
    }

    public static function set_setup_ready($ready) {
        update_option(self::OPTION_SETUP_READY, $ready ? 1 : 0);
    }

    public static function maybe_redirect_onboarding() {
        if (!self::setup_is_ready()) {
            return;
        }
        if (!is_page('onboarding') && trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/') !== 'onboarding') {
            return;
        }

        $token = class_exists('DG_Founding_Offers') ? DG_Founding_Offers::token_from_request() : '';
        $offer = $token !== '' ? DG_Founding_Offers::get($token) : null;
        if ($offer && in_array($offer['status'], ['accepted', 'setup', 'trialing'], true)) {
            wp_safe_redirect(DG_Founding_Offers::setup_url($token));
            exit;
        }

        wp_safe_redirect(home_url('/founding-customers-preview/'));
        exit;
    }

    public static function render() {
        self::hydrate_query_from_path();
        $view = get_query_var('dg_founding');
        if ($view === '') {
            return;
        }

        status_header(200);
        nocache_headers();

        if ($view === 'invite') {
            self::render_invite();
            exit;
        }
        if ($view === 'accept') {
            self::render_accept();
            exit;
        }
        if ($view === 'setup') {
            self::render_setup();
            exit;
        }
        if ($view === 'trial') {
            self::render_trial();
            exit;
        }
    }

    public static function register_rest() {
        register_rest_route(DG_REST_NAMESPACE, '/founding/accept', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_accept'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(DG_REST_NAMESPACE, '/founding/setup', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_setup'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(DG_REST_NAMESPACE, '/founding/checkout', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_checkout'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(DG_REST_NAMESPACE, '/founding/health', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_health'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function rest_health() {
        return rest_ensure_response([
            'ok' => true,
            'setup' => home_url('/founding/setup/'),
            'setup_ready_flag' => self::setup_is_ready(),
        ]);
    }

    public static function rest_accept($request) {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = $request->get_params();
        }
        $token = sanitize_text_field((string) ($body['token'] ?? ''));
        $agreed = !empty($body['agree_founding_terms']);
        if (!$agreed) {
            return new WP_Error('terms_required', 'Accept the Founding Customer Terms to proceed.', ['status' => 422]);
        }
        $result = DG_Founding_Offers::accept($token);
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response([
            'ok' => true,
            'redirect' => DG_Founding_Offers::setup_url($token),
        ]);
    }

    public static function rest_setup($request) {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = $request->get_params();
        }
        $token = sanitize_text_field((string) ($body['token'] ?? DG_Founding_Offers::token_from_request()));
        $result = DG_Founding_Offers::save_setup($token, $body);
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response(['ok' => true, 'offer' => self::public_offer($result)]);
    }

    public static function rest_checkout($request) {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = $request->get_params();
        }
        $token = sanitize_text_field((string) ($body['token'] ?? DG_Founding_Offers::token_from_request()));
        $offer = DG_Founding_Offers::get($token);
        if (!$offer) {
            return new WP_Error('not_found', 'Offer not found.', ['status' => 404]);
        }
        if (!in_array($offer['status'], ['accepted', 'setup', 'trialing'], true)) {
            return new WP_Error('not_accepted', 'Accept the Founding 10 offer before starting a trial.', ['status' => 403]);
        }
        if (!empty($body['billing_interval']) || !empty($body['platform_tier'])) {
            $saved = DG_Founding_Offers::save_setup($token, $body);
            if (!is_wp_error($saved)) {
                $offer = $saved;
            }
        }
        $session = DG_Founding_Checkout::create_session($offer);
        if (is_wp_error($session)) {
            return $session;
        }
        return rest_ensure_response([
            'ok' => true,
            'url' => $session['url'] ?? '',
            'session_id' => $session['id'] ?? '',
        ]);
    }

    public static function register_admin_menu() {
        if (!class_exists('DG_Founding_Admin')) {
            return;
        }
        add_submenu_page(
            'dg-platform',
            'Founding 10 Offers',
            '🎟️ Founding 10',
            'manage_options',
            'dg-platform-founding',
            ['DG_Founding_Admin', 'render']
        );
        if (class_exists('DG_Admin_Menu')) {
            DG_Admin_Menu::register_slug('dg-platform-founding', 'platform');
        }
    }

    /**
     * Pretty permalinks can miss custom rules on wp server until flush.
     * Resolve the Founding routes from the request path as a fallback.
     */
    private static function hydrate_query_from_path() {
        if ((string) get_query_var('dg_founding') !== '') {
            return;
        }
        $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        if (preg_match('#^founding/accept/([^/]+)$#', $path, $matches)) {
            set_query_var('dg_founding', 'accept');
            set_query_var('dg_founding_token', $matches[1]);
            return;
        }
        $map = [
            'founding/setup' => 'setup',
            'founding/trial-started' => 'trial',
            'founding-customers-preview' => 'invite',
        ];
        if (isset($map[$path])) {
            set_query_var('dg_founding', $map[$path]);
        }
    }

    private static function render_invite() {
        $file = DG_PLATFORM_PATH . 'marketing/pages/founding-customers-page.html';
        if (!file_exists($file)) {
            wp_die('Founding invite preview is missing.');
        }
        header('Content-Type: text/html; charset=utf-8');
        $html = (string) file_get_contents($file);
        $banner = '<div style="background:#1E3A5F;color:#DBEAFE;text-align:center;padding:.65rem 1rem;font:600 13px/1.4 Inter,system-ui,sans-serif;">Preview source — not the live Founding 10 funnel. Live /founding-customers/ stays unchanged until this journey is proven.</div>';
        $html = preg_replace('/<body([^>]*)>/', '<body$1>' . $banner, $html, 1);
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private static function render_accept() {
        $token = sanitize_text_field((string) get_query_var('dg_founding_token'));
        $offer = DG_Founding_Offers::get($token);
        if (!$offer) {
            status_header(404);
            wp_die('This Founding 10 offer link is not valid.');
        }
        include DG_PLATFORM_PATH . 'templates/frontend/founding-accept.php';
    }

    private static function render_setup() {
        $token = DG_Founding_Offers::token_from_request();
        $offer = $token !== '' ? DG_Founding_Offers::get($token) : null;
        if (!$offer || !in_array($offer['status'], ['accepted', 'setup', 'trialing'], true)) {
            $offer = null;
        }
        include DG_PLATFORM_PATH . 'templates/frontend/founding-setup.php';
    }

    private static function render_trial() {
        $token = DG_Founding_Offers::token_from_request();
        $session_id = sanitize_text_field((string) ($_GET['session_id'] ?? ''));
        $offer = $token !== '' ? DG_Founding_Offers::get($token) : null;
        if ($session_id !== '' && class_exists('DG_Stripe_Billing')) {
            $session = DG_Stripe_Billing::request(
                'checkout/sessions/' . rawurlencode($session_id) . '?expand[]=subscription',
                [],
                'GET'
            );
            if (!is_wp_error($session) && is_array($session)) {
                DG_Stripe_Billing::handle_checkout_completed($session);
                if ($token !== '') {
                    $sub_id = is_array($session['subscription'] ?? null)
                        ? (string) ($session['subscription']['id'] ?? '')
                        : (string) ($session['subscription'] ?? '');
                    DG_Founding_Offers::mark_trialing($token, $session_id, $sub_id);
                    $offer = DG_Founding_Offers::get($token);
                }
            }
        }
        include DG_PLATFORM_PATH . 'templates/frontend/founding-trial-started.php';
    }

    /** @param array<string,mixed> $offer */
    public static function public_offer(array $offer) {
        return [
            'token' => $offer['token'] ?? '',
            'status' => $offer['status'] ?? '',
            'email' => $offer['email'] ?? '',
            'name' => $offer['name'] ?? '',
            'business_name' => $offer['business_name'] ?? '',
            'platform_tier' => $offer['platform_tier'] ?? 'starter',
            'billing_interval' => $offer['billing_interval'] ?? 'month',
            'apps' => $offer['apps'] ?? [],
            'premium' => $offer['premium'] ?? [],
            'addons' => $offer['addons'] ?? [],
            'setup' => $offer['setup'] ?? [],
            'lines' => class_exists('DG_Founding_Checkout') ? DG_Founding_Checkout::line_items_preview($offer) : [],
            'recurring_cents' => class_exists('DG_Founding_Checkout') ? DG_Founding_Checkout::recurring_total_cents($offer) : 0,
        ];
    }
}
