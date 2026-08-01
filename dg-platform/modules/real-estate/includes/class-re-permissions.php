<?php
/**
 * Real Estate module capability helpers.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_RE_Permissions {

    public static function cap_view_leads() {
        return 'dg_re_view_leads';
    }

    public static function cap_manage_leads() {
        return 'dg_re_manage_leads';
    }

    public static function cap_view_buyers() {
        return 'dg_re_view_buyers';
    }

    public static function cap_manage_buyers() {
        return 'dg_re_manage_buyers';
    }

    public static function cap_view_listings() {
        return 'dg_re_view_listings';
    }

    public static function cap_view_agents() {
        return 'dg_re_view_agents';
    }

    public static function cap_view_appraisals() {
        return 'dg_re_view_appraisals';
    }

    public static function cap_import() {
        return 'dg_re_import_properties';
    }

    public static function can_view_leads() {
        return DG_Permissions::current_user_can(self::cap_view_leads());
    }

    public static function can_manage_leads() {
        return DG_Permissions::current_user_can(self::cap_manage_leads());
    }

    public static function can_view_buyers() {
        return DG_Permissions::current_user_can(self::cap_view_buyers());
    }

    public static function can_manage_buyers() {
        return DG_Permissions::current_user_can(self::cap_manage_buyers());
    }
}
