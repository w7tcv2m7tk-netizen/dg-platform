<?php
/**
 * OAuth flows for social platforms.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Social_Pro_OAuth {

    public static function init() {
        add_action('admin_init', [__CLASS__, 'handle_callback']);
    }

    public static function authorize_url($platform) {
        $platform = sanitize_key($platform);
        $settings = DG_Social_Pro_Settings::all();
        $definitions = DG_Social_Pro_Settings::platform_definitions();
        $def = $definitions[$platform] ?? null;
        if (!$def || empty($def['oauth_url'])) {
            return '';
        }

        $state = wp_create_nonce('dg_social_oauth_' . $platform);
        set_transient('dg_social_oauth_state_' . get_current_user_id() . '_' . $platform, $state, 600);

        $redirect = DG_Social_Pro_Settings::oauth_redirect_uri();
        $params = [
            'response_type' => 'code',
            'redirect_uri' => $redirect,
            'state' => $platform . '|' . $state,
        ];

        switch ($platform) {
            case 'facebook':
                $params['client_id'] = $settings['facebook_app_id'];
                $params['scope'] = $def['scopes'];
                break;
            case 'linkedin':
                $params['client_id'] = $settings['linkedin_client_id'];
                $params['scope'] = $def['scopes'];
                break;
            case 'x':
                $params['client_id'] = $settings['x_client_id'];
                $params['scope'] = $def['scopes'];
                $params['code_challenge'] = self::pkce_challenge($platform);
                $params['code_challenge_method'] = 'S256';
                break;
            case 'pinterest':
                $params['client_id'] = $settings['pinterest_app_id'];
                $params['scope'] = $def['scopes'];
                break;
            default:
                return '';
        }

        return add_query_arg($params, $def['oauth_url']);
    }

    public static function handle_callback() {
        if (!is_admin() || !isset($_GET['page']) || $_GET['page'] !== 'dg-platform-social-pro') {
            return;
        }
        if (empty($_GET['oauth']) || empty($_GET['code']) || empty($_GET['state'])) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }

        $state_parts = explode('|', sanitize_text_field(wp_unslash($_GET['state'])), 2);
        $platform = sanitize_key($state_parts[0] ?? '');
        $nonce = $state_parts[1] ?? '';
        if (!$platform || !wp_verify_nonce($nonce, 'dg_social_oauth_' . $platform)) {
            wp_die('Invalid OAuth state.');
        }

        $code = sanitize_text_field(wp_unslash($_GET['code']));
        $result = self::exchange_code($platform, $code);

        $redirect = admin_url('admin.php?page=dg-platform-social-pro&tab=connections');
        if (!empty($result['success'])) {
            wp_safe_redirect(add_query_arg('connected', $platform, $redirect));
        } else {
            wp_safe_redirect(add_query_arg(['oauth_error' => 1, 'platform' => $platform, 'msg' => rawurlencode($result['message'] ?? 'OAuth failed')], $redirect));
        }
        exit;
    }

    /** @return array{success:bool,message?:string} */
    private static function exchange_code($platform, $code) {
        $settings = DG_Social_Pro_Settings::all();
        $definitions = DG_Social_Pro_Settings::platform_definitions();
        $def = $definitions[$platform] ?? null;
        if (!$def || empty($def['token_url'])) {
            return ['success' => false, 'message' => 'Platform not supported.'];
        }

        $redirect = DG_Social_Pro_Settings::oauth_redirect_uri();
        $body = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirect,
        ];

        switch ($platform) {
            case 'facebook':
                $body['client_id'] = $settings['facebook_app_id'];
                $body['client_secret'] = $settings['facebook_app_secret'];
                break;
            case 'linkedin':
                $body['client_id'] = $settings['linkedin_client_id'];
                $body['client_secret'] = $settings['linkedin_client_secret'];
                break;
            case 'x':
                $body['client_id'] = $settings['x_client_id'];
                $body['code_verifier'] = get_transient('dg_social_pkce_' . get_current_user_id() . '_x');
                break;
            case 'pinterest':
                $body['client_id'] = $settings['pinterest_app_id'];
                break;
            default:
                return ['success' => false, 'message' => 'Unknown platform.'];
        }

        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
        if ($platform === 'pinterest') {
            $auth = base64_encode($settings['pinterest_app_id'] . ':' . $settings['pinterest_app_secret']);
            $headers['Authorization'] = 'Basic ' . $auth;
        }

        $response = wp_remote_post($def['token_url'], [
            'timeout' => 30,
            'headers' => $headers,
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['access_token'])) {
            $msg = $data['error_description'] ?? ($data['error']['message'] ?? 'Token exchange failed.');
            return ['success' => false, 'message' => $msg];
        }

        $connection = [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? '',
            'expires_in' => (int) ($data['expires_in'] ?? 0),
            'token_type' => $data['token_type'] ?? 'Bearer',
        ];

        if ($platform === 'facebook') {
            self::finalize_facebook($connection);
        } elseif ($platform === 'linkedin') {
            self::finalize_linkedin($connection);
        } elseif ($platform === 'x') {
            self::finalize_x($connection);
        } elseif ($platform === 'pinterest') {
            self::finalize_pinterest($connection);
        }

        DG_Social_Pro_Settings::save_connection($platform, $connection);
        return ['success' => true];
    }

    /** @param array<string,mixed> $connection */
    private static function finalize_facebook(array &$connection) {
        $token = $connection['access_token'];
        $pages = wp_remote_get('https://graph.facebook.com/v19.0/me/accounts?access_token=' . rawurlencode($token));
        if (is_wp_error($pages)) {
            return;
        }
        $data = json_decode(wp_remote_retrieve_body($pages), true);
        $page = $data['data'][0] ?? null;
        if (!$page) {
            $connection['account_name'] = 'Facebook (no pages found)';
            return;
        }

        $connection['page_id'] = $page['id'];
        $connection['page_access_token'] = $page['access_token'];
        $connection['account_name'] = $page['name'];

        // Linked Instagram Business account.
        $ig = wp_remote_get('https://graph.facebook.com/v19.0/' . rawurlencode($page['id']) . '?fields=instagram_business_account&access_token=' . rawurlencode($page['access_token']));
        if (!is_wp_error($ig)) {
            $ig_data = json_decode(wp_remote_retrieve_body($ig), true);
            if (!empty($ig_data['instagram_business_account']['id'])) {
                DG_Social_Pro_Settings::save_connection('instagram', [
                    'access_token' => $page['access_token'],
                    'instagram_account_id' => $ig_data['instagram_business_account']['id'],
                    'account_name' => $page['name'] . ' (Instagram)',
                    'page_id' => $page['id'],
                ]);
            }
        }
    }

    /** @param array<string,mixed> $connection */
    private static function finalize_linkedin(array &$connection) {
        $response = wp_remote_get('https://api.linkedin.com/v2/userinfo', [
            'headers' => ['Authorization' => 'Bearer ' . $connection['access_token']],
        ]);
        if (is_wp_error($response)) {
            return;
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($data['sub'])) {
            $connection['author_urn'] = 'urn:li:person:' . $data['sub'];
            $connection['account_name'] = $data['name'] ?? ($data['email'] ?? 'LinkedIn');
        }
    }

    /** @param array<string,mixed> $connection */
    private static function finalize_x(array &$connection) {
        $response = wp_remote_get('https://api.twitter.com/2/users/me', [
            'headers' => ['Authorization' => 'Bearer ' . $connection['access_token']],
        ]);
        if (is_wp_error($response)) {
            return;
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($data['data']['username'])) {
            $connection['account_name'] = '@' . $data['data']['username'];
            $connection['user_id'] = $data['data']['id'];
        }
    }

    /** @param array<string,mixed> $connection */
    private static function finalize_pinterest(array &$connection) {
        $response = wp_remote_get('https://api.pinterest.com/v5/user_account', [
            'headers' => ['Authorization' => 'Bearer ' . $connection['access_token']],
        ]);
        if (is_wp_error($response)) {
            return;
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $connection['account_name'] = $data['username'] ?? 'Pinterest';

        $boards = wp_remote_get('https://api.pinterest.com/v5/boards', [
            'headers' => ['Authorization' => 'Bearer ' . $connection['access_token']],
        ]);
        if (!is_wp_error($boards)) {
            $board_data = json_decode(wp_remote_retrieve_body($boards), true);
            if (!empty($board_data['items'][0]['id'])) {
                $connection['board_id'] = $board_data['items'][0]['id'];
                $connection['board_name'] = $board_data['items'][0]['name'] ?? '';
            }
        }
    }

    private static function pkce_challenge($platform) {
        $verifier = bin2hex(random_bytes(32));
        set_transient('dg_social_pkce_' . get_current_user_id() . '_' . $platform, $verifier, 600);
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}
