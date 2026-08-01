<?php
/**
 * Lead assignment helpers for Real Estate module.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_RE_Lead_Assignment {

    public static function users() {
        return get_users([
            'orderby' => 'display_name',
            'order' => 'ASC',
            'who' => 'authors',
            'capability' => 'edit_posts',
        ]);
    }

    public static function user_label($user_id) {
        if (!$user_id) {
            return 'Unassigned';
        }
        $user = get_userdata((int) $user_id);
        return $user ? $user->display_name : 'User #' . (int) $user_id;
    }
}
