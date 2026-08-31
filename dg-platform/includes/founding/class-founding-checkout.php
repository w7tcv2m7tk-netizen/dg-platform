<?php
/**
 * Founding 10 Stripe Checkout Sessions — 14-day trial, no Payment Links.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Founding_Checkout {

    const TRIAL_DAYS = 14;

    /** @return array<string,array{label:string,monthly:int}> */
    public static function platform_catalog() {
        return [
            'starter' => ['label' => 'Starter', 'monthly' => 9900],
            'professional' => ['label' => 'Growth', 'monthly' => 24900],
            'business' => ['label' => 'Scale', 'monthly' => 49900],
        ];
    }

    /** @return array<string,array{label:string,monthly:int,group:string}> */
    public static function add_on_catalog() {
        return [
            'real-estate' => ['label' => 'Property / Real Estate', 'monthly' => 9900, 'group' => 'apps'],
            'accommodation' => ['label' => 'Hospitality & Accommodation', 'monthly' => 9900, 'group' => 'apps'],
            'services' => ['label' => 'Services', 'monthly' => 9900, 'group' => 'apps'],
            'creator' => ['label' => 'Creator & Media', 'monthly' => 9900, 'group' => 'apps'],
            'finance' => ['label' => 'Finance', 'monthly' => 9900, 'group' => 'apps'],
            'automotive' => ['label' => 'Automotive', 'monthly' => 9900, 'group' => 'apps'],
            'commercial' => ['label' => 'Commercial Property', 'monthly' => 9900, 'group' => 'apps'],
            'ai_visibility' => ['label' => 'AI Visibility', 'monthly' => 9900, 'group' => 'premium'],
            'seo' => ['label' => 'SEO', 'monthly' => 9900, 'group' => 'premium'],
            'automation' => ['label' => 'Automation', 'monthly' => 4900, 'group' => 'premium'],
            'analytics' => ['label' => 'Analytics', 'monthly' => 4900, 'group' => 'premium'],
            'social' => ['label' => 'Social', 'monthly' => 7900, 'group' => 'premium'],
            'voice_ai' => ['label' => 'Voice AI', 'monthly' => 9900, 'group' => 'premium'],
            'extra_users' => ['label' => 'Extra users', 'monthly' => 2900, 'group' => 'addons'],
            'white_label' => ['label' => 'White Label', 'monthly' => 19900, 'group' => 'addons'],
        ];
    }

    /**
     * @param array<string,mixed> $offer
     * @return array<int,array{name:string,amount:int}>
     */
    public static function line_items_preview(array $offer) {
        $interval = ($offer['billing_interval'] ?? 'month') === 'year' ? 'year' : 'month';
        $items = [];
        foreach (self::build_price_rows($offer, $interval) as $row) {
            $items[] = [
                'name' => $row['name'],
                'amount' => $row['unit_amount'],
            ];
        }
        return $items;
    }

    public static function recurring_total_cents(array $offer) {
        $total = 0;
        foreach (self::line_items_preview($offer) as $item) {
            $total += (int) $item['amount'];
        }
        return $total;
    }

    /**
     * Hosted Checkout Session — card collected, trial_period_days=14, $0 now.
     *
     * @param array<string,mixed> $offer
     * @return array<string,mixed>|WP_Error
     */
    public static function create_session(array $offer) {
        if (!class_exists('DG_Stripe_Billing')) {
            return new WP_Error('stripe_unavailable', 'Stripe billing is not available.');
        }

        $interval = ($offer['billing_interval'] ?? 'month') === 'year' ? 'year' : 'month';
        $rows = self::build_price_rows($offer, $interval);
        if ($rows === []) {
            return new WP_Error('empty_cart', 'Select a DigitalGate plan before starting the trial.');
        }

        $token = (string) ($offer['token'] ?? '');
        $success = add_query_arg('token', $token, home_url('/founding/trial-started/'));
        $success .= (strpos($success, '?') === false ? '?' : '&') . 'session_id={CHECKOUT_SESSION_ID}';
        $cancel = add_query_arg('token', $token, home_url('/founding/setup/'));

        $body = [
            'mode' => 'subscription',
            'success_url' => $success,
            'cancel_url' => $cancel,
            'customer_email' => (string) ($offer['email'] ?? ''),
            'payment_method_collection' => 'always',
            'subscription_data[trial_period_days]' => (string) self::TRIAL_DAYS,
            'subscription_data[metadata][dg_founding]' => 'true',
            'subscription_data[metadata][dg_offer_token]' => $token,
            'subscription_data[metadata][dg_plan]' => (string) ($offer['platform_tier'] ?? ''),
            'subscription_data[metadata][dg_billing_interval]' => $interval,
            'subscription_data[metadata][contact_email]' => (string) ($offer['email'] ?? ''),
            'metadata[dg_founding]' => 'true',
            'metadata[dg_offer_token]' => $token,
            'metadata[dg_plan]' => (string) ($offer['platform_tier'] ?? ''),
            'metadata[dg_platform_tier]' => (string) ($offer['platform_tier'] ?? ''),
            'metadata[dg_category]' => 'founding',
            'metadata[dg_billing_interval]' => $interval,
            'metadata[business_name]' => (string) ($offer['business_name'] ?? ''),
            'metadata[customer_name]' => (string) ($offer['name'] ?? ''),
            'metadata[contact_email]' => (string) ($offer['email'] ?? ''),
        ];

        foreach ($rows as $i => $row) {
            $body["line_items[{$i}][quantity]"] = '1';
            $body["line_items[{$i}][price_data][currency]"] = 'aud';
            $body["line_items[{$i}][price_data][unit_amount]"] = (string) $row['unit_amount'];
            $body["line_items[{$i}][price_data][recurring][interval]"] = $interval;
            $body["line_items[{$i}][price_data][product_data][name]"] = $row['name'];
            $body["line_items[{$i}][price_data][product_data][metadata][dg_founding]"] = 'true';
        }

        $session = DG_Stripe_Billing::request('checkout/sessions', $body, 'POST');
        if (is_wp_error($session)) {
            return $session;
        }

        if (!empty($session['id']) && $token !== '') {
            DG_Founding_Offers::update($token, [
                'stripe_session_id' => (string) $session['id'],
            ]);
        }

        return $session;
    }

    /**
     * API-only trial proof (test mode). Does not use Payment Links.
     * Creates a customer + subscription with trial_period_days=14 and a test card.
     *
     * @param string $interval month|year
     * @return array<string,mixed>|WP_Error
     */
    public static function prove_trial($interval = 'month') {
        $interval = $interval === 'year' ? 'year' : 'month';
        $email = 'founding-trial-' . $interval . '-' . time() . '@example.test';

        $customer = DG_Stripe_Billing::request('customers', [
            'email' => $email,
            'name' => 'Founding Trial ' . $interval,
            'metadata[dg_founding]' => 'true',
            'metadata[dg_proof]' => 'trial',
        ], 'POST');
        if (is_wp_error($customer)) {
            return $customer;
        }

        $pm = DG_Stripe_Billing::request('payment_methods', [
            'type' => 'card',
            'card[token]' => 'tok_visa',
        ], 'POST');
        if (is_wp_error($pm)) {
            return $pm;
        }

        $attach = DG_Stripe_Billing::request(
            'payment_methods/' . rawurlencode((string) $pm['id']) . '/attach',
            ['customer' => (string) $customer['id']],
            'POST'
        );
        if (is_wp_error($attach)) {
            return $attach;
        }

        DG_Stripe_Billing::request('customers/' . rawurlencode((string) $customer['id']), [
            'invoice_settings[default_payment_method]' => (string) $pm['id'],
        ], 'POST');

        $offer = [
            'platform_tier' => 'starter',
            'billing_interval' => $interval,
            'apps' => ['services'],
            'premium' => [],
            'addons' => [],
            'token' => 'proof_' . $interval,
        ];
        $rows = self::build_price_rows($offer, $interval);

        $body = [
            'customer' => (string) $customer['id'],
            'trial_period_days' => (string) self::TRIAL_DAYS,
            'default_payment_method' => (string) $pm['id'],
            'payment_settings[save_default_payment_method]' => 'on_subscription',
            'metadata[dg_founding]' => 'true',
            'metadata[dg_proof]' => $interval,
        ];
        foreach ($rows as $i => $row) {
            $body["items[{$i}][price_data][currency]"] = 'aud';
            $body["items[{$i}][price_data][unit_amount]"] = (string) $row['unit_amount'];
            $body["items[{$i}][price_data][recurring][interval]"] = $interval;
            $body["items[{$i}][price_data][product_data][name]"] = $row['name'];
        }

        $subscription = DG_Stripe_Billing::request('subscriptions?expand[]=latest_invoice', $body, 'POST');
        if (is_wp_error($subscription)) {
            return $subscription;
        }

        $invoice = is_array($subscription['latest_invoice'] ?? null) ? $subscription['latest_invoice'] : [];
        $amount_due = (int) ($invoice['amount_due'] ?? -1);
        $amount_paid = (int) ($invoice['amount_paid'] ?? -1);

        return [
            'ok' => ($subscription['status'] ?? '') === 'trialing' && $amount_due === 0,
            'interval' => $interval,
            'customer_id' => $customer['id'] ?? '',
            'subscription_id' => $subscription['id'] ?? '',
            'status' => $subscription['status'] ?? '',
            'trial_end' => isset($subscription['trial_end']) ? (int) $subscription['trial_end'] : 0,
            'default_payment_method' => $subscription['default_payment_method'] ?? $pm['id'],
            'amount_due' => $amount_due,
            'amount_paid' => $amount_paid,
            'recurring_cents' => self::recurring_total_cents($offer),
        ];
    }

    /**
     * @param array<string,mixed> $offer
     * @return array<int,array{name:string,unit_amount:int}>
     */
    private static function build_price_rows(array $offer, $interval) {
        $multiplier = $interval === 'year' ? 10 : 1;
        $rows = [];
        $tier = (string) ($offer['platform_tier'] ?? 'starter');
        $plans = self::platform_catalog();
        if (isset($plans[$tier])) {
            $rows[] = [
                'name' => 'DigitalGate Platform — ' . $plans[$tier]['label'] . ($interval === 'year' ? ' (annual)' : ''),
                'unit_amount' => (int) $plans[$tier]['monthly'] * $multiplier,
            ];
        }
        $catalog = self::add_on_catalog();
        $keys = array_merge(
            (array) ($offer['apps'] ?? []),
            (array) ($offer['premium'] ?? []),
            (array) ($offer['addons'] ?? [])
        );
        foreach ($keys as $key) {
            if (!isset($catalog[$key])) {
                continue;
            }
            $rows[] = [
                'name' => 'DigitalGate — ' . $catalog[$key]['label'] . ($interval === 'year' ? ' (annual)' : ''),
                'unit_amount' => (int) $catalog[$key]['monthly'] * $multiplier,
            ];
        }
        return $rows;
    }
}
