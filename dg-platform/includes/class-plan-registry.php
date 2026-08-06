<?php
/**
 * Platform plan tiers — SaaS licence gating (Starter → Enterprise).
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Plan_Registry {

    const OPTION_PLAN = 'dg_platform_plan';
    const OPTION_ADDONS = 'dg_platform_plan_addons';

    /** @var array<string,int> */
    private static $tier_rank = [
        'starter' => 1,
        'professional' => 2,
        'business' => 3,
        'enterprise' => 4,
    ];

    public static function init() {
        add_filter('dg_platform_module_definitions', [__CLASS__, 'annotate_module_definitions'], 20);
    }

    public static function tiers() {
        $tiers = [
            'starter' => [
                'key' => 'starter',
                'label' => 'Starter',
                'price' => 99,
                'price_label' => '$99/mo',
                'users' => 1,
                'tagline' => 'Core business platform for solo operators.',
                'features' => [
                    'crm_core',
                    'contacts',
                    'tasks',
                    'calendar',
                    'notes',
                    'dashboard',
                    'documents',
                    'activities',
                    'search',
                ],
                'max_industry_modules' => 0,
            ],
            'professional' => [
                'key' => 'professional',
                'label' => 'Growth',
                'price' => 249,
                'price_label' => '$249/mo',
                'users' => 5,
                'tagline' => 'Growth automation and intelligence for growing teams.',
                'features' => [
                    'crm_core',
                    'contacts',
                    'tasks',
                    'calendar',
                    'notes',
                    'dashboard',
                    'documents',
                    'activities',
                    'search',
                    'automation',
                    'reports',
                    'email_sms',
                    'website_manager',
                    'seo',
                    'ai_assistant',
                ],
                'max_industry_modules' => 1,
            ],
            'business' => [
                'key' => 'business',
                'label' => 'Scale',
                'price' => 499,
                'price_label' => '$499/mo',
                'users' => 0,
                'users_label' => 'Unlimited',
                'tagline' => 'Advanced AI visibility, pipelines, and API access.',
                'features' => [
                    'crm_core',
                    'contacts',
                    'tasks',
                    'calendar',
                    'notes',
                    'dashboard',
                    'documents',
                    'activities',
                    'search',
                    'automation',
                    'reports',
                    'email_sms',
                    'website_manager',
                    'seo',
                    'ai_assistant',
                    'ai_visibility',
                    'advanced_automation',
                    'advanced_reporting',
                    'multiple_pipelines',
                    'api',
                ],
                'max_industry_modules' => 99,
            ],
            'enterprise' => [
                'key' => 'enterprise',
                'label' => 'Enterprise',
                'price' => 0,
                'price_label' => 'Custom',
                'users' => 0,
                'users_label' => 'Unlimited',
                'tagline' => 'White-label, priority support, and custom integrations.',
                'features' => [
                    'crm_core',
                    'contacts',
                    'tasks',
                    'calendar',
                    'notes',
                    'dashboard',
                    'documents',
                    'activities',
                    'search',
                    'automation',
                    'reports',
                    'email_sms',
                    'website_manager',
                    'seo',
                    'ai_assistant',
                    'ai_visibility',
                    'advanced_automation',
                    'advanced_reporting',
                    'multiple_pipelines',
                    'api',
                    'white_label',
                    'audit_log',
                    'priority_support',
                ],
                'max_industry_modules' => 99,
            ],
        ];

        return apply_filters('dg_platform_plan_tiers', $tiers);
    }

    public static function premium_addons() {
        return apply_filters('dg_platform_premium_addons', [
            'ai_visibility_pro' => ['label' => 'AI Visibility Pro', 'price' => 99, 'feature' => 'ai_visibility_pro'],
            'seo_pro' => ['label' => 'SEO Pro', 'price' => 99, 'feature' => 'seo_pro'],
            'automation_pro' => ['label' => 'Automation Pro', 'price' => 49, 'feature' => 'automation_pro'],
            'analytics_pro' => ['label' => 'Analytics Pro', 'price' => 49, 'feature' => 'analytics_pro'],
            'social_pro' => ['label' => 'Social Pro', 'price' => 79, 'feature' => 'social_pro'],
        ]);
    }

    public static function optional_addons() {
        return apply_filters('dg_platform_optional_addons', [
            'voice_ai' => ['label' => 'Voice AI', 'price' => 99, 'feature' => 'voice_ai', 'billing' => 'monthly'],
            'extra_users' => ['label' => 'Extra Users', 'price' => 29, 'feature' => 'extra_users', 'billing' => 'per_user'],
            'white_label' => ['label' => 'White Label', 'price' => 199, 'feature' => 'white_label', 'billing' => 'monthly'],
            'training' => ['label' => 'Training & Onboarding', 'price' => 497, 'feature' => 'training', 'billing' => 'one_time'],
        ]);
    }

    /**
     * Premium Pro apps only show in admin when explicitly selected in Platform Plan add-ons.
     */
    public static function has_premium_app($addon_key) {
        $premium = self::premium_addons();
        if (!isset($premium[$addon_key])) {
            return false;
        }
        return self::has_addon($addon_key);
    }

    /** @return string e.g. "+$99/mo" or "+$497 one-time" */
    public static function addon_price_label($addon) {
        $price = (int) ($addon['price'] ?? 0);
        $billing = $addon['billing'] ?? 'monthly';
        if ($billing === 'one_time') {
            return '+$' . $price . ' one-time';
        }
        if ($billing === 'per_user') {
            return '+$' . $price . '/user';
        }
        return '+$' . $price . '/mo';
    }

    public static function industry_app_modules() {
        return ['real-estate', 'accommodation', 'finance', 'services', 'dealership', 'commercial', 'creator'];
    }

    public static function current() {
        $plan = get_option(self::OPTION_PLAN, '');
        if ($plan === '' || !isset(self::$tier_rank[$plan])) {
            return self::default_for_site();
        }
        return $plan;
    }

    public static function default_for_site() {
        if (class_exists('DG_Site_Profile')) {
            if (DG_Site_Profile::is_digitalgate()) {
                return 'enterprise';
            }
            if (DG_Site_Profile::is_roe_realty() || DG_Site_Profile::is_currumbin_hideaway()) {
                return 'business';
            }
            if (DG_Site_Profile::is_aetherra()) {
                return 'professional';
            }
        }
        return 'business';
    }

    public static function current_tier() {
        $tiers = self::tiers();
        $key = self::current();
        return isset($tiers[$key]) ? $tiers[$key] : $tiers['business'];
    }

    public static function set_plan($plan) {
        if (!isset(self::$tier_rank[$plan])) {
            return false;
        }
        update_option(self::OPTION_PLAN, $plan);
        return true;
    }

    public static function tier_rank($tier) {
        return self::$tier_rank[$tier] ?? 0;
    }

    public static function meets_tier($required) {
        return self::tier_rank(self::current()) >= self::tier_rank($required);
    }

    public static function active_addons() {
        $addons = get_option(self::OPTION_ADDONS, []);
        return is_array($addons) ? array_values(array_filter($addons)) : [];
    }

    public static function set_addons(array $addons) {
        update_option(self::OPTION_ADDONS, array_values(array_unique(array_map('sanitize_text_field', $addons))));
    }

    public static function has_addon($key) {
        return in_array($key, self::active_addons(), true);
    }

    public static function has_feature($feature) {
        $tier = self::current_tier();
        if (in_array($feature, $tier['features'] ?? [], true)) {
            return true;
        }

        foreach (array_merge(self::premium_addons(), self::optional_addons()) as $addon_key => $addon) {
            if (($addon['feature'] ?? '') === $feature && self::has_addon($addon_key)) {
                return true;
            }
        }

        if ($feature === 'voice_ai' && self::meets_tier('business')) {
            return true;
        }

        return (bool) apply_filters('dg_platform_plan_has_feature', false, $feature, self::current());
    }

    public static function module_min_tier($module_key) {
        if ($module_key === 'core' || $module_key === 'marketing') {
            return 'starter';
        }
        if (in_array($module_key, self::industry_app_modules(), true)) {
            return 'professional';
        }
        return 'professional';
    }

    public static function module_allowed($module_key) {
        if ($module_key === 'core') {
            return true;
        }

        $min = self::module_min_tier($module_key);
        if (!self::meets_tier($min)) {
            return false;
        }

        if (!in_array($module_key, self::industry_app_modules(), true)) {
            return true;
        }

        $active = get_option('dg_platform_active_modules', ['core']);
        $industry_active = array_intersect($active, self::industry_app_modules());
        $tier = self::current_tier();
        $max = (int) ($tier['max_industry_modules'] ?? 0);

        if (in_array($module_key, $industry_active, true)) {
            return true;
        }

        return count($industry_active) < $max;
    }

    public static function filter_modules_for_plan(array $modules) {
        $allowed = ['core'];
        foreach ($modules as $module) {
            if ($module === 'core') {
                continue;
            }
            if (self::module_allowed($module) || in_array($module, $modules, true)) {
                $allowed[] = $module;
            }
        }
        return array_values(array_unique($allowed));
    }

    public static function annotate_module_definitions($definitions) {
        foreach ($definitions as $key => &$def) {
            if ($key === 'core') {
                continue;
            }
            $def['min_tier'] = self::module_min_tier($key);
            $def['plan_allowed'] = self::module_allowed($key);
        }
        return $definitions;
    }

    public static function feature_labels() {
        return [
            'automation' => 'Growth Automation',
            'reports' => 'Growth Intelligence',
            'seo' => 'AI Visibility & SEO',
            'ai_visibility' => 'AI Visibility',
            'advanced_reporting' => 'Advanced Growth Intelligence',
            'api' => 'API Access',
        ];
    }
}
