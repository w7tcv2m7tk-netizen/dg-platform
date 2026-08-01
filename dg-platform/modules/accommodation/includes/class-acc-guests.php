<?php
/**
 * Guest CRM — dg_guest posts synced to core contacts.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Acc_Guests {

    public static function post_type() {
        return 'dg_guest';
    }

    public static function sync_from_booking($booking_id) {
        $booking_id = (int) $booking_id;
        $email = sanitize_email(get_post_meta($booking_id, 'dg_booking_email', true));
        if ($email === '') {
            return 0;
        }

        $name = sanitize_text_field(get_post_meta($booking_id, 'dg_booking_name', true));
        $phone = sanitize_text_field(get_post_meta($booking_id, 'dg_booking_phone', true));
        $total = (float) get_post_meta($booking_id, 'dg_booking_total', true);
        $checkin = get_post_meta($booking_id, 'dg_booking_checkin', true);
        $checkout = get_post_meta($booking_id, 'dg_booking_checkout', true);
        $source = sanitize_text_field(get_post_meta($booking_id, 'dg_booking_source', true) ?: 'direct');

        $existing = get_posts([
            'post_type' => self::post_type(),
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [['key' => 'dg_guest_email', 'value' => $email]],
        ]);

        if ($existing) {
            $guest_id = (int) $existing[0];
        } else {
            $guest_id = wp_insert_post([
                'post_type' => self::post_type(),
                'post_title' => $name ?: $email,
                'post_status' => 'publish',
            ]);
            if (is_wp_error($guest_id) || !$guest_id) {
                return 0;
            }
            update_post_meta($guest_id, 'dg_guest_email', $email);
            update_post_meta($guest_id, 'dg_guest_source', $source);
        }

        if ($phone) {
            update_post_meta($guest_id, 'dg_guest_phone', $phone);
        }

        $nights = ($checkin && $checkout) ? max(0, (int) round((strtotime($checkout) - strtotime($checkin)) / 86400)) : 0;
        update_post_meta($guest_id, 'dg_guest_total_stays', (int) get_post_meta($guest_id, 'dg_guest_total_stays', true) + 1);
        update_post_meta($guest_id, 'dg_guest_total_nights', (int) get_post_meta($guest_id, 'dg_guest_total_nights', true) + $nights);
        update_post_meta($guest_id, 'dg_guest_total_spent', (float) get_post_meta($guest_id, 'dg_guest_total_spent', true) + $total);
        if ($checkin) {
            update_post_meta($guest_id, 'dg_guest_last_stay', $checkin);
        }
        update_post_meta($booking_id, 'dg_booking_guest_id', $guest_id);

        self::sync_to_core($guest_id);
        return (int) $guest_id;
    }

    public static function sync_to_core($guest_id) {
        if (!class_exists('DG_Contacts')) {
            return 0;
        }

        $guest_id = (int) $guest_id;
        $post = get_post($guest_id);
        if (!$post || $post->post_type !== self::post_type()) {
            return 0;
        }

        $email = sanitize_email(get_post_meta($guest_id, 'dg_guest_email', true));
        if ($email === '') {
            return 0;
        }

        $parts = self::split_name($post->post_title);
        $row = [
            'first_name' => $parts['first_name'],
            'last_name' => $parts['last_name'],
            'email' => $email,
            'phone' => sanitize_text_field(get_post_meta($guest_id, 'dg_guest_phone', true)),
            'source' => sanitize_text_field(get_post_meta($guest_id, 'dg_guest_source', true) ?: 'accommodation'),
            'status' => 'active',
            'notes' => sanitize_textarea_field(get_post_meta($guest_id, 'dg_guest_notes', true)),
            'legacy_table' => 'dg_guest',
            'legacy_id' => $guest_id,
        ];

        $existing = DG_Contacts::get_by_legacy('dg_guest', $guest_id);
        if ($existing) {
            DG_Contacts::update((int) $existing->id, $row);
            $contact_id = (int) $existing->id;
        } else {
            $by_email = method_exists('DG_Contacts', 'get_by_email')
                ? DG_Contacts::get_by_email($email)
                : null;
            if ($by_email) {
                DG_Contacts::update((int) $by_email->id, $row);
                $contact_id = (int) $by_email->id;
            } else {
                $created = DG_Contacts::create($row);
                $contact_id = is_wp_error($created) ? 0 : (int) $created;
            }
        }

        if ($contact_id && class_exists('DG_Entity_Meta')) {
            DG_Entity_Meta::set('contact', $contact_id, 'guest_total_stays', get_post_meta($guest_id, 'dg_guest_total_stays', true));
            DG_Entity_Meta::set('contact', $contact_id, 'guest_total_spent', get_post_meta($guest_id, 'dg_guest_total_spent', true));
            DG_Entity_Meta::set('contact', $contact_id, 'guest_vip', get_post_meta($guest_id, 'dg_guest_vip', true));
        }

        if ($contact_id && class_exists('DG_Activities')) {
            DG_Activities::log([
                'contact_id' => $contact_id,
                'entity_type' => 'contact',
                'entity_id' => $contact_id,
                'activity_type' => 'sync',
                'subject' => 'Guest synced to core CRM',
                'content' => $post->post_title,
            ]);
        }

        update_post_meta($guest_id, 'dg_guest_contact_id', $contact_id);
        return (int) $contact_id;
    }

    public static function split_name($full_name) {
        $parts = preg_split('/\s+/', trim((string) $full_name), 2);
        return [
            'first_name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? '',
        ];
    }
}
