<?php
if (!defined('ABSPATH')) exit;

class DG_Svc_Permissions {
    public static function menu_cap() {
        return current_user_can('dg_svc_manage_jobs') || current_user_can('manage_options') ? 'dg_svc_manage_jobs' : 'manage_options';
    }
    public static function can_view() {
        return current_user_can('dg_svc_view_jobs') || current_user_can('dg_svc_manage_jobs') || current_user_can('manage_options');
    }
    public static function can_manage() {
        return current_user_can('dg_svc_manage_jobs') || current_user_can('manage_options');
    }
}
