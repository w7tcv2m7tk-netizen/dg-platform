<?php
if (!defined('ABSPATH')) exit;

class DG_Dealer_Inventory {

    public static function register_post_type() {
        register_post_type('dg_vehicle', [
            'labels' => ['name' => 'Vehicles', 'singular_name' => 'Vehicle', 'add_new' => 'Add Vehicle'],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'thumbnail', 'editor'],
        ]);
    }

    public static function list($limit = 50) {
        return get_posts(['post_type' => 'dg_vehicle', 'posts_per_page' => $limit, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC']);
    }

    public static function count_available() {
        $all = get_posts(['post_type' => 'dg_vehicle', 'posts_per_page' => -1, 'post_status' => 'publish', 'fields' => 'ids']);
        $n = 0;
        foreach ($all as $id) {
            if (get_post_meta($id, 'dg_vehicle_status', true) !== 'sold') $n++;
        }
        return $n;
    }
}
