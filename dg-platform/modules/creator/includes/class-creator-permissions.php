<?php
/**
 * Creator module permissions.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Creator_Permissions {

    public static function menu_cap() {
        return current_user_can('dg_creator_manage_content') || current_user_can('manage_options')
            ? 'dg_creator_manage_content'
            : 'manage_options';
    }

    public static function can_view() {
        return current_user_can('dg_creator_view_content')
            || current_user_can('dg_creator_manage_content')
            || current_user_can('manage_options');
    }

    public static function can_manage() {
        return current_user_can('dg_creator_manage_content') || current_user_can('manage_options');
    }
}
