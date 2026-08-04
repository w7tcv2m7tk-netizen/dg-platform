<?php
/**
 * Site portal login — rendered by DG_Client_Portal / DG_Site_Portal (not theme/Oxygen body).
 *
 * @var array<string,mixed> $ctx
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

$ctx = isset($ctx) && is_array($ctx) ? $ctx : [];
$redirect_url = $ctx['redirect_url'] ?? home_url('/');
$login_error = !empty($ctx['login_error']);
$access_denied = !empty($ctx['access_denied']);
$logged_in_no_access = !empty($ctx['logged_in_no_access']);
$login_url = $ctx['login_url'] ?? home_url('/');
$onboarding_url = $ctx['onboarding_url'] ?? '';
$is_builder = !empty($ctx['is_builder']);
$site_label = $ctx['site_label'] ?? 'Portal';
$portal_label = $ctx['portal_label'] ?? 'Sign In';
$login_tagline = $ctx['login_tagline'] ?? '';
$access_denied_message = $ctx['access_denied_message'] ?? '';
$support_email = $ctx['support_email'] ?? '';
$show_onboarding_link = !empty($ctx['show_onboarding_link']);
$theme = $ctx['theme'] ?? 'digitalgate';
$login_icon = $ctx['login_icon'] ?? 'fa-layer-group';
$login_icon_color = $ctx['login_icon_color'] ?? '#3B82F6';

$themes = [
    'digitalgate' => [
        'bg' => '#0A0E17',
        'card' => '#1E293B',
        'border' => '#334155',
        'text' => '#FFFFFF',
        'muted' => '#94A3B8',
        'input_bg' => '#0F172A',
        'accent' => '#3B82F6',
        'accent_hover' => '#2563EB',
        'link' => '#60A5FA',
    ],
    'hideaway' => [
        'bg' => '#F7F4EE',
        'card' => '#FFFFFF',
        'border' => '#E0D6CC',
        'text' => '#1C2B2A',
        'muted' => '#6B7A78',
        'input_bg' => '#FCF9F5',
        'accent' => '#B9A48A',
        'accent_hover' => '#9A8568',
        'link' => '#1C2B2A',
    ],
    'roe' => [
        'bg' => '#F5F7F6',
        'card' => '#FFFFFF',
        'border' => '#D8E0DC',
        'text' => '#1C2B2A',
        'muted' => '#5C6B68',
        'input_bg' => '#FFFFFF',
        'accent' => '#1C2B2A',
        'accent_hover' => '#0F1918',
        'link' => '#1C2B2A',
    ],
    'aetherra' => [
        'bg' => '#0B0B12',
        'card' => '#151522',
        'border' => '#2A2A3D',
        'text' => '#F5F3FF',
        'muted' => '#A5A3C2',
        'input_bg' => '#0F0F18',
        'accent' => '#8B5CF6',
        'accent_hover' => '#7C3AED',
        'link' => '#C4B5FD',
    ],
];
$palette = $themes[$theme] ?? $themes['digitalgate'];
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html(sprintf('%s | %s', $portal_label, $site_label)); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@100..800&family=Inter:opsz,wght@14..32,400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { margin: 0; }
        .dg-site-portal { font-family: 'Inter', sans-serif; background: <?php echo esc_attr($palette['bg']); ?>; color: <?php echo esc_attr($palette['text']); ?>; line-height: 1.5; margin: 0; padding: 0; min-height: 100vh; }
        .dg-site-portal * { box-sizing: border-box; }
        .dg-site-portal .portal-wrap { max-width: 440px; margin: 0 auto; padding: 3rem 1.5rem; }
        .dg-site-portal .portal-logo { text-align: center; margin-bottom: 2rem; }
        .dg-site-portal .portal-logo h1 { font-family: 'Sora', sans-serif; font-size: 1.5rem; margin: 0 0 0.35rem; color: <?php echo esc_attr($palette['text']); ?>; }
        .dg-site-portal .portal-logo p { color: <?php echo esc_attr($palette['muted']); ?>; margin: 0; font-size: 0.9rem; }
        .dg-site-portal .login-card { background: <?php echo esc_attr($palette['card']); ?>; border: 1px solid <?php echo esc_attr($palette['border']); ?>; border-radius: 24px; padding: 2rem; }
        .dg-site-portal .alert { padding: 0.85rem 1rem; border-radius: 12px; margin-bottom: 1.25rem; font-size: 0.85rem; }
        .dg-site-portal .alert-error { background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); color: #FCA5A5; }
        .dg-site-portal .alert-info { background: rgba(59, 130, 246, 0.12); border: 1px solid rgba(59, 130, 246, 0.35); color: #BFDBFE; }
        .dg-site-portal #dg-portal-login label { display: block; color: <?php echo esc_attr($palette['muted']); ?>; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.35rem; }
        .dg-site-portal #dg-portal-login .login-username,
        .dg-site-portal #dg-portal-login .login-password { margin-bottom: 1rem; }
        .dg-site-portal #dg-portal-login input[type="text"],
        .dg-site-portal #dg-portal-login input[type="password"] {
            width: 100%; background: <?php echo esc_attr($palette['input_bg']); ?>; border: 1px solid <?php echo esc_attr($palette['border']); ?>; border-radius: 12px;
            color: <?php echo esc_attr($palette['text']); ?>; padding: 0.75rem 1rem; font-size: 0.9rem;
        }
        .dg-site-portal #dg-portal-login input:focus { outline: none; border-color: <?php echo esc_attr($palette['accent']); ?>; }
        .dg-site-portal #dg-portal-login .login-remember { margin: 0.5rem 0 1.25rem; color: <?php echo esc_attr($palette['muted']); ?>; font-size: 0.82rem; }
        .dg-site-portal #dg-portal-login .login-submit input {
            width: 100%; background: <?php echo esc_attr($palette['accent']); ?>; border: none; border-radius: 12px; color: #fff;
            padding: 0.85rem; font-size: 0.9rem; font-weight: 700; cursor: pointer;
        }
        .dg-site-portal #dg-portal-login .login-submit input:hover { background: <?php echo esc_attr($palette['accent_hover']); ?>; }
        .dg-site-portal .login-links { margin-top: 1.25rem; text-align: center; font-size: 0.88rem; font-weight: 600; }
        .dg-site-portal .login-links a { color: <?php echo esc_attr($palette['link']); ?>; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .dg-site-portal .login-links a:hover { text-decoration: underline; }
        .dg-site-portal .login-secondary { margin-top: 0.75rem; text-align: center; font-size: 0.78rem; }
        .dg-site-portal .login-secondary a { color: <?php echo esc_attr($palette['muted']); ?>; text-decoration: none; }
        .dg-site-portal .login-secondary a:hover { color: <?php echo esc_attr($palette['text']); ?>; text-decoration: underline; }
        .dg-site-portal .footer-note { text-align: center; margin-top: 1.5rem; color: <?php echo esc_attr($palette['muted']); ?>; font-size: 0.78rem; }
        .dg-site-portal .builder-placeholder { color: <?php echo esc_attr($palette['muted']); ?>; font-size: 0.85rem; text-align: center; padding: 1rem 0; }
    </style>
    <?php wp_head(); ?>
</head>
<body <?php body_class('dg-site-portal-page dg-site-portal-theme-' . sanitize_html_class($theme)); ?>>
<div class="dg-site-portal">
<div class="portal-wrap">

    <div class="portal-logo">
        <h1><i class="fas <?php echo esc_attr($login_icon); ?>" style="color:<?php echo esc_attr($login_icon_color); ?>;"></i> <?php echo esc_html($site_label); ?></h1>
        <?php if ($login_tagline) : ?>
        <p><?php echo esc_html($login_tagline); ?></p>
        <?php endif; ?>
    </div>

    <div class="login-card">

        <?php if ($login_error) : ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php esc_html_e('Invalid username or password. Try again or reset your password.', 'dg-platform'); ?></div>
        <?php endif; ?>

        <?php if ($access_denied) : ?>
        <div class="alert alert-error"><i class="fas fa-lock"></i> <?php echo esc_html($access_denied_message); ?></div>
        <?php endif; ?>

        <?php if ($logged_in_no_access) : ?>
        <div class="alert alert-info">
            <?php esc_html_e('You are signed in, but this account does not have portal access.', 'dg-platform'); ?>
            <?php if ($support_email) : ?>
            <?php esc_html_e('Contact', 'dg-platform'); ?> <a href="mailto:<?php echo esc_attr($support_email); ?>" style="color:inherit;"><?php echo esc_html($support_email); ?></a>.
            <?php endif; ?>
        </div>
        <?php elseif ($is_builder) : ?>
        <p class="builder-placeholder"><?php esc_html_e('Portal login preview. Save and view the page on the front end to test sign-in.', 'dg-platform'); ?></p>
        <?php elseif (function_exists('wp_login_form')) : ?>
        <?php
        wp_login_form([
            'form_id' => 'dg-portal-login',
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
        <?php if ($show_onboarding_link && $onboarding_url) : ?>
        <p class="login-secondary"><a href="<?php echo esc_url($onboarding_url); ?>"><?php esc_html_e('Complete onboarding', 'dg-platform'); ?></a></p>
        <?php endif; ?>
    </div>

    <p class="footer-note"><?php esc_html_e('Use the email address associated with your account.', 'dg-platform'); ?></p>

</div>
</div>
<?php wp_footer(); ?>
</body>
</html>
