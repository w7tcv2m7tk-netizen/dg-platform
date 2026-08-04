<?php
/**
 * Social Pro — settings and feature gate.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Social_Pro_Settings {

    const OPTION = 'dg_social_pro_settings';
    const CONNECTIONS_OPTION = 'dg_social_pro_connections';

    /** @return array<string,array<string,mixed>> */
    public static function platform_definitions() {
        return apply_filters('dg_social_pro_platforms', [
            'facebook' => [
                'label' => 'Facebook',
                'icon' => '📘',
                'color' => '#1877F2',
                'max_chars' => 63206,
                'supports_image' => true,
                'supports_link' => true,
                'oauth_url' => 'https://www.facebook.com/v19.0/dialog/oauth',
                'token_url' => 'https://graph.facebook.com/v19.0/oauth/access_token',
                'scopes' => 'pages_manage_posts,pages_read_engagement,pages_show_list',
                'help' => 'Connect a Facebook Page. Requires a Meta Developer app with Facebook Login.',
            ],
            'instagram' => [
                'label' => 'Instagram',
                'icon' => '📸',
                'color' => '#E4405F',
                'max_chars' => 2200,
                'supports_image' => true,
                'supports_link' => false,
                'requires' => 'facebook',
                'help' => 'Uses your connected Facebook Page\'s linked Instagram Business account.',
            ],
            'linkedin' => [
                'label' => 'LinkedIn',
                'icon' => '💼',
                'color' => '#0A66C2',
                'max_chars' => 3000,
                'supports_image' => true,
                'supports_link' => true,
                'oauth_url' => 'https://www.linkedin.com/oauth/v2/authorization',
                'token_url' => 'https://www.linkedin.com/oauth/v2/accessToken',
                'scopes' => 'w_member_social openid profile email',
                'help' => 'Post to your LinkedIn profile or company page. Requires LinkedIn Developer app.',
            ],
            'x' => [
                'label' => 'X (Twitter)',
                'icon' => '𝕏',
                'color' => '#000000',
                'max_chars' => 280,
                'supports_image' => true,
                'supports_link' => true,
                'oauth_url' => 'https://twitter.com/i/oauth2/authorize',
                'token_url' => 'https://api.twitter.com/2/oauth2/token',
                'scopes' => 'tweet.read tweet.write users.read offline.access',
                'help' => 'Requires X API access (Basic tier or higher) and a Developer app with OAuth 2.0.',
            ],
            'pinterest' => [
                'label' => 'Pinterest',
                'icon' => '📌',
                'color' => '#E60023',
                'max_chars' => 500,
                'supports_image' => true,
                'supports_link' => true,
                'oauth_url' => 'https://www.pinterest.com/oauth/',
                'token_url' => 'https://api.pinterest.com/v5/oauth/token',
                'scopes' => 'boards:read,pins:read,pins:write',
                'help' => 'Pin to a board. Requires a Pinterest Developer app and board ID.',
            ],
        ]);
    }

    public static function is_enabled() {
        if (!class_exists('DG_Plan_Registry')) {
            return true;
        }
        return DG_Plan_Registry::has_premium_app('social_pro');
    }

    public static function admin_visible() {
        return self::is_enabled();
    }

    public static function defaults() {
        return [
            'facebook_app_id' => '',
            'facebook_app_secret' => '',
            'linkedin_client_id' => '',
            'linkedin_client_secret' => '',
            'x_client_id' => '',
            'x_client_secret' => '',
            'pinterest_app_id' => '',
            'pinterest_app_secret' => '',
            'default_link' => '',
            'utm_source' => 'dg-platform',
            'utm_medium' => 'social',
        ];
    }

    /** @return array<string,mixed> */
    public static function all() {
        $saved = get_option(self::OPTION, []);
        return wp_parse_args(is_array($saved) ? $saved : [], self::defaults());
    }

    /** @return array<string,array<string,mixed>> */
    public static function connections() {
        $saved = get_option(self::CONNECTIONS_OPTION, []);
        return is_array($saved) ? $saved : [];
    }

    /** @param array<string,mixed> $data */
    public static function save_connection($platform, array $data) {
        $platform = sanitize_key($platform);
        $connections = self::connections();
        $connections[$platform] = array_merge($connections[$platform] ?? [], $data, [
            'updated_at' => current_time('mysql'),
        ]);
        update_option(self::CONNECTIONS_OPTION, $connections, false);
    }

    public static function disconnect($platform) {
        $connections = self::connections();
        unset($connections[sanitize_key($platform)]);
        update_option(self::CONNECTIONS_OPTION, $connections, false);
    }

    public static function is_connected($platform) {
        $conn = self::connections()[sanitize_key($platform)] ?? [];
        return !empty($conn['access_token']);
    }

    /** @return array<string,mixed> */
    public static function connection($platform) {
        return self::connections()[sanitize_key($platform)] ?? [];
    }

    public static function save(array $post) {
        $settings = self::all();
        $text_fields = [
            'facebook_app_id', 'facebook_app_secret',
            'linkedin_client_id', 'linkedin_client_secret',
            'x_client_id', 'x_client_secret',
            'pinterest_app_id', 'pinterest_app_secret',
            'default_link', 'utm_source', 'utm_medium',
        ];
        foreach ($text_fields as $key) {
            if (isset($post[$key])) {
                $settings[$key] = sanitize_text_field(wp_unslash($post[$key]));
            }
        }
        update_option(self::OPTION, $settings);
    }

    public static function oauth_redirect_uri() {
        return admin_url('admin.php?page=dg-platform-social-pro&tab=connections&oauth=1');
    }

    public static function get($key, $default = '') {
        $all = self::all();
        return $all[$key] ?? $default;
    }
}
