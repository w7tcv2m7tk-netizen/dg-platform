<?php
/**
 * Authenticated CRM REST endpoints for Cursor MCP / dev tooling.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_RE_CRM_Dev_API {

    public static function register_routes() {
        register_rest_route(DG_REST_NAMESPACE, '/leads/summary', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_summary'],
            'permission_callback' => [__CLASS__, 'can_access'],
            'args' => [
                'days' => [
                    'type' => 'integer',
                    'default' => 30,
                    'minimum' => 1,
                    'maximum' => 365,
                ],
            ],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/leads/vendor', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'list_vendor_leads'],
            'permission_callback' => [__CLASS__, 'can_access'],
            'args' => self::list_args(),
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/leads/vendor/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_vendor_lead'],
            'permission_callback' => [__CLASS__, 'can_access'],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/leads/buyer', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'list_buyer_leads'],
            'permission_callback' => [__CLASS__, 'can_access'],
            'args' => self::list_args(),
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/bookings/recent', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'list_recent_bookings'],
            'permission_callback' => [__CLASS__, 'can_access'],
            'args' => [
                'limit' => [
                    'type' => 'integer',
                    'default' => 20,
                    'minimum' => 1,
                    'maximum' => 100,
                ],
            ],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/properties', [
            [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'list_properties'],
                'permission_callback' => [__CLASS__, 'can_access'],
                'args' => [
                    'dg_property_id' => [
                        'type' => 'string',
                        'required' => false,
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'default' => 20,
                        'minimum' => 1,
                        'maximum' => 100,
                    ],
                ],
            ],
            [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'upsert_property'],
                'permission_callback' => [__CLASS__, 'can_access'],
            ],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/properties/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [__CLASS__, 'update_property_by_id'],
            'permission_callback' => [__CLASS__, 'can_access'],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/agents', [
            [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'list_agents'],
                'permission_callback' => [__CLASS__, 'can_access'],
                'args' => [
                    'dg_membership_id' => [
                        'type' => 'string',
                        'required' => false,
                    ],
                    'email' => [
                        'type' => 'string',
                        'required' => false,
                    ],
                ],
            ],
            [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'upsert_agent'],
                'permission_callback' => [__CLASS__, 'can_access'],
            ],
        ]);
    }

    public static function can_access($request) {
        if (!class_exists('DG_Dev_API')) {
            return false;
        }
        return DG_Dev_API::verify_request($request);
    }

    public static function get_summary($request) {
        if (!class_exists('DG_RE_Pipeline_Reports')) {
            return new WP_Error('unavailable', 'Pipeline reports unavailable.', ['status' => 503]);
        }

        $days = (int) $request->get_param('days');

        return rest_ensure_response([
            'site' => home_url(),
            'generated_at' => current_time('mysql'),
            'period_days' => $days,
            'property_reports_this_month' => DG_RE_Pipeline_Reports::property_reports_this_month(),
            'bookings_this_month' => DG_RE_Pipeline_Reports::bookings_this_month(),
            'vendor_conversion' => DG_RE_Pipeline_Reports::vendor_conversion_summary(),
            'recent_activity' => DG_RE_Pipeline_Reports::recent_activity_summary($days),
            'vendor_pipeline' => DG_RE_Pipeline_Reports::vendor_stage_counts(),
            'vendor_sources' => self::format_source_rows(DG_RE_Pipeline_Reports::vendor_source_counts()),
            'buyer_pipeline' => DG_RE_Pipeline_Reports::buyer_stage_counts(),
        ]);
    }

    public static function list_vendor_leads($request) {
        if (!class_exists('DG_RE_Vendor_Leads')) {
            return new WP_Error('unavailable', 'Vendor leads unavailable.', ['status' => 503]);
        }

        $leads = DG_RE_Vendor_Leads::list([
            'status' => $request->get_param('status') ?: null,
            'source' => $request->get_param('source') ?: null,
            'stage' => $request->get_param('stage') ?: null,
            'assigned_to' => $request->get_param('assigned_to') ? (int) $request->get_param('assigned_to') : null,
            'limit' => (int) ($request->get_param('limit') ?: 25),
            'offset' => (int) ($request->get_param('offset') ?: 0),
        ]);

        return rest_ensure_response([
            'total_returned' => count($leads),
            'leads' => array_map([__CLASS__, 'format_vendor_lead'], $leads),
        ]);
    }

    public static function get_vendor_lead($request) {
        if (!class_exists('DG_RE_Vendor_Leads')) {
            return new WP_Error('unavailable', 'Vendor leads unavailable.', ['status' => 503]);
        }

        $lead = DG_RE_Vendor_Leads::get((int) $request['id']);
        if (!$lead) {
            return new WP_Error('not_found', 'Vendor lead not found.', ['status' => 404]);
        }

        return rest_ensure_response(self::format_vendor_lead($lead, true));
    }

    public static function list_buyer_leads($request) {
        if (!class_exists('DG_RE_Buyer_Leads')) {
            return new WP_Error('unavailable', 'Buyer leads unavailable.', ['status' => 503]);
        }

        $leads = DG_RE_Buyer_Leads::list([
            'assigned_to' => $request->get_param('assigned_to') ? (int) $request->get_param('assigned_to') : null,
            'limit' => (int) ($request->get_param('limit') ?: 25),
            'offset' => (int) ($request->get_param('offset') ?: 0),
        ]);

        return rest_ensure_response([
            'total_returned' => count($leads),
            'leads' => array_map([__CLASS__, 'format_buyer_lead'], $leads),
        ]);
    }

    public static function list_recent_bookings($request) {
        global $wpdb;

        $table = $wpdb->prefix . 'roe_crm_bookings';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return rest_ensure_response(['total_returned' => 0, 'bookings' => []]);
        }

        $limit = (int) $request->get_param('limit');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, c.first_name, c.last_name, c.email, c.phone
             FROM $table b
             LEFT JOIN {$wpdb->prefix}roe_crm_contacts c ON b.contact_id = c.id
             ORDER BY b.booking_date DESC, b.booking_time DESC
             LIMIT %d",
            $limit
        ));

        $bookings = [];
        foreach ($rows as $row) {
            $bookings[] = [
                'id' => (int) $row->id,
                'contact' => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
                'email' => self::format_email($row->email ?? ''),
                'phone' => $row->phone ?? '',
                'service' => $row->service_name,
                'type' => $row->booking_type,
                'date' => $row->booking_date,
                'time' => $row->booking_time,
                'status' => $row->status,
                'created_at' => $row->created_at,
            ];
        }

        return rest_ensure_response([
            'total_returned' => count($bookings),
            'bookings' => $bookings,
        ]);
    }

    public static function list_properties($request) {
        $dg_id = sanitize_text_field((string) ($request->get_param('dg_property_id') ?? ''));
        if ($dg_id !== '') {
            $found = self::find_property_by_dg_id($dg_id);
            if (!$found) {
                return rest_ensure_response(['total_returned' => 0, 'properties' => []]);
            }
            return rest_ensure_response([
                'total_returned' => 1,
                'properties' => [self::format_dg_property($found)],
            ]);
        }

        $limit = (int) ($request->get_param('limit') ?: 100);
        $query = new WP_Query([
            'post_type' => 'property',
            'post_status' => ['publish', 'draft', 'pending'],
            'posts_per_page' => $limit,
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);

        $properties = [];
        while ($query->have_posts()) {
            $query->the_post();
            $properties[] = self::format_dg_property(get_the_ID());
        }
        wp_reset_postdata();

        return rest_ensure_response([
            'total_returned' => count($properties),
            'properties' => $properties,
        ]);
    }

    public static function upsert_property($request) {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            return new WP_Error('invalid_body', 'Expected JSON property payload.', ['status' => 400]);
        }

        $dg_id = sanitize_text_field((string) ($body['dg_property_id'] ?? $body['id'] ?? ''));
        if ($dg_id === '') {
            return new WP_Error('validation_error', 'dg_property_id is required.', ['status' => 422]);
        }

        $existing_id = self::find_property_by_dg_id($dg_id);
        $property_id = $existing_id
            ? self::apply_property_payload($existing_id, $body, false)
            : self::apply_property_payload(0, $body, true);

        if (is_wp_error($property_id)) {
            return $property_id;
        }

        return rest_ensure_response([
            'ok' => true,
            'created' => !$existing_id,
            'property' => self::format_dg_property((int) $property_id),
        ]);
    }

    public static function update_property_by_id($request) {
        $property_id = (int) $request['id'];
        if (get_post_type($property_id) !== 'property') {
            return new WP_Error('not_found', 'Property not found.', ['status' => 404]);
        }

        $body = $request->get_json_params();
        if (!is_array($body)) {
            return new WP_Error('invalid_body', 'Expected JSON property payload.', ['status' => 400]);
        }

        $result = self::apply_property_payload($property_id, $body, false);
        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response([
            'ok' => true,
            'created' => false,
            'property' => self::format_dg_property((int) $result),
        ]);
    }

    private static function find_property_by_dg_id($dg_id) {
        $query = new WP_Query([
            'post_type' => 'property',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => 1,
            'meta_key' => 'roe_property_dg_id',
            'meta_value' => $dg_id,
            'fields' => 'ids',
        ]);
        if (empty($query->posts)) {
            return 0;
        }
        return (int) $query->posts[0];
    }

    private static function map_status_to_wp($status) {
        $map = [
            'listed' => 'For Sale',
            'under_offer' => 'Under Contract',
            'sold' => 'Sold',
            'withdrawn' => 'Withdrawn',
            'appraisal' => 'For Sale',
            'prospect' => 'For Sale',
        ];
        $key = strtolower((string) $status);
        return $map[$key] ?? 'For Sale';
    }

    private static function should_publish($status) {
        return in_array(strtolower((string) $status), ['listed', 'under_offer', 'sold'], true);
    }

    /**
     * @param int   $property_id 0 to create
     * @param array $body
     * @param bool  $create
     * @return int|WP_Error
     */
    private static function apply_property_payload($property_id, $body, $create) {
        $dg_id = sanitize_text_field((string) ($body['dg_property_id'] ?? $body['id'] ?? ''));
        $address = sanitize_text_field((string) ($body['address'] ?? $body['address_line1'] ?? ''));
        $suburb = sanitize_text_field((string) ($body['suburb'] ?? ''));
        $state = sanitize_text_field((string) ($body['state'] ?? ''));
        $postcode = sanitize_text_field((string) ($body['postcode'] ?? ''));
        $status = sanitize_text_field((string) ($body['status'] ?? 'listed'));
        $title = sanitize_text_field((string) ($body['title'] ?? ''));
        if ($title === '') {
            $title = trim($address . ($suburb !== '' ? ', ' . $suburb : ''));
        }
        if ($title === '') {
            $title = 'Property ' . ($dg_id !== '' ? $dg_id : time());
        }

        $description = wp_kses_post((string) ($body['description'] ?? ''));
        $post_status = self::should_publish($status) ? 'publish' : 'draft';

        $post_data = [
            'post_type' => 'property',
            'post_status' => $post_status,
            'post_title' => $title,
        ];
        if ($description !== '') {
            $post_data['post_content'] = $description;
        }

        if ($create) {
            $property_id = wp_insert_post($post_data, true);
            if (is_wp_error($property_id)) {
                return $property_id;
            }
        } else {
            $post_data['ID'] = $property_id;
            $updated = wp_update_post($post_data, true);
            if (is_wp_error($updated)) {
                return $updated;
            }
        }

        $price = $body['price'] ?? null;
        if ($price === null && isset($body['listing_price_cents'])) {
            $price = ((float) $body['listing_price_cents']) / 100;
        }

        $meta = [
            'roe_property_dg_id' => $dg_id,
            'roe_property_status' => self::map_status_to_wp($status),
            'roe_property_address' => $address,
            'roe_property_suburb' => $suburb,
            'roe_property_state' => $state,
            'roe_property_postcode' => $postcode,
            'roe_property_title' => $title,
            'roe_property_description' => $description,
            'roe_property_type' => sanitize_text_field((string) ($body['property_type'] ?? '')),
            'roe_property_bedrooms' => isset($body['bedrooms']) ? (string) (int) $body['bedrooms'] : '',
            'roe_property_bathrooms' => isset($body['bathrooms']) ? (string) (int) $body['bathrooms'] : '',
            'roe_property_car_spaces' => isset($body['car_spaces']) ? (string) (int) $body['car_spaces'] : '',
            'roe_property_land_size' => sanitize_text_field((string) ($body['land_size'] ?? '')),
            'roe_property_building_size' => sanitize_text_field((string) ($body['building_size'] ?? '')),
            'roe_property_features' => sanitize_textarea_field((string) ($body['features'] ?? '')),
            'roe_property_external_id' => sanitize_text_field((string) ($body['external_id'] ?? $dg_id)),
        ];

        if ($price !== null && $price !== '') {
            $meta['roe_property_price'] = (string) $price;
        }

        if (!empty($body['agent']) && is_array($body['agent'])) {
            $meta['roe_property_agent_name'] = sanitize_text_field((string) ($body['agent']['name'] ?? ''));
            $meta['roe_property_agent_phone'] = sanitize_text_field((string) ($body['agent']['phone'] ?? ''));
            $meta['roe_property_agent_email'] = sanitize_email((string) ($body['agent']['email'] ?? ''));
        }

        $agent_id = isset($body['agent_id']) ? (int) $body['agent_id'] : 0;
        if ($agent_id > 0 && get_post_type($agent_id) === 'agent') {
            $meta['roe_property_agent_id'] = (string) $agent_id;
            if (empty($meta['roe_property_agent_name'])) {
                $meta['roe_property_agent_name'] = get_the_title($agent_id);
            }
            if (empty($meta['roe_property_agent_phone'])) {
                $meta['roe_property_agent_phone'] = get_post_meta($agent_id, 'roe_agent_phone', true);
            }
            if (empty($meta['roe_property_agent_email'])) {
                $meta['roe_property_agent_email'] = get_post_meta($agent_id, 'roe_agent_email', true);
            }
        }

        // Optional image URLs — sideload into media library and set gallery.
        $image_urls = [];
        if (!empty($body['images']) && is_array($body['images'])) {
            $image_urls = $body['images'];
        } elseif (!empty($body['gallery_urls']) && is_array($body['gallery_urls'])) {
            $image_urls = $body['gallery_urls'];
        }
        if (!empty($image_urls)) {
            $attachment_ids = self::sideload_property_images((int) $property_id, $image_urls);
            if (!empty($attachment_ids)) {
                $meta['roe_property_gallery'] = implode(',', $attachment_ids);
                if (!has_post_thumbnail($property_id)) {
                    set_post_thumbnail($property_id, (int) $attachment_ids[0]);
                }
            }
        }

        foreach ($meta as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            update_post_meta($property_id, $key, $value);
        }

        self::upsert_sync_record((int) $property_id, $dg_id);

        return (int) $property_id;
    }

    /**
     * Sideload remote image URLs into the media library for a property.
     *
     * @param int   $property_id
     * @param array $urls
     * @return int[] attachment IDs
     */
    private static function sideload_property_images($property_id, $urls) {
        if (!function_exists('media_sideload_image')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $ids = [];
        $count = 0;
        foreach ($urls as $url) {
            if ($count >= 20) {
                break;
            }
            $url = esc_url_raw(trim((string) $url));
            if ($url === '' || !preg_match('#^https?://#i', $url)) {
                continue;
            }

            // Reuse existing attachment if this URL was already downloaded.
            $existing = attachment_url_to_postid($url);
            if ($existing) {
                $ids[] = (int) $existing;
                $count++;
                continue;
            }

            $attachment_id = media_sideload_image($url, $property_id, null, 'id');
            if (!is_wp_error($attachment_id) && $attachment_id) {
                $ids[] = (int) $attachment_id;
                $count++;
            }
        }

        return $ids;
    }

    private static function upsert_sync_record($property_id, $dg_id) {
        global $wpdb;
        $sync_table = $wpdb->prefix . 'roe_property_sync';
        if ($wpdb->get_var("SHOW TABLES LIKE '$sync_table'") !== $sync_table) {
            return;
        }

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $sync_table WHERE property_id = %d AND source = %s LIMIT 1",
            $property_id,
            'digitalgate'
        ));

        if ($existing) {
            $wpdb->update(
                $sync_table,
                [
                    'external_id' => $dg_id,
                    'last_synced' => current_time('mysql'),
                    'sync_status' => 'active',
                ],
                ['id' => (int) $existing]
            );
            return;
        }

        $wpdb->insert($sync_table, [
            'property_id' => $property_id,
            'external_id' => $dg_id,
            'source' => 'digitalgate',
            'last_synced' => current_time('mysql'),
            'sync_status' => 'active',
        ]);
    }

    private static function format_dg_property($post_id) {
        $post_id = (int) $post_id;

        $gallery_meta = get_post_meta($post_id, 'roe_property_gallery', true);
        $images = [];
        if (!empty($gallery_meta)) {
            $ids = array_filter(array_map('trim', explode(',', (string) $gallery_meta)));
            foreach ($ids as $attachment_id) {
                $url = wp_get_attachment_image_url((int) $attachment_id, 'large');
                if ($url) {
                    $images[] = $url;
                }
            }
        }

        $featured = get_the_post_thumbnail_url($post_id, 'large');
        if ($featured && !in_array($featured, $images, true)) {
            array_unshift($images, $featured);
        }

        $description = get_post_meta($post_id, 'roe_property_description', true);
        if ($description === '' || $description === false) {
            $description = get_post_field('post_content', $post_id);
        }

        return [
            'id' => $post_id,
            'dg_property_id' => get_post_meta($post_id, 'roe_property_dg_id', true),
            'title' => get_the_title($post_id),
            'permalink' => get_permalink($post_id),
            'post_status' => get_post_status($post_id),
            'status' => get_post_meta($post_id, 'roe_property_status', true),
            'address' => get_post_meta($post_id, 'roe_property_address', true),
            'suburb' => get_post_meta($post_id, 'roe_property_suburb', true),
            'state' => get_post_meta($post_id, 'roe_property_state', true),
            'postcode' => get_post_meta($post_id, 'roe_property_postcode', true),
            'price' => get_post_meta($post_id, 'roe_property_price', true),
            'property_type' => get_post_meta($post_id, 'roe_property_type', true),
            'bedrooms' => get_post_meta($post_id, 'roe_property_bedrooms', true),
            'bathrooms' => get_post_meta($post_id, 'roe_property_bathrooms', true),
            'car_spaces' => get_post_meta($post_id, 'roe_property_car_spaces', true),
            'land_size' => get_post_meta($post_id, 'roe_property_land_size', true),
            'building_size' => get_post_meta($post_id, 'roe_property_building_size', true),
            'features' => get_post_meta($post_id, 'roe_property_features', true),
            'description' => $description,
            'external_id' => get_post_meta($post_id, 'roe_property_external_id', true),
            'images' => array_values(array_filter($images)),
            'featured_image' => $featured ?: null,
            'agent' => [
                'id' => (int) get_post_meta($post_id, 'roe_property_agent_id', true),
                'name' => get_post_meta($post_id, 'roe_property_agent_name', true),
                'phone' => get_post_meta($post_id, 'roe_property_agent_phone', true),
                'email' => get_post_meta($post_id, 'roe_property_agent_email', true),
            ],
            'modified_at' => get_post_modified_time('c', true, $post_id),
        ];
    }

    public static function list_agents($request) {
        if (!post_type_exists('agent')) {
            return new WP_Error('unavailable', 'Agent profiles are not available on this site.', ['status' => 404]);
        }

        $dg_id = sanitize_text_field((string) ($request->get_param('dg_membership_id') ?? ''));
        $email = sanitize_email((string) ($request->get_param('email') ?? ''));

        if ($dg_id !== '') {
            $found = self::find_agent_by_dg_id($dg_id);
            return rest_ensure_response([
                'total_returned' => $found ? 1 : 0,
                'agents' => $found ? [self::format_dg_agent($found)] : [],
            ]);
        }

        if ($email !== '') {
            $found = self::find_agent_by_email($email);
            return rest_ensure_response([
                'total_returned' => $found ? 1 : 0,
                'agents' => $found ? [self::format_dg_agent($found)] : [],
            ]);
        }

        $query = new WP_Query([
            'post_type' => 'agent',
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => 50,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $agents = [];
        while ($query->have_posts()) {
            $query->the_post();
            $agents[] = self::format_dg_agent(get_the_ID());
        }
        wp_reset_postdata();

        return rest_ensure_response([
            'total_returned' => count($agents),
            'agents' => $agents,
        ]);
    }

    public static function upsert_agent($request) {
        if (!post_type_exists('agent')) {
            return new WP_Error('unavailable', 'Agent profiles are not available on this site.', ['status' => 404]);
        }

        $body = $request->get_json_params();
        if (!is_array($body)) {
            return new WP_Error('invalid_body', 'Expected JSON agent payload.', ['status' => 400]);
        }

        $dg_id = sanitize_text_field((string) ($body['dg_membership_id'] ?? ''));
        $email = sanitize_email((string) ($body['email'] ?? ''));
        $name = sanitize_text_field((string) ($body['name'] ?? ''));
        if ($name === '') {
            $name = $email !== '' ? $email : 'Agent';
        }

        $existing_id = 0;
        if ($dg_id !== '') {
            $existing_id = self::find_agent_by_dg_id($dg_id);
        }
        if (!$existing_id && $email !== '') {
            $existing_id = self::find_agent_by_email($email);
        }

        $post_data = [
            'post_type' => 'agent',
            'post_status' => 'publish',
            'post_title' => $name,
        ];
        $bio = isset($body['bio']) ? wp_kses_post((string) $body['bio']) : '';
        if ($bio !== '') {
            $post_data['post_content'] = $bio;
        }

        if ($existing_id) {
            $post_data['ID'] = $existing_id;
            $agent_id = wp_update_post($post_data, true);
        } else {
            $agent_id = wp_insert_post($post_data, true);
        }

        if (is_wp_error($agent_id)) {
            return $agent_id;
        }

        $meta = [
            'roe_agent_dg_id' => $dg_id,
            'roe_agent_email' => $email,
            'roe_agent_phone' => sanitize_text_field((string) ($body['phone'] ?? '')),
            'roe_agent_title' => sanitize_text_field((string) ($body['title'] ?? '')),
            'roe_agent_position' => sanitize_text_field((string) ($body['position'] ?? $body['title'] ?? '')),
            'roe_agent_bio' => $bio,
        ];
        foreach ($meta as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            update_post_meta($agent_id, $key, $value);
        }

        $photo_url = isset($body['photo_url']) ? esc_url_raw(trim((string) $body['photo_url'])) : '';
        if ($photo_url !== '' && preg_match('#^https?://#i', $photo_url)) {
            $attachment_ids = self::sideload_property_images((int) $agent_id, [$photo_url]);
            if (!empty($attachment_ids[0])) {
                set_post_thumbnail((int) $agent_id, (int) $attachment_ids[0]);
                update_post_meta((int) $agent_id, 'roe_agent_photo_url', $photo_url);
            }
        }

        return rest_ensure_response([
            'ok' => true,
            'created' => !$existing_id,
            'agent' => self::format_dg_agent((int) $agent_id),
        ]);
    }

    private static function find_agent_by_dg_id($dg_id) {
        $query = new WP_Query([
            'post_type' => 'agent',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => 1,
            'meta_key' => 'roe_agent_dg_id',
            'meta_value' => $dg_id,
            'fields' => 'ids',
        ]);
        return empty($query->posts) ? 0 : (int) $query->posts[0];
    }

    private static function find_agent_by_email($email) {
        $query = new WP_Query([
            'post_type' => 'agent',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => 1,
            'meta_key' => 'roe_agent_email',
            'meta_value' => $email,
            'fields' => 'ids',
        ]);
        return empty($query->posts) ? 0 : (int) $query->posts[0];
    }

    private static function format_dg_agent($post_id) {
        $post_id = (int) $post_id;
        $featured = get_the_post_thumbnail_url($post_id, 'large');
        return [
            'id' => $post_id,
            'dg_membership_id' => get_post_meta($post_id, 'roe_agent_dg_id', true),
            'name' => get_the_title($post_id),
            'permalink' => get_permalink($post_id),
            'email' => get_post_meta($post_id, 'roe_agent_email', true),
            'phone' => get_post_meta($post_id, 'roe_agent_phone', true),
            'title' => get_post_meta($post_id, 'roe_agent_title', true),
            'bio' => get_post_meta($post_id, 'roe_agent_bio', true),
            'photo_url' => $featured ?: (get_post_meta($post_id, 'roe_agent_photo_url', true) ?: null),
            'post_status' => get_post_status($post_id),
        ];
    }

    private static function list_args() {
        return [
            'status' => ['type' => 'string'],
            'source' => ['type' => 'string'],
            'stage' => ['type' => 'string'],
            'assigned_to' => ['type' => 'integer'],
            'limit' => [
                'type' => 'integer',
                'default' => 25,
                'minimum' => 1,
                'maximum' => 100,
            ],
            'offset' => [
                'type' => 'integer',
                'default' => 0,
                'minimum' => 0,
            ],
        ];
    }

    private static function format_source_rows($rows) {
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'source' => $row->source,
                'count' => (int) $row->total,
            ];
        }
        return $out;
    }

    private static function format_vendor_lead($lead, $detailed = false) {
        $name = trim(($lead->first_name ?? '') . ' ' . ($lead->last_name ?? ''));
        $formatted = [
            'id' => (int) $lead->id,
            'name' => $name !== '' ? $name : 'Unknown',
            'email' => self::format_email($lead->email ?? ''),
            'phone' => $lead->phone ?? '',
            'property_address' => $lead->property_address ?? '',
            'source' => $lead->source ?? '',
            'stage' => $lead->stage ?? 'vendor_lead',
            'status' => $lead->status ?? '',
            'assigned_to' => isset($lead->assigned_to) ? (int) $lead->assigned_to : null,
            'created_at' => $lead->created_at ?? '',
        ];

        if ($detailed) {
            $formatted['notes'] = $lead->notes ?? '';
            $formatted['pipeline_id'] = isset($lead->pipeline_record_id) ? (int) $lead->pipeline_record_id : null;
            $formatted['contact_id'] = isset($lead->dg_contact_id) ? (int) $lead->dg_contact_id : null;
        }

        return $formatted;
    }

    private static function format_buyer_lead($lead) {
        $meta = json_decode($lead->pipeline_metadata ?? '{}', true);
        $name = trim(($lead->first_name ?? '') . ' ' . ($lead->last_name ?? ''));

        return [
            'id' => (int) $lead->id,
            'name' => $name !== '' ? $name : 'Unknown',
            'email' => self::format_email($lead->email ?? ''),
            'phone' => $lead->phone ?? '',
            'property_address' => $meta['property_address'] ?? '',
            'property_url' => $meta['property_url'] ?? '',
            'requirements' => $lead->requirements ?? '',
            'stage' => $lead->stage ?? 'inquiry',
            'status' => $lead->status ?? '',
            'assigned_to' => isset($lead->assigned_to) ? (int) $lead->assigned_to : null,
            'created_at' => $lead->created_at ?? '',
        ];
    }

    private static function format_email($email) {
        if (class_exists('DG_RE_Contacts')) {
            return DG_RE_Contacts::display_email($email);
        }
        if (strpos($email, '@leads.roerealty.local') !== false) {
            return '';
        }
        return $email;
    }
}

add_action('rest_api_init', function () {
    if (class_exists('DG_RE_CRM_Dev_API')) {
        DG_RE_CRM_Dev_API::register_routes();
    }
});
