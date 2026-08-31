<?php
/**
 * Founding 10 offer tokens — v1 email offer → secure accept link.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Founding_Offers {

    const OPTION = 'dg_founding_offers';
    const COOKIE = 'dg_founding_offer';

    /** @return array<string,array<string,mixed>> */
    public static function all() {
        $rows = get_option(self::OPTION, []);
        return is_array($rows) ? $rows : [];
    }

    /** @return array<string,mixed>|null */
    public static function get($token) {
        $token = sanitize_text_field((string) $token);
        if ($token === '') {
            return null;
        }
        $rows = self::all();
        return isset($rows[$token]) && is_array($rows[$token]) ? $rows[$token] : null;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public static function create(array $data) {
        $token = 'f10_' . wp_generate_password(24, false, false);
        $now = current_time('mysql');
        $row = [
            'token' => $token,
            'status' => 'issued',
            'email' => sanitize_email((string) ($data['email'] ?? '')),
            'name' => sanitize_text_field((string) ($data['name'] ?? '')),
            'business_name' => sanitize_text_field((string) ($data['business_name'] ?? '')),
            'platform_tier' => sanitize_key((string) ($data['platform_tier'] ?? 'starter')),
            'billing_interval' => in_array(($data['billing_interval'] ?? 'month'), ['month', 'year'], true)
                ? $data['billing_interval'] : 'month',
            'apps' => self::sanitize_list($data['apps'] ?? []),
            'premium' => self::sanitize_list($data['premium'] ?? []),
            'addons' => self::sanitize_list($data['addons'] ?? []),
            'setup' => [],
            'stripe_session_id' => '',
            'stripe_subscription_id' => '',
            'stripe_status' => '',
            'created_at' => $now,
            'accepted_at' => '',
            'setup_completed_at' => '',
            'trial_started_at' => '',
        ];
        $rows = self::all();
        $rows[$token] = $row;
        update_option(self::OPTION, $rows, false);
        return $row;
    }

    /** @param array<string,mixed> $patch */
    public static function update($token, array $patch) {
        $token = sanitize_text_field((string) $token);
        $rows = self::all();
        if (!isset($rows[$token]) || !is_array($rows[$token])) {
            return null;
        }
        $rows[$token] = array_merge($rows[$token], $patch);
        $rows[$token]['token'] = $token;
        update_option(self::OPTION, $rows, false);
        return $rows[$token];
    }

    public static function accept($token) {
        $offer = self::get($token);
        if (!$offer) {
            return new WP_Error('not_found', 'This Founding 10 offer link is not valid.', ['status' => 404]);
        }
        if (in_array($offer['status'], ['void'], true)) {
            return new WP_Error('void', 'This offer is no longer available.', ['status' => 410]);
        }
        $patch = [
            'status' => $offer['status'] === 'trialing' ? $offer['status'] : 'accepted',
            'accepted_at' => $offer['accepted_at'] !== '' ? $offer['accepted_at'] : current_time('mysql'),
        ];
        $updated = self::update($token, $patch);
        self::set_cookie($token);
        return $updated;
    }

    /** @param array<string,mixed> $setup */
    public static function save_setup($token, array $setup) {
        $offer = self::get($token);
        if (!$offer) {
            return new WP_Error('not_found', 'Offer not found.', ['status' => 404]);
        }
        if (!in_array($offer['status'], ['accepted', 'setup', 'trialing'], true)) {
            return new WP_Error('not_accepted', 'Accept the Founding 10 offer before onboarding.', ['status' => 403]);
        }

        $clean = self::sanitize_setup($setup);
        $tier = sanitize_key((string) ($setup['platform_tier'] ?? $offer['platform_tier']));
        $interval = in_array(($setup['billing_interval'] ?? $offer['billing_interval']), ['month', 'year'], true)
            ? $setup['billing_interval'] : $offer['billing_interval'];

        return self::update($token, [
            'status' => $offer['status'] === 'trialing' ? 'trialing' : 'setup',
            'setup' => $clean,
            'platform_tier' => $tier !== '' ? $tier : $offer['platform_tier'],
            'billing_interval' => $interval,
            'apps' => isset($setup['apps']) ? self::sanitize_list($setup['apps']) : $offer['apps'],
            'premium' => isset($setup['premium']) ? self::sanitize_list($setup['premium']) : $offer['premium'],
            'addons' => isset($setup['addons']) ? self::sanitize_list($setup['addons']) : $offer['addons'],
            'setup_completed_at' => current_time('mysql'),
        ]);
    }

    public static function mark_trialing($token, $session_id, $subscription_id) {
        return self::update($token, [
            'status' => 'trialing',
            'stripe_session_id' => sanitize_text_field((string) $session_id),
            'stripe_subscription_id' => sanitize_text_field((string) $subscription_id),
            'stripe_status' => 'trialing',
            'trial_started_at' => current_time('mysql'),
        ]);
    }

    public static function find_by_session($session_id) {
        $session_id = sanitize_text_field((string) $session_id);
        if ($session_id === '') {
            return null;
        }
        foreach (self::all() as $row) {
            if (($row['stripe_session_id'] ?? '') === $session_id) {
                return $row;
            }
        }
        return null;
    }

    public static function find_by_email($email) {
        $email = sanitize_email((string) $email);
        if ($email === '') {
            return null;
        }
        $match = null;
        foreach (self::all() as $row) {
            if (strtolower((string) ($row['email'] ?? '')) === strtolower($email)) {
                $match = $row;
            }
        }
        return $match;
    }

    public static function set_cookie($token) {
        if (headers_sent()) {
            return;
        }
        $expire = time() + WEEK_IN_SECONDS;
        $path = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
        $domain = defined('COOKIE_DOMAIN') ? (string) COOKIE_DOMAIN : '';
        setcookie(self::COOKIE, sanitize_text_field((string) $token), $expire, $path, $domain, is_ssl(), true);
        $_COOKIE[self::COOKIE] = sanitize_text_field((string) $token);
    }

    public static function token_from_request() {
        $token = sanitize_text_field((string) ($_GET['token'] ?? ''));
        if ($token !== '') {
            return $token;
        }
        return sanitize_text_field((string) ($_COOKIE[self::COOKIE] ?? ''));
    }

    public static function accept_url($token) {
        return home_url('/founding/accept/' . rawurlencode($token) . '/');
    }

    public static function setup_url($token = '') {
        $url = home_url('/founding/setup/');
        if ($token !== '') {
            $url = add_query_arg('token', $token, $url);
        }
        return $url;
    }

    /** @param mixed $list @return array<int,string> */
    private static function sanitize_list($list) {
        if (!is_array($list)) {
            return [];
        }
        $out = [];
        foreach ($list as $item) {
            $key = sanitize_key((string) $item);
            if ($key !== '') {
                $out[] = $key;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * @param array<string,mixed> $setup
     * @return array<string,mixed>
     */
    private static function sanitize_setup(array $setup) {
        $text = static function ($key) use ($setup) {
            return sanitize_text_field((string) ($setup[$key] ?? ''));
        };
        $area = static function ($key) use ($setup) {
            return sanitize_textarea_field((string) ($setup[$key] ?? ''));
        };
        return [
            'business_name' => $text('business_name'),
            'abn' => $text('abn'),
            'street_address' => $text('street_address'),
            'city' => $text('city'),
            'state' => $text('state'),
            'postcode' => $text('postcode'),
            'phone' => $text('phone'),
            'business_email' => sanitize_email((string) ($setup['business_email'] ?? '')),
            'contact_name' => $text('contact_name'),
            'position' => $text('position'),
            'contact_phone' => $text('contact_phone'),
            'contact_email' => sanitize_email((string) ($setup['contact_email'] ?? '')),
            'about_business' => $area('about_business'),
            'goals' => $area('goals'),
            'team_members' => $area('team_members'),
            'website_url' => esc_url_raw((string) ($setup['website_url'] ?? '')),
            'brand_colours' => $text('brand_colours'),
            'systems' => $area('systems'),
            'implementation' => $area('implementation'),
        ];
    }
}
