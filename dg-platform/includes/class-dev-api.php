<?php
/**
 * Dev API key for Cursor MCP and local tooling.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Dev_API {

    const OPTION = 'dg_dev_api_key';

    public static function init() {
        add_action('admin_post_dg_regenerate_dev_api_key', [__CLASS__, 'handle_regenerate']);
    }

    public static function get_key() {
        return (string) get_option(self::OPTION, '');
    }

    public static function get_or_create_key() {
        $key = self::get_key();
        if ($key === '') {
            $key = self::generate_key();
            update_option(self::OPTION, $key);
        }
        return $key;
    }

    public static function generate_key() {
        return 'dgdev_' . wp_generate_password(40, false, false);
    }

    public static function regenerate() {
        $key = self::generate_key();
        update_option(self::OPTION, $key);
        return $key;
    }

    public static function verify_request($request) {
        if (DG_Permissions::current_user_can('dg_re_view_leads')) {
            return true;
        }
        if (DG_Permissions::current_user_can('dg_marketing_view_clients')) {
            return true;
        }
        if (class_exists('DG_Acc_Permissions') && DG_Acc_Permissions::can_view_bookings()) {
            return true;
        }

        $api_key = self::extract_key_from_request($request);
        $stored = self::get_key();

        return $stored !== '' && $api_key !== '' && hash_equals($stored, $api_key);
    }

    public static function extract_key_from_request($request) {
        $api_key = $request->get_header('X-API-Key');
        if ($api_key) {
            return trim($api_key);
        }

        $auth = $request->get_header('Authorization');
        if ($auth && stripos($auth, 'Bearer ') === 0) {
            return trim(substr($auth, 7));
        }

        return '';
    }

    public static function handle_regenerate() {
        if (!check_admin_referer('dg_regenerate_dev_api_key') || !DG_Permissions::current_user_can('dg_manage_api_keys')) {
            wp_die('Unauthorized');
        }
        self::regenerate();
        wp_redirect(admin_url('admin.php?page=dg-platform-api&dev_key_regenerated=1'));
        exit;
    }
}

DG_Dev_API::init();
