<?php
if (!defined('ABSPATH')) exit;

class DG_Com_Listings {
    public static function register_post_type() {
        register_post_type('dg_commercial', [
            'labels' => ['name' => 'Commercial listings', 'singular_name' => 'Commercial listing', 'add_new' => 'Add listing'],
            'public' => false, 'show_ui' => true, 'show_in_menu' => false, 'supports' => ['title', 'editor', 'thumbnail'],
        ]);
    }

    public static function list($limit = 50) {
        return get_posts(['post_type' => 'dg_commercial', 'posts_per_page' => $limit, 'post_status' => 'publish']);
    }

    public static function count_active() {
        return count(get_posts([
            'post_type' => 'dg_commercial',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => 'dg_com_status',
                    'value' => 'leased',
                    'compare' => '!=',
                ],
            ],
            'fields' => 'ids',
        ]));
    }
}
