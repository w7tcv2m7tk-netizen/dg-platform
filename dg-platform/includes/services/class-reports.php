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

    public static function get_dashboard_stats() {
        return [
            'organisations' => DG_Organisations::count(),
            'contacts' => DG_Contacts::count(),
            'tasks_pending' => DG_Tasks::count('pending'),
            'tasks_completed' => DG_Tasks::count('completed'),
            'calendar_upcoming' => DG_Calendar::count_upcoming(),
            'activities' => DG_Activities::count(),
            'documents' => DG_Documents::count(),
        ];
    }

    public static function get_module_widgets() {
        $widgets = [
            [
                'id' => 'core_contacts',
                'label' => 'Contacts',
                'value' => DG_Contacts::count(),
                'color' => '#1565C0',
            ],
            [
                'id' => 'core_tasks',
                'label' => 'Pending Tasks',
                'value' => DG_Tasks::count('pending'),
                'color' => '#F57C00',
            ],
            [
                'id' => 'core_calendar',
                'label' => 'Upcoming Events',
                'value' => DG_Calendar::count_upcoming(),
                'color' => '#7B1FA2',
            ],
        ];

        return apply_filters('dg_platform_dashboard_widgets', $widgets);
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
