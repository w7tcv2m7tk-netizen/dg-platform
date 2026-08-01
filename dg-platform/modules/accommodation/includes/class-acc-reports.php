<?php
/**
 * Accommodation reporting helpers.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Acc_Reports {

    public static function properties_count() {
        return (int) wp_count_posts('dg_accommodation')->publish;
    }

    public static function guests_count() {
        return (int) wp_count_posts('dg_guest')->publish;
    }

    public static function bookings_query($args = []) {
        $defaults = [
            'post_type' => 'dg_booking',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
        ];
        return get_posts(wp_parse_args($args, $defaults));
    }

    public static function upcoming_bookings($days = 30) {
        $today = current_time('Y-m-d');
        $until = date('Y-m-d', strtotime('+' . (int) $days . ' days'));
        $ids = self::bookings_query([
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'dg_booking_checkin', 'value' => $today, 'compare' => '>=', 'type' => 'DATE'],
                ['key' => 'dg_booking_checkin', 'value' => $until, 'compare' => '<=', 'type' => 'DATE'],
                ['key' => 'dg_booking_status', 'value' => ['cancelled', 'completed'], 'compare' => 'NOT IN'],
            ],
        ]);
        return count($ids);
    }

    public static function revenue_this_month() {
        $start = date('Y-m-01');
        $end = date('Y-m-t');
        $total = 0.0;
        foreach (self::bookings_query([
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'dg_booking_checkin', 'value' => [$start, $end], 'compare' => 'BETWEEN', 'type' => 'DATE'],
                ['key' => 'dg_booking_status', 'value' => ['confirmed', 'airbnb', 'bookingcom', 'completed'], 'compare' => 'IN'],
            ],
        ]) as $id) {
            $total += (float) get_post_meta($id, 'dg_booking_total', true);
        }
        return round($total, 2);
    }

    public static function bookings_by_status() {
        $counts = [];
        foreach (self::bookings_query() as $id) {
            $status = get_post_meta($id, 'dg_booking_status', true) ?: 'pending';
            if (!isset($counts[$status])) {
                $counts[$status] = 0;
            }
            $counts[$status]++;
        }
        return $counts;
    }

    public static function summary() {
        return [
            'properties' => self::properties_count(),
            'guests' => self::guests_count(),
            'upcoming_30d' => self::upcoming_bookings(30),
            'revenue_month' => self::revenue_this_month(),
            'status_counts' => self::bookings_by_status(),
        ];
    }
}
