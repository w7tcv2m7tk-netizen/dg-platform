<?php
/**
 * Lead assignment helpers for Marketing CRM.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Marketing_Lead_Assignment {

    public static function assignable_users() {
        $users = get_users([
            'capability' => 'dg_marketing_manage_clients',
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);
        if (!$users) {
            $users = get_users(['role' => 'administrator', 'orderby' => 'display_name']);
        }
        return $users;
    }

    public static function label($user_id) {
        $user = get_userdata((int) $user_id);
        return $user ? $user->display_name : 'Unassigned';
    }
}
