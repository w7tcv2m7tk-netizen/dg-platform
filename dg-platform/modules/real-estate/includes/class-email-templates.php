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
    const APPRAISAL_PATH = '/property-appraisal/';

    public static function init() {
        add_filter('wp_mail_from', [__CLASS__, 'mail_from_address']);
        add_filter('wp_mail_from_name', [__CLASS__, 'mail_from_name']);
    }

    public static function mail_from_address($email) {
        return 'ben@roerealty.com.au';
    }

    public static function mail_from_name($name) {
        return 'Ben Roe | Roe Realty';
    }

    public static function site_url($path = '') {
        return 'https://roerealty.com.au' . $path;
    }

    public static function appraisal_url() {
        return self::site_url(self::APPRAISAL_PATH);
    }

    public static function defaults() {
        $appraisal = self::appraisal_url();

        return [
            'property_report_admin' => [
                'label' => 'Property Report — Admin Notification',
                'subject' => 'Property Report Request - {full_name}',
                'body' => "New property report request\n\nName: {full_name}\nProperty: {property_address}\nEmail: {email}\nPhone: {phone}\nSubmitted: {submitted_at}",
            ],
            'property_report_lead' => [
                'label' => 'Property Report — Lead Auto-Reply',
                'subject' => 'Your Property Value & Buyer Demand Report',
                'body' => "Hi {first_name},\n\nThanks for requesting your Property Value & Buyer Demand Report for {property_address}.\n\nI'm reviewing recent sales, active listings, and buyer demand in your area so you get a clear picture of where your property sits in today's market.\n\nYou'll receive your full report shortly. If you'd like a more detailed breakdown in the meantime, you can book a free property appraisal — or simply reply to this email.\n\nBest regards,\nBen Roe | Roe Realty\n0420 227 227",
            ],
            'booking_admin' => [
                'label' => 'Booking — Admin Notification',
                'subject' => 'New Appraisal Booking - {full_name}',
                'body' => "New appointment booked\n\nName: {full_name}\nEmail: {email}\nPhone: {phone}\nService: {service_name}\nWhen: {appointment_when}\n\n{vendor_context}\n\nNotes:\n{notes}",
            ],
            'booking_confirmation' => [
                'label' => 'Booking — Lead Confirmation',
                'subject' => 'Your Roe Realty Appointment Confirmation',
                'body' => "Hi {first_name},\n\nYour appointment is confirmed.\n\nService: {service_name}\nWhen: {appointment_when}\n\nIf you need to reschedule, reply to this email or call 0420 227 227.\n\nWe look forward to seeing you.\n\nBest regards,\nBen Roe | Roe Realty\n0420 227 227\nhttps://roerealty.com.au",
            ],
            'buyer_enquiry_admin' => [
                'label' => 'Buyer Enquiry — Admin Notification',
                'subject' => 'New Buyer Enquiry - {property_address}',
                'body' => "New buyer enquiry\n\nName: {full_name}\nEmail: {email}\nPhone: {phone}\nProperty: {property_address}\nURL: {property_url}\n\nMessage:\n{notes}\n\nSubmitted: {submitted_at}",
            ],
            'buyer_enquiry_confirmation' => [
                'label' => 'Buyer Enquiry — Lead Confirmation',
                'subject' => 'Thanks for your enquiry - Roe Realty',
                'body' => "Hi {first_name},\n\nThanks for your enquiry about {property_address}.\n\nWe've received your message and will be in touch shortly.\n\nBest regards,\nBen Roe | Roe Realty\n0420 227 227",
            ],
            'vendor_lead_admin' => [
                'label' => 'Vendor Lead — Admin Notification',
                'subject' => 'New Vendor Lead - {property_address}',
                'body' => "New vendor lead\n\nName: {full_name}\nEmail: {email}\nPhone: {phone}\nProperty: {property_address}\nSource: {source}\n\nNotes:\n{notes}\n\nSubmitted: {submitted_at}",
            ],
            'vendor_lead_booked_admin' => [
                'label' => 'Vendor Lead Booked — Admin Notification (legacy)',
                'subject' => 'Vendor Lead Booked Appraisal - {full_name}',
                'body' => "A vendor lead booked an appointment.\n\nName: {full_name}\nEmail: {email}\nPhone: {phone}\nProperty: {property_address}\nService: {service_name}\nWhen: {appointment_when}\n\n{notes}",
            ],
            'weekly_pipeline_report' => [
                'label' => 'Weekly Pipeline Report — Admin Email',
                'subject' => 'Roe Realty Weekly Pipeline Report',
                'body' => "Weekly CRM summary ({report_period})\n\nTHIS MONTH\nProperty reports: {property_reports}\nBookings: {bookings_month}\nVendor → appraisal+ rate: {conversion_rate}%\n\nLAST 7 DAYS\nVendor leads: {vendor_leads_week}\nBuyer enquiries: {buyer_leads_week}\nBookings: {bookings_week}\n\nVENDOR PIPELINE\n{vendor_pipeline}\n\nBUYER PIPELINE\n{buyer_pipeline}\n\nLEADS BY SOURCE\n{lead_sources}\n\nFull dashboard:\n{admin_url}",
            ],
            'followup_2' => [
                'label' => 'Follow-up Email 2 (Day 1)',
                'subject' => 'What most homeowners miss in their property report',
                'body' => "Hi {first_name},\n\nI hope you've had a chance to review your Property Value & Buyer Demand Report for {property_address}.\n\nMost homeowners focus on the price range — but the real insight is in buyer demand and recent comparable sales.\n\nOn the Gold Coast right now, we're seeing strong demand in certain price brackets and wider variation depending on buyer competition.\n\nIf you'd like, I can walk you through exactly what your report means for your property.\n\nBest regards,\nBen Roe | Roe Realty",
            ],
            'followup_3' => [
                'label' => 'Follow-up Email 3 (Day 3)',
                'subject' => 'Your property position may have already changed',
                'body' => "Hi {first_name},\n\nThe market around {property_address} doesn't stand still for long.\n\nNew listings, recent sales, and shifting buyer activity can change your property's position within days — not months.\n\nI can quickly update you on what buyers are paying nearby and what that means for your timing.\n\nBest regards,\nBen Roe | Roe Realty",
            ],
            'followup_4' => [
                'label' => 'Follow-up Email 4 (Day 5)',
                'subject' => 'Timing matters more than most people realise',
                'body' => "Hi {first_name},\n\nOne of the biggest factors in selling outcomes isn't just price — it's timing.\n\nProperties often achieve stronger results when they align with peak buyer demand and low local competition.\n\nIf you're considering selling in the next 6–12 months, it's worth understanding your timing position now.\n\nBest regards,\nBen Roe | Roe Realty",
            ],
            'followup_5' => [
                'label' => 'Follow-up Email 5 (Day 9)',
                'subject' => 'Should I keep your file open?',
                'body' => "Hi {first_name},\n\nJust checking in on your Property Value & Buyer Demand Report for {property_address}.\n\nWould you like me to keep monitoring your property's market position, or close the file for now?\n\nEither way is fine — just let me know.\n\nBest regards,\nBen Roe | Roe Realty",
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
            return ['subject' => '', 'body' => '', 'body_html' => ''];
        }
        $replacements = [];
        foreach ($vars as $name => $value) {
            $replacements['{' . $name . '}'] = (string) $value;
        }
        $body = strtr($template['body'], $replacements);
        $body = str_replace(
            ['https://roerealty.com.au/card/', 'https://roerealty.com.au/card'],
            self::appraisal_url(),
            $body
        );

        return [
            'subject' => strtr($template['subject'], $replacements),
            'body' => $body,
            'body_html' => self::format_body_html($body, $key),
        ];
    }

    public static function send_mail($to, $template_key, $vars = [], $reply_to = null) {
        $mail = self::render($template_key, $vars);
        if ($mail['subject'] === '' && $mail['body'] === '') {
            return false;
        }
        $headers = self::mail_headers(true);
        if ($reply_to) {
            $headers[] = 'Reply-To: ' . $reply_to;
        }
        return wp_mail($to, $mail['subject'], $mail['body_html'], $headers);
    }

    public static function mail_headers($html = true) {
        $headers = [
            'From: Ben Roe | Roe Realty <ben@roerealty.com.au>',
            'Reply-To: Ben Roe <ben@roerealty.com.au>',
        ];
        $headers[] = $html
            ? 'Content-Type: text/html; charset=UTF-8'
            : 'Content-Type: text/plain; charset=UTF-8';
        return $headers;
    }

    public static function placeholders_help() {
        return '{first_name}, {full_name}, {email}, {phone}, {property_address}, {property_url}, {source}, {service_name}, {appointment_when}, {submitted_at}, {notes}, {vendor_context}, {vendor_lead_id}, {report_period}, {vendor_pipeline}, {buyer_pipeline}, {lead_sources}, {admin_url}';
    }

    private static function is_lead_facing($key) {
        return in_array($key, [
            'property_report_lead',
            'booking_confirmation',
            'buyer_enquiry_confirmation',
            'followup_2',
            'followup_3',
            'followup_4',
            'followup_5',
        ], true);
    }

    private static function show_appraisal_cta($key) {
        return in_array($key, [
            'property_report_lead',
            'followup_2',
            'followup_3',
            'followup_4',
            'followup_5',
        ], true);
    }

    public static function format_body_html($body, $template_key) {
        $body = trim($body);
        if ($body === '') {
            return '';
        }

        if (self::is_lead_facing($template_key)) {
            $content = self::format_lead_html($body, $template_key);
        } else {
            $content = self::format_admin_html($body);
        }

        return self::wrap_shell($content);
    }

    private static function format_lead_html($body, $template_key) {
        $lines = preg_split('/\r\n|\r|\n/', $body);
        $html = '';
        $buffer = [];

        $flush = function () use (&$buffer, &$html) {
            if (!$buffer) {
                return;
            }
            $text = implode('<br>', array_map('esc_html', $buffer));
            $html .= '<p style="margin:0 0 16px;line-height:1.65;color:#3D4F4D;font-size:16px;">' . $text . '</p>';
            $buffer = [];
        };

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                $flush();
                continue;
            }
            if (preg_match('#^https?://#', $line)) {
                $flush();
                $html .= '<p style="margin:0 0 16px;"><a href="' . esc_url($line) . '" style="color:#C9A46C;text-decoration:none;">' . esc_html($line) . '</a></p>';
                continue;
            }
            $buffer[] = $line;
        }
        $flush();

        if (self::show_appraisal_cta($template_key)) {
            $html .= self::cta_block(self::appraisal_url(), 'Book Your Free Appraisal');
        }

        return $html;
    }

    private static function format_admin_html($body) {
        $lines = preg_split('/\r\n|\r|\n/', $body);
        $rows = [];
        $intro = [];
        $in_details = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^([^:]+):\s*(.*)$/', $line, $m) && strlen($m[1]) < 40) {
                $in_details = true;
                $value = $m[2];
                if ($value === '' && $m[1] === 'Notes') {
                    $value = '—';
                }
                if (preg_match('#^https?://#', $value)) {
                    $value = '<a href="' . esc_url($value) . '" style="color:#C9A46C;">' . esc_html($value) . '</a>';
                } else {
                    $value = esc_html($value);
                }
                $rows[] = ['label' => esc_html($m[1]), 'value' => $value];
            } elseif (!$in_details) {
                $intro[] = esc_html($line);
            } else {
                $rows[] = ['label' => 'Notes', 'value' => nl2br(esc_html($line))];
            }
        }

        $html = '';
        if ($intro) {
            $html .= '<p style="margin:0 0 20px;font-size:18px;font-weight:600;color:#1C2B2A;">' . implode('<br>', $intro) . '</p>';
        }
        if ($rows) {
            $html .= '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">';
            foreach ($rows as $row) {
                $html .= '<tr>';
                $html .= '<td style="padding:10px 12px;border-bottom:1px solid #EDE6DE;width:130px;font-weight:600;color:#6B7A78;vertical-align:top;">' . $row['label'] . '</td>';
                $html .= '<td style="padding:10px 12px;border-bottom:1px solid #EDE6DE;color:#1C2B2A;vertical-align:top;">' . $row['value'] . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
        }

        if ($html === '') {
            $html = '<p style="margin:0;line-height:1.6;color:#3D4F4D;">' . nl2br(esc_html($body)) . '</p>';
        }

        return $html;
    }

    private static function cta_block($url, $label) {
        return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px 0 8px;"><tr><td>'
            . '<a href="' . esc_url($url) . '" style="display:inline-block;background:#C9A46C;color:#ffffff;text-decoration:none;font-weight:600;font-size:16px;padding:14px 28px;border-radius:8px;">'
            . esc_html($label) . '</a></td></tr></table>';
    }

    private static function wrap_shell($content) {
        $logo_text = 'Roe Realty';
        $footer = 'Ben Roe | Roe Realty &nbsp;·&nbsp; 0420 227 227 &nbsp;·&nbsp; <a href="' . esc_url(self::site_url()) . '" style="color:#C9A46C;text-decoration:none;">roerealty.com.au</a>';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:#F5F0EB;font-family:Georgia,\'Times New Roman\',serif;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#F5F0EB;padding:32px 16px;"><tr><td align="center">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px;background:#ffffff;border:1px solid #E0D6CC;border-radius:16px;overflow:hidden;">'
            . '<tr><td style="padding:28px 32px 16px;border-bottom:3px solid #C9A46C;">'
            . '<div style="font-size:22px;font-weight:700;color:#1C2B2A;letter-spacing:0.02em;">' . esc_html($logo_text) . '</div>'
            . '<div style="font-size:13px;color:#6B7A78;margin-top:4px;">Gold Coast Real Estate</div>'
            . '</td></tr>'
            . '<tr><td style="padding:32px;">' . $content . '</td></tr>'
            . '<tr><td style="padding:20px 32px 28px;background:#FAF8F5;border-top:1px solid #EDE6DE;font-size:13px;line-height:1.6;color:#6B7A78;text-align:center;">'
            . $footer . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    public static function maybe_upgrade() {
        $version = get_option('dg_re_email_templates_version', '0');
        if (version_compare($version, '10.0.14', '>=')) {
            return;
        }

        $saved = get_option(self::OPTION, []);
        $defaults = self::defaults();

        foreach ($defaults as $key => $default) {
            if (!isset($saved[$key])) {
                $saved[$key] = [
                    'subject' => $default['subject'],
                    'body' => $default['body'],
                ];
                continue;
            }
            $saved[$key]['body'] = str_replace(
                ['https://roerealty.com.au/card/', 'https://roerealty.com.au/card'],
                self::appraisal_url(),
                $saved[$key]['body']
            );
            if (in_array($key, ['property_report_lead', 'followup_2', 'followup_3', 'followup_4', 'followup_5', 'booking_admin'], true)) {
                $saved[$key] = [
                    'subject' => $default['subject'],
                    'body' => $default['body'],
                ];
            }
        }

        update_option(self::OPTION, $saved);
        update_option('dg_re_email_templates_version', '10.0.14');
    }

    public static function reset_to_defaults() {
        delete_option(self::OPTION);
        update_option('dg_re_email_templates_version', '10.0.14');
    }
}

DG_RE_Email_Templates::init();
