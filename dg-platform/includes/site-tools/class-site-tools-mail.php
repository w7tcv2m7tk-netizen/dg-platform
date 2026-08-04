<?php
/**
 * SMTP mail transport — replaces Fluent SMTP for platform emails.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Site_Tools_Mail {

    public static function init() {
        add_action('phpmailer_init', [__CLASS__, 'configure_phpmailer']);
    }

    public static function configure_phpmailer($phpmailer) {
        if (!DG_Site_Tools_Settings::is_enabled() || !DG_Site_Tools_Settings::get('smtp_enabled')) {
            return;
        }

        $host = DG_Site_Tools_Settings::get('smtp_host');
        $user = DG_Site_Tools_Settings::get('smtp_user');
        if (!$host || !$user) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = $host;
        $phpmailer->Port = (int) DG_Site_Tools_Settings::get('smtp_port', 587);
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = $user;
        $phpmailer->Password = DG_Site_Tools_Settings::get('smtp_pass');

        $encryption = DG_Site_Tools_Settings::get('smtp_encryption', 'tls');
        if ($encryption === 'ssl') {
            $phpmailer->SMTPSecure = 'ssl';
        } elseif ($encryption === 'tls') {
            $phpmailer->SMTPSecure = 'tls';
        } else {
            $phpmailer->SMTPSecure = '';
            $phpmailer->SMTPAutoTLS = false;
        }

        $from_email = DG_Site_Tools_Settings::get('smtp_from_email');
        $from_name = DG_Site_Tools_Settings::get('smtp_from_name');
        if ($from_email) {
            $phpmailer->setFrom($from_email, $from_name ?: get_bloginfo('name'), false);
        }
    }

    /** @return array{success:bool,message:string} */
    public static function send_test() {
        $to = get_option('admin_email');
        $sent = wp_mail(
            $to,
            'DG Platform SMTP test',
            "This is a test email from Site Tools on " . home_url() . ".\n\nIf you received this, SMTP is working."
        );

        if ($sent) {
            return ['success' => true, 'message' => 'Test email sent to ' . $to];
        }

        global $phpmailer;
        $error = '';
        if (isset($phpmailer) && is_object($phpmailer) && !empty($phpmailer->ErrorInfo)) {
            $error = ' ' . $phpmailer->ErrorInfo;
        }

        return ['success' => false, 'message' => 'Test email failed.' . $error];
    }
}
