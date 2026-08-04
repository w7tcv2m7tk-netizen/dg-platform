<?php
/**
 * Reporting framework.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Reports {

    private static function safe_count(callable $fn) {
        try {
            return (int) call_user_func($fn);
        } catch (Throwable $e) {
            return 0;
        }
    }

    public static function get_dashboard_stats() {
        if (class_exists('DG_Activator')) {
            DG_Activator::maybe_upgrade_schema();
        }
        return [
            'organisations' => self::safe_count([DG_Organisations::class, 'count']),
            'contacts' => self::safe_count([DG_Contacts::class, 'count']),
            'tasks_pending' => self::safe_count(function () {
                return DG_Tasks::count('pending');
            }),
            'tasks_completed' => self::safe_count(function () {
                return DG_Tasks::count('completed');
            }),
            'calendar_upcoming' => self::safe_count([DG_Calendar::class, 'count_upcoming']),
            'activities' => self::safe_count([DG_Activities::class, 'count']),
            'documents' => self::safe_count([DG_Documents::class, 'count']),
        ];
    }

    public static function get_module_widgets() {
        $widgets = [
            [
                'id' => 'core_contacts',
                'label' => 'Contacts',
                'value' => self::safe_count([DG_Contacts::class, 'count']),
                'color' => '#1565C0',
            ],
            [
                'id' => 'core_tasks',
                'label' => 'Pending Tasks',
                'value' => self::safe_count(function () {
                    return DG_Tasks::count('pending');
                }),
                'color' => '#F57C00',
            ],
            [
                'id' => 'core_calendar',
                'label' => 'Upcoming Events',
                'value' => self::safe_count([DG_Calendar::class, 'count_upcoming']),
                'color' => '#7B1FA2',
            ],
        ];

        try {
            return apply_filters('dg_platform_dashboard_widgets', $widgets);
        } catch (Throwable $e) {
            return $widgets;
        }
    }

    public static function export_csv($rows, $filename = 'report.csv') {
        if (empty($rows)) {
            return false;
        }
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fputcsv($out, array_keys((array) $rows[0]));
        foreach ($rows as $row) {
            fputcsv($out, (array) $row);
        }
        fclose($out);
        exit;
    }
}
