<?php
/**
 * Admin email notifications for Roe Realty CRM events.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_RE_Admin_Notifications {

    public static function init() {
        add_action('dg_re_vendor_lead_created', [__CLASS__, 'on_vendor_lead_created'], 20, 4);
        add_action('dg_re_buyer_lead_created', [__CLASS__, 'on_buyer_lead_created'], 20, 4);
        add_action('dg_re_booking_created', [__CLASS__, 'on_booking_created'], 20, 3);
        add_action('dg_re_vendor_lead_booking_linked', [__CLASS__, 'on_vendor_lead_booking_linked'], 10, 4);
    }

    public static function admin_email() {
        return apply_filters('dg_re_admin_notification_email', 'enquiries@roerealty.com.au');
    }

    public static function send($subject, $body_html, $reply_to = null) {
        $headers = DG_RE_Email_Templates::mail_headers(true);
        if ($reply_to) {
            $headers[] = 'Reply-To: ' . $reply_to;
        }
        return wp_mail(self::admin_email(), $subject, $body_html, $headers);
    }

    public static function send_template($template_key, $vars, $reply_to = null) {
        $mail = DG_RE_Email_Templates::render($template_key, $vars);
        if ($mail['subject'] === '' && $mail['body'] === '') {
            return false;
        }
        return self::send($mail['subject'], $mail['body_html'], $reply_to);
    }

    public static function on_vendor_lead_created($lead_id, $contact_id, $pipeline_id, $data) {
        $source = sanitize_text_field($data['source'] ?? '');
        if ($source === 'property_report') {
            return;
        }

        $name_parts = DG_RE_Contacts::split_name($data['full_name'] ?? '');
        self::send_template('vendor_lead_admin', [
            'full_name' => trim(($name_parts['first_name'] ?? '') . ' ' . ($name_parts['last_name'] ?? '')),
            'first_name' => $name_parts['first_name'] ?? '',
            'email' => DG_RE_Contacts::display_email($data['email'] ?? '') ?: 'Not provided',
            'phone' => $data['phone'] ?? 'Not provided',
            'property_address' => $data['property_address'] ?? '',
            'source' => str_replace('_', ' ', $source),
            'submitted_at' => current_time('Y-m-d H:i:s'),
            'notes' => $data['notes'] ?? '',
        ], !empty($data['email']) ? $data['full_name'] . ' <' . sanitize_email($data['email']) . '>' : null);
    }

    public static function on_buyer_lead_created($buyer_id, $contact_id, $pipeline_id, $data) {
        $name_parts = DG_RE_Contacts::split_name($data['full_name'] ?? '');
        $full_name = trim(($name_parts['first_name'] ?? '') . ' ' . ($name_parts['last_name'] ?? ''));
        $email = DG_RE_Contacts::display_email($data['email'] ?? '');

        self::send_template('buyer_enquiry_admin', [
            'full_name' => $full_name,
            'first_name' => $name_parts['first_name'] ?? '',
            'email' => $email ?: 'Not provided',
            'phone' => $data['phone'] ?? 'Not provided',
            'property_address' => $data['property_address'] ?? '',
            'property_url' => $data['property_url'] ?? '',
            'notes' => $data['message'] ?? '',
            'submitted_at' => current_time('Y-m-d H:i:s'),
        ], $email ? $full_name . ' <' . $email . '>' : null);

        if ($email) {
            DG_RE_Email_Templates::send_mail($email, 'buyer_enquiry_confirmation', [
                'first_name' => $name_parts['first_name'] ?? $full_name,
                'full_name' => $full_name,
                'property_address' => $data['property_address'] ?? 'your enquiry',
                'email' => $email,
            ]);
        }
    }

    public static function on_booking_created($booking_id, $contact_id, $data) {
        // Admin booking email is sent in dg_re_send_booking_emails().
    }

    public static function on_vendor_lead_booking_linked($lead_id, $booking_id, $contact_id, $context) {
        // Merged into booking_admin email — no separate notification.
    }
}

DG_RE_Admin_Notifications::init();
