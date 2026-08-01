<?php
/**
 * Booking creation handler for Roe Realty /property-appraisal page.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

function dg_re_process_booking_creation($data) {
    global $wpdb;

    if (!empty($data['website'])) {
        return ['success' => true, 'message' => 'Booking received.'];
    }

    $name = sanitize_text_field($data['name'] ?? '');
    $email = sanitize_email($data['email'] ?? '');
    $phone = sanitize_text_field($data['phone'] ?? '');
    $service_id = (int) ($data['service'] ?? $data['service_id'] ?? 0);
    $service_name = sanitize_text_field($data['service_name'] ?? 'Property Appraisal');
    $booking_type = sanitize_text_field($data['booking_type'] ?? 'property_appraisal');
    $date = sanitize_text_field($data['date'] ?? '');
    $time = sanitize_text_field($data['time'] ?? '');
    $duration = (int) ($data['duration'] ?? 30);
    $notes = sanitize_textarea_field($data['notes'] ?? '');

    if ($name === '' || $email === '') {
        return ['success' => false, 'message' => 'Name and email are required.'];
    }
    if ($service_id <= 0 || $date === '' || $time === '') {
        return ['success' => false, 'message' => 'Please select a service, date, and time.'];
    }

    $booking_service = new Roe_CRM_Booking();
    $available = $booking_service->get_available_slots($date, $service_id);
    $time_normalized = date('H:i:s', strtotime($time));
    if (!in_array($time_normalized, $available, true)) {
        return ['success' => false, 'message' => 'That time slot is no longer available. Please choose another.'];
    }

    $contact_id = DG_RE_Contacts::resolve_contact_id([
        'full_name' => $name,
        'email' => $email,
        'phone' => $phone,
        'source' => 'booking',
        'notes' => $notes,
    ]);
    if (!$contact_id) {
        return ['success' => false, 'message' => 'Unable to save contact details.'];
    }

    $contact_id_for_booking = dg_re_resolve_legacy_contact_id($name, $email, $phone);
    if (!$contact_id_for_booking) {
        return ['success' => false, 'message' => 'Unable to save booking contact.'];
    }

    $booking_id = $booking_service->create_booking([
        'contact_id' => $contact_id_for_booking,
        'booking_type' => $booking_type,
        'service_name' => $service_name,
        'booking_date' => $date,
        'booking_time' => $time_normalized,
        'duration' => $duration,
        'notes' => $notes,
    ]);

    if (!$booking_id) {
        return ['success' => false, 'message' => 'Unable to create booking. Please try again.'];
    }

    $booking_row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}roe_crm_bookings WHERE id = %d",
        $booking_id
    ));

    if (class_exists('DG_Calendar') && $booking_row) {
        DG_Calendar::create_from_booking($booking_row, $contact_id);
    }

    if (class_exists('DG_Activities')) {
        DG_Activities::log([
            'entity_type' => 'booking',
            'entity_id' => (int) $booking_id,
            'contact_id' => $contact_id,
            'activity_type' => 'booking',
            'subject' => 'Appointment booked: ' . $service_name,
            'content' => $date . ' at ' . date('g:i A', strtotime($time_normalized)),
            'metadata' => ['booking_type' => $booking_type, 'service_id' => $service_id],
        ]);
    }

    dg_re_maybe_update_lead_on_booking($contact_id, $email, $booking_type);

    dg_re_send_booking_emails([
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'service_name' => $service_name,
        'date' => $date,
        'time' => $time_normalized,
        'notes' => $notes,
    ]);

    if (class_exists('DG_Automation')) {
        DG_Automation::trigger('booking_created', [
            'entity_type' => 'booking',
            'entity_id' => (int) $booking_id,
            'contact_id' => $contact_id,
            'email' => $email,
        ]);
    }

    do_action('dg_re_booking_created', $booking_id, $contact_id, $data);

    return [
        'success' => true,
        'message' => 'Your appointment has been booked! A confirmation email is on its way.',
        'booking_id' => (int) $booking_id,
    ];
}

function dg_re_resolve_legacy_contact_id($name, $email, $phone) {
    global $wpdb;
    $table = $wpdb->prefix . 'roe_crm_contacts';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        return 0;
    }
    $legacy = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE email = %s", $email));
    if ($legacy) {
        return (int) $legacy;
    }
    $parts = DG_RE_Contacts::split_name($name);
    $wpdb->insert($table, [
        'email' => $email,
        'first_name' => $parts['first_name'],
        'last_name' => $parts['last_name'],
        'phone' => $phone,
        'source' => 'booking',
        'status' => 'active',
        'last_activity' => current_time('mysql'),
    ]);
    return (int) $wpdb->insert_id;
}

function dg_re_send_booking_emails($booking) {
    $headers = DG_RE_Email_Templates::mail_headers();
    $when = date('l, j F Y', strtotime($booking['date'])) . ' at ' . date('g:i A', strtotime($booking['time']));
    $first_name = explode(' ', trim($booking['name']))[0];
    $vars = [
        'full_name' => $booking['name'],
        'first_name' => $first_name,
        'email' => $booking['email'],
        'phone' => $booking['phone'] ?: 'Not provided',
        'service_name' => $booking['service_name'],
        'appointment_when' => $when,
        'notes' => $booking['notes'] ?: '',
    ];

    $admin_to = apply_filters('dg_re_booking_admin_email', 'enquiries@roerealty.com.au');
    $admin_mail = DG_RE_Email_Templates::render('booking_admin', $vars);
    wp_mail($admin_to, $admin_mail['subject'], $admin_mail['body'], $headers);

    $confirm = DG_RE_Email_Templates::render('booking_confirmation', $vars);
    wp_mail($booking['email'], $confirm['subject'], $confirm['body'], $headers);
}

function dg_re_maybe_update_lead_on_booking($contact_id, $email, $booking_type) {
    if (!class_exists('DG_RE_Vendor_Leads')) {
        return;
    }
    global $wpdb;
    $leads_table = DG_RE_Vendor_Leads::leads_table();
    $lead = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $leads_table WHERE contact_id = %d ORDER BY created_at DESC LIMIT 1",
        $contact_id
    ));
    if ($lead) {
        DG_RE_Vendor_Leads::update_status((int) $lead->id, 'appointment_booked');
        if ($booking_type === 'property_appraisal') {
            DG_RE_Vendor_Leads::advance_stage((int) $lead->id, 'appraisal');
        }
    }
}
