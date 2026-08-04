<?php
/**
 * DG Platform admin crash debugger.
 *
 * INSTALL (pick one):
 *
 * A) Must-use plugin (recommended — catches fatals):
 *    Upload to: wp-content/mu-plugins/dg-platform-admin-debug.php
 *
 * B) WordPress root probe (one-off test page):
 *    Upload dg-admin-debug-probe.php next to wp-load.php, visit /dg-admin-debug-probe.php
 *
 * LOG FILE:
 *    wp-content/dg-admin-debug.log
 *
 * VIEW LOG:
 *    WP Admin → append ?dg_debug_log=1 to any admin URL (admins only)
 *    Or download dg-admin-debug.log via cPanel
 *
 * REMOVE when finished debugging.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('DG_Platform_Admin_Debug')) {

    class DG_Platform_Admin_Debug {

        const LOG = 'dg-admin-debug.log';

        public static function init() {
            if (defined('DG_PLATFORM_ADMIN_DEBUG') && !DG_PLATFORM_ADMIN_DEBUG) {
                return;
            }

            register_shutdown_function([__CLASS__, 'shutdown']);
            add_action('plugins_loaded', [__CLASS__, 'boot_log'], 1);
            add_action('admin_init', [__CLASS__, 'maybe_show_log']);
            add_action('admin_init', [__CLASS__, 'watch_admin_request'], 1);
            add_action('admin_notices', [__CLASS__, 'notice_if_recent_fatal']);

            $pages = [
                'toplevel_page_dg-platform',
                'dg-platform_page_dg-acc-dashboard',
                'dg-platform_page_dg-platform-seo',
                'dg-platform_page_dg-platform-social-pro',
            ];
            foreach ($pages as $hook) {
                add_action('load-' . $hook, [__CLASS__, 'probe_dashboard']);
                add_action('load-' . $hook, [__CLASS__, 'watch_render'], 9999);
            }
            add_action('admin_footer', [__CLASS__, 'render_complete'], 9999);
        }

        public static function watch_render() {
            self::log('render phase starting', self::current_screen_id());
        }

        public static function render_complete() {
            if (!self::is_dg_admin_request()) {
                return;
            }
            self::log('render phase complete', self::current_screen_id());
        }

        public static function log_path() {
            return defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/' . self::LOG : '';
        }

        /** @param mixed ...$parts */
        public static function log(...$parts) {
            $path = self::log_path();
            if ($path === '') {
                return;
            }

            $line = '[' . gmdate('Y-m-d H:i:s') . ' UTC] ';
            foreach ($parts as $part) {
                if (is_string($part) || is_numeric($part)) {
                    $line .= $part;
                } else {
                    $line .= wp_json_encode($part, JSON_UNESCAPED_SLASHES);
                }
                $line .= ' ';
            }
            $line = rtrim($line) . "\n";

            @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
        }

        public static function boot_log() {
            self::log('--- request ---', $_SERVER['REQUEST_METHOD'] ?? 'CLI', self::request_uri());
            self::log('PHP', PHP_VERSION, 'WP', get_bloginfo('version'));
            self::log('DG Platform', defined('DG_PLATFORM_VERSION') ? DG_PLATFORM_VERSION : 'not loaded');
            self::log('Active modules', get_option('dg_platform_active_modules', []));
        }

        public static function watch_admin_request() {
            if (!is_admin() || !self::is_dg_admin_request()) {
                return;
            }

            self::log('DG admin screen', self::current_screen_id(), 'user', get_current_user_id(), 'cap_check manage_options', current_user_can('manage_options') ? 'yes' : 'no');

            if (function_exists('get_current_screen')) {
                $screen = get_current_screen();
                if ($screen) {
                    self::log('screen', [
                        'id' => $screen->id,
                        'base' => $screen->base,
                        'post_type' => $screen->post_type ?? '',
                        'parent_file' => $screen->parent_file ?? '',
                    ]);
                }
            }
        }

        public static function probe_dashboard() {
            self::log('=== probe start ===', current_filter());

            self::probe_class('DG_Platform');
            self::probe_class('DG_Admin_Menu');
            self::probe_class('DG_Permissions');
            self::probe_class('DG_Reports');
            self::probe_class('DG_Activities');
            self::probe_class('DG_Onboarding');
            self::probe_class('DG_Acc_Reports');
            self::probe_class('DG_SEO_Analyzer');
            self::probe_class('DG_Social_Pro_Posts');

            global $submenu;
            $count = (!empty($submenu['dg-platform']) && is_array($submenu['dg-platform']))
                ? count($submenu['dg-platform'])
                : 0;
            self::log('submenu dg-platform items', $count);

            if (class_exists('DG_Admin_Menu') && method_exists('DG_Admin_Menu', 'launcher_apps')) {
                try {
                    $apps = DG_Admin_Menu::launcher_apps();
                    self::log('launcher_apps OK', count($apps), 'items');
                } catch (Throwable $e) {
                    self::log('launcher_apps FAIL', $e->getMessage(), $e->getFile() . ':' . $e->getLine());
                }
            } else {
                self::log('launcher_apps SKIPPED — method missing; upload full dg-platform v10.23.3+ zip');
                global $submenu;
                $fallback = 0;
                if (!empty($submenu['dg-platform']) && is_array($submenu['dg-platform'])) {
                    foreach ($submenu['dg-platform'] as $item) {
                        if (!is_array($item) || empty($item[2]) || $item[2] === 'dg-platform') {
                            continue;
                        }
                        if (strpos((string) $item[2], 'dg-sep-') === 0) {
                            continue;
                        }
                        $fallback++;
                    }
                }
                self::log('launcher_apps fallback count', $fallback);
            }

            if (class_exists('DG_Reports') && method_exists('DG_Reports', 'get_dashboard_stats')) {
                try {
                    $stats = DG_Reports::get_dashboard_stats();
                    self::log('dashboard_stats OK', $stats);
                } catch (Throwable $e) {
                    self::log('dashboard_stats FAIL', $e->getMessage(), $e->getFile() . ':' . $e->getLine());
                }
            }

            if (class_exists('DG_Activities') && method_exists('DG_Activities', 'recent')) {
                try {
                    $recent = DG_Activities::recent(3);
                    self::log('activities recent OK', is_array($recent) ? count($recent) : 0);
                } catch (Throwable $e) {
                    self::log('activities recent FAIL', $e->getMessage(), $e->getFile() . ':' . $e->getLine());
                }
            }

            if (class_exists('DG_Onboarding') && method_exists('DG_Onboarding', 'cached_summary')) {
                try {
                    $beta = DG_Onboarding::cached_summary(false);
                    self::log('onboarding summary OK', $beta);
                } catch (Throwable $e) {
                    self::log('onboarding summary FAIL', $e->getMessage(), $e->getFile() . ':' . $e->getLine());
                }
            }

            if (class_exists('DG_Acc_Reports') && method_exists('DG_Acc_Reports', 'summary')) {
                try {
                    $summary = DG_Acc_Reports::summary();
                    self::log('CVH summary OK', array_keys($summary));
                } catch (Throwable $e) {
                    self::log('CVH summary FAIL', $e->getMessage(), $e->getFile() . ':' . $e->getLine());
                }
            }

            self::log('=== probe end ===');
        }

        private static function probe_class($class) {
            self::log('class', $class, class_exists($class) ? 'loaded' : 'MISSING');
        }

        public static function shutdown() {
            $error = error_get_last();
            if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
                return;
            }

            if (!is_admin()) {
                return;
            }

            self::log(
                'FATAL',
                $error['message'],
                'in',
                $error['file'],
                'line',
                $error['line'],
                'uri',
                self::request_uri()
            );
        }

        public static function notice_if_recent_fatal() {
            if (!current_user_can('manage_options')) {
                return;
            }

            $path = self::log_path();
            if (!$path || !file_exists($path)) {
                return;
            }

            $tail = self::tail($path, 8000);
            if (stripos($tail, 'FATAL') === false && stripos($tail, 'FAIL') === false) {
                return;
            }

            $url = add_query_arg('dg_debug_log', '1');
            echo '<div class="notice notice-error"><p><strong>DG Debug:</strong> Issues logged to <code>'
                . esc_html(self::LOG)
                . '</code>. <a href="' . esc_url($url) . '">View log</a> — remove '
                . '<code>mu-plugins/dg-platform-admin-debug.php</code> when done.</p></div>';
        }

        public static function maybe_show_log() {
            if (!current_user_can('manage_options') || empty($_GET['dg_debug_log'])) {
                return;
            }

            $path = self::log_path();
            header('Content-Type: text/plain; charset=UTF-8');
            if (!$path || !file_exists($path)) {
                echo "Log file not found: " . self::LOG . "\n";
                exit;
            }
            echo self::tail($path, 120000);
            exit;
        }

        private static function tail($path, $bytes = 8000) {
            $size = filesize($path);
            if ($size === false) {
                return '';
            }
            $handle = fopen($path, 'rb');
            if (!$handle) {
                return '';
            }
            if ($size > $bytes) {
                fseek($handle, -$bytes, SEEK_END);
            }
            $data = fread($handle, $bytes);
            fclose($handle);
            return (string) $data;
        }

        private static function request_uri() {
            return isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        }

        private static function is_dg_admin_request() {
            if (!is_admin()) {
                return false;
            }
            $uri = self::request_uri();
            if (strpos($uri, 'page=dg-') !== false || strpos($uri, 'page=dg_') !== false) {
                return true;
            }
            if (strpos($uri, 'post_type=dg_') !== false) {
                return true;
            }
            if (isset($_GET['page'])) {
                $page = sanitize_key(wp_unslash($_GET['page']));
                if (strpos($page, 'dg-') === 0 || strpos($page, 'dg_') === 0) {
                    return true;
                }
            }
            return (bool) preg_match('#/wp-admin/(admin\.php|edit\.php|edit-tags\.php)#', $uri)
                && (strpos($uri, 'dg_') !== false || strpos($uri, 'dg-') !== false);
        }

        private static function current_screen_id() {
            if (!function_exists('get_current_screen')) {
                return '';
            }
            $screen = get_current_screen();
            return $screen ? (string) $screen->id : '';
        }
    }

    DG_Platform_Admin_Debug::init();
}
