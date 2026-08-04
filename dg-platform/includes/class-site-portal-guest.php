<?php
/**
 * Guest portal — bookings dashboard and guest user provisioning.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Site_Portal_Guest {

    public static function init() {
        add_action('dg_booking_confirmed', [__CLASS__, 'on_booking_confirmed'], 15, 1);
        add_action('save_post_dg_booking', [__CLASS__, 'maybe_provision_on_paid'], 15, 3);
    }

    public static function on_booking_confirmed($booking_id) {
        self::provision_from_booking((int) $booking_id);
    }

    public static function maybe_provision_on_paid($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }

        if (get_post_meta($post_id, 'dg_booking_paid', true) !== 'yes') {
            return;
        }

        $status = get_post_meta($post_id, 'dg_booking_status', true);
        if ($status === 'cancelled') {
            return;
        }

        self::provision_from_booking((int) $post_id);
    }

    public static function provision_from_booking($booking_id) {
        if (!class_exists('DG_Client_Portal') || !DG_Client_Portal::enabled()) {
            return;
        }
        if (DG_Client_Portal::portal_id() !== 'guest') {
            return;
        }

        $email = sanitize_email(get_post_meta($booking_id, 'dg_booking_email', true));
        if ($email === '') {
            return;
        }

        $name = sanitize_text_field(get_post_meta($booking_id, 'dg_booking_name', true) ?: 'Guest');
        $guest_id = 0;
        if (class_exists('DG_Acc_Guests')) {
            $guest_id = (int) DG_Acc_Guests::sync_from_booking($booking_id);
        }

        self::ensure_user($email, $name, $guest_id);
    }

    /**
     * @return array{user_id:int,created:bool,error?:string}
     */
    public static function ensure_user($email, $display_name, $guest_id = 0) {
        if (!class_exists('DG_Client_Portal')) {
            return ['user_id' => 0, 'created' => false, 'error' => 'Portal unavailable'];
        }

        DG_Client_Portal::register_role();

        $email = sanitize_email($email);
        if ($email === '') {
            return ['user_id' => 0, 'created' => false, 'error' => 'Invalid email'];
        }

        $role = DG_Client_Portal::role();
        $existing = email_exists($email);

        if ($existing) {
            $user = get_userdata($existing);
            if ($user && !DG_Client_Portal::is_portal_user($user)) {
                $user->add_role($role);
            }
            DG_Client_Portal::sync_portal_capabilities((int) $existing);
            if ($guest_id) {
                update_user_meta((int) $existing, 'dg_guest_id', (int) $guest_id);
            }
            return ['user_id' => (int) $existing, 'created' => false];
        }

        $password = wp_generate_password(16, true);
        $user_id = wp_create_user($email, $password, $email);
        if (is_wp_error($user_id)) {
            return ['user_id' => 0, 'created' => false, 'error' => $user_id->get_error_message()];
        }

        wp_update_user([
            'ID' => $user_id,
            'display_name' => $display_name,
            'first_name' => $display_name,
            'role' => $role,
        ]);

        DG_Client_Portal::sync_portal_capabilities((int) $user_id);
        if ($guest_id) {
            update_user_meta((int) $user_id, 'dg_guest_id', (int) $guest_id);
        }

        return ['user_id' => (int) $user_id, 'created' => true];
    }

    /** @return array<string,mixed> */
    public static function dashboard_context() {
        try {
            return self::build_dashboard_context();
        } catch (Throwable $e) {
            return self::fallback_dashboard_context();
        }
    }

    /** @return array<string,mixed> */
    private static function fallback_dashboard_context() {
        return [
            'guest_name' => 'Guest',
            'guest_email' => '',
            'upcoming_bookings' => [],
            'past_bookings' => [],
            'logout_url' => home_url('/guest-portal/'),
            'login_url' => home_url('/guest-portal/'),
            'portal_label' => 'Guest Portal',
            'site_label' => 'Currumbin Valley Hideaway',
            'support_email' => 'bookings@currumbinvalleyhideaway.com.au',
            'is_builder' => false,
        ];
    }

    /** @return array<string,mixed> */
    private static function build_dashboard_context() {
        $config = class_exists('DG_Site_Portal_Config') ? DG_Site_Portal_Config::current() : null;
        $guest_name = 'Guest';
        $guest_email = '';
        $is_builder = class_exists('DG_Client_Portal') && DG_Client_Portal::is_oxygen_builder();

        if (!$is_builder && function_exists('wp_get_current_user')) {
            $user = wp_get_current_user();
            if ($user && $user->ID) {
                $guest_name = class_exists('DG_Email_Names')
                    ? DG_Email_Names::first_name($user)
                    : ($user->first_name ?: $user->display_name ?: explode('@', $user->user_email)[0]);
                $guest_email = $user->user_email;
            }
        } elseif ($is_builder) {
            $guest_name = 'Preview';
            $guest_email = 'guest@example.com';
        }

        $bookings = $is_builder ? self::preview_bookings() : self::bookings_for_email($guest_email);
        $today = wp_date('Y-m-d');
        $upcoming = [];
        $past = [];

        foreach ($bookings as $booking) {
            $checkout = (string) ($booking['checkout'] ?? '');
            if ($checkout !== '' && $checkout >= $today) {
                $upcoming[] = $booking;
            } else {
                $past[] = $booking;
            }
        }

        usort($upcoming, static function ($a, $b) {
            return strcmp((string) ($a['checkin'] ?? ''), (string) ($b['checkin'] ?? ''));
        });
        usort($past, static function ($a, $b) {
            return strcmp((string) ($b['checkin'] ?? ''), (string) ($a['checkin'] ?? ''));
        });

        $login = class_exists('DG_Client_Portal') ? DG_Client_Portal::login_url() : home_url('/guest-portal/');

        return [
            'guest_name' => $guest_name,
            'guest_email' => $guest_email,
            'upcoming_bookings' => $upcoming,
            'past_bookings' => $past,
            'logout_url' => function_exists('wp_logout_url') ? wp_logout_url($login) : '/wp-login.php?action=logout',
            'login_url' => $login,
            'portal_label' => $config['label'] ?? 'Guest Portal',
            'site_label' => $config['site_label'] ?? 'Currumbin Valley Hideaway',
            'support_email' => $config['support_email'] ?? 'bookings@currumbinvalleyhideaway.com.au',
            'is_builder' => $is_builder,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function preview_bookings() {
        return [
            [
                'id' => 0,
                'ref' => 'CVH-PREVIEW',
                'accommodation' => 'Rainforest Dome',
                'checkin' => wp_date('Y-m-d', strtotime('+3 days')),
                'checkout' => wp_date('Y-m-d', strtotime('+5 days')),
                'guests' => '2',
                'status' => 'confirmed',
                'checkin_url' => home_url('/check-in-rainforest-dome/'),
                'checkin_page_label' => 'Rainforest Dome',
                'checkin_time' => '3:00 PM',
                'checkout_time' => '10:00 AM',
                'address' => 'Currumbin Valley, QLD',
            ],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public static function bookings_for_email($email) {
        $email = sanitize_email($email);
        if ($email === '') {
            return [];
        }

        $posts = get_posts([
            'post_type' => 'dg_booking',
            'posts_per_page' => 100,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => 'dg_booking_email',
                    'value' => $email,
                ],
            ],
            'orderby' => 'meta_value',
            'meta_key' => 'dg_booking_checkin',
            'order' => 'DESC',
        ]);

        $rows = [];
        foreach ($posts as $post) {
            $rows[] = self::format_booking($post->ID);
        }

        return $rows;
    }

    /** @return array<string,mixed> */
    private static function format_booking($booking_id) {
        $booking_id = (int) $booking_id;
        $accommodation_id = (int) get_post_meta($booking_id, 'dg_booking_accommodation_id', true);
        $checkin_data = class_exists('DG_Acc_Checkin')
            ? DG_Acc_Checkin::get_guest_checkin_details($accommodation_id)
            : [];

        return [
            'id' => $booking_id,
            'ref' => (string) get_post_meta($booking_id, 'dg_booking_ref', true),
            'accommodation' => (string) (get_post_meta($booking_id, 'dg_booking_accommodation_name', true) ?: 'Accommodation'),
            'checkin' => (string) get_post_meta($booking_id, 'dg_booking_checkin', true),
            'checkout' => (string) get_post_meta($booking_id, 'dg_booking_checkout', true),
            'guests' => (string) get_post_meta($booking_id, 'dg_booking_guests', true),
            'status' => (string) (get_post_meta($booking_id, 'dg_booking_status', true) ?: 'pending'),
            'checkin_url' => (string) ($checkin_data['checkin_url'] ?? ''),
            'checkin_page_label' => (string) ($checkin_data['checkin_page_label'] ?? ''),
            'checkin_time' => (string) ($checkin_data['checkin_time'] ?? ''),
            'checkout_time' => (string) ($checkin_data['checkout_time'] ?? ''),
            'address' => (string) ($checkin_data['address'] ?? ''),
            'wifi_password' => (string) ($checkin_data['wifi_password'] ?? ''),
            'instructions' => (string) ($checkin_data['instructions'] ?? ''),
        ];
    }

    public static function portal_url_for_email($email) {
        if (!class_exists('DG_Client_Portal') || !DG_Client_Portal::enabled()) {
            return '';
        }

        $login = DG_Client_Portal::login_url();
        $result = self::ensure_user($email, 'Guest');
        if (!empty($result['created']) && !empty($result['user_id'])) {
            $reset = DG_Client_Portal::password_set_link((int) $result['user_id'], $email);
            if ($reset && $reset !== $login) {
                return $reset;
            }
        }

        return $login;
    }
}

add_action('plugins_loaded', static function () {
    if (class_exists('DG_Site_Portal_Config')
        && class_exists('DG_Site_Profile')
        && DG_Site_Profile::is_currumbin_hideaway()) {
        DG_Site_Portal_Guest::init();
    }
}, 12);
