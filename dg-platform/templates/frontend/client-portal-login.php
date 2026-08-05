<?php
/**
 * Deprecated: use templates/frontend/site-portal-login.php (themed per portal).
 * Client portal login — legacy DigitalGate-only template.
 *
 * @var array<string,mixed> $ctx
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

$ctx = isset($ctx) && is_array($ctx) ? $ctx : [];
$redirect_url = $ctx['redirect_url'] ?? home_url('/client-dashboard/');
$login_error = !empty($ctx['login_error']);
$access_denied = !empty($ctx['access_denied']);
$logged_in_no_access = !empty($ctx['logged_in_no_access']);
$login_url = $ctx['login_url'] ?? home_url('/client-portal/');
$onboarding_url = $ctx['onboarding_url'] ?? home_url('/onboarding/');
$is_builder = !empty($ctx['is_builder']);
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php esc_html_e('Sign In', 'dg-platform'); ?> | DigitalGate Client Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@100..800&family=Inter:opsz,wght@14..32,400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { margin: 0; }
        .dg-client-portal { font-family: 'Inter', sans-serif; background: #0A0E17; color: #FFFFFF; line-height: 1.5; margin: 0; padding: 0; min-height: 100vh; }
        .dg-client-portal * { box-sizing: border-box; }
        .dg-client-portal .portal-wrap { max-width: 440px; margin: 0 auto; padding: 3rem 1.5rem; }
        .dg-client-portal .portal-logo { text-align: center; margin-bottom: 2rem; }
        .dg-client-portal .portal-logo img.portal-logo-mark { height: 40px; width: auto; margin-bottom: 0.75rem; }
        .dg-client-portal .portal-logo img.portal-logo-wordmark { height: 28px; width: auto; max-width: 220px; }
        .dg-client-portal .portal-logo p { color: #94A3B8; margin: 0.75rem 0 0; font-size: 0.9rem; }
        .dg-client-portal .login-card { background: #1E293B; border: 1px solid #334155; border-radius: 24px; padding: 2rem; }
        .dg-client-portal .alert { padding: 0.85rem 1rem; border-radius: 12px; margin-bottom: 1.25rem; font-size: 0.85rem; }
        .dg-client-portal .alert-error { background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); color: #FCA5A5; }
        .dg-client-portal .alert-info { background: rgba(59, 130, 246, 0.12); border: 1px solid rgba(59, 130, 246, 0.35); color: #BFDBFE; }
        .dg-client-portal #dg-client-login label { display: block; color: #94A3B8; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.35rem; }
        .dg-client-portal #dg-client-login .login-username,
        .dg-client-portal #dg-client-login .login-password { margin-bottom: 1rem; }
        .dg-client-portal #dg-client-login input[type="text"],
        .dg-client-portal #dg-client-login input[type="password"] {
            width: 100%; background: #0F172A; border: 1px solid #334155; border-radius: 12px;
            color: #fff; padding: 0.75rem 1rem; font-size: 0.9rem;
        }
        .dg-client-portal #dg-client-login input:focus { outline: none; border-color: #3B82F6; }
        .dg-client-portal #dg-client-login .login-remember { margin: 0.5rem 0 1.25rem; color: #94A3B8; font-size: 0.82rem; }
        .dg-client-portal #dg-client-login .login-submit input {
            width: 100%; background: #3B82F6; border: none; border-radius: 12px; color: #fff;
            padding: 0.85rem; font-size: 0.9rem; font-weight: 700; cursor: pointer;
        }
        .dg-client-portal #dg-client-login .login-submit input:hover { background: #2563EB; }
        .dg-client-portal .login-links { margin-top: 1.25rem; text-align: center; font-size: 0.88rem; font-weight: 600; }
        .dg-client-portal .login-links a { color: #60A5FA; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .dg-client-portal .login-links a:hover { text-decoration: underline; }
        .dg-client-portal .login-secondary { margin-top: 0.75rem; text-align: center; font-size: 0.78rem; }
        .dg-client-portal .login-secondary a { color: #64748B; text-decoration: none; }
        .dg-client-portal .login-secondary a:hover { color: #94A3B8; text-decoration: underline; }
        .dg-client-portal .footer-note { text-align: center; margin-top: 1.5rem; color: #64748B; font-size: 0.78rem; }
        .dg-client-portal .builder-placeholder { color: #94A3B8; font-size: 0.85rem; text-align: center; padding: 1rem 0; }
    </style>
    <?php wp_head(); ?>
</head>
<body <?php body_class('dg-client-portal-page'); ?>>
<div class="dg-client-portal">
<div class="portal-wrap">

    <div class="portal-logo">
        <?php if (class_exists('DG_Brand')) : ?>
            <img src="<?php echo esc_url(DG_Brand::icon_ui_url()); ?>" alt="" class="portal-logo-mark" aria-hidden="true">
            <img src="<?php echo esc_url(DG_Brand::logo_light_url()); ?>" alt="DigitalGate" class="portal-logo-wordmark">
        <?php else : ?>
            <h1>DigitalGate</h1>
        <?php endif; ?>
        <p><?php esc_html_e('Sign in to your platform dashboard', 'dg-platform'); ?></p>
    </div>

    <div class="login-card">

        <?php if ($login_error) : ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php esc_html_e('Invalid username or password. Try again or reset your password.', 'dg-platform'); ?></div>
        <?php endif; ?>

        <?php if ($access_denied) : ?>
        <div class="alert alert-error"><i class="fas fa-lock"></i> <?php esc_html_e('This area is for DigitalGate clients. Use the email from your purchase confirmation.', 'dg-platform'); ?></div>
        <?php endif; ?>

        <?php if ($logged_in_no_access) : ?>
        <div class="alert alert-info"><?php esc_html_e('You are signed in, but this account does not have client portal access. Contact', 'dg-platform'); ?> <a href="mailto:support@digitalgate.com.au" style="color:#BFDBFE;">support@digitalgate.com.au</a>.</div>
        <?php elseif ($is_builder) : ?>
        <p class="builder-placeholder"><?php esc_html_e('Client login form preview. Save and view the page on the front end to test sign-in.', 'dg-platform'); ?></p>
        <?php elseif (function_exists('wp_login_form')) : ?>
        <?php
        wp_login_form([
            'form_id' => 'dg-client-login',
            'redirect' => $redirect_url,
            'label_username' => __('Email or username', 'dg-platform'),
            'label_password' => __('Password', 'dg-platform'),
            'label_remember' => __('Remember me', 'dg-platform'),
            'label_log_in' => __('Sign in', 'dg-platform'),
            'remember' => true,
            'value_username' => '',
        ]);
        ?>
        <?php endif; ?>

        <div class="login-links">
            <a href="<?php echo esc_url(function_exists('wp_lostpassword_url') ? wp_lostpassword_url($login_url) : home_url('/wp-login.php?action=lostpassword')); ?>"><i class="fas fa-key"></i> <?php esc_html_e('Forgot password?', 'dg-platform'); ?></a>
        </div>
        <p class="login-secondary"><a href="<?php echo esc_url($onboarding_url); ?>"><?php esc_html_e('Complete onboarding', 'dg-platform'); ?></a></p>
    </div>

    <p class="footer-note"><?php esc_html_e('Administrators and clients use the same WordPress login.', 'dg-platform'); ?><br><?php esc_html_e('Admins: use your WP username and password.', 'dg-platform'); ?></p>

</div>
</div>
<?php wp_footer(); ?>
</body>
</html>
