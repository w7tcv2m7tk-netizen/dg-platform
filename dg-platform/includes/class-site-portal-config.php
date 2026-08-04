<?php
/**
 * Per-hostname portal configuration for DG_Site_Portal / DG_Client_Portal.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Site_Portal_Config {

    /**
     * Portal definitions keyed by production hostname.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function portals() {
        return apply_filters('dg_site_portal_configs', [
            'digitalgate.com.au' => [
                'id' => 'client',
                'enabled' => true,
                'label' => 'Client Portal',
                'site_label' => 'DigitalGate',
                'toolbar_label' => 'Client Dashboard',
                'role' => 'dg_client',
                'role_label' => 'DG Client',
                'cap' => 'dg_client_portal',
                'login_slug' => 'client-portal',
                'dashboard_slug' => 'client-dashboard',
                'account_slug' => 'client-account',
                'reports_slug' => 'client-reports',
                'onboarding_slug' => 'onboarding',
                'protected_slugs' => [
                    'client-dashboard',
                    'client-account',
                    'client-reports',
                    'customer-account',
                    'reports',
                ],
                'canonical_slugs' => [
                    'client-portal',
                    'client-dashboard',
                    'client-account',
                    'client-reports',
                ],
                'legacy_redirects' => [
                    'system-pages/client-portal' => 'client-portal',
                    'system-pages/client-dashboard' => 'client-dashboard',
                    'system-pages/customer-account' => 'client-account',
                    'system-pages/client-account' => 'client-account',
                    'system-pages/client-reports' => 'client-reports',
                    'customer-account' => 'client-account',
                ],
                'login_tagline' => 'Sign in to your platform dashboard',
                'access_denied_message' => 'This area is for DigitalGate clients. Use the email from your purchase confirmation.',
                'support_email' => 'support@digitalgate.com.au',
                'show_onboarding_link' => true,
                'allow_wp_admin' => true,
                'dashboard_renderer' => 'oxygen',
                'theme' => 'digitalgate',
                'login_icon' => 'fa-layer-group',
                'login_icon_color' => '#3B82F6',
            ],
            'currumbinvalleyhideaway.com.au' => [
                'id' => 'guest',
                'enabled' => true,
                'label' => 'Guest Portal',
                'site_label' => 'Currumbin Valley Hideaway',
                'toolbar_label' => 'Guest Portal',
                'role' => 'dg_guest_portal',
                'role_label' => 'CVH Guest',
                'cap' => 'dg_guest_portal',
                'login_slug' => 'guest-portal',
                'dashboard_slug' => 'guest-dashboard',
                'account_slug' => null,
                'reports_slug' => null,
                'onboarding_slug' => null,
                'protected_slugs' => [
                    'guest-portal',
                    'guest-dashboard',
                ],
                'canonical_slugs' => [
                    'guest-portal',
                    'guest-dashboard',
                ],
                'legacy_redirects' => [],
                'login_tagline' => 'View your bookings and check-in details',
                'access_denied_message' => 'This area is for Currumbin Valley Hideaway guests. Sign in with the email used when you booked.',
                'support_email' => 'bookings@currumbinvalleyhideaway.com.au',
                'show_onboarding_link' => false,
                'allow_wp_admin' => false,
                'dashboard_renderer' => 'plugin',
                'theme' => 'hideaway',
                'login_icon' => 'fa-tree',
                'login_icon_color' => '#B9A48A',
            ],
            'roerealty.com.au' => [
                'id' => 'owner',
                'enabled' => true,
                'label' => 'Owner Portal',
                'site_label' => 'Roe Realty',
                'toolbar_label' => 'Owner Portal',
                'role' => 'dg_owner_portal',
                'role_label' => 'Property Owner',
                'cap' => 'dg_owner_portal',
                'login_slug' => 'owner-portal',
                'dashboard_slug' => 'owner-dashboard',
                'account_slug' => null,
                'reports_slug' => 'owner-reports',
                'onboarding_slug' => null,
                'protected_slugs' => [
                    'owner-portal',
                    'owner-dashboard',
                    'owner-reports',
                ],
                'canonical_slugs' => [
                    'owner-portal',
                    'owner-dashboard',
                    'owner-reports',
                ],
                'legacy_redirects' => [],
                'login_tagline' => 'View your property reports and documents',
                'access_denied_message' => 'This area is for Roe Realty property owners. Contact your agent if you need access.',
                'support_email' => 'hello@roerealty.com.au',
                'show_onboarding_link' => false,
                'allow_wp_admin' => false,
                'dashboard_renderer' => 'placeholder',
                'theme' => 'roe',
                'login_icon' => 'fa-home',
                'login_icon_color' => '#1C2B2A',
            ],
            'aetherra.com.au' => [
                'id' => 'creator',
                'enabled' => true,
                'label' => 'Creator Studio',
                'site_label' => 'Aetherra',
                'toolbar_label' => 'Creator Studio',
                'role' => 'dg_creator_portal',
                'role_label' => 'Creator',
                'cap' => 'dg_creator_portal',
                'login_slug' => 'creator-portal',
                'dashboard_slug' => 'creator-dashboard',
                'account_slug' => null,
                'reports_slug' => null,
                'onboarding_slug' => null,
                'protected_slugs' => [
                    'creator-portal',
                    'creator-dashboard',
                ],
                'canonical_slugs' => [
                    'creator-portal',
                    'creator-dashboard',
                ],
                'legacy_redirects' => [],
                'login_tagline' => 'Your content, audience, and projects',
                'access_denied_message' => 'Creator Studio access is limited to authorised accounts.',
                'support_email' => 'hello@aetherra.com.au',
                'show_onboarding_link' => false,
                'allow_wp_admin' => true,
                'dashboard_renderer' => 'placeholder',
                'theme' => 'aetherra',
                'login_icon' => 'fa-wand-magic-sparkles',
                'login_icon_color' => '#8B5CF6',
            ],
        ]);
    }

    /** @return array<string,mixed>|null */
    public static function current() {
        if (!class_exists('DG_Site_Profile')) {
            return null;
        }

        $host = DG_Site_Profile::hostname();
        if ($host === '') {
            return null;
        }

        foreach (self::portals() as $domain => $config) {
            if ($host === $domain || strpos($host, $domain) !== false) {
                return array_merge($config, ['domain' => $domain]);
            }
        }

        return null;
    }

    /** @return array<string,mixed>|null */
    public static function for_id($portal_id) {
        $portal_id = sanitize_key((string) $portal_id);
        foreach (self::portals() as $config) {
            if (($config['id'] ?? '') === $portal_id) {
                return $config;
            }
        }
        return null;
    }
}
