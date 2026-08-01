<?php
/**
 * Marketing module capability helpers.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Marketing_Permissions {

    public static function cap_view_clients() {
        return 'dg_marketing_view_clients';
    }

    public static function cap_manage_clients() {
        return 'dg_marketing_manage_clients';
    }

    public static function cap_view_audits() {
        return 'dg_marketing_view_audits';
    }

    public static function cap_manage_audits() {
        return 'dg_marketing_manage_audits';
    }

    public static function cap_view_ai() {
        return 'dg_marketing_view_ai';
    }

    public static function cap_manage_ai() {
        return 'dg_marketing_manage_ai';
    }

    public static function cap_view_voice() {
        return 'dg_marketing_view_voice';
    }

    public static function cap_manage_voice() {
        return 'dg_marketing_manage_voice';
    }

    public static function cap_import() {
        return 'dg_marketing_import_contacts';
    }

    public static function can_view_clients() {
        return DG_Permissions::current_user_can(self::cap_view_clients());
    }

    public static function can_manage_clients() {
        return DG_Permissions::current_user_can(self::cap_manage_clients());
    }

    public static function can_view_audits() {
        return DG_Permissions::current_user_can(self::cap_view_audits());
    }

    public static function can_manage_audits() {
        return DG_Permissions::current_user_can(self::cap_manage_audits());
    }

    public static function can_view_ai() {
        return DG_Permissions::current_user_can(self::cap_view_ai());
    }

    public static function can_manage_ai() {
        return DG_Permissions::current_user_can(self::cap_manage_ai());
    }

    public static function can_view_voice() {
        return DG_Permissions::current_user_can(self::cap_view_voice());
    }

    public static function can_import() {
        return DG_Permissions::current_user_can(self::cap_import());
    }

    public static function menu_cap_clients() {
        return self::can_view_clients() ? self::cap_view_clients() : 'manage_options';
    }

    public static function menu_cap_audits() {
        return self::can_view_audits() ? self::cap_view_audits() : 'manage_options';
    }

    public static function menu_cap_ai() {
        return self::can_view_ai() ? self::cap_view_ai() : 'manage_options';
    }

    public static function menu_cap_voice() {
        return self::can_view_voice() ? self::cap_view_voice() : 'manage_options';
    }

    public static function menu_cap_import() {
        return self::can_import() ? self::cap_import() : 'manage_options';
    }
}
