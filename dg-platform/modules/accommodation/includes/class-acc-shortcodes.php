<?php
/**
 * Registers accommodation front-end shortcodes (delegates to module instance).
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Acc_Shortcodes {

    private static $map = [
        'dg_accommodation_display' => 'accommodation_display_shortcode',
        'dg_accommodation_details' => 'accommodation_details_shortcode',
        'dg_accommodation_enquiry' => 'accommodation_enquiry_shortcode',
        'dg_booking_confirmation' => 'booking_confirmation_shortcode',
        'dg_calendar' => 'booking_calendar_shortcode',
        'dg_accommodation_calendar' => 'booking_calendar_shortcode',
        'dg_airbnb' => 'airbnb_shortcode',
        'dg_bookingcom' => 'bookingcom_shortcode',
        'dg_enquiry_form' => 'enquiry_form_shortcode',
        'dg_contact_form' => 'contact_form_shortcode',
        'dg_stripe_elements' => 'stripe_elements_shortcode',
    ];

    public static function init() {
        add_action('init', [__CLASS__, 'register'], 20);
    }

    public static function register() {
        if (!class_exists('DG_Module_Accommodation')) {
            return;
        }
        $module = DG_Module_Accommodation::get_instance();
        foreach (self::$map as $tag => $method) {
            if (method_exists($module, $method)) {
                add_shortcode($tag, [$module, $method]);
            }
        }
    }
}

DG_Acc_Shortcodes::init();
