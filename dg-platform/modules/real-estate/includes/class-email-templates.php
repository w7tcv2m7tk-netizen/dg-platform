<?php
/**
 * Editable email templates for Roe Realty.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_RE_Email_Templates {

    const OPTION = 'dg_re_email_templates';

    public static function defaults() {
        return [
            'property_report_admin' => [
                'label' => 'Property Report — Admin Notification',
                'subject' => 'Property Report Request - {full_name}',
                'body' => "--- NEW PROPERTY REPORT REQUEST ---\n\nName: {full_name}\nProperty Address: {property_address}\nEmail: {email}\nPhone: {phone}\n\nSubmitted: {submitted_at}\n\n---\nBen Roe | Roe Realty\nhttps://roerealty.com.au",
            ],
            'property_report_lead' => [
                'label' => 'Property Report — Lead Auto-Reply',
                'subject' => 'Your Property Value & Buyer Demand Report',
                'body' => "Hi {first_name},\n\nThanks for requesting your Property Value & Buyer Demand Report.\n\nProperty: {property_address}\n\nI'm currently reviewing recent sales, active listings, and current buyer demand in your area so you get a clear and accurate picture of where your property sits in today's market.\n\nYou'll receive your full report shortly.\n\nIf you'd like a more detailed breakdown or have any questions in the meantime, you can book your free property appraisal here:\nhttps://roerealty.com.au/card/\n\nOr simply reply to this email.\n\nBest regards,\nBen Roe | Roe Realty\n0420 227 227\nhttps://roerealty.com.au",
            ],
            'booking_admin' => [
                'label' => 'Booking — Admin Notification',
                'subject' => 'New Booking - {full_name}',
                'body' => "--- NEW BOOKING ---\n\nName: {full_name}\nEmail: {email}\nPhone: {phone}\nService: {service_name}\nWhen: {appointment_when}\n\nNotes:\n{notes}",
            ],
            'booking_confirmation' => [
                'label' => 'Booking — Lead Confirmation',
                'subject' => 'Your Roe Realty Appointment Confirmation',
                'body' => "Hi {first_name},\n\nYour appointment is confirmed.\n\nService: {service_name}\nWhen: {appointment_when}\n\nIf you need to reschedule, reply to this email or call 0420 227 227.\n\nBest regards,\nBen Roe | Roe Realty\nhttps://roerealty.com.au",
            ],
            'followup_2' => [
                'label' => 'Follow-up Email 2 (Day 1)',
                'subject' => 'What most homeowners miss in their property report',
                'body' => "Hi {first_name},\n\nI hope you've had a chance to review your Property Value & Buyer Demand Report for:\n{property_address}\n\nOne thing I often see is that most homeowners focus only on the price range—but the real insight is in buyer demand and recent comparable sales.\n\nRight now, in many parts of the Gold Coast, we're seeing strong demand in certain price brackets, faster movement for well-presented homes, and wider price variation depending on buyer competition.\n\nIf you'd like, I can walk you through exactly what your report means for your property specifically.\n\n👉 Book your free property appraisal: https://roerealty.com.au/card/\n\nBest regards,\nBen Roe | Roe Realty",
            ],
            'followup_3' => [
                'label' => 'Follow-up Email 3 (Day 3)',
                'subject' => 'Your property position may have already changed',
                'body' => "Hi {first_name},\n\nThe property market around {property_address} doesn't stand still for long.\n\nNew listings, recent sales, and shifting buyer activity can change your property's position within days—not months.\n\nIf you want, I can quickly update you on what buyers are currently paying nearby, whether demand is increasing or softening, and what that means for your timing.\n\n👉 Book your free property appraisal: https://roerealty.com.au/card/\n\nBest regards,\nBen Roe | Roe Realty",
            ],
            'followup_4' => [
                'label' => 'Follow-up Email 4 (Day 5)',
                'subject' => 'Timing matters more than most people realise',
                'body' => "Hi {first_name},\n\nOne of the biggest factors that impacts selling outcomes isn't just price—it's timing.\n\nIn the current market, properties often achieve stronger results when they align with peak buyer demand, low local competition, and recent comparable sales activity.\n\nIf you're even slightly considering selling in the next 6–12 months, it's worth understanding your timing position now.\n\n👉 Book your free property appraisal: https://roerealty.com.au/card/\n\nBest regards,\nBen Roe | Roe Realty",
            ],
            'followup_5' => [
                'label' => 'Follow-up Email 5 (Day 9)',
                'subject' => 'Should I keep your file open?',
                'body' => "Hi {first_name},\n\nJust checking in on your Property Value & Buyer Demand Report for:\n{property_address}\n\nWould you like me to keep monitoring your property's market position, or close the file for now?\n\nIf you're still considering your options, I'm happy to give you a quick, no-obligation update.\n\n👉 Book your free property appraisal: https://roerealty.com.au/card/\n\nEither way is fine—just let me know.\n\nBest regards,\nBen Roe | Roe Realty",
            ],
        ];
    }

    public static function all() {
        $saved = get_option(self::OPTION, []);
        $merged = [];
        foreach (self::defaults() as $key => $default) {
            $merged[$key] = wp_parse_args($saved[$key] ?? [], $default);
        }
        return $merged;
    }

    public static function get($key) {
        $all = self::all();
        return $all[$key] ?? null;
    }

    public static function save($templates) {
        $clean = [];
        foreach (self::defaults() as $key => $default) {
            if (!isset($templates[$key])) {
                continue;
            }
            $clean[$key] = [
                'subject' => sanitize_text_field($templates[$key]['subject'] ?? $default['subject']),
                'body' => sanitize_textarea_field($templates[$key]['body'] ?? $default['body']),
            ];
        }
        update_option(self::OPTION, $clean);
    }

    public static function render($key, $vars = []) {
        $template = self::get($key);
        if (!$template) {
            return ['subject' => '', 'body' => ''];
        }
        $replacements = [];
        foreach ($vars as $name => $value) {
            $replacements['{' . $name . '}'] = (string) $value;
        }
        return [
            'subject' => strtr($template['subject'], $replacements),
            'body' => strtr($template['body'], $replacements),
        ];
    }

    public static function mail_headers() {
        return [
            'Content-Type: text/plain; charset=UTF-8',
            'From: Ben Roe | Roe Realty <ben@roerealty.com.au>',
            'Reply-To: Ben Roe <ben@roerealty.com.au>',
        ];
    }

    public static function placeholders_help() {
        return '{first_name}, {full_name}, {email}, {phone}, {property_address}, {service_name}, {appointment_when}, {submitted_at}, {notes}';
    }

    public static function maybe_upgrade() {
        $version = get_option('dg_re_email_templates_version', '0');
        if (version_compare($version, '10.0.8', '>=')) {
            return;
        }
        $saved = get_option(self::OPTION, []);
        $upgrade_keys = ['property_report_lead', 'followup_2', 'followup_3', 'followup_4', 'followup_5'];
        foreach (self::defaults() as $key => $default) {
            if (in_array($key, $upgrade_keys, true)) {
                $saved[$key] = [
                    'subject' => $default['subject'],
                    'body' => $default['body'],
                ];
            }
        }
        update_option(self::OPTION, $saved);
        update_option('dg_re_email_templates_version', '10.0.8');
    }

    public static function reset_to_defaults() {
        delete_option(self::OPTION);
        update_option('dg_re_email_templates_version', '10.0.8');
    }
}
