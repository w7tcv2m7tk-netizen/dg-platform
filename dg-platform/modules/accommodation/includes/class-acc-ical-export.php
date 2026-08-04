<?php
/**
 * iCal export feed for Booking.com / Airbnb calendar import.
 *
 * Serves a clean .ics URL: /ical/{slug}/{token}.ics
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Acc_Ical_Export {

    const TOKEN_META = 'dg_ical_export_token';

    public static function init() {
        add_action('init', [__CLASS__, 'register_rewrite'], 5);
        add_action('wp_loaded', [__CLASS__, 'maybe_serve_feed'], 0);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
        add_action('admin_init', [__CLASS__, 'maybe_flush_rewrite']);
        if (get_option('dg_acc_ical_rewrite_v2') !== '1') {
            update_option('dg_acc_needs_rewrite_flush', 1);
            update_option('dg_acc_ical_rewrite_v2', '1');
        }
        if (get_option('dg_acc_ical_rewrite_v3') !== '1') {
            update_option('dg_acc_needs_rewrite_flush', 1);
            update_option('dg_acc_ical_rewrite_v3', '1');
        }
    }

    public static function register_rewrite() {
        add_rewrite_rule(
            '^ical/([^/]+)/([a-zA-Z0-9]+)\.ics$',
            'index.php?dg_ical_slug=$matches[1]&dg_ical_token=$matches[2]',
            'top'
        );
        add_rewrite_tag('%dg_ical_slug%', '([^/]+)');
        add_rewrite_tag('%dg_ical_token%', '([a-zA-Z0-9]+)');
    }

    public static function maybe_flush_rewrite() {
        if (get_option('dg_acc_needs_rewrite_flush')) {
            flush_rewrite_rules(false);
            delete_option('dg_acc_needs_rewrite_flush');
        }
    }

    public static function token_for($post_id) {
        $post_id = (int) $post_id;
        $token = get_post_meta($post_id, self::TOKEN_META, true);
        if (!$token || strlen($token) < 16) {
            $token = wp_generate_password(24, false, false);
            update_post_meta($post_id, self::TOKEN_META, $token);
        }
        return $token;
    }

    public static function url_for($post_id) {
        $post_id = (int) $post_id;
        if (!$post_id || get_post_type($post_id) !== 'dg_accommodation') {
            return '';
        }
        $slug = get_post_field('post_name', $post_id);
        if (!$slug) {
            return '';
        }
        $token = self::token_for($post_id);
        return home_url('/ical/' . rawurlencode($slug) . '/' . $token . '.ics');
    }

    /** Fallback URL when pretty permalinks fail — still ends in .ics for OTAs. */
    public static function fallback_url_for($post_id) {
        $post_id = (int) $post_id;
        if (!$post_id || get_post_type($post_id) !== 'dg_accommodation') {
            return '';
        }
        $slug = get_post_field('post_name', $post_id);
        if (!$slug) {
            return '';
        }
        return add_query_arg([
            'dg_ical_slug' => $slug,
            'dg_ical_token' => self::token_for($post_id),
        ], home_url('/feed/dg-accommodation.ics'));
    }

    public static function register_rest_routes() {
        register_rest_route('dg-acc/v1', '/ical-export/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_serve'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('dg-acc/v1', '/ical/(?P<slug>[a-z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_serve_slug'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function rest_serve_slug(WP_REST_Request $request) {
        $slug = sanitize_title((string) $request->get_param('slug'));
        $key = sanitize_text_field((string) $request->get_param('key'));
        $post = self::find_accommodation($slug);
        if (!$post || !self::verify($post->ID, $key)) {
            return new WP_REST_Response('Unauthorized', 403);
        }
        return self::ics_response($post->ID);
    }

    public static function rest_serve(WP_REST_Request $request) {
        $post_id = (int) $request->get_param('id');
        $key = sanitize_text_field((string) $request->get_param('key'));
        if (!$key || !self::verify($post_id, $key)) {
            return new WP_REST_Response('Unauthorized', 403);
        }
        return self::ics_response($post_id);
    }

    public static function maybe_serve_feed() {
        $slug = '';
        $token = '';

        if (!empty($_GET['dg_ical_slug']) && !empty($_GET['dg_ical_token'])) {
            $slug = sanitize_title(wp_unslash($_GET['dg_ical_slug']));
            $token = sanitize_text_field(wp_unslash($_GET['dg_ical_token']));
        } elseif (!empty($_SERVER['REQUEST_URI'])) {
            $uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
            if (preg_match('#/ical/([a-z0-9-]+)/([a-zA-Z0-9]+)\.ics#i', $uri, $m)) {
                $slug = sanitize_title($m[1]);
                $token = sanitize_text_field($m[2]);
            } elseif (preg_match('#/feed/dg-accommodation\.ics#i', $uri) && !empty($_GET['dg_ical_slug']) && !empty($_GET['dg_ical_token'])) {
                $slug = sanitize_title(wp_unslash($_GET['dg_ical_slug']));
                $token = sanitize_text_field(wp_unslash($_GET['dg_ical_token']));
            }
        }

        if (!$slug || !$token) {
            if (isset($_GET['dg_ical_export'])) {
                $post_id = (int) $_GET['dg_ical_export'];
                $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
                if ($post_id && self::verify($post_id, $key)) {
                    self::output_ics($post_id);
                }
                status_header(403);
                exit('Unauthorized');
            }
            return;
        }

        $post = self::find_accommodation($slug);
        if (!$post || !self::verify($post->ID, $token)) {
            status_header(403);
            header('Content-Type: text/plain; charset=utf-8');
            exit('Unauthorized');
        }

        self::output_ics($post->ID);
    }

    /** @return WP_Post|null */
    private static function find_accommodation($slug) {
        if (!$slug) {
            return null;
        }
        $post = get_page_by_path($slug, OBJECT, 'dg_accommodation');
        if ($post instanceof WP_Post) {
            return $post;
        }
        $posts = get_posts([
            'post_type' => 'dg_accommodation',
            'name' => $slug,
            'posts_per_page' => 1,
            'post_status' => ['publish', 'draft', 'private'],
        ]);
        return !empty($posts[0]) ? $posts[0] : null;
    }

    private static function verify($post_id, $key) {
        if (!$post_id || get_post_type($post_id) !== 'dg_accommodation') {
            return false;
        }
        $stored = get_post_meta($post_id, self::TOKEN_META, true);
        return $stored && hash_equals((string) $stored, (string) $key);
    }

    private static function ics_response($post_id) {
        return new WP_REST_Response(self::build_ics($post_id), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="' . sanitize_file_name(get_post_field('post_name', $post_id)) . '.ics"',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }

    private static function output_ics($post_id) {
        if (function_exists('nocache_headers')) {
            nocache_headers();
        }
        status_header(200);
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="' . sanitize_file_name(get_post_field('post_name', $post_id)) . '.ics"');
        header('X-Robots-Tag: noindex');
        echo self::build_ics($post_id);
        exit;
    }

    private static function build_ics($post_id) {
        $title = get_the_title($post_id);
        $host = wp_parse_url(home_url(), PHP_URL_HOST) ?: 'localhost';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//DG Platform//Accommodation iCal//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::escape($title),
            'X-WR-TIMEZONE:Australia/Brisbane',
        ];

        $bookings = get_posts([
            'post_type' => 'dg_booking',
            'posts_per_page' => 500,
            'post_status' => 'publish',
            'fields' => 'ids',
            'meta_query' => [
                ['key' => 'dg_booking_accommodation_id', 'value' => $post_id, 'compare' => '='],
            ],
        ]);

        foreach ($bookings as $booking_id) {
            $status = get_post_meta($booking_id, 'dg_booking_status', true);
            if ($status === 'cancelled') {
                continue;
            }
            $checkin = get_post_meta($booking_id, 'dg_booking_checkin', true);
            $checkout = get_post_meta($booking_id, 'dg_booking_checkout', true);
            if (!$checkin || !$checkout) {
                continue;
            }
            $lines = array_merge($lines, self::event_lines(
                'booking-' . $booking_id . '@' . $host,
                $checkin,
                $checkout,
                'Booked - ' . $title
            ));
        }

        $blocked = get_post_meta($post_id, 'dg_blocked_dates', true);
        if (is_string($blocked) && trim($blocked) !== '') {
            $i = 0;
            foreach (preg_split('/\r\n|\r|\n/', $blocked) as $range) {
                $range = trim($range);
                if ($range === '') {
                    continue;
                }
                if (preg_match('/^(\d{4}-\d{2}-\d{2})\s*(?:to|-)\s*(\d{4}-\d{2}-\d{2})$/i', $range, $m)) {
                    $lines = array_merge($lines, self::event_lines(
                        'blocked-' . $post_id . '-' . ($i++) . '@' . $host,
                        $m[1],
                        $m[2],
                        'Blocked - ' . $title
                    ));
                }
            }
        }

        $lines[] = 'END:VCALENDAR';

        $body = implode("\r\n", $lines) . "\r\n";
        return self::fold_lines($body);
    }

    /** @return string[] */
    private static function event_lines($uid, $start, $end, $summary) {
        $now = gmdate('Ymd\THis\Z');
        return [
            'BEGIN:VEVENT',
            'UID:' . self::escape($uid),
            'DTSTAMP:' . $now,
            'CREATED:' . $now,
            'LAST-MODIFIED:' . $now,
            'DTSTART;VALUE=DATE:' . self::date_only($start),
            'DTEND;VALUE=DATE:' . self::date_only($end),
            'SUMMARY:' . self::escape($summary),
            'STATUS:CONFIRMED',
            'TRANSP:OPAQUE',
            'END:VEVENT',
        ];
    }

    private static function date_only($date) {
        return str_replace('-', '', substr($date, 0, 10));
    }

    private static function escape($value) {
        return str_replace(['\\', ';', ',', "\n", "\r"], ['\\\\', '\;', '\,', '\n', ''], (string) $value);
    }

    private static function fold_lines($ics) {
        $out = [];
        foreach (preg_split('/\r\n|\n|\r/', $ics) as $line) {
            if ($line === '') {
                continue;
            }
            while (strlen($line) > 73) {
                $out[] = substr($line, 0, 73);
                $line = ' ' . substr($line, 73);
            }
            $out[] = $line;
        }
        return implode("\r\n", $out) . "\r\n";
    }
}
