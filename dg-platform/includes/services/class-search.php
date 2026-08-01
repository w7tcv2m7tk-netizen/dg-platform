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

        return array_slice($results, 0, $limit);
    }
}
