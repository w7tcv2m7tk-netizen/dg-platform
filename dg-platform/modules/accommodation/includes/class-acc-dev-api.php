<?php
/**
 * Accommodation REST endpoints for Gen 2 / Cursor MCP / dev tooling.
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
            [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'list_bookings'],
                'permission_callback' => [__CLASS__, 'can_access'],
                'args' => [
                    'status' => ['type' => 'string'],
                    'limit' => ['type' => 'integer', 'default' => 25, 'minimum' => 1, 'maximum' => 100],
                    'offset' => ['type' => 'integer', 'default' => 0, 'minimum' => 0],
                    'from' => ['type' => 'string'],
                    'to' => ['type' => 'string'],
                ],
            ],
            [
                'methods' => 'PATCH',
                'callback' => [__CLASS__, 'update_bookings'],
                'permission_callback' => [__CLASS__, 'can_manage'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [__CLASS__, 'delete_bookings'],
                'permission_callback' => [__CLASS__, 'can_manage'],
            ],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/accommodation/properties', [
            [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'list_properties'],
                'permission_callback' => [__CLASS__, 'can_access'],
            ],
            [
                'methods' => 'PATCH',
                'callback' => [__CLASS__, 'update_properties'],
                'permission_callback' => [__CLASS__, 'can_manage'],
            ],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/accommodation/guests', [
            [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'list_guests'],
                'permission_callback' => [__CLASS__, 'can_access'],
                'args' => [
                    'limit' => ['type' => 'integer', 'default' => 25, 'minimum' => 1, 'maximum' => 100],
                ],
            ],
            [
                'methods' => 'PATCH',
                'callback' => [__CLASS__, 'update_guests'],
                'permission_callback' => [__CLASS__, 'can_manage'],
            ],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/accommodation/availability', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_availability'],
            'permission_callback' => [__CLASS__, 'can_access'],
            'args' => [
                'property_id' => ['type' => 'integer'],
                'from' => ['type' => 'string'],
                'to' => ['type' => 'string'],
            ],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/accommodation/housekeeping', [
            [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'list_housekeeping'],
                'permission_callback' => [__CLASS__, 'can_access'],
            ],
            [
                'methods' => 'PATCH',
                'callback' => [__CLASS__, 'update_housekeeping'],
                'permission_callback' => [__CLASS__, 'can_manage'],
            ],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/accommodation/ota-sync', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'sync_ota_calendars'],
            'permission_callback' => [__CLASS__, 'can_manage'],
            'args' => [
                'property_id' => ['type' => 'integer'],
                'source' => ['type' => 'string', 'default' => 'all'],
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

    public static function can_manage($request) {
        if (!class_exists('DG_Dev_API')) {
            return false;
        }
        if (DG_Dev_API::verify_request($request)) {
            return true;
        }
        return class_exists('DG_Acc_Permissions') && DG_Acc_Permissions::can_manage_bookings();
    }

    public static function get_summary($request) {
        $days = (int) $request->get_param('days');
        $summary = class_exists('DG_Acc_Reports') ? DG_Acc_Reports::summary() : [];
        $summary['site'] = home_url();
        $summary['site_profile'] = class_exists('DG_Site_Profile') ? DG_Site_Profile::label() : 'DG Platform';
        $summary['generated_at'] = current_time('mysql');
        $summary['period_days'] = $days;

        // Gen 2 dashboard aliases.
        $summary['revenue_mtd'] = isset($summary['revenue_month'])
            ? (float) $summary['revenue_month']
            : 0.0;
        $summary['occupancy_rate'] = self::occupancy_rate_for_period($days);

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
            'orderby' => 'meta_value',
            'meta_key' => 'dg_booking_checkin',
            'order' => 'DESC',
        ];
        $meta_query = [];
        $status = $request->get_param('status');
        if ($status) {
            $meta_query[] = ['key' => 'dg_booking_status', 'value' => sanitize_text_field($status)];
        }
        $from = sanitize_text_field((string) $request->get_param('from'));
        $to = sanitize_text_field((string) $request->get_param('to'));
        if ($from !== '') {
            $meta_query[] = ['key' => 'dg_booking_checkout', 'value' => $from, 'compare' => '>', 'type' => 'DATE'];
        }
        if ($to !== '') {
            $meta_query[] = ['key' => 'dg_booking_checkin', 'value' => $to, 'compare' => '<', 'type' => 'DATE'];
        }
        if ($meta_query) {
            $args['meta_query'] = count($meta_query) > 1
                ? array_merge(['relation' => 'AND'], $meta_query)
                : $meta_query;
        }
        return rest_ensure_response([
            'bookings' => self::format_bookings(get_posts($args)),
            'total' => (int) wp_count_posts('dg_booking')->publish,
        ]);
    }

    /**
     * Query args for accommodation units in the Dev API / Gen 2.
     * Includes coming soon + events listings (not only bookable), and draft/private for ops.
     *
     * @param array $extra Extra get_posts args.
     * @return array
     */
    private static function accommodation_query_args($extra = []) {
        $defaults = [
            'post_type' => 'dg_accommodation',
            'posts_per_page' => -1,
            'post_status' => ['publish', 'private', 'draft', 'pending'],
            'orderby' => 'title',
            'order' => 'ASC',
        ];
        return array_merge($defaults, $extra);
    }

    /** @return WP_Post[] sorted bookable → coming_soon → events_future → other */
    private static function get_accommodation_posts($extra = []) {
        $posts = get_posts(self::accommodation_query_args($extra));
        if (!class_exists('DG_Acc_Listing_Status')) {
            return $posts;
        }
        $rank = [
            DG_Acc_Listing_Status::BOOKABLE => 0,
            DG_Acc_Listing_Status::COMING_SOON => 1,
            DG_Acc_Listing_Status::EVENTS_FUTURE => 2,
        ];
        usort($posts, static function ($a, $b) use ($rank) {
            $ra = $rank[DG_Acc_Listing_Status::get($a->ID)] ?? 9;
            $rb = $rank[DG_Acc_Listing_Status::get($b->ID)] ?? 9;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }
            return strcasecmp($a->post_title, $b->post_title);
        });
        return $posts;
    }

    public static function list_properties($request) {
        $properties = [];
        $listing_filter = sanitize_key((string) $request->get_param('listing_status'));
        foreach (self::get_accommodation_posts() as $p) {
            $row = self::format_property($p);
            if ($listing_filter !== '' && ($row['listing_status'] ?? '') !== $listing_filter) {
                continue;
            }
            $properties[] = $row;
        }
        return rest_ensure_response([
            'properties' => $properties,
            'total' => count($properties),
            'includes' => ['coming_soon', 'events_future', 'draft', 'private'],
        ]);
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

    public static function get_availability($request) {
        $from = sanitize_text_field((string) $request->get_param('from'));
        $to = sanitize_text_field((string) $request->get_param('to'));
        if ($from === '') {
            $from = current_time('Y-m-d');
        }
        if ($to === '') {
            $to = date('Y-m-d', strtotime($from . ' +60 days'));
        }

        $property_id = (int) $request->get_param('property_id');
        $extra = [];
        if ($property_id > 0) {
            $extra['include'] = [$property_id];
            $extra['post_status'] = ['publish', 'private', 'draft', 'pending'];
        }
        $properties = self::get_accommodation_posts($extra);

        $units = [];
        foreach ($properties as $p) {
            $blocked = class_exists('DG_Acc_Frontend')
                ? DG_Acc_Frontend::get_blocked_dates($p->ID)
                : [];
            $blocked = array_values(array_filter($blocked, static function ($d) use ($from, $to) {
                return $d >= $from && $d <= $to;
            }));

            $bookings = self::format_bookings(get_posts([
                'post_type' => 'dg_booking',
                'posts_per_page' => -1,
                'meta_query' => [
                    'relation' => 'AND',
                    ['key' => 'dg_booking_accommodation_id', 'value' => $p->ID, 'compare' => '='],
                    ['key' => 'dg_booking_checkout', 'value' => $from, 'compare' => '>', 'type' => 'DATE'],
                    ['key' => 'dg_booking_checkin', 'value' => $to, 'compare' => '<', 'type' => 'DATE'],
                    ['key' => 'dg_booking_status', 'value' => ['cancelled'], 'compare' => 'NOT IN'],
                ],
            ]));

            $manual = self::expand_manual_blocked_dates($p->ID);
            $manual_in_range = array_values(array_filter($manual, static function ($d) use ($from, $to) {
                return $d >= $from && $d <= $to;
            }));

            $units[] = [
                'id' => $p->ID,
                'title' => $p->post_title,
                'listing_status' => class_exists('DG_Acc_Listing_Status') ? DG_Acc_Listing_Status::get($p->ID) : 'bookable',
                'weekday_rate' => (float) get_post_meta($p->ID, 'dg_weekday_rate', true),
                'weekend_rate' => (float) get_post_meta($p->ID, 'dg_weekend_rate', true),
                'cleaning_fee' => (float) get_post_meta($p->ID, 'dg_cleaning_fee', true),
                // Merged: bookings + manual (legacy). Prefer manual_blocked_dates for operator UI.
                'blocked_dates' => $blocked,
                'manual_blocked_dates' => $manual_in_range,
                'bookings' => $bookings,
            ];
        }

        return rest_ensure_response([
            'from' => $from,
            'to' => $to,
            'units' => $units,
            'total' => count($units),
        ]);
    }

    public static function list_housekeeping($request) {
        $items = [];
        foreach (self::get_accommodation_posts() as $p) {
            $items[] = [
                'id' => $p->ID,
                'title' => $p->post_title,
                'status' => get_post_meta($p->ID, 'dg_housekeeping_status', true) ?: 'unknown',
                'notes' => get_post_meta($p->ID, 'dg_housekeeping_notes', true) ?: '',
                'last_cleaned' => get_post_meta($p->ID, 'dg_housekeeping_last_cleaned', true) ?: null,
                'cleaning_form_url' => class_exists('DG_Acc_Cleaning')
                    ? DG_Acc_Cleaning::cleaning_url_for_property($p->ID)
                    : '',
                'checkin_url' => class_exists('DG_Acc_Checkin')
                    ? DG_Acc_Checkin::checkin_url_for_property($p->ID)
                    : '',
            ];
        }

        $summary = class_exists('DG_Acc_Housekeeping')
            ? DG_Acc_Housekeeping::status_summary()
            : [];

        return rest_ensure_response([
            'items' => $items,
            'summary' => $summary,
            'statuses' => class_exists('DG_Acc_Housekeeping') ? DG_Acc_Housekeeping::STATUSES : [],
            'total' => count($items),
        ]);
    }

    public static function update_housekeeping($request) {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            return new WP_Error('invalid_body', 'Expected JSON body.', ['status' => 400]);
        }

        $updates = [];
        if (isset($body['updates']) && is_array($body['updates'])) {
            $updates = $body['updates'];
        } elseif (isset($body['property_id'])) {
            $updates[] = $body;
        }

        if (!$updates) {
            return new WP_Error('missing_updates', 'Provide updates[{property_id,status,notes?}].', ['status' => 400]);
        }

        $allowed = class_exists('DG_Acc_Housekeeping')
            ? array_keys(DG_Acc_Housekeeping::STATUSES)
            : ['clean', 'dirty', 'in_progress', 'inspection'];

        $saved = [];
        foreach ($updates as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['property_id'] ?? $row['id'] ?? 0);
            if (!$id || get_post_type($id) !== 'dg_accommodation') {
                continue;
            }
            $status = sanitize_text_field((string) ($row['status'] ?? ''));
            if (!in_array($status, $allowed, true)) {
                continue;
            }
            $prev = get_post_meta($id, 'dg_housekeeping_status', true);
            update_post_meta($id, 'dg_housekeeping_status', $status);
            if (array_key_exists('notes', $row)) {
                update_post_meta($id, 'dg_housekeeping_notes', sanitize_textarea_field((string) $row['notes']));
            }
            if ($status === 'clean' && $prev !== 'clean') {
                update_post_meta($id, 'dg_housekeeping_last_cleaned', current_time('mysql'));
            }
            $saved[] = $id;
        }

        return rest_ensure_response([
            'ok' => true,
            'updated' => $saved,
            'count' => count($saved),
        ]);
    }

    /**
     * Occupancy as 0–100 percentage for the given lookback window.
     */
    private static function occupancy_rate_for_period($days) {
        $days = max(1, (int) $days);
        $properties = get_posts([
            'post_type' => 'dg_accommodation',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
        ]);
        $property_count = count($properties);
        if ($property_count === 0) {
            return 0.0;
        }

        $end = current_time('Y-m-d');
        $start = date('Y-m-d', strtotime($end . ' -' . ($days - 1) . ' days'));
        $capacity = $property_count * $days;
        $occupied = 0;

        foreach ($properties as $pid) {
            $bookings = get_posts([
                'post_type' => 'dg_booking',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => [
                    'relation' => 'AND',
                    ['key' => 'dg_booking_accommodation_id', 'value' => $pid, 'compare' => '='],
                    ['key' => 'dg_booking_checkout', 'value' => $start, 'compare' => '>', 'type' => 'DATE'],
                    ['key' => 'dg_booking_checkin', 'value' => $end, 'compare' => '<=', 'type' => 'DATE'],
                    ['key' => 'dg_booking_status', 'value' => ['cancelled'], 'compare' => 'NOT IN'],
                ],
            ]);
            foreach ($bookings as $bid) {
                $checkin = get_post_meta($bid, 'dg_booking_checkin', true);
                $checkout = get_post_meta($bid, 'dg_booking_checkout', true);
                if (!$checkin || !$checkout) {
                    continue;
                }
                $cur = max(strtotime($checkin), strtotime($start));
                $stop = min(strtotime($checkout), strtotime($end . ' +1 day'));
                while ($cur < $stop) {
                    $occupied++;
                    $cur = strtotime('+1 day', $cur);
                }
            }
        }

        return round(min(100, ($occupied / $capacity) * 100), 1);
    }

    /** Known amenity keys from the accommodation CPT editor. */
    private static function feature_labels() {
        return [
            'fire_pit' => 'Fire Pit',
            'mountain_views' => 'Mountain Views',
            'sauna' => 'Sauna',
            'outdoor_shower' => 'Outdoor Shower',
            'air_conditioning' => 'Air Conditioning',
            'pet_friendly' => 'Pet Friendly',
            'wifi' => 'WiFi',
            'kitchenette' => 'Kitchenette',
            'bbq' => 'BBQ',
            'parking' => 'Parking',
            'private_deck' => 'Private Deck',
            'spa' => 'Spa',
        ];
    }

    /** @return string[] public attachment URLs for gallery image IDs */
    private static function gallery_urls_for($gallery_raw) {
        $urls = [];
        if (!$gallery_raw) {
            return $urls;
        }
        $ids = array_filter(array_map('intval', preg_split('/[\s,]+/', (string) $gallery_raw)));
        foreach ($ids as $aid) {
            $url = wp_get_attachment_image_url($aid, 'large');
            if ($url) {
                $urls[] = $url;
            }
        }
        return $urls;
    }

    private static function format_property($p) {
        $airbnb_ical = (string) get_post_meta($p->ID, 'dg_ical_url', true);
        $bookingcom_ical = (string) get_post_meta($p->ID, 'dg_bookingcom_ical_url', true);
        $export_url = class_exists('DG_Acc_Ical_Export')
            ? DG_Acc_Ical_Export::url_for($p->ID)
            : '';
        $export_fallback = class_exists('DG_Acc_Ical_Export')
            ? DG_Acc_Ical_Export::fallback_url_for($p->ID)
            : '';

        $features_raw = get_post_meta($p->ID, 'dg_features', true);
        $features = [];
        if (is_array($features_raw)) {
            foreach (self::feature_labels() as $key => $_label) {
                $features[$key] = !empty($features_raw[$key]) ? 1 : 0;
            }
        } else {
            foreach (array_keys(self::feature_labels()) as $key) {
                $features[$key] = 0;
            }
        }

        $gallery = (string) get_post_meta($p->ID, 'dg_gallery', true);
        $type_terms = wp_get_object_terms($p->ID, 'dg_accommodation_type', ['fields' => 'all']);
        $type_name = '';
        $type_id = 0;
        if (!is_wp_error($type_terms) && !empty($type_terms[0])) {
            $type_name = $type_terms[0]->name;
            $type_id = (int) $type_terms[0]->term_id;
        }

        $float_or_null = static function ($meta) use ($p) {
            $v = get_post_meta($p->ID, $meta, true);
            if ($v === '' || $v === null) {
                return null;
            }
            return (float) $v;
        };
        $int_or_null = static function ($meta) use ($p) {
            $v = get_post_meta($p->ID, $meta, true);
            if ($v === '' || $v === null) {
                return null;
            }
            return (int) $v;
        };

        return [
            'id' => $p->ID,
            'title' => $p->post_title,
            'slug' => $p->post_name,
            'post_status' => $p->post_status,
            'description' => (string) get_post_meta($p->ID, 'dg_description', true),
            'accommodation_type' => $type_name,
            'accommodation_type_id' => $type_id,
            'address' => (string) get_post_meta($p->ID, 'dg_address', true),
            'latitude' => (string) get_post_meta($p->ID, 'dg_latitude', true),
            'longitude' => (string) get_post_meta($p->ID, 'dg_longitude', true),
            'weekday_rate' => $float_or_null('dg_weekday_rate'),
            'weekend_rate' => $float_or_null('dg_weekend_rate'),
            'weekday_peak_rate' => $float_or_null('dg_weekday_peak_rate'),
            'weekend_peak_rate' => $float_or_null('dg_weekend_peak_rate'),
            'peak_season_start' => (string) get_post_meta($p->ID, 'dg_peak_season_start', true),
            'peak_season_end' => (string) get_post_meta($p->ID, 'dg_peak_season_end', true),
            'last_minute_discount' => $int_or_null('dg_last_minute_discount'),
            'early_bird_discount' => $int_or_null('dg_early_bird_discount'),
            'cleaning_fee' => $float_or_null('dg_cleaning_fee'),
            'security_deposit' => $float_or_null('dg_security_deposit'),
            'extra_guest_fee' => $float_or_null('dg_extra_guest_fee'),
            'sleeps' => $int_or_null('dg_sleeps'),
            'bedrooms' => $int_or_null('dg_bedrooms'),
            'bathrooms' => $float_or_null('dg_bathrooms'),
            'max_guests' => $int_or_null('dg_max_guests'),
            'min_nights' => $int_or_null('dg_min_nights'),
            'size' => $float_or_null('dg_size'),
            'checkin_time' => (string) (get_post_meta($p->ID, 'dg_checkin_time', true) ?: ''),
            'checkout_time' => (string) (get_post_meta($p->ID, 'dg_checkout_time', true) ?: ''),
            'features' => $features,
            'feature_labels' => self::feature_labels(),
            'gallery' => $gallery,
            'gallery_urls' => self::gallery_urls_for($gallery),
            'featured_image_url' => get_the_post_thumbnail_url($p->ID, 'large') ?: '',
            'video_url' => (string) get_post_meta($p->ID, 'dg_video_url', true),
            'virtual_tour' => (string) get_post_meta($p->ID, 'dg_virtual_tour', true),
            'featured' => (bool) get_post_meta($p->ID, 'dg_featured', true),
            'landing_page_id' => (int) get_post_meta($p->ID, 'dg_landing_page_id', true) ?: null,
            'airbnb_id' => (string) get_post_meta($p->ID, 'dg_airbnb_id', true),
            'bookingcom_id' => (string) get_post_meta($p->ID, 'dg_bookingcom_id', true),
            'housekeeping_status' => get_post_meta($p->ID, 'dg_housekeeping_status', true) ?: 'unknown',
            'housekeeping_notes' => get_post_meta($p->ID, 'dg_housekeeping_notes', true) ?: '',
            'last_cleaned' => get_post_meta($p->ID, 'dg_housekeeping_last_cleaned', true) ?: null,
            'listing_status' => class_exists('DG_Acc_Listing_Status') ? DG_Acc_Listing_Status::get($p->ID) : 'bookable',
            'checkin_slug' => get_post_meta($p->ID, 'dg_checkin_slug', true),
            'checkin_url' => class_exists('DG_Acc_Checkin')
                ? DG_Acc_Checkin::checkin_url_for_property($p->ID)
                : '',
            'cleaning_form_url' => class_exists('DG_Acc_Cleaning')
                ? DG_Acc_Cleaning::cleaning_url_for_property($p->ID)
                : '',
            // OTA iCal — import URLs (editable) + DigitalGate export (readonly for OTAs).
            'airbnb_ical_url' => $airbnb_ical,
            'bookingcom_ical_url' => $bookingcom_ical,
            'ical_export_url' => $export_url,
            'ical_export_fallback_url' => ($export_fallback && $export_fallback !== $export_url) ? $export_fallback : '',
            'airbnb_last_sync' => get_post_meta($p->ID, 'dg_ical_last_sync', true) ?: null,
            'bookingcom_last_sync' => get_post_meta($p->ID, 'dg_bookingcom_ical_last_sync', true) ?: null,
            'airbnb_last_error' => get_post_meta($p->ID, 'dg_ical_last_error', true) ?: null,
            'bookingcom_last_error' => get_post_meta($p->ID, 'dg_bookingcom_ical_last_error', true) ?: null,
        ];
    }

    private static function format_bookings($posts) {
        $out = [];
        foreach ($posts as $b) {
            $guest = (string) get_post_meta($b->ID, 'dg_booking_name', true);
            if ($guest === '') {
                $guest = $b->post_title ?: '';
                // Strip " — Property" suffix from iCal titles when used as display name.
                if (strpos($guest, ' — ') !== false) {
                    $guest = trim(explode(' — ', $guest, 2)[0]);
                }
            }
            $source = (string) get_post_meta($b->ID, 'dg_booking_source', true);
            $status = (string) get_post_meta($b->ID, 'dg_booking_status', true) ?: 'pending';
            $out[] = [
                'id' => $b->ID,
                'ref' => get_post_meta($b->ID, 'dg_booking_ref', true),
                'guest_name' => $guest,
                'email' => get_post_meta($b->ID, 'dg_booking_email', true),
                'accommodation' => get_post_meta($b->ID, 'dg_booking_accommodation_name', true),
                'accommodation_id' => (int) get_post_meta($b->ID, 'dg_booking_accommodation_id', true),
                'checkin' => get_post_meta($b->ID, 'dg_booking_checkin', true),
                'checkout' => get_post_meta($b->ID, 'dg_booking_checkout', true),
                'status' => $status,
                'source' => $source !== '' ? $source : $status,
                'total' => (float) get_post_meta($b->ID, 'dg_booking_total', true),
            ];
        }
        return $out;
    }

    /**
     * Trigger Airbnb / Booking.com iCal import for one or all properties.
     */
    public static function sync_ota_calendars($request) {
        if (!class_exists('DG_Acc_Ical_Import')) {
            return new WP_Error('dg_ota_unavailable', 'OTA sync is not available on this site.', ['status' => 501]);
        }

        $source = sanitize_key((string) $request->get_param('source'));
        if ($source === '') {
            $source = 'all';
        }
        $property_id = (int) $request->get_param('property_id');

        $query = self::accommodation_query_args(['fields' => 'ids']);
        if ($property_id > 0) {
            $query['include'] = [$property_id];
        }

        $ids = get_posts($query);
        $results = [];
        $imported = 0;
        $updated = 0;
        $cancelled = 0;
        $errors = [];

        foreach ($ids as $id) {
            $airbnb = get_post_meta($id, 'dg_ical_url', true);
            $bookingcom = get_post_meta($id, 'dg_bookingcom_ical_url', true);
            if (!$airbnb && !$bookingcom) {
                continue;
            }
            $sync = DG_Acc_Ical_Import::sync_accommodation((int) $id, $source);
            $imported += (int) ($sync['imported'] ?? 0);
            $updated += (int) ($sync['updated'] ?? 0);
            $cancelled += (int) ($sync['cancelled'] ?? 0);
            if (!empty($sync['errors'])) {
                $errors = array_merge($errors, $sync['errors']);
            }
            $results[] = [
                'property_id' => (int) $id,
                'title' => get_the_title($id),
                'message' => $sync['message'] ?? '',
                'airbnb_last_sync' => get_post_meta($id, 'dg_ical_last_sync', true) ?: null,
                'bookingcom_last_sync' => get_post_meta($id, 'dg_bookingcom_ical_last_sync', true) ?: null,
                'airbnb_last_error' => get_post_meta($id, 'dg_ical_last_error', true) ?: null,
                'bookingcom_last_error' => get_post_meta($id, 'dg_bookingcom_ical_last_error', true) ?: null,
            ];
        }

        return rest_ensure_response([
            'ok' => empty($errors) || ($imported + $updated) > 0,
            'imported' => $imported,
            'updated' => $updated,
            'cancelled' => $cancelled,
            'properties' => $results,
            'errors' => $errors,
            'message' => empty($results)
                ? 'No properties have Airbnb or Booking.com calendar URLs configured.'
                : sprintf('Synced %d propert%s — %d new, %d updated, %d removed.', count($results), count($results) === 1 ? 'y' : 'ies', $imported, $updated, $cancelled),
        ]);
    }

    /** @return array<int, array> */
    private static function extract_updates($request) {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            return [];
        }
        if (isset($body['updates']) && is_array($body['updates'])) {
            return $body['updates'];
        }
        if (isset($body['id']) || isset($body['property_id'])) {
            return [$body];
        }
        return [];
    }

    public static function update_properties($request) {
        $updates = self::extract_updates($request);
        if (!$updates) {
            return new WP_Error('missing_updates', 'Provide updates[{id, …unit meta fields…, block_dates?, unblock_dates?, manual_blocked_dates?}].', ['status' => 400]);
        }

        $listing_labels = class_exists('DG_Acc_Listing_Status')
            ? DG_Acc_Listing_Status::labels()
            : ['bookable' => 'Open', 'coming_soon' => 'Coming soon', 'events_future' => 'Events'];

        $float_fields = [
            'weekday_rate' => 'dg_weekday_rate',
            'weekend_rate' => 'dg_weekend_rate',
            'weekday_peak_rate' => 'dg_weekday_peak_rate',
            'weekend_peak_rate' => 'dg_weekend_peak_rate',
            'cleaning_fee' => 'dg_cleaning_fee',
            'security_deposit' => 'dg_security_deposit',
            'extra_guest_fee' => 'dg_extra_guest_fee',
            'bathrooms' => 'dg_bathrooms',
            'size' => 'dg_size',
        ];
        $int_fields = [
            'sleeps' => 'dg_sleeps',
            'bedrooms' => 'dg_bedrooms',
            'max_guests' => 'dg_max_guests',
            'min_nights' => 'dg_min_nights',
            'last_minute_discount' => 'dg_last_minute_discount',
            'early_bird_discount' => 'dg_early_bird_discount',
            'landing_page_id' => 'dg_landing_page_id',
        ];
        $text_fields = [
            'address' => 'dg_address',
            'latitude' => 'dg_latitude',
            'longitude' => 'dg_longitude',
            'peak_season_start' => 'dg_peak_season_start',
            'peak_season_end' => 'dg_peak_season_end',
            'checkin_time' => 'dg_checkin_time',
            'checkout_time' => 'dg_checkout_time',
            'gallery' => 'dg_gallery',
            'airbnb_id' => 'dg_airbnb_id',
            'bookingcom_id' => 'dg_bookingcom_id',
            'housekeeping_notes' => 'dg_housekeeping_notes',
        ];
        $url_fields = [
            'video_url' => 'dg_video_url',
            'virtual_tour' => 'dg_virtual_tour',
        ];

        $saved = [];
        foreach ($updates as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? $row['property_id'] ?? 0);
            if (!$id || get_post_type($id) !== 'dg_accommodation') {
                continue;
            }

            $post_update = ['ID' => $id];
            if (isset($row['title']) && is_string($row['title']) && trim($row['title']) !== '') {
                $post_update['post_title'] = sanitize_text_field($row['title']);
            }
            if (isset($row['post_status']) && is_string($row['post_status'])) {
                $status = sanitize_key($row['post_status']);
                if (in_array($status, ['publish', 'draft', 'private', 'pending'], true)) {
                    $post_update['post_status'] = $status;
                }
            }
            if (count($post_update) > 1) {
                wp_update_post($post_update);
            }

            if (array_key_exists('description', $row) && is_string($row['description'])) {
                update_post_meta($id, 'dg_description', wp_kses_post($row['description']));
            }

            if (array_key_exists('accommodation_type_id', $row)) {
                $term_id = (int) $row['accommodation_type_id'];
                if ($term_id > 0) {
                    wp_set_object_terms($id, $term_id, 'dg_accommodation_type');
                } elseif ($row['accommodation_type_id'] === null || $row['accommodation_type_id'] === '' || $term_id === 0) {
                    wp_set_object_terms($id, [], 'dg_accommodation_type');
                }
            }

            foreach ($float_fields as $field => $meta) {
                if (!array_key_exists($field, $row)) {
                    continue;
                }
                if ($row[$field] === null || $row[$field] === '') {
                    delete_post_meta($id, $meta);
                } else {
                    update_post_meta($id, $meta, (float) $row[$field]);
                }
            }

            foreach ($int_fields as $field => $meta) {
                if (!array_key_exists($field, $row)) {
                    continue;
                }
                if ($row[$field] === null || $row[$field] === '') {
                    delete_post_meta($id, $meta);
                } else {
                    update_post_meta($id, $meta, (int) $row[$field]);
                }
            }

            foreach ($text_fields as $field => $meta) {
                if (!array_key_exists($field, $row)) {
                    continue;
                }
                $val = is_string($row[$field]) || is_numeric($row[$field])
                    ? sanitize_text_field((string) $row[$field])
                    : '';
                if ($val === '') {
                    delete_post_meta($id, $meta);
                } else {
                    update_post_meta($id, $meta, $val);
                }
            }

            foreach ($url_fields as $field => $meta) {
                if (!array_key_exists($field, $row)) {
                    continue;
                }
                $url = is_string($row[$field]) ? esc_url_raw(trim($row[$field])) : '';
                if ($url === '') {
                    delete_post_meta($id, $meta);
                } else {
                    update_post_meta($id, $meta, $url);
                }
            }

            if (array_key_exists('featured', $row)) {
                update_post_meta($id, 'dg_featured', !empty($row['featured']) ? 1 : 0);
            }

            if (array_key_exists('features', $row) && is_array($row['features'])) {
                $normalized = [];
                foreach (array_keys(self::feature_labels()) as $key) {
                    $normalized[$key] = !empty($row['features'][$key]) ? 1 : 0;
                }
                update_post_meta($id, 'dg_features', $normalized);
            }

            if (!empty($row['listing_status']) && isset($listing_labels[$row['listing_status']])) {
                update_post_meta($id, class_exists('DG_Acc_Listing_Status') ? DG_Acc_Listing_Status::META : 'dg_listing_status', sanitize_key($row['listing_status']));
            }

            if (!empty($row['housekeeping_status']) && class_exists('DG_Acc_Housekeeping')) {
                $hk = sanitize_key((string) $row['housekeeping_status']);
                if (isset(DG_Acc_Housekeeping::STATUSES[$hk])) {
                    update_post_meta($id, 'dg_housekeeping_status', $hk);
                }
            }

            // OTA iCal import URLs (empty string clears). Export URL is derived — not writable.
            if (array_key_exists('airbnb_ical_url', $row)) {
                $url = is_string($row['airbnb_ical_url']) ? esc_url_raw(trim($row['airbnb_ical_url'])) : '';
                if ($url === '') {
                    delete_post_meta($id, 'dg_ical_url');
                } else {
                    update_post_meta($id, 'dg_ical_url', $url);
                }
            }
            if (array_key_exists('bookingcom_ical_url', $row)) {
                $url = is_string($row['bookingcom_ical_url']) ? esc_url_raw(trim($row['bookingcom_ical_url'])) : '';
                if ($url === '') {
                    delete_post_meta($id, 'dg_bookingcom_ical_url');
                } else {
                    update_post_meta($id, 'dg_bookingcom_ical_url', $url);
                }
            }

            // Ensure export token exists after OTA URL edits.
            if (class_exists('DG_Acc_Ical_Export') && (array_key_exists('airbnb_ical_url', $row) || array_key_exists('bookingcom_ical_url', $row))) {
                DG_Acc_Ical_Export::token_for($id);
            }

            // Manual operator blocks only — never touches dg_ota_blocked_dates.
            $touched_blocks = false;
            if (array_key_exists('manual_blocked_dates', $row) && is_array($row['manual_blocked_dates'])) {
                self::save_manual_blocked_dates($id, self::sanitize_date_list($row['manual_blocked_dates']));
                $touched_blocks = true;
            } else {
                $block = isset($row['block_dates']) && is_array($row['block_dates'])
                    ? self::sanitize_date_list($row['block_dates'])
                    : [];
                $unblock = isset($row['unblock_dates']) && is_array($row['unblock_dates'])
                    ? self::sanitize_date_list($row['unblock_dates'])
                    : [];
                if ($block || $unblock) {
                    $current = self::expand_manual_blocked_dates($id);
                    $set = array_fill_keys($current, true);
                    foreach ($block as $d) {
                        $set[$d] = true;
                    }
                    foreach ($unblock as $d) {
                        unset($set[$d]);
                    }
                    self::save_manual_blocked_dates($id, array_keys($set));
                    $touched_blocks = true;
                }
            }

            $prop = self::format_property(get_post($id));
            if ($touched_blocks) {
                $manual = self::expand_manual_blocked_dates($id);
                $prop['manual_blocked_dates'] = $manual;
                $prop['blocked_dates'] = class_exists('DG_Acc_Frontend')
                    ? DG_Acc_Frontend::get_blocked_dates($id)
                    : $manual;
            }
            $saved[] = $prop;
        }

        return rest_ensure_response([
            'ok' => true,
            'updated' => $saved,
            'count' => count($saved),
        ]);
    }

    public static function update_bookings($request) {
        $updates = self::extract_updates($request);
        if (!$updates) {
            return new WP_Error('missing_updates', 'Provide updates[{id,guest_name?,email?,phone?,checkin?,checkout?,status?,total?,accommodation_id?}].', ['status' => 400]);
        }

        $allowed_status = ['confirmed', 'pending', 'airbnb', 'bookingcom', 'cancelled', 'completed'];
        $saved = [];

        foreach ($updates as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if (!$id || get_post_type($id) !== 'dg_booking') {
                continue;
            }

            if (isset($row['guest_name']) && is_string($row['guest_name'])) {
                $name = sanitize_text_field($row['guest_name']);
                update_post_meta($id, 'dg_booking_name', $name);
                if ($name !== '') {
                    $property = get_post_meta($id, 'dg_booking_accommodation_name', true);
                    wp_update_post([
                        'ID' => $id,
                        'post_title' => $property ? ($name . ' — ' . $property) : $name,
                    ]);
                }
            }
            if (array_key_exists('email', $row)) {
                update_post_meta($id, 'dg_booking_email', sanitize_email((string) $row['email']));
            }
            if (array_key_exists('phone', $row)) {
                update_post_meta($id, 'dg_booking_phone', sanitize_text_field((string) $row['phone']));
            }
            if (!empty($row['checkin'])) {
                update_post_meta($id, 'dg_booking_checkin', sanitize_text_field((string) $row['checkin']));
            }
            if (!empty($row['checkout'])) {
                update_post_meta($id, 'dg_booking_checkout', sanitize_text_field((string) $row['checkout']));
            }
            if (!empty($row['status']) && in_array($row['status'], $allowed_status, true)) {
                update_post_meta($id, 'dg_booking_status', sanitize_key($row['status']));
            }
            if (array_key_exists('total', $row) && $row['total'] !== null && $row['total'] !== '') {
                update_post_meta($id, 'dg_booking_total', (float) $row['total']);
            }
            if (!empty($row['accommodation_id'])) {
                $acc_id = (int) $row['accommodation_id'];
                if ($acc_id && get_post_type($acc_id) === 'dg_accommodation') {
                    update_post_meta($id, 'dg_booking_accommodation_id', $acc_id);
                    update_post_meta($id, 'dg_booking_accommodation_name', get_the_title($acc_id));
                }
            }
            if (array_key_exists('ref', $row) && is_string($row['ref'])) {
                update_post_meta($id, 'dg_booking_ref', sanitize_text_field($row['ref']));
            }

            $saved[] = self::format_bookings([get_post($id)])[0] ?? ['id' => $id];
        }

        return rest_ensure_response([
            'ok' => true,
            'updated' => $saved,
            'count' => count($saved),
        ]);
    }

    /**
     * Soft-delete bookings: set status to cancelled (matches OTA iCal removal).
     * Does not hard-destroy posts — keeps OTA UID history for re-import.
     */
    public static function delete_bookings($request) {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        $ids = [];
        if (!empty($body['ids']) && is_array($body['ids'])) {
            $ids = array_map('intval', $body['ids']);
        } elseif (!empty($body['id'])) {
            $ids = [(int) $body['id']];
        } else {
            $updates = self::extract_updates($request);
            if ($updates) {
                foreach ($updates as $row) {
                    if (is_array($row) && !empty($row['id'])) {
                        $ids[] = (int) $row['id'];
                    }
                }
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if (!$ids) {
            return new WP_Error('missing_ids', 'Provide ids[] or id of bookings to cancel.', ['status' => 400]);
        }

        $cancelled = [];
        $acc_ids = [];

        foreach ($ids as $id) {
            if (!$id || get_post_type($id) !== 'dg_booking') {
                continue;
            }
            update_post_meta($id, 'dg_booking_status', 'cancelled');
            $acc_id = (int) get_post_meta($id, 'dg_booking_accommodation_id', true);
            if ($acc_id) {
                $acc_ids[$acc_id] = true;
            }
            $cancelled[] = self::format_bookings([get_post($id)])[0] ?? ['id' => $id, 'status' => 'cancelled'];
        }

        if (class_exists('DG_Acc_Ota')) {
            foreach (array_keys($acc_ids) as $acc_id) {
                DG_Acc_Ota::rebuild_blocked_dates((int) $acc_id);
            }
        }

        return rest_ensure_response([
            'ok' => true,
            'cancelled' => $cancelled,
            'count' => count($cancelled),
        ]);
    }

    public static function update_guests($request) {
        $updates = self::extract_updates($request);
        if (!$updates) {
            return new WP_Error('missing_updates', 'Provide updates[{id,name?,email?,phone?}].', ['status' => 400]);
        }

        $saved = [];
        foreach ($updates as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if (!$id || get_post_type($id) !== 'dg_guest') {
                continue;
            }

            if (isset($row['name']) && is_string($row['name']) && trim($row['name']) !== '') {
                wp_update_post([
                    'ID' => $id,
                    'post_title' => sanitize_text_field($row['name']),
                ]);
            }
            if (array_key_exists('email', $row)) {
                update_post_meta($id, 'dg_guest_email', sanitize_email((string) $row['email']));
            }
            if (array_key_exists('phone', $row)) {
                update_post_meta($id, 'dg_guest_phone', sanitize_text_field((string) $row['phone']));
            }

            $post = get_post($id);
            $saved[] = [
                'id' => $id,
                'name' => $post ? $post->post_title : '',
                'email' => get_post_meta($id, 'dg_guest_email', true),
                'phone' => get_post_meta($id, 'dg_guest_phone', true),
                'total_stays' => (int) get_post_meta($id, 'dg_guest_total_stays', true),
            ];
        }

        return rest_ensure_response([
            'ok' => true,
            'updated' => $saved,
            'count' => count($saved),
        ]);
    }

    /**
     * Expand dg_blocked_dates meta into individual YYYY-MM-DD days (inclusive ranges).
     *
     * @return string[]
     */
    private static function expand_manual_blocked_dates($property_id) {
        $manual = get_post_meta((int) $property_id, 'dg_blocked_dates', true);
        if (!is_string($manual) || trim($manual) === '') {
            return [];
        }

        $days = [];
        foreach (preg_split('/\r\n|\r|\n/', $manual) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(\d{4}-\d{2}-\d{2})\s+to\s+(\d{4}-\d{2}-\d{2})$/i', $line, $m)) {
                $current = strtotime($m[1]);
                $end = strtotime($m[2]);
                if ($current === false || $end === false) {
                    continue;
                }
                while ($current <= $end) {
                    $days[] = date('Y-m-d', $current);
                    $current = strtotime('+1 day', $current);
                }
            } elseif (preg_match('/^(\d{4}-\d{2}-\d{2})$/', $line, $m)) {
                $days[] = $m[1];
            }
        }

        $days = array_values(array_unique($days));
        sort($days);
        return $days;
    }

    /**
     * @param mixed $raw
     * @return string[]
     */
    private static function sanitize_date_list($raw) {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $d) {
            if (!is_string($d) && !is_numeric($d)) {
                continue;
            }
            $d = trim((string) $d);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                $out[] = $d;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * Persist expanded day list as compressed inclusive ranges in dg_blocked_dates.
     * Does not modify dg_ota_blocked_dates.
     *
     * @param string[] $days
     */
    private static function save_manual_blocked_dates($property_id, array $days) {
        $days = self::sanitize_date_list($days);
        sort($days);
        if (!$days) {
            delete_post_meta((int) $property_id, 'dg_blocked_dates');
            return;
        }

        $ranges = [];
        $range_start = $days[0];
        $prev = $days[0];
        for ($i = 1, $n = count($days); $i < $n; $i++) {
            $d = $days[$i];
            $expected = date('Y-m-d', strtotime($prev . ' +1 day'));
            if ($d === $expected) {
                $prev = $d;
                continue;
            }
            $ranges[] = $range_start . ' to ' . $prev;
            $range_start = $d;
            $prev = $d;
        }
        $ranges[] = $range_start . ' to ' . $prev;

        update_post_meta((int) $property_id, 'dg_blocked_dates', implode("\n", $ranges));
    }
}
