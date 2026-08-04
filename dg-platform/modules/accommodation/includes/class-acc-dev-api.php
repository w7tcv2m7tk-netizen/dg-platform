<?php
/**
 * Accommodation REST endpoints for Cursor MCP / dev tooling.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Acc_Dev_API {

    public static function register_routes() {
        register_rest_route(DG_REST_NAMESPACE, '/accommodation/summary', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_summary'],
            'permission_callback' => [__CLASS__, 'can_access'],
            'args' => [
                'days' => ['type' => 'integer', 'default' => 30, 'minimum' => 1, 'maximum' => 365],
            ],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/accommodation/bookings', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'list_bookings'],
            'permission_callback' => [__CLASS__, 'can_access'],
            'args' => [
                'status' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'default' => 25, 'minimum' => 1, 'maximum' => 100],
                'offset' => ['type' => 'integer', 'default' => 0, 'minimum' => 0],
            ],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/accommodation/properties', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'list_properties'],
            'permission_callback' => [__CLASS__, 'can_access'],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/accommodation/guests', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'list_guests'],
            'permission_callback' => [__CLASS__, 'can_access'],
            'args' => [
                'limit' => ['type' => 'integer', 'default' => 25, 'minimum' => 1, 'maximum' => 100],
            ],
        ]);
    }

    public static function can_access($request) {
        if (!class_exists('DG_Dev_API')) {
            return false;
        }
        if (DG_Dev_API::verify_request($request)) {
            return true;
        }
        return class_exists('DG_Acc_Permissions') && DG_Acc_Permissions::can_view_bookings();
    }

    public static function get_summary($request) {
        $days = (int) $request->get_param('days');
        $summary = class_exists('DG_Acc_Reports') ? DG_Acc_Reports::summary() : [];
        $summary['site'] = home_url();
        $summary['site_profile'] = class_exists('DG_Site_Profile') ? DG_Site_Profile::label() : 'DG Platform';
        $summary['generated_at'] = current_time('mysql');
        $summary['period_days'] = $days;

        if (class_exists('DG_Acc_Housekeeping')) {
            $summary['housekeeping'] = DG_Acc_Housekeeping::status_summary();
        }

        $today = current_time('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $checkins_tomorrow = get_posts([
            'post_type' => 'dg_booking',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                ['key' => 'dg_booking_checkin', 'value' => $tomorrow, 'compare' => '=', 'type' => 'DATE'],
                ['key' => 'dg_booking_status', 'value' => ['cancelled', 'completed'], 'compare' => 'NOT IN'],
            ],
        ]);
        $summary['checkins_tomorrow'] = count($checkins_tomorrow);
        $summary['checkins_tomorrow_ids'] = array_map('intval', $checkins_tomorrow);

        $recent = self::format_bookings(get_posts([
            'post_type' => 'dg_booking',
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
        ]));
        $summary['recent_bookings'] = $recent;

        return rest_ensure_response($summary);
    }

    public static function list_bookings($request) {
        $args = [
            'post_type' => 'dg_booking',
            'posts_per_page' => (int) $request->get_param('limit'),
            'offset' => (int) $request->get_param('offset'),
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        $status = $request->get_param('status');
        if ($status) {
            $args['meta_query'] = [['key' => 'dg_booking_status', 'value' => sanitize_text_field($status)]];
        }
        return rest_ensure_response([
            'bookings' => self::format_bookings(get_posts($args)),
            'total' => (int) wp_count_posts('dg_booking')->publish,
        ]);
    }

    public static function list_properties($request) {
        $properties = [];
        foreach (get_posts(['post_type' => 'dg_accommodation', 'posts_per_page' => -1, 'post_status' => 'publish']) as $p) {
            $properties[] = [
                'id' => $p->ID,
                'title' => $p->post_title,
                'slug' => $p->post_name,
                'weekday_rate' => (float) get_post_meta($p->ID, 'dg_weekday_rate', true),
                'cleaning_fee' => (float) get_post_meta($p->ID, 'dg_cleaning_fee', true),
                'housekeeping_status' => get_post_meta($p->ID, 'dg_housekeeping_status', true) ?: 'unknown',
                'listing_status' => class_exists('DG_Acc_Listing_Status') ? DG_Acc_Listing_Status::get($p->ID) : 'bookable',
                'checkin_slug' => get_post_meta($p->ID, 'dg_checkin_slug', true),
                'cleaning_form_url' => class_exists('DG_Acc_Cleaning') ? DG_Acc_Cleaning::cleaning_url_for_property($p->ID) : '',
            ];
        }
        return rest_ensure_response(['properties' => $properties, 'total' => count($properties)]);
    }

    public static function list_guests($request) {
        $guests = [];
        foreach (get_posts([
            'post_type' => 'dg_guest',
            'posts_per_page' => (int) $request->get_param('limit'),
            'post_status' => 'publish',
        ]) as $g) {
            $guests[] = [
                'id' => $g->ID,
                'name' => $g->post_title,
                'email' => get_post_meta($g->ID, 'dg_guest_email', true),
                'phone' => get_post_meta($g->ID, 'dg_guest_phone', true),
                'total_stays' => (int) get_post_meta($g->ID, 'dg_guest_total_stays', true),
            ];
        }
        return rest_ensure_response(['guests' => $guests, 'total' => (int) wp_count_posts('dg_guest')->publish]);
    }

    private static function format_bookings($posts) {
        $out = [];
        foreach ($posts as $b) {
            $out[] = [
                'id' => $b->ID,
                'ref' => get_post_meta($b->ID, 'dg_booking_ref', true),
                'guest_name' => get_post_meta($b->ID, 'dg_booking_name', true),
                'email' => get_post_meta($b->ID, 'dg_booking_email', true),
                'accommodation' => get_post_meta($b->ID, 'dg_booking_accommodation_name', true),
                'accommodation_id' => (int) get_post_meta($b->ID, 'dg_booking_accommodation_id', true),
                'checkin' => get_post_meta($b->ID, 'dg_booking_checkin', true),
                'checkout' => get_post_meta($b->ID, 'dg_booking_checkout', true),
                'status' => get_post_meta($b->ID, 'dg_booking_status', true) ?: 'pending',
                'total' => (float) get_post_meta($b->ID, 'dg_booking_total', true),
            ];
        }
        return $out;
    }
}
