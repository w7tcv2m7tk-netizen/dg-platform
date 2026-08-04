<?php
/**
 * Client-facing progress overview across accessible platform apps.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Client_Reports {

    /** @var string[] */
    private static $chart_palette = ['#3B82F6', '#10B981', '#F59E0B', '#8B5CF6', '#EC4899', '#06B6D4', '#64748B'];

    /** @return array<string,mixed> */
    public static function template_context() {
        try {
            return self::build_template_context();
        } catch (Throwable $e) {
            return self::fallback_context();
        }
    }

    /** @return array<string,mixed> */
    private static function fallback_context() {
        return [
            'client_name' => 'Client',
            'dashboard_url' => home_url('/client-dashboard/'),
            'generated_label' => wp_date('j M Y, g:i a'),
            'setup_steps' => [],
            'setup_percent' => 0,
            'summary_cards' => [],
            'sections' => [],
            'premium_apps' => [],
            'trend_charts' => [],
            'charts' => [],
            'pdf_filename' => 'digitalgate-progress-report.pdf',
            'is_builder' => false,
            'has_data' => false,
            'ai_narrative' => null,
        ];
    }

    /** @return array<string,mixed> */
    private static function build_template_context() {
        $is_builder = class_exists('DG_Client_Portal') && DG_Client_Portal::is_oxygen_builder();
        $setup = self::setup_status($is_builder);
        $sections = $is_builder ? self::preview_sections() : self::live_sections();
        $premium_apps = $is_builder ? self::preview_premium_apps() : self::live_premium_apps();
        $trend_charts = $is_builder ? self::preview_trend_charts() : self::build_trend_charts($sections, 30);
        $summary_cards = self::summary_cards($sections, $setup, $premium_apps);
        $charts = array_merge(
            self::collect_charts($sections),
            self::collect_charts_from_premium($premium_apps),
            $trend_charts
        );
        $ai_narrative = $is_builder ? null : self::ai_narrative($setup, $premium_apps, $summary_cards);

        return [
            'client_name' => self::client_name($is_builder),
            'dashboard_url' => class_exists('DG_Client_Portal') ? DG_Client_Portal::dashboard_url() : home_url('/client-dashboard/'),
            'generated_label' => wp_date('j M Y, g:i a'),
            'setup_steps' => $setup['steps'],
            'setup_percent' => $setup['percent'],
            'summary_cards' => $summary_cards,
            'sections' => $sections,
            'premium_apps' => $premium_apps,
            'trend_charts' => $trend_charts,
            'charts' => $charts,
            'ai_narrative' => $ai_narrative,
            'pdf_filename' => 'digitalgate-progress-report-' . wp_date('Y-m-d') . '.pdf',
            'is_builder' => $is_builder,
            'has_data' => !empty($sections) || !empty($summary_cards) || !empty($premium_apps),
        ];
    }

    private static function client_name($is_builder) {
        if ($is_builder) {
            return 'Preview';
        }
        if (!function_exists('wp_get_current_user')) {
            return 'Client';
        }
        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            return 'Client';
        }
        return $user->display_name ?: $user->first_name ?: explode('@', $user->user_email)[0];
    }

    /** @return array{steps:array<int,array<string,mixed>>,percent:int} */
    private static function setup_status($is_builder) {
        $payment_done = $is_builder;
        $onboarding_done = false;
        $setup_live = false;

        if (!$is_builder && function_exists('wp_get_current_user')) {
            $user = wp_get_current_user();
            if ($user && $user->ID) {
                $contact_id = (int) get_user_meta($user->ID, 'dg_contact_id', true);
                if ($contact_id && class_exists('DG_Contacts')) {
                    $contact = DG_Contacts::get($contact_id);
                    if ($contact) {
                        $tags = is_string($contact->tags ?? null) ? $contact->tags : '';
                        $payment_done = stripos($tags, 'Payment Received') !== false;
                        $onboarding_done = stripos($tags, 'Onboarding Complete') !== false;
                        $setup_live = stripos($tags, 'Platform Live') !== false;
                    }
                } elseif ($user->ID) {
                    $payment_done = true;
                }
            }
        }

        $steps = [
            ['label' => 'Payment received', 'done' => $payment_done, 'desc' => 'Stripe checkout completed'],
            ['label' => 'Onboarding submitted', 'done' => $onboarding_done, 'desc' => 'Business and platform requirements'],
            ['label' => 'Platform configuration', 'done' => $setup_live, 'desc' => 'Modules and integrations'],
            ['label' => 'Go live', 'done' => $setup_live, 'desc' => 'Training and ongoing support'],
        ];

        $done = 0;
        foreach ($steps as $step) {
            if (!empty($step['done'])) {
                $done++;
            }
        }

        return [
            'steps' => $steps,
            'percent' => (int) round(100 * $done / max(count($steps), 1)),
        ];
    }

    private static function user_can($cap) {
        if (current_user_can('manage_options')) {
            return true;
        }
        return current_user_can($cap);
    }

    /** @return array<int,array<string,mixed>> */
    private static function live_sections() {
        $sections = [];

        if (self::user_can('dg_view_contacts') || self::user_can('dg_view_tasks') || self::user_can('dg_view_reports')) {
            $section = self::core_section();
            if ($section) {
                $sections[] = $section;
            }
        }
        if (self::user_can('dg_marketing_view_clients') && class_exists('DG_Marketing_Pipeline_Reports')) {
            $section = self::marketing_section();
            if ($section) {
                $sections[] = $section;
            }
        }
        if (self::user_can('dg_re_view_leads') && class_exists('DG_RE_Pipeline_Reports')) {
            $section = self::real_estate_section();
            if ($section) {
                $sections[] = $section;
            }
        }
        if (self::user_can('dg_acc_view_bookings') && class_exists('DG_Acc_Reports')) {
            $section = self::accommodation_section();
            if ($section) {
                $sections[] = $section;
            }
        }
        if (self::user_can('dg_fin_view_loans') && class_exists('DG_Fin_Reports')) {
            $section = self::finance_section();
            if ($section) {
                $sections[] = $section;
            }
        }
        if (self::user_can('dg_svc_view_jobs') && class_exists('DG_Svc_Reports')) {
            $section = self::services_section();
            if ($section) {
                $sections[] = $section;
            }
        }
        if (self::user_can('dg_dealer_view_inventory') && class_exists('DG_Dealer_Reports')) {
            $section = self::dealership_section();
            if ($section) {
                $sections[] = $section;
            }
        }
        if (self::user_can('dg_com_view_listings') && class_exists('DG_Com_Reports')) {
            $section = self::commercial_section();
            if ($section) {
                $sections[] = $section;
            }
        }
        if (self::user_can('dg_creator_view_content') && class_exists('DG_Creator_Reports')) {
            $section = self::creator_section();
            if ($section) {
                $sections[] = $section;
            }
        }

        return apply_filters('dg_client_reports_sections', $sections);
    }

    /** @return array<int,array<string,mixed>> */
    private static function preview_sections() {
        return [
            [
                'id' => 'core',
                'title' => 'Core CRM',
                'icon' => 'fa-layer-group',
                'color' => '#3B82F6',
                'stats' => [
                    ['label' => 'Contacts', 'value' => '248', 'suffix' => ''],
                    ['label' => 'Tasks done', 'value' => '79', 'suffix' => '%'],
                    ['label' => 'Upcoming events', 'value' => '6', 'suffix' => ''],
                ],
                'charts' => [
                    self::make_chart('preview-core-tasks', 'Task completion', 'doughnut', ['Completed', 'Pending'], [79, 21], '79%'),
                ],
            ],
            [
                'id' => 'marketing',
                'title' => 'Marketing',
                'icon' => 'fa-bullhorn',
                'color' => '#8B5CF6',
                'stats' => [
                    ['label' => 'Active clients', 'value' => '42', 'suffix' => ''],
                    ['label' => 'Conversion rate', 'value' => '38', 'suffix' => '%'],
                    ['label' => 'Audits this month', 'value' => '12', 'suffix' => ''],
                ],
                'charts' => [
                    self::make_chart('preview-mkt-pipeline', 'Client pipeline', 'bar', ['Lead', 'Engaged', 'Client', 'Active'], [18, 12, 8, 4]),
                ],
            ],
        ];
    }

    /** @return array<string,mixed>|null */
    private static function core_section() {
        $stats = class_exists('DG_Reports') ? DG_Reports::get_dashboard_stats() : [];
        $pending = (int) ($stats['tasks_pending'] ?? 0);
        $completed = (int) ($stats['tasks_completed'] ?? 0);
        $task_total = $pending + $completed;
        $task_rate = $task_total > 0 ? (int) round(100 * $completed / $task_total) : 0;

        $section_stats = [
            ['label' => 'Contacts', 'value' => self::format_number($stats['contacts'] ?? 0), 'suffix' => ''],
            ['label' => 'Tasks completed', 'value' => (string) $task_rate, 'suffix' => '%'],
            ['label' => 'Upcoming events', 'value' => self::format_number($stats['calendar_upcoming'] ?? 0), 'suffix' => ''],
            ['label' => 'Activities logged', 'value' => self::format_number($stats['activities'] ?? 0), 'suffix' => ''],
        ];

        $charts = [];
        if ($task_total > 0) {
            $charts[] = self::make_chart('core-tasks', 'Task completion', 'doughnut', ['Completed', 'Pending'], [$completed, $pending], $task_rate . '%');
        }

        return [
            'id' => 'core',
            'title' => 'Core CRM',
            'icon' => 'fa-layer-group',
            'color' => '#3B82F6',
            'stats' => $section_stats,
            'charts' => $charts,
        ];
    }

    /** @return array<string,mixed>|null */
    private static function marketing_section() {
        $summary = DG_Marketing_Pipeline_Reports::summary(30);
        $conversion = (int) round((float) ($summary['conversion_rate'] ?? 0));

        $section_stats = [
            ['label' => 'Total clients', 'value' => self::format_number($summary['clients_total'] ?? 0), 'suffix' => ''],
            ['label' => 'Conversion rate', 'value' => (string) $conversion, 'suffix' => '%'],
            ['label' => 'Audits this month', 'value' => self::format_number(DG_Marketing_Pipeline_Reports::audits_this_month()), 'suffix' => ''],
            ['label' => 'Voice leads', 'value' => self::format_number($summary['voice_leads_this_period'] ?? 0), 'suffix' => ''],
        ];

        $charts = [];
        $pipeline = self::stage_chart('mkt-pipeline', 'Client pipeline', $summary['status_pipeline'] ?? []);
        if ($pipeline) {
            $charts[] = $pipeline;
        }

        return [
            'id' => 'marketing',
            'title' => 'Marketing',
            'icon' => 'fa-bullhorn',
            'color' => '#8B5CF6',
            'stats' => $section_stats,
            'charts' => $charts,
        ];
    }

    /** @return array<string,mixed>|null */
    private static function real_estate_section() {
        $vendor_conv = DG_RE_Pipeline_Reports::vendor_conversion_summary();
        $conversion = (int) round((float) ($vendor_conv['rate'] ?? 0));

        $section_stats = [
            ['label' => 'Vendor leads', 'value' => self::format_number($vendor_conv['total'] ?? 0), 'suffix' => ''],
            ['label' => 'Vendor conversion', 'value' => (string) $conversion, 'suffix' => '%'],
            ['label' => 'Bookings this month', 'value' => self::format_number(DG_RE_Pipeline_Reports::bookings_this_month()), 'suffix' => ''],
            ['label' => 'Property reports', 'value' => self::format_number(DG_RE_Pipeline_Reports::property_reports_this_month()), 'suffix' => ''],
        ];

        $charts = [];
        $vendor = self::stage_chart('re-vendor-pipeline', 'Vendor pipeline', DG_RE_Pipeline_Reports::vendor_stage_counts());
        if ($vendor) {
            $charts[] = $vendor;
        }
        $buyer = self::stage_chart('re-buyer-pipeline', 'Buyer pipeline', DG_RE_Pipeline_Reports::buyer_stage_counts());
        if ($buyer) {
            $charts[] = $buyer;
        }

        return [
            'id' => 'real-estate',
            'title' => 'Real Estate',
            'icon' => 'fa-home',
            'color' => '#10B981',
            'stats' => $section_stats,
            'charts' => $charts,
        ];
    }

    /** @return array<string,mixed>|null */
    private static function accommodation_section() {
        $summary = DG_Acc_Reports::summary();
        $properties = max(1, (int) ($summary['properties'] ?? 0));
        $occupancy = (int) round(100 * ((int) ($summary['upcoming_30d'] ?? 0)) / $properties);
        $occupancy = min(100, $occupancy);

        $section_stats = [
            ['label' => 'Properties', 'value' => self::format_number($summary['properties'] ?? 0), 'suffix' => ''],
            ['label' => 'Upcoming stays', 'value' => self::format_number($summary['upcoming_30d'] ?? 0), 'suffix' => ''],
            ['label' => 'Revenue this month', 'value' => self::format_currency($summary['revenue_month'] ?? 0), 'suffix' => ''],
            ['label' => 'Guest profiles', 'value' => self::format_number($summary['guests'] ?? 0), 'suffix' => ''],
        ];

        $charts = [];
        $status = $summary['status_counts'] ?? [];
        if (!empty($status)) {
            $labels = [];
            $values = [];
            foreach ($status as $label => $count) {
                if ((int) $count > 0) {
                    $labels[] = ucwords(str_replace('_', ' ', (string) $label));
                    $values[] = (int) $count;
                }
            }
            if ($values) {
                $charts[] = self::make_chart('acc-bookings', 'Bookings by status', 'doughnut', $labels, $values, $occupancy . '%');
            }
        }

        return [
            'id' => 'accommodation',
            'title' => 'Accommodation',
            'icon' => 'fa-bed',
            'color' => '#06B6D4',
            'stats' => $section_stats,
            'charts' => $charts,
        ];
    }

    /** @return array<string,mixed> */
    private static function finance_section() {
        $summary = DG_Fin_Reports::summary();
        $apps = (int) ($summary['applications'] ?? 0);
        $settled = (int) ($summary['settled'] ?? 0);
        $rate = $apps > 0 ? (int) round(100 * $settled / $apps) : 0;

        return [
            'id' => 'finance',
            'title' => 'Finance',
            'icon' => 'fa-coins',
            'color' => '#F59E0B',
            'stats' => [
                ['label' => 'Active applications', 'value' => self::format_number($apps), 'suffix' => ''],
                ['label' => 'Pipeline value', 'value' => self::format_currency($summary['pipeline_value'] ?? 0), 'suffix' => ''],
                ['label' => 'Approved', 'value' => self::format_number($summary['approved'] ?? 0), 'suffix' => ''],
                ['label' => 'Settled', 'value' => (string) $rate, 'suffix' => '%'],
            ],
            'charts' => $apps > 0 ? [self::make_chart('fin-pipeline', 'Settlement progress', 'doughnut', ['Settled', 'In progress'], [$settled, max(0, $apps - $settled)], $rate . '%')] : [],
        ];
    }

    /** @return array<string,mixed> */
    private static function services_section() {
        $summary = DG_Svc_Reports::summary();
        $jobs = (int) ($summary['jobs'] ?? 0);
        $complete = (int) ($summary['complete'] ?? 0);
        $rate = $jobs > 0 ? (int) round(100 * $complete / $jobs) : 0;

        return [
            'id' => 'services',
            'title' => 'Services',
            'icon' => 'fa-wrench',
            'color' => '#EC4899',
            'stats' => [
                ['label' => 'Active jobs', 'value' => self::format_number($jobs), 'suffix' => ''],
                ['label' => 'Scheduled', 'value' => self::format_number($summary['scheduled'] ?? 0), 'suffix' => ''],
                ['label' => 'Quoted pipeline', 'value' => self::format_currency($summary['quoted_value'] ?? 0), 'suffix' => ''],
                ['label' => 'Completion rate', 'value' => (string) $rate, 'suffix' => '%'],
            ],
            'charts' => $jobs > 0 ? [self::make_chart('svc-jobs', 'Job completion', 'doughnut', ['Complete', 'Active'], [$complete, max(0, $jobs - $complete)], $rate . '%')] : [],
        ];
    }

    /** @return array<string,mixed> */
    private static function dealership_section() {
        $summary = DG_Dealer_Reports::summary();
        $leads = max(1, (int) ($summary['leads'] ?? 0));
        $sold = (int) ($summary['sold'] ?? 0);
        $rate = (int) round(100 * $sold / $leads);

        $charts = [];
        if ((int) ($summary['leads'] ?? 0) > 0) {
            $charts[] = self::make_chart('dealer-leads', 'Lead outcomes', 'bar', ['Leads', 'Test drives', 'Sold'], [
                (int) ($summary['leads'] ?? 0),
                (int) ($summary['test_drives'] ?? 0),
                $sold,
            ]);
        }

        return [
            'id' => 'dealership',
            'title' => 'Automotive',
            'icon' => 'fa-car',
            'color' => '#64748B',
            'stats' => [
                ['label' => 'Available vehicles', 'value' => self::format_number($summary['vehicles'] ?? 0), 'suffix' => ''],
                ['label' => 'Active leads', 'value' => self::format_number($summary['leads'] ?? 0), 'suffix' => ''],
                ['label' => 'Test drives', 'value' => self::format_number($summary['test_drives'] ?? 0), 'suffix' => ''],
                ['label' => 'Sold rate', 'value' => (string) min(100, $rate), 'suffix' => '%'],
            ],
            'charts' => $charts,
        ];
    }

    /** @return array<string,mixed> */
    private static function commercial_section() {
        $summary = DG_Com_Reports::summary();
        $tenancies = max(1, (int) ($summary['tenancies'] ?? 0));
        $active = (int) ($summary['active_leases'] ?? 0);
        $rate = (int) round(100 * $active / $tenancies);

        $charts = [];
        if ((int) ($summary['tenancies'] ?? 0) > 0) {
            $charts[] = self::make_chart(
                'com-leases',
                'Lease activation',
                'doughnut',
                ['Active', 'Pending'],
                [$active, max(0, (int) ($summary['tenancies'] ?? 0) - $active)],
                min(100, $rate) . '%'
            );
        }

        return [
            'id' => 'commercial',
            'title' => 'Commercial',
            'icon' => 'fa-city',
            'color' => '#2563EB',
            'stats' => [
                ['label' => 'Active listings', 'value' => self::format_number($summary['listings'] ?? 0), 'suffix' => ''],
                ['label' => 'Tenancies', 'value' => self::format_number($summary['tenancies'] ?? 0), 'suffix' => ''],
                ['label' => 'Active leases', 'value' => (string) min(100, $rate), 'suffix' => '%'],
                ['label' => 'Rent roll', 'value' => self::format_currency($summary['rent_roll'] ?? 0), 'suffix' => '/mo'],
            ],
            'charts' => $charts,
        ];
    }

    /** @return array<string,mixed> */
    private static function creator_section() {
        $summary = DG_Creator_Reports::summary();
        $published = (int) ($summary['published_posts'] ?? 0);
        $drafts = (int) ($summary['draft_posts'] ?? 0);
        $total = $published + $drafts;
        $rate = $total > 0 ? (int) round(100 * $published / $total) : 0;

        return [
            'id' => 'creator',
            'title' => 'Creator',
            'icon' => 'fa-video',
            'color' => '#A855F7',
            'stats' => [
                ['label' => 'Published posts', 'value' => self::format_number($published), 'suffix' => ''],
                ['label' => 'Draft posts', 'value' => self::format_number($drafts), 'suffix' => ''],
                ['label' => 'Live pages', 'value' => self::format_number($summary['pages'] ?? 0), 'suffix' => ''],
                ['label' => 'Published rate', 'value' => (string) $rate, 'suffix' => '%'],
            ],
            'charts' => $total > 0 ? [self::make_chart('creator-content', 'Content status', 'doughnut', ['Published', 'Drafts'], [$published, $drafts], $rate . '%')] : [],
        ];
    }

    /** @param array<string,array{label:string,count:int}> $stages */
    private static function stage_chart($id, $title, array $stages) {
        $labels = [];
        $values = [];
        foreach ($stages as $stage) {
            $count = (int) ($stage['count'] ?? 0);
            if ($count > 0) {
                $labels[] = (string) ($stage['label'] ?? 'Stage');
                $values[] = $count;
            }
        }
        if (!$values) {
            return null;
        }
        return self::make_chart($id, $title, 'bar', $labels, $values);
    }

    /** @return array<string,mixed> */
    private static function make_chart($id, $title, $type, array $labels, array $values, $center_label = '') {
        return [
            'id' => $id,
            'title' => $title,
            'type' => $type,
            'labels' => array_values($labels),
            'values' => array_map('intval', array_values($values)),
            'colors' => array_slice(self::$chart_palette, 0, max(count($values), 1)),
            'center_label' => $center_label,
        ];
    }

    /** @param array<string,mixed> $setup @param array<int,array<string,mixed>> $premium_apps @param array<int,array<string,mixed>> $summary_cards @return array<string,mixed>|null */
    private static function ai_narrative(array $setup, array $premium_apps, array $summary_cards) {
        if (!class_exists('DG_AI_Assist') || !DG_AI_Assist::available()) {
            return null;
        }

        $user_id = get_current_user_id();
        $cache_key = 'dg_ai_report_narr_' . $user_id . '_' . wp_date('Y-m-d');
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $context = [
            'setup_percent' => $setup['percent'] ?? 0,
            'summary_cards' => $summary_cards,
            'premium_apps' => array_map(function ($app) {
                return [
                    'title' => $app['title'] ?? '',
                    'headline' => $app['headline'] ?? '',
                    'percent' => $app['percent'] ?? 0,
                ];
            }, $premium_apps),
        ];

        $result = DG_AI_Assist::reports_narrative($context);
        if (is_wp_error($result)) {
            return null;
        }

        set_transient($cache_key, $result, DAY_IN_SECONDS);
        return $result;
    }

    /** @param array<int,array<string,mixed>> $sections */
    private static function summary_cards(array $sections, array $setup, array $premium_apps = []) {
        $cards = [
            [
                'label' => 'Platform setup',
                'value' => (string) ($setup['percent'] ?? 0),
                'suffix' => '%',
                'icon' => 'fa-route',
                'color' => '#3B82F6',
            ],
        ];

        foreach ($premium_apps as $app) {
            if (empty($app['percent'])) {
                continue;
            }
            $cards[] = [
                'label' => ($app['title'] ?? 'Premium') . ' · ' . ($app['critical_value'] ?? 'Score'),
                'value' => (string) ($app['percent'] ?? 0),
                'suffix' => !empty($app['percent_suffix']) ? (string) $app['percent_suffix'] : '%',
                'icon' => (string) ($app['icon'] ?? 'fa-star'),
                'color' => (string) ($app['color'] ?? '#8B5CF6'),
            ];
            if (count($cards) >= 4) {
                break;
            }
        }

        foreach ($sections as $section) {
            if (empty($section['stats'][0])) {
                continue;
            }
            $stat = $section['stats'][0];
            $cards[] = [
                'label' => $section['title'] . ' · ' . $stat['label'],
                'value' => (string) $stat['value'],
                'suffix' => (string) ($stat['suffix'] ?? ''),
                'icon' => (string) ($section['icon'] ?? 'fa-chart-line'),
                'color' => (string) ($section['color'] ?? '#3B82F6'),
            ];
            if (count($cards) >= 6) {
                break;
            }
        }

        return $cards;
    }

    /** @param array<int,array<string,mixed>> $premium_apps @return array<int,array<string,mixed>> */
    private static function collect_charts_from_premium(array $premium_apps) {
        $charts = [];
        foreach ($premium_apps as $app) {
            foreach ($app['charts'] ?? [] as $chart) {
                if (is_array($chart) && !empty($chart['id'])) {
                    $charts[] = $chart;
                }
            }
        }
        return $charts;
    }

    /** @return array<int,array<string,mixed>> */
    private static function preview_premium_apps() {
        return [
            self::wrap_premium_app([
                'id' => 'ai_visibility_pro',
                'title' => 'AI Visibility Pro',
                'icon' => 'fa-robot',
                'color' => '#8B5CF6',
                'headline' => '72/100 AI visibility',
                'grade' => 'B',
                'percent' => 72,
                'percent_suffix' => '/100',
                'status' => 'good',
                'status_label' => 'Healthy',
                'critical_value' => 'Combined AI score',
                'stats' => [
                    ['label' => 'ChatGPT visibility', 'value' => '74', 'suffix' => '/100'],
                    ['label' => 'Gemini visibility', 'value' => '69', 'suffix' => '/100'],
                    ['label' => 'Technical readiness', 'value' => '73', 'suffix' => '/100'],
                ],
                'highlights' => ['3 scans in the last 30 days', 'Grade B — room to improve Gemini citations'],
                'charts' => [self::make_chart('preview-ai-score', 'AI visibility trend', 'doughnut', ['Visible', 'Gap'], [72, 28], '72%')],
            ]),
            self::wrap_premium_app([
                'id' => 'automation_pro',
                'title' => 'Automation Pro',
                'icon' => 'fa-bolt',
                'color' => '#F59E0B',
                'headline' => '94% automation success',
                'grade' => 'A',
                'percent' => 94,
                'status' => 'good',
                'status_label' => 'Running smoothly',
                'critical_value' => 'Success rate',
                'stats' => [
                    ['label' => 'Runs (30 days)', 'value' => '128', 'suffix' => ''],
                    ['label' => 'Completed', 'value' => '120', 'suffix' => ''],
                    ['label' => 'Failed', 'value' => '8', 'suffix' => ''],
                ],
                'highlights' => ['Lead nurture sequences active', 'Zero pending failures'],
                'charts' => [],
            ]),
            self::wrap_premium_app([
                'id' => 'analytics_pro',
                'title' => 'Analytics Pro',
                'icon' => 'fa-chart-area',
                'color' => '#3B82F6',
                'headline' => 'Growth tracking active',
                'grade' => 'A',
                'percent' => 100,
                'status' => 'good',
                'status_label' => 'Collecting data',
                'critical_value' => 'Metrics tracked',
                'stats' => [
                    ['label' => 'Daily snapshots', 'value' => '18', 'suffix' => ' KPIs'],
                    ['label' => 'Modules covered', 'value' => '4', 'suffix' => ''],
                ],
                'highlights' => ['30-day trend lines available below'],
                'charts' => [],
            ]),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function live_premium_apps() {
        if (!class_exists('DG_Plan_Registry')) {
            return [];
        }

        $apps = [];
        $builders = [
            'ai_visibility_pro' => 'premium_ai_visibility',
            'seo_pro' => 'premium_seo',
            'automation_pro' => 'premium_automation',
            'analytics_pro' => 'premium_analytics',
            'social_pro' => 'premium_social',
        ];

        foreach ($builders as $key => $method) {
            if (!DG_Plan_Registry::has_premium_app($key)) {
                continue;
            }
            $app = self::$method();
            if ($app) {
                $apps[] = $app;
            }
        }

        return apply_filters('dg_client_reports_premium_apps', $apps);
    }

    /** @param array<string,mixed> $app @return array<string,mixed> */
    private static function wrap_premium_app(array $app) {
        $percent = (int) ($app['percent'] ?? 0);
        if (empty($app['status'])) {
            $app['status'] = $percent >= 75 ? 'good' : ($percent >= 50 ? 'attention' : 'critical');
        }
        if (empty($app['status_label'])) {
            $labels = ['good' => 'Healthy', 'attention' => 'Needs attention', 'critical' => 'Priority action'];
            $app['status_label'] = $labels[$app['status']] ?? 'Active';
        }
        return $app;
    }

    /** @return array<string,mixed>|null */
    private static function premium_ai_visibility() {
        if (!class_exists('DG_AI_Visibility_History')) {
            return null;
        }
        $avg = DG_AI_Visibility_History::averages(30);
        $latest = DG_AI_Visibility_History::latest();
        $combined = (int) round((float) ($avg['combined_avg'] ?? 0));
        if ($combined <= 0 && $latest) {
            $combined = (int) ($latest->combined_score ?? 0);
        }

        $charts = [];
        $trend = self::ai_visibility_score_history(30);
        if (count($trend) >= 2) {
            $charts[] = self::make_line_chart('premium-ai-trend', 'AI visibility (30 days)', $trend, '#8B5CF6');
        }

        return self::wrap_premium_app([
            'id' => 'ai_visibility_pro',
            'title' => 'AI Visibility Pro',
            'icon' => 'fa-robot',
            'color' => '#8B5CF6',
            'headline' => $combined > 0 ? $combined . '/100 AI visibility' : 'Awaiting first scan',
            'grade' => $latest ? (string) ($latest->grade ?? '') : '',
            'percent' => min(100, $combined),
            'percent_suffix' => '/100',
            'status' => $combined >= 75 ? 'good' : ($combined >= 50 ? 'attention' : 'critical'),
            'critical_value' => 'Combined AI score',
            'stats' => [
                ['label' => 'ChatGPT visibility', 'value' => (string) round((float) ($avg['openai_avg'] ?? 0)), 'suffix' => '/100'],
                ['label' => 'Gemini visibility', 'value' => (string) round((float) ($avg['gemini_avg'] ?? 0)), 'suffix' => '/100'],
                ['label' => 'Technical readiness', 'value' => (string) round((float) ($avg['technical_avg'] ?? 0)), 'suffix' => '/100'],
                ['label' => 'Scans (30 days)', 'value' => self::format_number($avg['scans'] ?? 0), 'suffix' => ''],
            ],
            'highlights' => self::ai_visibility_highlights($combined, (int) ($avg['scans'] ?? 0), $latest),
            'charts' => $charts,
        ]);
    }

    /** @return array<int,string> */
    private static function ai_visibility_highlights($combined, $scans, $latest) {
        $highlights = [];
        if ($scans > 0) {
            $highlights[] = $scans . ' scan' . ($scans === 1 ? '' : 's') . ' in the last 30 days';
        }
        if ($combined >= 75) {
            $highlights[] = 'Strong AI discoverability across models';
        } elseif ($combined >= 50) {
            $highlights[] = 'Improve structured data and citation-ready content';
        } elseif ($combined > 0) {
            $highlights[] = 'Priority: run a fresh scan and action recommendations';
        }
        if ($latest && !empty($latest->grade)) {
            $highlights[] = 'Latest grade: ' . $latest->grade;
        }
        return $highlights;
    }

    /** @return array<int,object> */
    private static function ai_visibility_score_history($days = 30) {
        global $wpdb;
        if (!class_exists('DG_AI_Visibility_History')) {
            return [];
        }
        DG_AI_Visibility_History::ensure_table();
        $host = class_exists('DG_Site_Profile') ? DG_Site_Profile::hostname() : parse_url(home_url(), PHP_URL_HOST);
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) AS snapshot_date, AVG(combined_score) AS metric_value
             FROM " . DG_AI_Visibility_History::table() . "
             WHERE site_host = %s AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY DATE(created_at)
             ORDER BY snapshot_date ASC",
            $host,
            (int) $days
        ));
    }

    /** @return array<string,mixed>|null */
    private static function premium_automation() {
        if (!class_exists('DG_Automation_Pro_Workflows')) {
            return null;
        }
        $stats = DG_Automation_Pro_Workflows::log_stats(30);
        $total = (int) ($stats['total'] ?? 0);
        $completed = (int) ($stats['completed'] ?? 0);
        $rate = $total > 0 ? (int) round(100 * $completed / $total) : 0;

        return self::wrap_premium_app([
            'id' => 'automation_pro',
            'title' => 'Automation Pro',
            'icon' => 'fa-bolt',
            'color' => '#F59E0B',
            'headline' => $total > 0 ? $rate . '% automation success' : 'No runs yet this month',
            'grade' => $rate >= 90 ? 'A' : ($rate >= 70 ? 'B' : 'C'),
            'percent' => $rate,
            'critical_value' => 'Success rate',
            'stats' => [
                ['label' => 'Runs (30 days)', 'value' => self::format_number($total), 'suffix' => ''],
                ['label' => 'Completed', 'value' => self::format_number($completed), 'suffix' => ''],
                ['label' => 'Failed', 'value' => self::format_number($stats['failed'] ?? 0), 'suffix' => ''],
                ['label' => 'Pending', 'value' => self::format_number($stats['pending'] ?? 0), 'suffix' => ''],
            ],
            'highlights' => $total > 0
                ? ['Automations executed ' . self::format_number($total) . ' times in 30 days', ($stats['failed'] ?? 0) > 0 ? 'Review failed runs in Automation Pro' : 'All recent runs completed successfully']
                : ['Workflows will appear once triggers fire'],
            'charts' => $total > 0 ? [self::make_chart('premium-automation', 'Run outcomes (30 days)', 'doughnut', ['Completed', 'Failed', 'Pending'], [$completed, (int) ($stats['failed'] ?? 0), (int) ($stats['pending'] ?? 0)], $rate . '%')] : [],
        ]);
    }

    /** @return array<string,mixed>|null */
    private static function premium_analytics() {
        $metric_count = 0;
        if (class_exists('DG_Analytics_Pro_Collector')) {
            $metric_count = count(DG_Analytics_Pro_Collector::collect());
        }

        return self::wrap_premium_app([
            'id' => 'analytics_pro',
            'title' => 'Analytics Pro',
            'icon' => 'fa-chart-area',
            'color' => '#3B82F6',
            'headline' => $metric_count > 0 ? $metric_count . ' KPIs tracked daily' : 'Analytics collecting',
            'grade' => 'A',
            'percent' => min(100, max(10, $metric_count * 5)),
            'critical_value' => 'KPI coverage',
            'stats' => [
                ['label' => 'Metrics tracked', 'value' => self::format_number($metric_count), 'suffix' => ''],
                ['label' => 'Trend window', 'value' => '30', 'suffix' => ' days'],
            ],
            'highlights' => ['Daily snapshots power the trend charts below', 'Cross-module KPIs in one view'],
            'charts' => [],
        ]);
    }

    /** @return array<string,mixed>|null */
    private static function premium_seo() {
        $optimized = 0;
        $total = 0;
        $avg_score = 0;
        if (class_exists('DG_SEO_Analyzer') && class_exists('DG_SEO_Settings')) {
            $pages = DG_SEO_Analyzer::list_pages();
            $total = count($pages);
            $score_total = 0;
            $scored = 0;
            foreach ($pages as $page) {
                $post_id = (int) ($page['id'] ?? (is_object($page) ? $page->ID : 0));
                if (!$post_id) {
                    continue;
                }
                $seo = DG_SEO_Settings::get_post_seo_stored($post_id);
                if ($seo['title'] !== '' && $seo['description'] !== '') {
                    $optimized++;
                }
                if (class_exists('DG_SEO_Analyzer')) {
                    try {
                        $analysis = DG_SEO_Analyzer::analyze($post_id);
                        if (!empty($analysis['score'])) {
                            $score_total += (int) $analysis['score'];
                            $scored++;
                        }
                    } catch (Throwable $e) {
                        // ignore per-page analysis errors
                    }
                }
            }
            $avg_score = $scored > 0 ? (int) round($score_total / $scored) : 0;
        }
        $rate = $total > 0 ? (int) round(100 * $optimized / $total) : 0;

        return self::wrap_premium_app([
            'id' => 'seo_pro',
            'title' => 'SEO Pro',
            'icon' => 'fa-search',
            'color' => '#10B981',
            'headline' => $total > 0 ? $rate . '% pages SEO-ready' : 'SEO Pro active',
            'grade' => $avg_score >= 80 ? 'A' : ($avg_score >= 60 ? 'B' : 'C'),
            'percent' => $rate,
            'critical_value' => 'Pages optimized',
            'stats' => [
                ['label' => 'Pages tracked', 'value' => self::format_number($total), 'suffix' => ''],
                ['label' => 'SEO-ready pages', 'value' => self::format_number($optimized), 'suffix' => ''],
                ['label' => 'Avg. page score', 'value' => (string) $avg_score, 'suffix' => '/100'],
            ],
            'highlights' => $total > 0
                ? [$optimized . ' of ' . $total . ' pages have title and meta description', 'Sitemap and schema managed by SEO Pro']
                : ['Add content pages to begin SEO scoring'],
            'charts' => $total > 0 ? [self::make_chart('premium-seo', 'SEO readiness', 'doughnut', ['Optimized', 'Needs work'], [$optimized, max(0, $total - $optimized)], $rate . '%')] : [],
        ]);
    }

    /** @return array<string,mixed>|null */
    private static function premium_social() {
        $counts = self::social_post_counts();
        $published = (int) ($counts['published'] ?? 0);
        $scheduled = (int) ($counts['scheduled'] ?? 0);
        $drafts = (int) ($counts['draft'] ?? 0);
        $total = $published + $scheduled + $drafts;
        $rate = $total > 0 ? (int) round(100 * $published / $total) : 0;

        return self::wrap_premium_app([
            'id' => 'social_pro',
            'title' => 'Social Pro',
            'icon' => 'fa-share-alt',
            'color' => '#EC4899',
            'headline' => $total > 0 ? $published . ' posts published' : 'Social Pro ready',
            'grade' => $rate >= 70 ? 'A' : 'B',
            'percent' => $rate,
            'critical_value' => 'Published rate',
            'stats' => [
                ['label' => 'Published', 'value' => self::format_number($published), 'suffix' => ''],
                ['label' => 'Scheduled', 'value' => self::format_number($scheduled), 'suffix' => ''],
                ['label' => 'Drafts', 'value' => self::format_number($drafts), 'suffix' => ''],
            ],
            'highlights' => $scheduled > 0
                ? [$scheduled . ' posts scheduled — queue looks healthy', 'Multi-platform publishing from one workspace']
                : ['Compose and schedule posts in Social Pro'],
            'charts' => $total > 0 ? [self::make_chart('premium-social', 'Content pipeline', 'bar', ['Published', 'Scheduled', 'Drafts'], [$published, $scheduled, $drafts])] : [],
        ]);
    }

    /** @return array<string,int> */
    private static function social_post_counts() {
        if (!class_exists('DG_Social_Pro_Posts')) {
            return [];
        }
        DG_Social_Pro_Posts::ensure_table();
        global $wpdb;
        $table = DG_Social_Pro_Posts::table();
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return [];
        }
        $rows = $wpdb->get_results("SELECT status, COUNT(*) AS total FROM $table GROUP BY status");
        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row->status] = (int) $row->total;
        }
        return $counts;
    }

    /** @return array<int,array<string,mixed>> */
    private static function preview_trend_charts() {
        $labels = [];
        $values = [];
        for ($i = 29; $i >= 0; $i--) {
            $labels[] = wp_date('j M', strtotime('-' . $i . ' days'));
            $values[] = 40 + ($i % 7) * 3 + (29 - $i);
        }
        return [
            self::make_line_chart('preview-trend-contacts', 'Contacts (30 days)', self::rows_from_series($labels, $values), '#3B82F6'),
            self::make_line_chart('preview-trend-activity', 'Platform activity (30 days)', self::rows_from_series($labels, array_map(function ($v) {
                return (int) round($v * 0.6);
            }, $values)), '#10B981'),
        ];
    }

    /** @param array<int,string> $labels @param array<int,int|float> $values @return array<int,object> */
    private static function rows_from_series(array $labels, array $values) {
        $rows = [];
        foreach ($labels as $i => $label) {
            $rows[] = (object) [
                'snapshot_date' => $label,
                'metric_value' => $values[$i] ?? 0,
            ];
        }
        return $rows;
    }

    /** @param array<int,array<string,mixed>> $sections @return array<int,array<string,mixed>> */
    private static function build_trend_charts(array $sections, $days = 30) {
        if (!class_exists('DG_Analytics_Pro_Snapshots')) {
            return self::build_fallback_trend_charts($sections, $days);
        }

        $keys = self::trend_metric_keys($sections);
        $charts = [];
        foreach ($keys as $key => $meta) {
            $history = DG_Analytics_Pro_Snapshots::history($key, $days);
            if (count($history) < 2) {
                continue;
            }
            $charts[] = self::make_line_chart('trend-' . $key, $meta['title'], $history, $meta['color']);
        }

        if (class_exists('DG_Plan_Registry') && DG_Plan_Registry::has_premium_app('ai_visibility_pro')) {
            $ai = self::ai_visibility_score_history($days);
            if (count($ai) >= 2) {
                $charts[] = self::make_line_chart('trend-ai-visibility', 'AI visibility score (30 days)', $ai, '#8B5CF6');
            }
        }

        return $charts;
    }

    /** @param array<int,array<string,mixed>> $sections @return array<int,array<string,mixed>> */
    private static function build_fallback_trend_charts(array $sections, $days = 30) {
        $charts = [];
        $ai = self::ai_visibility_score_history($days);
        if (count($ai) >= 2 && class_exists('DG_Plan_Registry') && DG_Plan_Registry::has_premium_app('ai_visibility_pro')) {
            $charts[] = self::make_line_chart('trend-ai-visibility', 'AI visibility score (30 days)', $ai, '#8B5CF6');
        }
        return $charts;
    }

    /** @param array<int,array<string,mixed>> $sections @return array<string,array{title:string,color:string}> */
    private static function trend_metric_keys(array $sections) {
        $keys = [
            'core_contacts' => ['title' => 'Contacts (30 days)', 'color' => '#3B82F6'],
            'core_tasks_completed' => ['title' => 'Tasks completed (30 days)', 'color' => '#10B981'],
        ];

        foreach ($sections as $section) {
            $id = (string) ($section['id'] ?? '');
            if ($id === 'marketing') {
                $keys['mkt_clients'] = ['title' => 'Marketing clients (30 days)', 'color' => '#8B5CF6'];
            }
            if ($id === 'real-estate') {
                $keys['re_vendor_leads'] = ['title' => 'Vendor leads (30 days)', 'color' => '#10B981'];
            }
            if ($id === 'accommodation') {
                $keys['acc_upcoming_30d'] = ['title' => 'Upcoming stays (30 days)', 'color' => '#06B6D4'];
            }
        }

        return $keys;
    }

    /** @param array<int,object> $history_rows @return array<string,mixed> */
    private static function make_line_chart($id, $title, array $history_rows, $color = '#3B82F6') {
        $labels = [];
        $values = [];
        foreach ($history_rows as $row) {
            $date = (string) ($row->snapshot_date ?? '');
            $labels[] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? wp_date('j M', strtotime($date)) : $date;
            $values[] = (float) ($row->metric_value ?? 0);
        }

        return [
            'id' => $id,
            'title' => $title,
            'type' => 'line',
            'labels' => $labels,
            'values' => $values,
            'colors' => [$color],
        ];
    }

    /** @param array<int,array<string,mixed>> $sections @return array<int,array<string,mixed>> */
    private static function collect_charts(array $sections) {
        $charts = [];
        foreach ($sections as $section) {
            foreach ($section['charts'] ?? [] as $chart) {
                if (is_array($chart) && !empty($chart['id'])) {
                    $charts[] = $chart;
                }
            }
        }
        return $charts;
    }

    private static function format_number($value) {
        return number_format_i18n((int) $value);
    }

    private static function format_currency($value) {
        if (function_exists('wc_price')) {
            return wp_strip_all_tags(wc_price((float) $value));
        }
        return '$' . number_format_i18n((float) $value, 0);
    }
}
