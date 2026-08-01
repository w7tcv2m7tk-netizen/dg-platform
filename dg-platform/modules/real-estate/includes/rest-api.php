<?php

if (!defined("ABSPATH")) { exit; }

function roe_realty_api_get_properties($request) {
    $per_page = $request->get_param('per_page') ?: 20;
    $page = $request->get_param('page') ?: 1;
    $status = $request->get_param('status');
    $suburb = $request->get_param('suburb');
    
    $args = array(
        'post_type' => 'property',
        'posts_per_page' => $per_page,
        'paged' => $page,
        'post_status' => 'publish'
    );
    
    if ($status) {
        $args['meta_query'][] = array(
            'key' => 'roe_property_status',
            'value' => $status,
            'compare' => '='
        );
    }
    
    if ($suburb) {
        $args['meta_query'][] = array(
            'key' => 'roe_property_suburb',
            'value' => $suburb,
            'compare' => '='
        );
    }
    
    $query = new WP_Query($args);
    $properties = array();
    
    while ($query->have_posts()) {
        $query->the_post();
        $properties[] = roe_realty_api_format_property(get_the_ID());
    }
    
    wp_reset_postdata();
    
    return rest_ensure_response(array(
        'properties' => $properties,
        'total' => $query->found_posts,
        'pages' => $query->max_num_pages,
        'current_page' => $page
    ));
}

function roe_realty_api_get_property($request) {
    $id = $request->get_param('id');
    $property = roe_realty_api_format_property($id);
    
    if (!$property) {
        return new WP_Error('not_found', 'Property not found', array('status' => 404));
    }
    
    return rest_ensure_response($property);
}

function roe_realty_api_format_property($post_id) {
    if (get_post_type($post_id) !== 'property') {
        return null;
    }
    
    $meta = array(
        'price' => get_post_meta($post_id, 'roe_property_price', true),
        'status' => get_post_meta($post_id, 'roe_property_status', true),
        'address' => get_post_meta($post_id, 'roe_property_address', true),
        'suburb' => get_post_meta($post_id, 'roe_property_suburb', true),
        'state' => get_post_meta($post_id, 'roe_property_state', true),
        'postcode' => get_post_meta($post_id, 'roe_property_postcode', true),
        'bedrooms' => get_post_meta($post_id, 'roe_property_bedrooms', true),
        'bathrooms' => get_post_meta($post_id, 'roe_property_bathrooms', true),
        'car_spaces' => get_post_meta($post_id, 'roe_property_car_spaces', true),
        'land_size' => get_post_meta($post_id, 'roe_property_land_size', true),
        'building_size' => get_post_meta($post_id, 'roe_property_building_size', true),
        'year_built' => get_post_meta($post_id, 'roe_property_year_built', true),
        'property_type' => get_post_meta($post_id, 'roe_property_type', true),
        'title' => get_post_meta($post_id, 'roe_property_title', true),
        'description' => get_post_meta($post_id, 'roe_property_description', true),
        'features' => get_post_meta($post_id, 'roe_property_features', true),
        'inspection_times' => get_post_meta($post_id, 'roe_property_inspection_times', true),
        'external_id' => get_post_meta($post_id, 'roe_property_external_id', true)
    );
    
    $gallery = get_post_meta($post_id, 'roe_property_gallery', true);
    $images = array();
    if (!empty($gallery)) {
        $ids = array_map('trim', explode(',', $gallery));
        foreach ($ids as $id) {
            $images[] = wp_get_attachment_image_url($id, 'large');
        }
    }
    
    return array(
        'id' => $post_id,
        'title' => get_the_title($post_id),
        'permalink' => get_permalink($post_id),
        'featured_image' => get_the_post_thumbnail_url($post_id, 'large'),
        'meta' => $meta,
        'gallery' => $images,
        'agent' => array(
            'name' => get_post_meta($post_id, 'roe_property_agent_name', true),
            'phone' => get_post_meta($post_id, 'roe_property_agent_phone', true),
            'email' => get_post_meta($post_id, 'roe_property_agent_email', true)
        )
    );
}

function roe_realty_api_verify_key($request) {
    $api_key = $request->get_header('X-API-Key');
    $stored_key = get_option('roe_realty_api_key', '');
    return $api_key === $stored_key;
}

function roe_realty_api_import($request) {
    $data = $request->get_body();
    $format = $request->get_param('format') ?: 'json';
    $source = $request->get_param('source') ?: 'api';
    $provider = $request->get_param('provider') ?: 'rea';
    
    $importer = new Roe_Property_Importer($source, $provider);
    $result = $importer->import($data, $format);
    
    return rest_ensure_response($result);
}

function dg_re_register_rest_routes() {
    register_rest_route('roerealty/v1', '/properties', [
        'methods' => 'GET',
        'callback' => 'roe_realty_api_get_properties',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('roerealty/v1', '/properties/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'roe_realty_api_get_property',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('roerealty/v1', '/import', [
        'methods' => 'POST',
        'callback' => 'roe_realty_api_import',
        'permission_callback' => 'roe_realty_api_verify_key',
    ]);
    register_rest_route('roerealty/v1', '/property-report', [
        'methods' => 'POST',
        'callback' => 'dg_re_api_property_report',
        'permission_callback' => '__return_true',
    ]);
}

function dg_re_api_property_report($request) {
    if (!function_exists('dg_re_process_property_report_lead')) {
        return new WP_Error('unavailable', 'Property report handler is unavailable.', ['status' => 503]);
    }

    $params = $request->get_json_params();
    if (!$params) {
        $params = $request->get_body_params();
    }

    $result = dg_re_process_property_report_lead($params ?: []);

    if (!empty($result['success'])) {
        return rest_ensure_response([
            'success' => true,
            'message' => $result['message'],
            'vendor_lead_id' => $result['vendor_lead_id'] ?? null,
        ]);
    }

    return new WP_Error('report_failed', $result['message'] ?? 'Unable to process report request.', ['status' => 400]);
}
