<?php
/**
 * Creator reporting helpers.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Creator_Reports {

    public static function summary() {
        $counts = wp_count_posts('post');
        $pages = wp_count_posts('page');
        $contacts = 0;
        if (class_exists('DG_Contacts')) {
            global $wpdb;
            $table = $wpdb->prefix . 'dg_contacts';
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
                $contacts = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
            }
        }

        return [
            'published_posts' => isset($counts->publish) ? (int) $counts->publish : 0,
            'draft_posts' => isset($counts->draft) ? (int) $counts->draft : 0,
            'pages' => isset($pages->publish) ? (int) $pages->publish : 0,
            'contacts' => $contacts,
        ];
    }
}
