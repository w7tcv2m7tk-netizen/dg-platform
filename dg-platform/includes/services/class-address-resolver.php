<?php
/**
 * Australian address resolver — proxies Gen 2 platform API with local fallback.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Address_Resolver {

    const DEFAULT_APP_URL = 'https://app.digitalgate.com.au';

    public static function app_url() {
        if (defined('DG_APP_URL') && DG_APP_URL !== '') {
            return untrailingslashit(DG_APP_URL);
        }
        $option = (string) get_option('dg_app_url', '');
        if ($option !== '') {
            return untrailingslashit($option);
        }
        return self::DEFAULT_APP_URL;
    }

    public static function resolve($raw_address, $args = []) {
        $raw = trim((string) $raw_address);
        if ($raw === '') {
            return new WP_Error('empty_address', 'Address is required.');
        }

        $platform = self::resolve_via_platform($raw, $args);
        if (!is_wp_error($platform)) {
            return $platform;
        }

        $local = self::resolve_via_nominatim($raw, $args);
        if (!is_wp_error($local)) {
            return $local;
        }

        return self::fallback_payload($raw);
    }

    public static function apply_to_lead_data($data) {
        $raw = sanitize_text_field($data['property_address'] ?? '');
        if ($raw === '') {
            return $data;
        }

        $resolved = self::resolve($raw);
        if (is_wp_error($resolved)) {
            return $data;
        }

        $data['property_address'] = $resolved['formatted'];
        $data['property_suburb'] = $resolved['suburb'];
        $data['property_state'] = $resolved['state'];
        $data['property_postcode'] = $resolved['postcode'];
        $data['property_address_line1'] = $resolved['addressLine1'];
        $data['property_address_metadata'] = $resolved['metadata'];

        return $data;
    }

    public static function apply_to_property_meta($post_id, $address, $suburb = '', $state = 'QLD', $postcode = '') {
        $raw = trim((string) $address);
        if ($raw === '') {
            return;
        }

        $combined = $raw;
        if ($suburb !== '') {
            $combined = $raw . ', ' . $suburb;
            if ($state !== '') {
                $combined .= ' ' . $state;
            }
            if ($postcode !== '') {
                $combined .= ' ' . $postcode;
            }
        }

        $resolved = self::resolve($combined);
        if (is_wp_error($resolved)) {
            return;
        }

        update_post_meta($post_id, 'roe_property_address', sanitize_text_field($resolved['addressLine1']));
        update_post_meta($post_id, 'roe_property_suburb', sanitize_text_field($resolved['suburb']));
        update_post_meta($post_id, 'roe_property_state', sanitize_text_field($resolved['state']));
        update_post_meta($post_id, 'roe_property_postcode', sanitize_text_field($resolved['postcode']));
        update_post_meta($post_id, 'roe_property_address_metadata', wp_json_encode($resolved['metadata']));
    }

    private static function resolve_via_platform($raw, $args) {
        if (!class_exists('DG_Dev_API')) {
            return new WP_Error('no_dev_api', 'Dev API unavailable.');
        }

        $key = DG_Dev_API::get_key();
        if ($key === '') {
            return new WP_Error('no_api_key', 'Dev API key not configured.');
        }

        $url = self::app_url() . '/api/v1/addresses/resolve';
        $response = wp_remote_post($url, [
            'timeout' => 12,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Key' => $key,
            ],
            'body' => wp_json_encode([
                'rawAddress' => $raw,
                'geocode' => !empty($args['geocode']),
                'forceGeocode' => !empty($args['forceGeocode']),
                'regionBias' => $args['regionBias'] ?? 'Gold Coast, QLD, Australia',
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300 || empty($body['data'])) {
            return new WP_Error('platform_resolve_failed', 'Platform address resolve failed.', ['status' => $code]);
        }

        return self::normalize_payload($raw, $body['data']);
    }

    private static function resolve_via_nominatim($raw, $args) {
        $query = rawurlencode($raw . ', Australia');
        $region = rawurlencode($args['regionBias'] ?? 'Gold Coast, QLD, Australia');
        $url = 'https://nominatim.openstreetmap.org/search?q=' . $query . '&format=json&addressdetails=1&countrycodes=au&limit=1&viewbox=&bounded=0';

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'DigitalGate-Platform/' . (defined('DG_PLATFORM_VERSION') ? DG_PLATFORM_VERSION : '1'),
                'Accept-Language' => 'en-AU',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $results = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($results) || empty($results[0])) {
            return new WP_Error('nominatim_miss', 'No geocode match.');
        }

        $result = $results[0];
        $addr = isset($result['address']) && is_array($result['address']) ? $result['address'] : [];

        $line1 = trim(
            implode(' ', array_filter([
                $addr['house_number'] ?? '',
                $addr['road'] ?? '',
            ]))
        );
        if ($line1 === '') {
            $line1 = trim(explode(',', (string) ($result['display_name'] ?? $raw))[0]);
        }

        $suburb = $addr['suburb'] ?? $addr['town'] ?? $addr['city'] ?? $addr['locality'] ?? 'Gold Coast';
        $state = strtoupper($addr['state'] ?? 'QLD');
        if (strlen($state) > 3) {
            $state = self::state_abbrev($state);
        }
        $postcode = $addr['postcode'] ?? '0000';

        return self::normalize_payload($raw, [
            'addressLine1' => $line1,
            'suburb' => $suburb,
            'state' => $state,
            'postcode' => $postcode,
            'confidence' => 'geocoded',
            'geocodeSource' => 'nominatim',
            'latitude' => isset($result['lat']) ? (float) $result['lat'] : null,
            'longitude' => isset($result['lon']) ? (float) $result['lon'] : null,
            'formattedAddress' => $result['display_name'] ?? '',
        ]);
    }

    private static function fallback_payload($raw) {
        $parts = array_map('trim', explode(',', $raw));
        $line1 = $parts[0] ?? $raw;
        $suburb = $parts[1] ?? 'Gold Coast';

        return self::normalize_payload($raw, [
            'addressLine1' => $line1,
            'suburb' => $suburb,
            'state' => 'QLD',
            'postcode' => '0000',
            'confidence' => 'fallback',
        ]);
    }

    private static function normalize_payload($raw, $data) {
        $line1 = sanitize_text_field($data['addressLine1'] ?? $raw);
        $suburb = sanitize_text_field($data['suburb'] ?? 'Gold Coast');
        $state = strtoupper(sanitize_text_field($data['state'] ?? 'QLD'));
        $postcode = sanitize_text_field($data['postcode'] ?? '0000');

        $formatted = $line1 . ', ' . $suburb . ' ' . $state . ' ' . $postcode;

        return [
            'rawAddress' => $raw,
            'addressLine1' => $line1,
            'suburb' => $suburb,
            'state' => $state,
            'postcode' => $postcode,
            'formatted' => $formatted,
            'confidence' => sanitize_text_field($data['confidence'] ?? 'parsed'),
            'geocodeSource' => isset($data['geocodeSource']) ? sanitize_text_field($data['geocodeSource']) : null,
            'latitude' => isset($data['latitude']) ? (float) $data['latitude'] : null,
            'longitude' => isset($data['longitude']) ? (float) $data['longitude'] : null,
            'formattedAddress' => sanitize_text_field($data['formattedAddress'] ?? $formatted),
            'metadata' => [
                'address_confidence' => sanitize_text_field($data['confidence'] ?? 'parsed'),
                'geocode_source' => isset($data['geocodeSource']) ? sanitize_text_field($data['geocodeSource']) : null,
                'latitude' => isset($data['latitude']) ? (float) $data['latitude'] : null,
                'longitude' => isset($data['longitude']) ? (float) $data['longitude'] : null,
                'formatted_address' => sanitize_text_field($data['formattedAddress'] ?? $formatted),
            ],
        ];
    }

    private static function state_abbrev($name) {
        $map = [
            'queensland' => 'QLD',
            'new south wales' => 'NSW',
            'victoria' => 'VIC',
            'south australia' => 'SA',
            'western australia' => 'WA',
            'tasmania' => 'TAS',
            'northern territory' => 'NT',
            'australian capital territory' => 'ACT',
        ];
        $key = strtolower(trim($name));
        return $map[$key] ?? strtoupper(substr($name, 0, 3));
    }
}
