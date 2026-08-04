<?php
/**
 * Cloudflare API — cache purge and basic analytics.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Site_Tools_Cloudflare {

    /** @return array{token:string,zone_id:string,source:string} */
    public static function credentials() {
        $token = trim((string) DG_Site_Tools_Settings::get('cf_api_token'));
        $zone = trim((string) DG_Site_Tools_Settings::get('cf_zone_id'));
        if ($token !== '' && $zone !== '') {
            return ['token' => $token, 'zone_id' => $zone, 'source' => 'site_tools'];
        }

        $legacy = self::legacy_credentials();
        if ($legacy['token'] !== '' && $legacy['zone_id'] !== '') {
            return $legacy;
        }

        return ['token' => '', 'zone_id' => '', 'source' => 'none'];
    }

    public static function is_configured() {
        $credentials = self::credentials();
        return $credentials['token'] !== '' && $credentials['zone_id'] !== '';
    }

    /** @return array{token:string,zone_id:string,source:string} */
    public static function legacy_credentials() {
        if (defined('SWCFPC_CF_API_TOKEN') && defined('SWCFPC_CF_API_ZONE_ID')) {
            $token = trim((string) SWCFPC_CF_API_TOKEN);
            $zone = trim((string) SWCFPC_CF_API_ZONE_ID);
            if ($token !== '' && $zone !== '') {
                return ['token' => $token, 'zone_id' => $zone, 'source' => 'wp_config'];
            }
        }

        if (defined('SPC_SETTINGS') && is_array(SPC_SETTINGS)) {
            $token = trim((string) (SPC_SETTINGS['cf_apitoken'] ?? ''));
            $zone = trim((string) (SPC_SETTINGS['cf_zoneid'] ?? ''));
            if ($token !== '' && $zone !== '') {
                return ['token' => $token, 'zone_id' => $zone, 'source' => 'spc_config'];
            }
        }

        if (class_exists('SPC\\Services\\Settings_Store')) {
            $store = \SPC\Services\Settings_Store::get_instance();
            $token = trim((string) $store->get('cf_apitoken', ''));
            $zone = trim((string) $store->get('cf_zoneid', ''));
            if ($token !== '' && $zone !== '') {
                return ['token' => $token, 'zone_id' => $zone, 'source' => 'super_page_cache'];
            }
        }

        $config = get_option('swcfpc_config', []);
        if (is_array($config)) {
            $token = trim((string) ($config['cf_apitoken'] ?? $config['cf_api_token'] ?? ''));
            $zone = trim((string) ($config['cf_zoneid'] ?? $config['cf_zone_id'] ?? ''));
            if ($token !== '' && $zone !== '') {
                return ['token' => $token, 'zone_id' => $zone, 'source' => 'super_page_cache'];
            }
        }

        return ['token' => '', 'zone_id' => '', 'source' => 'none'];
    }

    /** Import Cloudflare credentials from Super Page Cache into Site Tools when available. */
    public static function import_legacy_credentials() {
        $legacy = self::legacy_credentials();
        if ($legacy['source'] === 'none' || $legacy['source'] === 'site_tools') {
            return ['success' => false, 'message' => 'No Super Page Cache credentials found to import.'];
        }

        $current_token = trim((string) DG_Site_Tools_Settings::get('cf_api_token'));
        $current_zone = trim((string) DG_Site_Tools_Settings::get('cf_zone_id'));
        if ($current_token !== '' && $current_zone !== '') {
            return ['success' => true, 'message' => 'Site Tools already has Cloudflare credentials.'];
        }

        DG_Site_Tools_Settings::save([
            'cf_api_token' => $legacy['token'],
            'cf_zone_id' => $legacy['zone_id'],
        ]);

        return [
            'success' => true,
            'message' => 'Imported Cloudflare credentials from Super Page Cache.',
            'source' => $legacy['source'],
        ];
    }

    /** @return array{success:bool,message?:string,data?:array<string,mixed>} */
    private static function request($method, $path, $body = null) {
        $credentials = self::credentials();
        $token = $credentials['token'];
        if (!$token) {
            return ['success' => false, 'message' => 'Cloudflare API token not configured.'];
        }

        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 20,
        ];

        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request('https://api.cloudflare.com/client/v4/' . ltrim($path, '/'), $args);
        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['success'])) {
            $msg = $data['errors'][0]['message'] ?? 'Cloudflare API error.';
            return ['success' => false, 'message' => $msg];
        }

        return ['success' => true, 'data' => $data['result'] ?? $data];
    }

    /** @return array{configured:bool,zone_name?:string,plan?:string,status?:string,message?:string,source?:string} */
    public static function zone_status() {
        $credentials = self::credentials();
        $zone = $credentials['zone_id'];
        if (!$zone) {
            return ['configured' => false, 'message' => 'Zone ID not set.'];
        }

        $result = self::request('GET', "zones/{$zone}");
        if (!$result['success']) {
            return ['configured' => true, 'message' => $result['message'] ?? 'Unable to fetch zone.'];
        }

        $z = $result['data'];
        return [
            'configured' => true,
            'zone_name' => $z['name'] ?? '',
            'plan' => $z['plan']['name'] ?? '',
            'status' => $z['status'] ?? '',
            'source' => $credentials['source'],
        ];
    }

    /**
     * Last 7 days requests/bandwidth via GraphQL.
     *
     * @return array{success:bool,requests?:int,bandwidth?:int,message?:string}
     */
    public static function analytics_summary() {
        $credentials = self::credentials();
        $zone = $credentials['zone_id'];
        if (!$zone) {
            return ['success' => false, 'message' => 'Zone ID not set.'];
        }

        $query = [
            'query' => 'query { viewer { zones(filter: {zoneTag: "' . $zone . '"}) { httpRequests1dGroups(limit: 7, orderBy: [date_ASC]) { sum { requests bytes } } } } }',
        ];

        $token = $credentials['token'];
        $response = wp_remote_post('https://api.cloudflare.com/client/v4/graphql', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($query),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $groups = $data['data']['viewer']['zones'][0]['httpRequests1dGroups'] ?? [];
        if (!$groups) {
            return ['success' => false, 'message' => 'No analytics data returned. Ensure token has Analytics read permission.'];
        }

        $requests = 0;
        $bytes = 0;
        foreach ($groups as $group) {
            $requests += (int) ($group['sum']['requests'] ?? 0);
            $bytes += (int) ($group['sum']['bytes'] ?? 0);
        }

        return [
            'success' => true,
            'requests' => $requests,
            'bandwidth' => $bytes,
        ];
    }
}
