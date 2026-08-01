<?php
/**
 * Property listing status — bookable stays vs coming soon vs future events.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Acc_Listing_Status {

    const META = 'dg_listing_status';

    const BOOKABLE = 'bookable';
    const COMING_SOON = 'coming_soon';
    const EVENTS_FUTURE = 'events_future';

    public static function labels() {
        return [
            self::BOOKABLE => 'Open for bookings',
            self::COMING_SOON => 'Coming soon',
            self::EVENTS_FUTURE => 'Events & functions (future)',
        ];
    }

    public static function get($post_id) {
        $status = get_post_meta($post_id, self::META, true);
        if ($status && isset(self::labels()[$status])) {
            return $status;
        }
        return self::infer_from_type($post_id);
    }

    public static function infer_from_type($post_id) {
        $terms = wp_get_post_terms($post_id, 'dg_accommodation_type', ['fields' => 'slugs']);
        if (is_wp_error($terms) || empty($terms)) {
            return self::BOOKABLE;
        }
        foreach ($terms as $slug) {
            if ($slug === 'the-shed') {
                return self::EVENTS_FUTURE;
            }
            if (strpos($slug, 'dome') !== false) {
                return self::COMING_SOON;
            }
        }
        return self::BOOKABLE;
    }

    public static function is_bookable($post_id) {
        return self::get($post_id) === self::BOOKABLE;
    }

    public static function public_label($post_id) {
        $status = self::get($post_id);
        switch ($status) {
            case self::COMING_SOON:
                return 'Coming soon';
            case self::EVENTS_FUTURE:
                return 'Events — opening soon';
            default:
                return '';
        }
    }

    public static function init() {
        add_action('save_post_dg_accommodation', [__CLASS__, 'maybe_set_default'], 12);
    }

    public static function maybe_set_default($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (get_post_meta($post_id, self::META, true)) {
            return;
        }
        update_post_meta($post_id, self::META, self::infer_from_type($post_id));
    }
}

DG_Acc_Listing_Status::init();
