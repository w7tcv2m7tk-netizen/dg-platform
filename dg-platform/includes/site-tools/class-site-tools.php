<?php
/**
 * Site Tools bootstrap.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Site_Tools {

    /** @var bool */
    private static $booted = false;

    public static function init() {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        if (!self::require_file('class-site-tools-settings.php')) {
            return;
        }

        self::require_file('class-site-tools-cache.php');
        self::require_file('class-site-tools-mail.php');
        self::require_file('class-site-tools-images.php');
        self::require_file('class-site-tools-snippets.php');
        self::require_file('class-site-tools-cloudflare.php');
        self::require_file('class-site-tools-google.php');
        self::require_file('class-site-tools-health.php');
        self::require_file('class-site-tools-dev-api.php');

        if (!self::require_file('class-site-tools-admin.php')) {
            return;
        }

        if (class_exists('DG_Site_Tools_Settings') && DG_Site_Tools_Settings::is_enabled()) {
            if (class_exists('DG_Site_Tools_Mail')) {
                DG_Site_Tools_Mail::init();
            }
            if (class_exists('DG_Site_Tools_Images')) {
                DG_Site_Tools_Images::init();
            }
            if (class_exists('DG_Site_Tools_Snippets')) {
                DG_Site_Tools_Snippets::init();
            }
        }

        if (is_admin() && class_exists('DG_Site_Tools_Admin')) {
            DG_Site_Tools_Admin::init();
        }
    }

    private static function require_file($file) {
        $path = __DIR__ . '/' . $file;
        if (!file_exists($path)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('DG Platform Site Tools: missing file ' . $file);
            }
            return false;
        }
        require_once $path;
        return true;
    }
}

add_action('plugins_loaded', ['DG_Site_Tools', 'init'], 7);
