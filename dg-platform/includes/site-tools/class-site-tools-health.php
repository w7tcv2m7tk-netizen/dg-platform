<?php
/**
 * Platform health checks for beta onboarding and support.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Site_Tools_Health {

    /** @return array{score:int,pass:int,warn:int,fail:int,checks:array<int,array<string,mixed>>} */
    public static function run() {
        $checks = array_merge(
            self::environment_checks(),
            self::platform_checks(),
            self::integration_checks(),
            self::conflict_checks()
        );

        $pass = $warn = $fail = 0;
        foreach ($checks as $check) {
            if ($check['status'] === 'pass') {
                $pass++;
            } elseif ($check['status'] === 'warn') {
                $warn++;
            } else {
                $fail++;
            }
        }

        $total = max(1, count($checks));
        $score = (int) round((($pass + ($warn * 0.5)) / $total) * 100);

        return [
            'score' => $score,
            'pass' => $pass,
            'warn' => $warn,
            'fail' => $fail,
            'checks' => $checks,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function environment_checks() {
        global $wpdb;

        $php_ok = version_compare(PHP_VERSION, '7.4', '>=');
        $wp_ok = version_compare(get_bloginfo('version'), '6.0', '>=');
        $ssl = is_ssl() || (strpos(home_url(), 'https://') === 0);
        $permalink = get_option('permalink_structure') !== '';
        $uploads = wp_upload_dir();
        $uploads_writable = empty($uploads['error']);

        return [
            self::item('php_version', 'PHP version', $php_ok ? 'pass' : 'fail', PHP_VERSION . ' (7.4+ required)'),
            self::item('wp_version', 'WordPress version', $wp_ok ? 'pass' : 'warn', get_bloginfo('version') . ' (6.0+ recommended)'),
            self::item('ssl', 'HTTPS', $ssl ? 'pass' : 'warn', $ssl ? 'Site uses HTTPS' : 'Site not served over HTTPS'),
            self::item('permalinks', 'Permalinks', $permalink ? 'pass' : 'fail', $permalink ? 'Pretty permalinks enabled' : 'Plain permalinks — save Settings → Permalinks'),
            self::item('uploads', 'Uploads writable', $uploads_writable ? 'pass' : 'fail', $uploads_writable ? 'Uploads directory OK' : ($uploads['error'] ?? 'Not writable')),
            self::item('db', 'Database connection', 'pass', 'Connected (' . $wpdb->dbname . ')'),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function platform_checks() {
        global $wpdb;

        $tables = [
            'dg_contacts' => $wpdb->prefix . 'dg_contacts',
            'dg_automations' => $wpdb->prefix . 'dg_automations',
        ];
        $missing = [];
        foreach ($tables as $label => $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                $missing[] = $label;
            }
        }

        $active = get_option('dg_platform_active_modules', ['core']);
        $plan = class_exists('DG_Plan_Registry') ? DG_Plan_Registry::current() : 'unknown';
        $cron_auto = (bool) wp_next_scheduled('dg_process_automations');
        if (!$cron_auto) {
            if (class_exists('DG_Automation')) {
                DG_Automation::schedule_cron();
                $cron_auto = (bool) wp_next_scheduled('dg_process_automations');
            }
        }
        $cron_ai = class_exists('DG_AI_Visibility_Cron') ? (bool) wp_next_scheduled(DG_AI_Visibility_Cron::HOOK) : null;
        $rest_ok = self::rest_api_available();

        $checks = [
            self::item('dg_version', 'DG Platform', 'pass', 'v' . DG_PLATFORM_VERSION),
            self::item('dg_tables', 'Core database tables', empty($missing) ? 'pass' : 'fail', empty($missing) ? 'All core tables present' : 'Missing: ' . implode(', ', $missing)),
            self::item('dg_modules', 'Active modules', !empty($active) ? 'pass' : 'warn', implode(', ', $active)),
            self::item('dg_plan', 'Platform plan', 'pass', ucfirst($plan)),
            self::item('dg_cron', 'Automation cron', $cron_auto ? 'pass' : 'warn', $cron_auto ? 'Scheduled' : 'Not scheduled — visit site or run WP-Cron'),
            self::item('dg_rest', 'REST API', $rest_ok ? 'pass' : 'warn', $rest_ok ? 'Responding' : 'REST check failed'),
        ];

        if ($cron_ai !== null) {
            $checks[] = self::item('dg_ai_cron', 'AI Visibility cron', $cron_ai ? 'pass' : 'warn', $cron_ai ? 'Scheduled' : 'Not scheduled');
        }

        if (class_exists('DG_Site_Profile') && DG_Site_Profile::modules_need_sync()) {
            $checks[] = self::item('dg_module_sync', 'Module sync', 'warn', 'Active modules differ from hostname recommendation — check Modules & Plan');
        }

        return $checks;
    }

    private static function rest_api_available() {
        if (!function_exists('rest_url')) {
            return false;
        }
        $routes = rest_get_server()->get_routes();
        $namespace = defined('DG_REST_NAMESPACE') ? DG_REST_NAMESPACE : 'digitalgate/v1';
        foreach ($routes as $route => $handlers) {
            if (strpos($route, '/' . $namespace . '/') === 0) {
                return true;
            }
        }
        return false;
    }

    /** @return array<int,array<string,mixed>> */
    private static function integration_checks() {
        $checks = [];

        $smtp = DG_Site_Tools_Settings::get('smtp_enabled') && DG_Site_Tools_Settings::get('smtp_host');
        $checks[] = self::item('smtp', 'SMTP email', $smtp ? 'pass' : 'warn', $smtp ? 'Configured in Site Tools' : 'Not configured — forms may use PHP mail()');

        $cf = DG_Site_Tools_Cloudflare::is_configured();
        $cf_source = DG_Site_Tools_Cloudflare::credentials()['source'] ?? 'none';
        $cf_zone = class_exists('DG_Site_Tools_Cloudflare') ? DG_Site_Tools_Cloudflare::zone_status() : ['configured' => false];
        $cf_verified = $cf && !empty($cf_zone['zone_name']);
        $cf_detail = $cf_verified
            ? 'Connected to ' . ($cf_zone['zone_name'] ?? 'Cloudflare zone') . ($cf_source !== 'site_tools' ? ' (via ' . str_replace('_', ' ', $cf_source) . ')' : '')
            : ($cf
                ? 'Credentials saved — verify token has Zone.Cache Purge permission'
                : 'Add token + Zone ID in Site Tools → Cache & CDN (not API Settings)');
        $checks[] = self::item('cloudflare', 'Cloudflare API', $cf_verified ? 'pass' : ($cf ? 'warn' : 'warn'), $cf_detail);

        $pagespeed = class_exists('DG_Integrations') && DG_Integrations::get_api_key('pagespeed');
        $checks[] = self::item('pagespeed_key', 'PageSpeed API key', $pagespeed ? 'pass' : 'warn', $pagespeed ? 'Set in API Settings' : 'Optional — needed for performance scores');

        if (class_exists('DG_SEO_Settings')) {
            $seo = DG_SEO_Settings::all();
            $checks[] = self::item('seo_sitemap', 'SEO sitemap', !empty($seo['sitemap_enabled']) ? 'pass' : 'warn', !empty($seo['sitemap_enabled']) ? home_url('/sitemap_index.xml') : 'Sitemap disabled');
        }

        if (class_exists('DG_Site_Profile') && DG_Site_Profile::is_roe_realty()) {
            $import_key = get_option('roe_realty_api_key', '');
            $checks[] = self::item('re_import', 'Property import API', $import_key ? 'pass' : 'warn', $import_key ? 'Import key configured' : 'Set roe_realty_api_key for property import');
        }

        return $checks;
    }

    /** @return array<int,array<string,mixed>> */
    private static function conflict_checks() {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $conflicts = [
            'seo-by-rank-math/rank-math.php' => ['label' => 'Rank Math', 'severity' => 'warn', 'fix' => 'Deactivate — DG SEO replaces it'],
            'fluent-smtp/fluent-smtp.php' => ['label' => 'Fluent SMTP', 'severity' => 'warn', 'fix' => 'Migrate to Site Tools → Email'],
            'fluent-snippets/fluent-snippets.php' => ['label' => 'Fluent Snippets', 'severity' => 'warn', 'fix' => 'Migrate to DG modules or Site Tools → Snippets'],
            'wp-smushit/wp-smush.php' => ['label' => 'Smush', 'severity' => 'warn', 'fix' => 'Use Site Tools → Images'],
            'google-site-kit/google-site-kit.php' => ['label' => 'Google Site Kit', 'severity' => 'warn', 'fix' => 'Use SEO + Site Tools → Analytics'],
        ];

        $checks = [];
        $any = false;
        foreach ($conflicts as $plugin => $info) {
            if (is_plugin_active($plugin)) {
                $any = true;
                $checks[] = self::item('conflict_' . sanitize_key($info['label']), $info['label'] . ' active', $info['severity'], $info['fix']);
            }
        }

        if (!$any) {
            $checks[] = self::item('conflicts', 'Legacy plugin conflicts', 'pass', 'No conflicting plugins detected');
        }

        return $checks;
    }

    /** @return array<string,mixed> */
    private static function item($id, $label, $status, $detail) {
        return [
            'id' => $id,
            'label' => $label,
            'status' => $status,
            'detail' => $detail,
        ];
    }
}
