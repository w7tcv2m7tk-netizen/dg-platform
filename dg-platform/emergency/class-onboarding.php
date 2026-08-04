<?php
/**
 * Beta setup wizard — interactive onboarding checklist with auto-checks.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Onboarding {

    const DISMISS_OPTION = 'dg_platform_onboarding_dismissed';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 11);
        add_action('admin_post_dg_dismiss_onboarding', [__CLASS__, 'handle_dismiss']);
        add_action('admin_post_dg_reset_onboarding', [__CLASS__, 'handle_reset']);
        add_action('admin_notices', [__CLASS__, 'maybe_show_notice']);
    }

    public static function register_menu() {
        if (!current_user_can('manage_options')) {
            return;
        }

        add_submenu_page(
            'dg-platform',
            'Beta Setup',
            '🚀 Beta Setup',
            'manage_options',
            'dg-platform-onboarding',
            [__CLASS__, 'render_page']
        );
    }

    public static function handle_dismiss() {
        if (!check_admin_referer('dg_dismiss_onboarding') || !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        update_option(self::DISMISS_OPTION, 1);
        wp_safe_redirect(admin_url('admin.php?page=dg-platform'));
        exit;
    }

    public static function handle_reset() {
        if (!check_admin_referer('dg_reset_onboarding') || !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        delete_option(self::DISMISS_OPTION);
        wp_safe_redirect(admin_url('admin.php?page=dg-platform-onboarding&reset=1'));
        exit;
    }

    public static function maybe_show_notice() {
        if (!current_user_can('manage_options') || get_option(self::DISMISS_OPTION)) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->id === 'dg-platform_page_dg-platform-onboarding') {
            return;
        }

        $summary = self::cached_summary(false);
        if (!$summary || !empty($summary['ready'])) {
            return;
        }

        $url = admin_url('admin.php?page=dg-platform-onboarding');
        $dismiss = wp_nonce_url(admin_url('admin-post.php?action=dg_dismiss_onboarding'), 'dg_dismiss_onboarding');
        ?>
        <div class="notice notice-info">
            <p>
                <strong>DG Platform beta setup:</strong>
                <?php echo (int) $summary['complete']; ?>/<?php echo (int) $summary['total']; ?> steps complete
                (<?php echo (int) $summary['percent']; ?>%).
                <?php if ($summary['fail'] > 0) : ?>
                    <span style="color:#B45309;"><?php echo (int) $summary['fail']; ?> item(s) need attention.</span>
                <?php endif; ?>
                <a href="<?php echo esc_url($url); ?>" class="button button-primary" style="margin-left:8px;">Open Beta Setup</a>
                <a href="<?php echo esc_url($dismiss); ?>" style="margin-left:8px;">Dismiss</a>
            </p>
        </div>
        <?php
    }

    /** @return array<string,mixed> */
    public static function summary() {
        return self::cached_summary(true);
    }

    /**
     * Lightweight summary for dashboard/notices — skips remote HTTP checks unless forced.
     *
     * @return array<string,mixed>
     */
    public static function cached_summary($force = false) {
        $cache_key = 'dg_onboarding_summary_v' . DG_PLATFORM_VERSION;
        if (!$force) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        try {
            $summary = self::evaluate($force)['summary'];
            set_transient($cache_key, $summary, $force ? 15 * MINUTE_IN_SECONDS : HOUR_IN_SECONDS);
            return $summary;
        } catch (Throwable $e) {
            return [
                'total' => 0,
                'complete' => 0,
                'fail' => 0,
                'warn' => 0,
                'percent' => 0,
                'ready' => false,
            ];
        }
    }

    /** @return array{sections:array<int,array<string,mixed>>,summary:array<string,mixed>} */
    public static function evaluate($run_remote_checks = true) {
        $sections = [];
        foreach (self::section_definitions() as $section) {
            $steps = [];
            foreach ($section['steps'] as $step) {
                $check = $step['check'] ?? null;
                $status = 'warn';
                if (!$run_remote_checks && self::is_remote_check($check)) {
                    $status = 'warn';
                } elseif (is_callable($check)) {
                    try {
                        $status = call_user_func($check);
                    } catch (Throwable $e) {
                        $status = 'warn';
                    }
                }
                $steps[] = array_merge($step, ['status' => $status]);
            }
            $sections[] = [
                'id' => $section['id'],
                'title' => $section['title'],
                'steps' => $steps,
            ];
        }

        return [
            'sections' => $sections,
            'summary' => self::summarize($sections),
        ];
    }

    /** @param callable|null $check */
    private static function is_remote_check($check) {
        if (!is_array($check) || count($check) !== 2) {
            return false;
        }
        return in_array($check[1], [
            'check_sitemap',
            'check_llms',
            'check_re_booking_page',
            'check_voice_webhook',
        ], true);
    }

    /** @param array<int,array<string,mixed>> $sections */
    private static function summarize(array $sections) {
        $total = $complete = $fail = $warn = 0;
        foreach ($sections as $section) {
            foreach ($section['steps'] as $step) {
                $total++;
                if ($step['status'] === 'pass') {
                    $complete++;
                } elseif ($step['status'] === 'fail') {
                    $fail++;
                } elseif ($step['status'] === 'warn') {
                    $warn++;
                }
            }
        }

        $percent = $total > 0 ? (int) round(($complete / $total) * 100) : 0;
        $ready = $fail === 0 && $percent >= 85;

        return [
            'total' => $total,
            'complete' => $complete,
            'fail' => $fail,
            'warn' => $warn,
            'percent' => $percent,
            'ready' => $ready,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function section_definitions() {
        $is_re = class_exists('DG_Site_Profile') && DG_Site_Profile::is_roe_realty();
        $is_dg = class_exists('DG_Site_Profile') && DG_Site_Profile::is_digitalgate();

        $sections = [
            [
                'id' => 'foundation',
                'title' => 'Foundation',
                'steps' => [
                    self::step('permalinks', 'Pretty permalinks', 'Settings → Permalinks → Post name', admin_url('options-permalink.php'), [__CLASS__, 'check_permalinks']),
                    self::step('modules', 'Correct modules active', self::modules_detail(), admin_url('admin.php?page=dg-platform-modules'), [__CLASS__, 'check_modules']),
                    self::step('plan', 'Plan tier configured', self::plan_detail(), admin_url('admin.php?page=dg-platform-modules'), [__CLASS__, 'check_plan']),
                ],
            ],
            [
                'id' => 'site_tools',
                'title' => 'Site Tools & Health',
                'steps' => [
                    self::step('health', 'Platform Health ≥ 85%, zero failures', 'DG Platform → Site Tools → Platform Health', admin_url('admin.php?page=dg-platform-site-tools&tab=health'), [__CLASS__, 'check_health']),
                    self::step('smtp', 'SMTP email configured', 'Site Tools → Email — send test email', admin_url('admin.php?page=dg-platform-site-tools&tab=email'), [__CLASS__, 'check_smtp']),
                    self::step('cache', 'Cloudflare cache purge ready', 'Site Tools → Cache — API token + Zone ID', admin_url('admin.php?page=dg-platform-site-tools&tab=cache'), [__CLASS__, 'check_cloudflare']),
                ],
            ],
            [
                'id' => 'growth',
                'title' => 'SEO Pro, AI & Analytics',
                'steps' => [
                    self::step('api_keys', 'API keys configured', 'PageSpeed + OpenAI/Gemini for AI Visibility', admin_url('admin.php?page=dg-platform-api'), [__CLASS__, 'check_api_keys']),
                    self::step('seo_home', 'SEO Pro home title & description', 'DG Platform → SEO Pro → Global', admin_url('admin.php?page=dg-platform-seo'), [__CLASS__, 'check_seo_home']),
                    self::step('sitemap', 'XML sitemap responding', home_url('/sitemap_index.xml'), admin_url('admin.php?page=dg-platform-seo&tab=sitemap'), [__CLASS__, 'check_sitemap']),
                    self::step('llms', 'AI llms.txt endpoint', home_url('/llms.txt'), admin_url('admin.php?page=dg-platform-ai-visibility'), [__CLASS__, 'check_llms']),
                    self::step('ai_scan', 'AI Visibility scan completed', 'Run first scan from AI Visibility Pro', admin_url('admin.php?page=dg-platform-ai-visibility'), [__CLASS__, 'check_ai_scan']),
                ],
            ],
            [
                'id' => 'cleanup',
                'title' => 'Legacy plugin cleanup',
                'steps' => [
                    self::step('legacy', 'Standard plugin stack only', 'Oxygen + Breakdance (Elements & Forms) + DG Platform — no Rank Math, Fluent, Smush, or Site Kit', admin_url('plugins.php'), [__CLASS__, 'check_legacy_plugins']),
                ],
            ],
        ];

        if ($is_re) {
            $sections[] = [
                'id' => 'real_estate',
                'title' => 'Real Estate module',
                'steps' => [
                    self::step('re_types', 'Property & Agent post types', 'Create a test property in admin', admin_url('edit.php?post_type=property'), [__CLASS__, 'check_re_post_types']),
                    self::step('re_booking', 'Appraisal booking page', '/property-appraisal/ loads on frontend — ' . home_url('/property-appraisal/'), admin_url('admin.php?page=dg-re-bookings'), [__CLASS__, 'check_re_booking_page']),
                    self::step('re_import', 'Property import API key', 'Optional — for listing import', admin_url('admin.php?page=dg-platform-api'), [__CLASS__, 'check_re_import_key']),
                ],
            ];
        }

        if ($is_dg) {
            $sections[] = [
                'id' => 'marketing',
                'title' => 'DigitalGate Marketing CRM',
                'steps' => [
                    self::step('mkt_voice', 'Voice agent webhook active', 'GET ' . rest_url('digitalgate/v1/voice-agent'), admin_url('admin.php?page=dg-platform-clients'), [__CLASS__, 'check_voice_webhook']),
                    self::step('mkt_beta_page', 'Beta program page published', 'Paste beta-program-page.html into Oxygen page at /beta/', admin_url('edit.php?post_type=page'), [__CLASS__, 'check_beta_page']),
                ],
            ];
        }

        $sections[] = [
            'id' => 'golive',
            'title' => 'Go-live',
            'steps' => [
                self::step('purge', 'Purge Cloudflare cache', 'Site Tools → Cache → Purge all cache (marks complete after first successful purge)', admin_url('admin.php?page=dg-platform-site-tools&tab=cache'), [__CLASS__, 'check_manual_purge']),
                self::step('rest', 'REST API responding', rest_url('digitalgate/v1/stats'), admin_url('admin.php?page=dg-platform-api'), [__CLASS__, 'check_rest_routes']),
            ],
        ];

        return $sections;
    }

    /** @return array<string,mixed> */
    private static function step($id, $label, $detail, $url, $check) {
        return [
            'id' => $id,
            'label' => $label,
            'detail' => $detail,
            'url' => $url,
            'check' => $check,
        ];
    }

    private static function modules_detail() {
        if (!class_exists('DG_Site_Profile')) {
            return 'Core + one vertical module';
        }
        return 'Recommended: ' . implode(', ', DG_Site_Profile::recommended_modules());
    }

    private static function plan_detail() {
        if (class_exists('DG_Site_Profile') && DG_Site_Profile::is_digitalgate()) {
            return 'Enterprise for DigitalGate';
        }
        if (class_exists('DG_Site_Profile') && DG_Site_Profile::is_roe_realty()) {
            return 'Business or higher for Real Estate beta';
        }
        return 'Match plan to sold tier';
    }

    public static function check_permalinks() {
        return get_option('permalink_structure') ? 'pass' : 'fail';
    }

    public static function check_modules() {
        $active = get_option('dg_platform_active_modules', ['core']);
        if (!class_exists('DG_Site_Profile')) {
            return count($active) >= 1 ? 'pass' : 'warn';
        }
        $recommended = DG_Site_Profile::recommended_modules();
        sort($recommended);
        $sorted = array_values(array_filter($active));
        sort($sorted);
        if ($recommended === $sorted) {
            return 'pass';
        }
        return 'warn';
    }

    public static function check_plan() {
        if (!class_exists('DG_Plan_Registry')) {
            return 'warn';
        }
        $plan = DG_Plan_Registry::current();
        if (class_exists('DG_Site_Profile') && DG_Site_Profile::is_digitalgate()) {
            return $plan === 'enterprise' ? 'pass' : 'warn';
        }
        if (class_exists('DG_Site_Profile') && (DG_Site_Profile::is_roe_realty() || DG_Site_Profile::is_currumbin_hideaway())) {
            return in_array($plan, ['business', 'enterprise'], true) ? 'pass' : 'warn';
        }
        return $plan ? 'pass' : 'warn';
    }

    public static function check_health() {
        if (!class_exists('DG_Site_Tools_Health')) {
            return 'warn';
        }
        $health = DG_Site_Tools_Health::run();
        if (($health['fail'] ?? 0) > 0) {
            return 'fail';
        }
        if (($health['score'] ?? 0) >= 85) {
            return 'pass';
        }
        return 'warn';
    }

    public static function check_smtp() {
        if (!class_exists('DG_Site_Tools_Settings')) {
            return 'warn';
        }
        $on = DG_Site_Tools_Settings::get('smtp_enabled') && DG_Site_Tools_Settings::get('smtp_host') && DG_Site_Tools_Settings::get('smtp_user');
        return $on ? 'pass' : 'warn';
    }

    public static function check_cloudflare() {
        if (!class_exists('DG_Site_Tools_Cloudflare')) {
            return 'warn';
        }
        $zone = DG_Site_Tools_Cloudflare::zone_status();
        if (!empty($zone['zone_name'])) {
            return 'pass';
        }
        if (DG_Site_Tools_Cloudflare::is_configured()) {
            return 'warn';
        }
        if (get_option('dg_site_tools_last_cache_purge')) {
            return 'pass';
        }
        return 'warn';
    }

    public static function check_api_keys() {
        $pagespeed = class_exists('DG_Integrations') && DG_Integrations::get_api_key('pagespeed');
        $openai = class_exists('DG_Integrations') && DG_Integrations::get_api_key('openai');
        if ($pagespeed && $openai) {
            return 'pass';
        }
        if ($pagespeed || $openai) {
            return 'warn';
        }
        return 'warn';
    }

    public static function check_seo_home() {
        if (!class_exists('DG_SEO_Settings')) {
            return 'warn';
        }
        $title = DG_SEO_Settings::get('home_title', '');
        $desc = DG_SEO_Settings::get('home_description', '');
        return ($title && $desc) ? 'pass' : 'warn';
    }

    public static function check_sitemap() {
        $resp = wp_remote_head(home_url('/sitemap_index.xml'), ['timeout' => 8, 'sslverify' => false]);
        if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
            return 'pass';
        }
        return 'fail';
    }

    public static function check_llms() {
        if (!class_exists('DG_AI_Visibility_Settings') || !DG_AI_Visibility_Settings::get('llms_txt_enabled')) {
            return 'warn';
        }
        $resp = wp_remote_head(home_url('/llms.txt'), ['timeout' => 8, 'sslverify' => false]);
        if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
            return 'pass';
        }
        return 'warn';
    }

    public static function check_ai_scan() {
        if (!class_exists('DG_AI_Visibility_History')) {
            return 'warn';
        }

        DG_AI_Visibility_History::ensure_table();

        if (DG_AI_Visibility_History::latest()) {
            return 'pass';
        }

        $averages = DG_AI_Visibility_History::averages(365);
        return ($averages['scans'] ?? 0) > 0 ? 'pass' : 'warn';
    }

    public static function check_legacy_plugins() {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $conflicts = [
            'seo-by-rank-math/rank-math.php',
            'fluent-smtp/fluent-smtp.php',
            'fluent-snippets/fluent-snippets.php',
            'wp-smushit/wp-smush.php',
            'google-site-kit/google-site-kit.php',
        ];
        foreach ($conflicts as $plugin) {
            if (is_plugin_active($plugin)) {
                return 'warn';
            }
        }
        return 'pass';
    }

    public static function check_re_post_types() {
        return post_type_exists('property') ? 'pass' : 'warn';
    }

    public static function check_re_booking_page() {
        $resp = wp_remote_head(home_url('/property-appraisal/'), ['timeout' => 8, 'sslverify' => false]);
        if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
            return 'pass';
        }
        return 'warn';
    }

    public static function check_re_import_key() {
        $key = get_option('roe_realty_api_key', '');
        return $key !== '' ? 'pass' : 'warn';
    }

    public static function check_voice_webhook() {
        $resp = wp_remote_get(rest_url('digitalgate/v1/voice-agent'), ['timeout' => 10, 'sslverify' => false]);
        if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
            return 'pass';
        }
        return 'warn';
    }

    public static function check_beta_page() {
        $page = get_page_by_path('beta');
        if ($page && $page->post_status === 'publish') {
            return 'pass';
        }
        return 'warn';
    }

    public static function check_manual_purge() {
        if (get_option('dg_site_tools_last_cache_purge')) {
            return 'pass';
        }
        return 'warn';
    }

    public static function check_rest_routes() {
        if (!function_exists('rest_get_server')) {
            return 'warn';
        }
        $routes = rest_get_server()->get_routes();
        foreach ($routes as $route => $handlers) {
            if (strpos($route, '/digitalgate/v1/') === 0) {
                return 'pass';
            }
        }
        return 'fail';
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $data = self::evaluate(true);
        $sections = $data['sections'];
        $summary = $data['summary'];

        include DG_PLATFORM_PATH . 'templates/admin/onboarding.php';
    }
}

DG_Onboarding::init();
