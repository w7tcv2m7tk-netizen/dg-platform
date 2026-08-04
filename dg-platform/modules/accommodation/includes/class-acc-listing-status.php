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
            $terms = [];
        }
        $post = get_post($post_id);
        $slug = $post ? $post->post_name : '';

        foreach ($terms as $term_slug) {
            if ($term_slug === 'the-shed') {
                return self::EVENTS_FUTURE;
            }
            if (strpos($term_slug, 'dome') !== false) {
                return self::COMING_SOON;
            }
            if (in_array($term_slug, ['tiny-home', 'private-studio', 'private-retreat'], true)) {
                return self::BOOKABLE;
            }
        }
        if (in_array($slug, ['tiny-home', 'private-studio', 'private-retreat'], true)) {
            return self::BOOKABLE;
        }
        if (strpos($slug, 'dome') !== false) {
            return self::COMING_SOON;
        }
        return self::BOOKABLE;
    }

    /** One-time fix: Tiny Home / Private Studio were incorrectly inferred as coming soon. */
    public static function maybe_upgrade_bookable_slugs() {
        if (get_option('dg_acc_listing_status_v2')) {
            return;
        }
        foreach (['tiny-home', 'private-studio', 'private-retreat'] as $slug) {
            $posts = get_posts([
                'post_type' => 'dg_accommodation',
                'name' => $slug,
                'posts_per_page' => -1,
                'post_status' => 'any',
            ]);
            foreach ($posts as $post) {
                $status = get_post_meta($post->ID, self::META, true);
                if ($status === self::COMING_SOON || $status === '') {
                    update_post_meta($post->ID, self::META, self::BOOKABLE);
                }
            }
        }
        update_option('dg_acc_listing_status_v2', 1);
    }

    public static function is_coming_soon($post_id) {
        return self::get($post_id) === self::COMING_SOON;
    }

    public static function is_bookable($post_id) {
        return self::get($post_id) === self::BOOKABLE;
    }

    public static function public_label($post_id) {
        $status = self::get($post_id);
        switch ($status) {
            case self::COMING_SOON:
                return 'COMING SOON';
            case self::EVENTS_FUTURE:
                return 'Events — opening soon';
            default:
                return '';
        }
    }

    public static function init() {
        add_action('save_post_dg_accommodation', [__CLASS__, 'maybe_set_default'], 12);
        add_action('init', [__CLASS__, 'maybe_upgrade_bookable_slugs'], 25);
        add_action('init', [__CLASS__, 'maybe_link_landing_pages'], 26);
    }

    public static function maybe_link_landing_pages() {
        if (class_exists('DG_Acc_Frontend')) {
            DG_Acc_Frontend::maybe_link_landing_pages();
        }
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