<?php
/**
 * Universal search across DG Platform entities.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Search {

    public static function query($term, $limit = 20) {
        global $wpdb;
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $like = '%' . $wpdb->esc_like($term) . '%';
        $results = [];

        $contacts = $wpdb->get_results($wpdb->prepare(
            "SELECT id, first_name, last_name, email, phone, source
             FROM {$wpdb->prefix}dg_contacts
             WHERE first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s
             ORDER BY updated_at DESC LIMIT %d",
            $like, $like, $like, $like, $limit
        ));
        foreach ($contacts as $row) {
            $results[] = [
                'type' => 'contact',
                'id' => (int) $row->id,
                'title' => trim($row->first_name . ' ' . $row->last_name),
                'subtitle' => (strpos($row->email, '@leads.roerealty.local') === false ? $row->email : '') ?: $row->phone,
                'url' => admin_url('admin.php?page=dg-platform-contacts&action=edit&id=' . $row->id),
            ];
        }

        if (post_type_exists('property')) {
            $properties = get_posts([
                'post_type' => 'property',
                'post_status' => 'publish',
                's' => $term,
                'posts_per_page' => $limit,
            ]);
            foreach ($properties as $post) {
                $results[] = [
                    'type' => 'property',
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'subtitle' => get_post_meta($post->ID, 'roe_property_suburb', true),
                    'url' => get_edit_post_link($post->ID, 'raw'),
                ];
            }
        }

        if (class_exists('DG_RE_Vendor_Leads')) {
            $table = DG_RE_Vendor_Leads::leads_table();
            $vendor_leads = $wpdb->get_results($wpdb->prepare(
                "SELECT l.id, l.property_address, l.source, c.first_name, c.last_name
                 FROM $table l
                 LEFT JOIN {$wpdb->prefix}dg_contacts c ON l.contact_id = c.id
                 WHERE l.property_address LIKE %s OR c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s
                 ORDER BY l.created_at DESC LIMIT %d",
                $like, $like, $like, $like, $limit
            ));
            foreach ($vendor_leads as $row) {
                $results[] = [
                    'type' => 'vendor_lead',
                    'id' => (int) $row->id,
                    'title' => $row->property_address ?: trim($row->first_name . ' ' . $row->last_name),
                    'subtitle' => 'Vendor lead · ' . str_replace('_', ' ', $row->source),
                    'url' => admin_url('admin.php?page=dg-re-vendor-lead&id=' . $row->id),
                ];
            }
        }

        if (class_exists('DG_RE_Buyer_Leads')) {
            $table = DG_RE_Buyer_Leads::buyers_table();
            $buyers = $wpdb->get_results($wpdb->prepare(
                "SELECT b.id, b.requirements, c.first_name, c.last_name
                 FROM $table b
                 LEFT JOIN {$wpdb->prefix}dg_contacts c ON b.contact_id = c.id
                 WHERE b.requirements LIKE %s OR c.first_name LIKE %s OR c.last_name LIKE %s
                 ORDER BY b.created_at DESC LIMIT %d",
                $like, $like, $like, $limit
            ));
            foreach ($buyers as $row) {
                $results[] = [
                    'type' => 'buyer_lead',
                    'id' => (int) $row->id,
                    'title' => trim($row->first_name . ' ' . $row->last_name) ?: 'Buyer enquiry',
                    'subtitle' => wp_trim_words($row->requirements, 12),
                    'url' => admin_url('admin.php?page=dg-re-buyer-lead&id=' . $row->id),
                ];
            }
        }

        if (class_exists('DG_Marketing_Clients')) {
            $clients = $wpdb->get_results($wpdb->prepare(
                'SELECT id, company_name, email, website, status FROM ' . DG_Marketing_Clients::companies_table() .
                ' WHERE company_name LIKE %s OR email LIKE %s OR website LIKE %s ORDER BY created_at DESC LIMIT %d',
                $like, $like, $like, $limit
            ));
            foreach ($clients as $row) {
                $results[] = [
                    'type' => 'agency_client',
                    'id' => (int) $row->id,
                    'title' => $row->company_name,
                    'subtitle' => 'Agency client · ' . ucfirst($row->status),
                    'url' => admin_url('admin.php?page=dg-platform-clients&client_id=' . $row->id . '&tab=view'),
                ];
            }

            $audits = $wpdb->prefix . 'dg_platform_audits';
            if ($wpdb->get_var("SHOW TABLES LIKE '$audits'") === $audits) {
                $audit_rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT a.id, a.overall_score, a.grade, c.company_name
                     FROM $audits a
                     LEFT JOIN " . DG_Marketing_Clients::companies_table() . " c ON c.id = a.company_id
                     WHERE c.company_name LIKE %s OR a.grade LIKE %s
                     ORDER BY a.audit_date DESC LIMIT %d",
                    $like, $like, $limit
                ));
                foreach ($audit_rows as $row) {
                    $results[] = [
                        'type' => 'visibility_audit',
                        'id' => (int) $row->id,
                        'title' => ($row->company_name ?: 'Audit') . ' — ' . $row->overall_score . '%',
                        'subtitle' => 'Grade ' . $row->grade,
                        'url' => admin_url('admin.php?page=dg-platform-audits'),
                    ];
                }
            }

            $voice = $wpdb->prefix . 'dg_platform_voice_logs';
            if ($wpdb->get_var("SHOW TABLES LIKE '$voice'") === $voice) {
                $voice_rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT v.id, v.lead_score, v.call_summary, c.company_name
                     FROM $voice v
                     LEFT JOIN " . DG_Marketing_Clients::companies_table() . " c ON c.id = v.company_id
                     WHERE v.call_summary LIKE %s OR c.company_name LIKE %s
                     ORDER BY v.created_at DESC LIMIT %d",
                    $like, $like, $limit
                ));
                foreach ($voice_rows as $row) {
                    $results[] = [
                        'type' => 'voice_lead',
                        'id' => (int) $row->id,
                        'title' => ($row->company_name ?: 'Voice lead') . ' — ' . $row->lead_score . '/100',
                        'subtitle' => wp_trim_words($row->call_summary, 10),
                        'url' => admin_url('admin.php?page=dg-platform-voice'),
                    ];
                }
            }
        }

        return array_slice($results, 0, $limit);
    }
}
