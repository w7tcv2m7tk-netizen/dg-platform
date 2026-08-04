<?php
/**
 * Collect KPIs from core and active modules.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Analytics_Pro_Collector {

    /** @return array<string,array{value:float,module:string}> */
    public static function collect() {
        $metrics = [];

        $stats = DG_Reports::get_dashboard_stats();
        foreach ($stats as $key => $value) {
            $metrics['core_' . $key] = ['value' => (float) $value, 'module' => 'core'];
        }

        if (class_exists('DG_RE_Pipeline_Reports')) {
            $metrics['re_bookings_month'] = ['value' => (float) DG_RE_Pipeline_Reports::bookings_this_month(), 'module' => 'real-estate'];
            $metrics['re_property_reports_month'] = ['value' => (float) DG_RE_Pipeline_Reports::property_reports_this_month(), 'module' => 'real-estate'];
            $vendor = DG_RE_Pipeline_Reports::vendor_conversion_summary();
            $metrics['re_vendor_leads'] = ['value' => (float) ($vendor['total'] ?? 0), 'module' => 'real-estate'];
            $metrics['re_vendor_conversion_rate'] = ['value' => (float) ($vendor['rate'] ?? 0), 'module' => 'real-estate'];
        }

        if (class_exists('DG_Acc_Reports')) {
            $acc = DG_Acc_Reports::summary();
            $metrics['acc_properties'] = ['value' => (float) ($acc['properties'] ?? 0), 'module' => 'accommodation'];
            $metrics['acc_upcoming_30d'] = ['value' => (float) ($acc['upcoming_30d'] ?? 0), 'module' => 'accommodation'];
            $metrics['acc_revenue_month'] = ['value' => (float) ($acc['revenue_month'] ?? 0), 'module' => 'accommodation'];
            $metrics['acc_guests'] = ['value' => (float) ($acc['guests'] ?? 0), 'module' => 'accommodation'];
        }

        if (class_exists('DG_Marketing_Pipeline_Reports')) {
            $metrics['mkt_audits_month'] = ['value' => (float) DG_Marketing_Pipeline_Reports::audits_this_month(), 'module' => 'marketing'];
            $metrics['mkt_voice_leads_month'] = ['value' => (float) DG_Marketing_Pipeline_Reports::voice_leads_this_month(), 'module' => 'marketing'];
            $conv = DG_Marketing_Pipeline_Reports::client_conversion_summary();
            $metrics['mkt_clients'] = ['value' => (float) ($conv['total'] ?? 0), 'module' => 'marketing'];
            $metrics['mkt_client_conversion_rate'] = ['value' => (float) ($conv['rate'] ?? 0), 'module' => 'marketing'];
        }

        if (class_exists('DG_AI_Visibility_History')) {
            $avg = DG_AI_Visibility_History::averages(30);
            $metrics['ai_visibility_score'] = ['value' => (float) ($avg['combined_avg'] ?? 0), 'module' => 'ai-visibility'];
        }

        if (class_exists('DG_Automation_Pro_Workflows')) {
            $auto = DG_Automation_Pro_Workflows::log_stats(30);
            $metrics['automation_runs'] = ['value' => (float) ($auto['total'] ?? 0), 'module' => 'automation'];
            $metrics['automation_success_rate'] = [
                'value' => ($auto['total'] ?? 0) > 0 ? round(100 * ($auto['completed'] ?? 0) / $auto['total'], 1) : 0,
                'module' => 'automation',
            ];
        }

        return apply_filters('dg_analytics_pro/metrics', $metrics);
    }

    /** @return array<int,array<string,mixed>> */
    public static function export_rows($module = null) {
        $metrics = self::collect();
        $rows = [];
        foreach ($metrics as $key => $row) {
            if ($module && ($row['module'] ?? '') !== $module) {
                continue;
            }
            $rows[] = [
                'metric' => $key,
                'label' => ucwords(str_replace('_', ' ', $key)),
                'value' => $row['value'],
                'module' => $row['module'],
                'date' => current_time('Y-m-d'),
            ];
        }
        return $rows;
    }
}
