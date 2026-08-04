<?php

if (!defined("ABSPATH")) { exit; }

function roe_property_display_shortcode() {
    // Try to get the property ID from the current post
    $post_id = null;
    
    // Method 1: Check if we're on a single property page
    if (is_singular('property')) {
        global $post;
        $post_id = $post->ID;
    }
    
    // Method 2: Try to find the property from the URL
    if (!$post_id) {
        $current_url = $_SERVER['REQUEST_URI'];
        if (strpos($current_url, '/property/') !== false) {
            $url_parts = explode('/', trim($current_url, '/'));
            $slug = end($url_parts);
            $property = get_page_by_path($slug, OBJECT, 'property');
            if ($property) {
                $post_id = $property->ID;
            }
        }
    }
    
    // Method 3: Try to get from the global post object
    if (!$post_id) {
        global $post;
        if ($post && get_post_type($post->ID) === 'property') {
            $post_id = $post->ID;
        }
    }
    
    // If still no ID, show a helpful message
    if (!$post_id) {
        return '<div style="background:#FFF3CD;padding:20px;border-radius:8px;border-left:4px solid #FFC107;color:#856404;max-width:600px;margin:20px auto;">
            <strong>No property found.</strong><br>
            This page doesn\'t appear to be a property listing. Please make sure you\'re viewing a valid property page.
        </div>';
    }
    
    // Verify it's a property post
    if (get_post_type($post_id) !== 'property') {
        return '<div style="background:#FFF3CD;padding:20px;border-radius:8px;border-left:4px solid #FFC107;color:#856404;max-width:600px;margin:20px auto;">
            <strong>This isn\'t a property page.</strong><br>
            The shortcode can only be used on property listings.
        </div>';
    }
    
    // Get all property data
    $property_title = get_post_meta($post_id, 'roe_property_title', true);
    $price = get_post_meta($post_id, 'roe_property_price', true);
    $status = get_post_meta($post_id, 'roe_property_status', true);
    $address = get_post_meta($post_id, 'roe_property_address', true);
    $suburb = get_post_meta($post_id, 'roe_property_suburb', true);
    $state = get_post_meta($post_id, 'roe_property_state', true);
    $postcode = get_post_meta($post_id, 'roe_property_postcode', true);
    $beds = get_post_meta($post_id, 'roe_property_bedrooms', true);
    $baths = get_post_meta($post_id, 'roe_property_bathrooms', true);
    $cars = get_post_meta($post_id, 'roe_property_car_spaces', true);
    $land = get_post_meta($post_id, 'roe_property_land_size', true);
    $building = get_post_meta($post_id, 'roe_property_building_size', true);
    $year_built = get_post_meta($post_id, 'roe_property_year_built', true);
    $property_type = get_post_meta($post_id, 'roe_property_type', true);
    $features = get_post_meta($post_id, 'roe_property_features', true);
    $floorplans = get_post_meta($post_id, 'roe_property_floorplans', true);
    $videos = get_post_meta($post_id, 'roe_property_videos', true);
    $virtual_tour = get_post_meta($post_id, 'roe_property_virtual_tour', true);
    $inspection_times = get_post_meta($post_id, 'roe_property_inspection_times', true);
    $external_id = get_post_meta($post_id, 'roe_property_external_id', true);
    $agent_name = get_post_meta($post_id, 'roe_property_agent_name', true);
    $agent_phone = get_post_meta($post_id, 'roe_property_agent_phone', true);
    $agent_email = get_post_meta($post_id, 'roe_property_agent_email', true);
    $agent_photo = get_post_meta($post_id, 'roe_property_agent_photo', true);
    $agent_id = get_post_meta($post_id, 'roe_property_agent_id', true);
    
    // Get description and gallery from custom fields
    $description_raw = get_post_meta($post_id, 'roe_property_description', true);
    $gallery_ids = get_post_meta($post_id, 'roe_property_gallery', true);
    
    // Get featured image
    $featured_image = get_post_thumbnail_id($post_id);
    
    // Build full address for map
    $full_address = implode(', ', array_filter(array($address, $suburb, $state, $postcode)));
    
    // Build gallery array (featured image first, then gallery images)
    $all_images = array();
    if ($featured_image) {
        $all_images[] = $featured_image;
    }
    if (!empty($gallery_ids)) {
        $gallery_array = array_map('trim', explode(',', $gallery_ids));
        foreach ($gallery_array as $id) {
            if (!empty($id) && !in_array($id, $all_images)) {
                $all_images[] = $id;
            }
        }
    }
    
    // Get Sold On date
    $sold_on = get_post_meta($post_id, 'roe_property_sold_on', true);
    
    $formatted_price = function_exists('roe_format_price') ? roe_format_price($price) : 'Contact for Price';
    
    $status_colors = array(
        'For Sale' => '#2E7D32',
        'Under Contract' => '#F57C00',
        'Sold' => '#C62828',
        'Withdrawn' => '#666'
    );
    $status_color = isset($status_colors[$status]) ? $status_colors[$status] : '#666';
    
    // Build gallery HTML
    $gallery_html = '';
    if (!empty($all_images)) {
        $gallery_html .= '<div class="property-gallery">';
        $gallery_html .= '<div class="gallery-grid">';
        
        $first_image = array_shift($all_images);
        $image_url = wp_get_attachment_image_url($first_image, 'large');
        $image_full = wp_get_attachment_image_url($first_image, 'full');
        if ($image_url) {
            $gallery_html .= '<a href="' . esc_url($image_full) . '" class="gallery-item gallery-main" data-fancybox="property-gallery" data-caption="' . esc_attr(get_the_title($post_id)) . '">';
            $gallery_html .= '<img src="' . esc_url($image_url) . '" alt="Featured property image" loading="lazy">';
            $gallery_html .= '</a>';
        }
        
        $gallery_html .= '<div class="gallery-thumbs">';
        $count = 0;
        foreach ($all_images as $image_id) {
            if ($count >= 2) break;
            $image_url = wp_get_attachment_image_url($image_id, 'medium_large');
            $image_full = wp_get_attachment_image_url($image_id, 'full');
            if ($image_url) {
                $gallery_html .= '<a href="' . esc_url($image_full) . '" class="gallery-item gallery-thumb" data-fancybox="property-gallery" data-caption="' . esc_attr(get_the_title($post_id)) . '">';
                $gallery_html .= '<img src="' . esc_url($image_url) . '" alt="Property image" loading="lazy">';
                $gallery_html .= '</a>';
            }
            $count++;
        }
        
        $remaining = count($all_images) - 2;
        if ($remaining > 0) {
            $remaining_images = array_slice($all_images, 2);
            $next_image = reset($remaining_images);
            $next_full = wp_get_attachment_image_url($next_image, 'full');
            $gallery_html .= '<a href="' . esc_url($next_full) . '" class="gallery-item gallery-thumb gallery-more" data-fancybox="property-gallery" data-caption="' . esc_attr(get_the_title($post_id)) . '">';
            $gallery_html .= '<img src="' . esc_url(wp_get_attachment_image_url($next_image, 'medium_large')) . '" alt="More images" loading="lazy">';
            $gallery_html .= '<div class="more-overlay">+ ' . $remaining . '</div>';
            $gallery_html .= '</a>';
        }
        
        $gallery_html .= '</div>';
        $gallery_html .= '</div>';
        $gallery_html .= '</div>';
        
        if ($remaining > 0) {
            $remaining_images = array_slice($all_images, 2);
            foreach ($remaining_images as $image_id) {
                $image_full = wp_get_attachment_image_url($image_id, 'full');
                if ($image_full) {
                    $gallery_html .= '<a href="' . esc_url($image_full) . '" data-fancybox="property-gallery" data-caption="' . esc_attr(get_the_title($post_id)) . '" style="display:none;"></a>';
                }
            }
        }
    }
    
    ob_start();
    ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />
    
    <style>
        .property-page * { box-sizing: border-box; }
        
        .property-hero { 
            background: #1C2B2A; 
            padding: 120px 20px 30px; 
            color: #fff; 
        }
        .property-hero .container { max-width: 1280px; margin: 0 auto; }
        .property-hero .price-row { 
            display: flex; 
            align-items: center; 
            gap: 16px; 
            flex-wrap: wrap; 
            margin-bottom: 5px; 
        }
        .property-hero h1 { 
            font-size: 2.2rem; 
            font-weight: 700; 
            margin: 0 0 5px 0; 
            font-family: 'Sora', sans-serif;
            color: #ffffff;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.35);
        }
        .property-hero .price { 
            color: #C9A46C; 
            font-size: 2rem; 
            font-weight: 700; 
        }
        .property-hero .status { 
            display: inline-block; 
            background: <?php echo $status_color; ?>; 
            color: #fff; 
            padding: 4px 16px; 
            border-radius: 40px; 
            font-size: 12px; 
            font-weight: 600; 
            text-transform: uppercase; 
        }
        .property-hero .address-sub {
            color: #B8C5C2; 
            font-size: 1rem; 
            margin-top: 4px;
            font-weight: 300;
        }
        .property-hero .sold-on {
            font-size: 0.95rem;
            color: #B8C5C2;
            margin-top: 4px;
            font-weight: 300;
        }
        
        .property-gallery { margin: 0; background: #000; }
        .gallery-grid { 
            display: grid; 
            grid-template-columns: 2fr 1fr; 
            gap: 2px; 
            background: #000; 
            max-height: 500px;
            overflow: hidden;
        }
        .gallery-item { 
            display: block; 
            overflow: hidden; 
            position: relative;
            background: #1a1a1a;
            cursor: pointer;
        }
        .gallery-item img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
            display: block; 
            transition: transform 0.3s ease;
        }
        .gallery-item:hover img { transform: scale(1.02); }
        .gallery-main { 
            grid-row: span 2; 
            min-height: 400px;
            max-height: 500px;
        }
        .gallery-main img { 
            min-height: 400px;
            max-height: 500px;
        }
        .gallery-thumbs {
            display: grid;
            grid-template-rows: 1fr 1fr;
            gap: 2px;
            background: #000;
        }
        .gallery-thumb { 
            min-height: 200px;
            max-height: 249px;
            overflow: hidden;
        }
        .gallery-thumb img { 
            min-height: 200px;
            max-height: 249px;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .gallery-more { position: relative; }
        .gallery-more .more-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 2.5rem;
            font-weight: 700;
            font-family: 'Sora', sans-serif;
        }
        
        .property-content { 
            padding: 40px 20px 60px; 
            background: #F5F2EF; 
        }
        .property-content .container { 
            max-width: 1280px; 
            margin: 0 auto; 
            display: grid; 
            grid-template-columns: 65% 35%; 
            gap: 50px; 
        }
        
        .property-description { 
            color: #4A5B59; 
            line-height: 1.8; 
        }
        .property-description h2 { 
            font-family: 'Sora', sans-serif; 
            color: #1C2B2A; 
            font-size: 1.6rem; 
            margin: 0 0 15px 0; 
        }
        .property-description p { margin-bottom: 1rem; }
        .property-description ul { margin: 0.5rem 0 1rem 1.5rem; }
        .property-description li { margin-bottom: 0.3rem; }
        .property-description strong { color: #1C2B2A; }
        
        .features-list {
            background: #fff;
            border: 1px solid #E0D6CC;
            padding: 24px;
            margin: 24px 0;
            border-radius: 16px;
        }
        .features-list h3 {
            font-family: 'Sora', sans-serif;
            color: #1C2B2A;
            font-size: 1.2rem;
            margin: 0 0 12px 0;
        }
        .features-list ul {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 20px;
        }
        .features-list ul li {
            color: #4A5B59;
            font-size: 0.9rem;
            padding-left: 24px;
            position: relative;
        }
        .features-list ul li::before {
            content: '✓';
            color: #C9A46C;
            position: absolute;
            left: 0;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .media-section {
            margin: 24px 0;
        }
        .media-section h3 {
            font-family: 'Sora', sans-serif;
            color: #1C2B2A;
            font-size: 1.2rem;
            margin: 0 0 12px 0;
        }
        .media-section iframe {
            width: 100%;
            max-width: 100%;
            border: 0;
            border-radius: 8px;
        }
        
        .inspection-box {
            background: #fff;
            border: 1px solid #E0D6CC;
            padding: 16px 20px;
            margin: 24px 0;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .inspection-box .icon { font-size: 1.8rem; }
        .inspection-box .label { font-weight: 600; color: #1C2B2A; font-size: 0.9rem; }
        .inspection-box .times { color: #4A5B59; font-size: 0.95rem; }
        
        .property-sidebar { display: flex; flex-direction: column; gap: 24px; }
        
        .feature-box { 
            background: #fff; 
            border: 1px solid #E0D6CC; 
            padding: 20px; 
            border-radius: 16px;
        }
        .feature-grid { 
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
        }
        .feature-item { 
            display: flex; 
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 8px 4px;
            border-radius: 8px;
            background: #F9F7F5;
        }
        .feature-item .icon { 
            color: #C9A46C; 
            font-size: 22px; 
            margin-bottom: 2px;
        }
        .feature-item .value { 
            font-weight: 700; 
            color: #1C2B2A; 
            font-size: 1.1rem;
        }
        .feature-item .label { 
            font-size: 0.7rem; 
            color: #6B7A78; 
        }
        .feature-item.full-width { 
            grid-column: 1 / -1;
            flex-direction: row;
            gap: 8px;
            justify-content: center;
            background: transparent;
            padding: 4px;
        }
        .feature-item.full-width .value { font-weight: 600; font-size: 0.9rem; }
        
        .agent-box { 
            background: #fff; 
            border: 1px solid #E0D6CC; 
            padding: 24px; 
            text-align: center; 
            border-radius: 16px;
        }
        .agent-box .agent-heading {
            font-size: 0.9rem;
            font-weight: 600;
            color: #6B7A78;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 16px;
        }
        .agent-box .agent-avatar { 
            width: 80px; 
            height: 80px; 
            border-radius: 50%; 
            margin: 0 auto 12px; 
            overflow: hidden;
            border: 2px solid #E0D6CC;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #C9A46C;
            font-size: 2rem;
            color: #fff;
            font-weight: 700;
            font-family: 'Sora', sans-serif;
        }
        .agent-box .agent-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .agent-box .name { 
            font-size: 1.2rem; 
            font-weight: 700; 
            color: #1C2B2A; 
        }
        .agent-box .phone, 
        .agent-box .email { 
            color: #4A5B59; 
            font-size: 0.95rem; 
        }
        .agent-box .btn { 
            display: inline-block; 
            background: #C9A46C; 
            color: #fff; 
            padding: 12px 30px; 
            border-radius: 40px; 
            text-decoration: none; 
            font-weight: 600; 
            margin-top: 12px; 
            transition: background 0.2s; 
            width: 100%; 
            text-align: center;
        }
        .agent-box .btn:hover { background: #B48B56; }
        
        .brochure-box {
            background: #fff;
            border: 1px solid #E0D6CC;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
        }
        .brochure-box .btn-brochure {
            display: inline-block;
            background: #1C2B2A;
            color: #fff;
            padding: 14px 20px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            width: 100%;
            text-align: center;
            font-size: 1rem;
            transition: background 0.2s;
            border: none;
            cursor: pointer;
        }
        .brochure-box .btn-brochure:hover { background: #C9A46C; }
        .brochure-box .btn-brochure i { margin-right: 8px; }
        .brochure-box .brochure-note {
            font-size: 0.75rem;
            color: #999;
            margin-top: 8px;
        }
        
        .enquiry-form { 
            background: #fff; 
            border: 1px solid #E0D6CC; 
            padding: 24px; 
            border-radius: 16px;
        }
        .enquiry-form h3 { 
            font-family: 'Sora', sans-serif; 
            color: #1C2B2A; 
            font-size: 1.1rem; 
            margin: 0 0 15px 0; 
        }
        .enquiry-form label { 
            display: block; 
            font-weight: 600; 
            font-size: 0.85rem; 
            color: #1C2B2A; 
            margin-bottom: 3px; 
        }
        .enquiry-form input, 
        .enquiry-form textarea { 
            width: 100%; 
            padding: 10px 12px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            font-size: 0.95rem; 
            margin-bottom: 12px; 
            background: #fafafa; 
        }
        .enquiry-form input:focus, 
        .enquiry-form textarea:focus { 
            border-color: #C9A46C; 
            outline: none; 
        }
        .enquiry-form textarea { min-height: 80px; resize: vertical; }
        .enquiry-form .btn-submit { 
            background: #C9A46C; 
            color: #fff; 
            padding: 12px; 
            border: 0; 
            border-radius: 40px; 
            font-weight: 700; 
            font-size: 1rem; 
            cursor: pointer; 
            width: 100%; 
            transition: background 0.2s; 
        }
        .enquiry-form .btn-submit:hover { background: #B48B56; }
        
        .floorplans-section-sidebar {
            margin: 0;
            background: #fff;
            border: 1px solid #E0D6CC;
            border-radius: 16px;
            padding: 20px;
        }
        .floorplans-section-sidebar h3 {
            font-family: 'Sora', sans-serif;
            color: #1C2B2A;
            font-size: 1.1rem;
            margin: 0 0 12px 0;
        }
        .floorplans-grid-sidebar {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .floorplan-item-sidebar {
            display: block;
            border: 1px solid #E0D6CC;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.2s;
        }
        .floorplan-item-sidebar:hover {
            transform: scale(1.02);
        }
        .floorplan-item-sidebar img {
            width: 100%;
            height: auto;
            display: block;
        }
        
        .property-map-sidebar {
            background: #fff;
            border: 1px solid #E0D6CC;
            border-radius: 16px;
            overflow: hidden;
        }
        .property-map-sidebar .map-label {
            padding: 12px 15px;
            font-weight: 600;
            color: #1C2B2A;
            border-bottom: 1px solid #E0D6CC;
            background: #fff;
            font-size: 0.9rem;
        }
        .property-map-sidebar #property-map-sidebar {
            height: 220px;
            width: 100%;
        }
        .property-map-sidebar .map-attribution {
            padding: 4px 15px;
            font-size: 10px;
            color: #999;
            text-align: right;
            border-top: 1px solid #E0D6CC;
            background: #fff;
        }
        .property-map-sidebar .map-attribution a {
            color: #666;
            text-decoration: none;
        }
        .property-map-sidebar .map-attribution a:hover {
            text-decoration: underline;
        }
        
        .back-link { text-align: center; padding: 40px 20px; }
        .back-link a { color: #8B6914; font-weight: 600; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
        
        .fancybox__container { --fancybox-bg: rgba(0,0,0,0.9); }
        .fancybox__nav { --fancybox-nav-width: 60px; }
        .fancybox__nav button {
            background: rgba(0,0,0,0.5) !important;
            color: #fff !important;
            border-radius: 0 !important;
            width: 60px !important;
            height: 80px !important;
            margin: 0 !important;
        }
        .fancybox__nav button:hover { background: rgba(0,0,0,0.8) !important; }
        .fancybox__nav button svg { width: 30px !important; height: 30px !important; }
        
        @media (max-width: 768px) {
            .property-content .container { grid-template-columns: 1fr; }
            .property-hero h1 { font-size: 1.8rem; }
            .property-hero { padding: 100px 15px 25px; }
            .property-content { padding: 30px 15px; }
            .features-list ul { grid-template-columns: 1fr; }
            .feature-grid { grid-template-columns: 1fr 1fr; }
            .gallery-grid { 
                grid-template-columns: 1fr 1fr; 
                max-height: none;
            }
            .gallery-main { 
                grid-column: span 2; 
                grid-row: span 1;
                min-height: 250px;
                max-height: 350px;
            }
            .gallery-main img { 
                min-height: 250px;
                max-height: 350px;
            }
            .gallery-thumbs {
                grid-column: span 2;
                grid-template-rows: 1fr 1fr;
                grid-template-columns: 1fr 1fr;
                gap: 2px;
            }
            .gallery-thumb { 
                min-height: 120px;
                max-height: 180px;
            }
            .gallery-thumb img { 
                min-height: 120px;
                max-height: 180px;
            }
            .floorplans-grid-sidebar {
                grid-template-columns: 1fr 1fr;
            }
            .property-map-sidebar #property-map-sidebar {
                height: 200px;
            }
        }
        @media (max-width: 480px) {
            .property-hero h1 { font-size: 1.4rem; }
            .property-hero .price { font-size: 1.5rem; }
            .property-content { padding: 20px 15px; }
            .gallery-main { min-height: 180px; max-height: 250px; }
            .gallery-main img { min-height: 180px; max-height: 250px; }
            .gallery-thumb { min-height: 80px; max-height: 120px; }
            .gallery-thumb img { min-height: 80px; max-height: 120px; }
            .gallery-more .more-overlay { font-size: 1.5rem; }
            .feature-grid { grid-template-columns: 1fr; }
            .floorplans-grid-sidebar {
                grid-template-columns: 1fr;
            }
            .inspection-box { flex-wrap: wrap; }
            .property-map-sidebar #property-map-sidebar {
                height: 180px;
            }
        }
    </style>
    
    <!-- ============================================================ -->
    <!-- HERO SECTION - ORIGINAL VERSION + SOLD ON -->
    <!-- ============================================================ -->
    <div class="property-hero">
        <div class="container">
            <h1><?php echo get_the_title($post_id); ?></h1>
            <div class="price-row">
                <span class="status"><?php echo esc_html($status); ?></span>
                <span class="price"><?php echo $formatted_price; ?></span>
            </div>
            <div class="address-sub"><?php echo esc_html($full_address); ?></div>
            <?php if ($status === 'Sold' && !empty($sold_on)) : ?>
                <div class="sold-on">Sold on <?php echo date_i18n('F j, Y', strtotime($sold_on)); ?></div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ============================================================ -->
    <!-- GALLERY - DIRECTLY UNDER HERO -->
    <!-- ============================================================ -->
    <?php if (!empty($gallery_html)) : ?>
        <?php echo $gallery_html; ?>
    <?php endif; ?>
    
    <!-- ============================================================ -->
    <!-- MAIN CONTENT -->
    <!-- ============================================================ -->
    <div class="property-content">
        <div class="container">
            
            <!-- ===== LEFT COLUMN ===== -->
            <div>
                <!-- Property Title (replaces "Property Description") -->
                <?php if (!empty($property_title) || !empty($description_raw)) : ?>
                    <div class="property-description">
                        <?php if (!empty($property_title)) : ?>
                            <h2><?php echo esc_html($property_title); ?></h2>
                        <?php else : ?>
                            <h2>Property Description</h2>
                        <?php endif; ?>
                        <?php if (!empty($description_raw)) : ?>
                            <?php echo nl2br(esc_html($description_raw)); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Features / Highlights (with icons) -->
                <?php if (!empty($features)) : ?>
                    <div class="features-list">
                        <h3>Features &amp; Highlights</h3>
                        <ul>
                            <?php 
                            $features_array = explode("\n", $features);
                            foreach ($features_array as $feature) {
                                $feature = trim($feature);
                                if (!empty($feature)) {
                                    $feature = ltrim($feature, '- *•');
                                    echo '<li>' . esc_html($feature) . '</li>';
                                }
                            }
                            ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <!-- Video -->
                <?php if (!empty($videos)) : ?>
                    <div class="media-section">
                        <h3>Video Tour</h3>
                        <?php 
                        if (strpos($videos, 'youtube.com') !== false || strpos($videos, 'youtu.be') !== false) {
                            $video_id = '';
                            if (strpos($videos, 'youtube.com/watch?v=') !== false) {
                                parse_str(parse_url($videos, PHP_URL_QUERY), $query);
                                $video_id = isset($query['v']) ? $query['v'] : '';
                            } elseif (strpos($videos, 'youtu.be/') !== false) {
                                $video_id = substr(parse_url($videos, PHP_URL_PATH), 1);
                            }
                            if ($video_id) {
                                echo '<iframe src="https://www.youtube.com/embed/' . esc_attr($video_id) . '" height="315" allowfullscreen></iframe>';
                            }
                        } else {
                            echo '<a href="' . esc_url($videos) . '" target="_blank" style="color:#8B6914;text-decoration:underline;">View Video Tour →</a>';
                        }
                        ?>
                    </div>
                <?php endif; ?>
                
                <!-- Virtual Tour -->
                <?php if (!empty($virtual_tour)) : ?>
                    <div class="media-section">
                        <h3>Virtual Tour</h3>
                        <a href="<?php echo esc_url($virtual_tour); ?>" target="_blank" style="display:inline-block;background:#C9A46C;color:#fff;padding:12px 24px;border-radius:40px;text-decoration:none;font-weight:600;">
                            🌐 Launch Virtual Tour
                        </a>
                    </div>
                <?php endif; ?>
                
                <!-- Inspection Times -->
                <?php if (!empty($inspection_times)) : ?>
                    <div class="inspection-box">
                        <span class="icon">🔑</span>
                        <span class="label">Inspection Times:</span>
                        <span class="times"><?php echo esc_html($inspection_times); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- ===== RIGHT COLUMN - SIDEBAR ===== -->
            <div class="property-sidebar">
                
                <!-- Property Specs (with icons) -->
                <div class="feature-box">
                    <div class="feature-grid">
                        <div class="feature-item">
                            <span class="icon">🛏</span>
                            <span class="value"><?php echo esc_html($beds); ?></span>
                            <span class="label">Beds</span>
                        </div>
                        <div class="feature-item">
                            <span class="icon">🛁</span>
                            <span class="value"><?php echo esc_html($baths); ?></span>
                            <span class="label">Baths</span>
                        </div>
                        <div class="feature-item">
                            <span class="icon">🚗</span>
                            <span class="value"><?php echo esc_html($cars); ?></span>
                            <span class="label">Cars</span>
                        </div>
                        <?php if ($land) : ?>
                        <div class="feature-item">
                            <span class="icon">📐</span>
                            <span class="value"><?php echo esc_html($land); ?></span>
                            <span class="label">m²</span>
                        </div>
                        <?php endif; ?>
                        <?php if ($building) : ?>
                        <div class="feature-item">
                            <span class="icon">🏠</span>
                            <span class="value"><?php echo esc_html($building); ?></span>
                            <span class="label">m²</span>
                        </div>
                        <?php endif; ?>
                        <?php if ($year_built) : ?>
                        <div class="feature-item">
                            <span class="icon">🏗️</span>
                            <span class="value"><?php echo esc_html($year_built); ?></span>
                            <span class="label">Built</span>
                        </div>
                        <?php endif; ?>
                        <?php if ($property_type) : ?>
                        <div class="feature-item full-width">
                            <span class="icon">🏷️</span>
                            <span class="value"><?php echo esc_html($property_type); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Agent Box -->
                <div class="agent-box">
                    <div class="agent-heading">Agent</div>
                    
                    <?php 
                    $agent_photo_url = '';
                    $agent_photo_url = get_post_meta($post_id, 'roe_property_agent_photo', true);
                    if (empty($agent_photo_url)) {
                        $agent_id = get_post_meta($post_id, 'roe_property_agent_id', true);
                        if ($agent_id && has_post_thumbnail($agent_id)) {
                            $agent_photo_url = get_the_post_thumbnail_url($agent_id, 'medium');
                        }
                    }
                    if (empty($agent_photo_url) && !empty($agent_name)) {
                        $agent_query = get_posts(array(
                            'post_type' => 'agent',
                            'title' => $agent_name,
                            'posts_per_page' => 1
                        ));
                        if (!empty($agent_query) && has_post_thumbnail($agent_query[0]->ID)) {
                            $agent_photo_url = get_the_post_thumbnail_url($agent_query[0]->ID, 'medium');
                        }
                    }
                    
                    if ($agent_photo_url) : ?>
                        <div class="agent-avatar" style="background:transparent;border:2px solid #E0D6CC;">
                            <img src="<?php echo esc_url($agent_photo_url); ?>" alt="<?php echo esc_attr($agent_name); ?>">
                        </div>
                    <?php else : ?>
                        <div class="agent-avatar">
                            <?php echo substr($agent_name, 0, 1); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="name"><?php echo esc_html($agent_name); ?></div>
                    <?php 
                    $agent_position = '';
                    if (!empty($agent_id)) {
                        $agent_position = get_post_meta($agent_id, 'roe_agent_position', true);
                    }
                    if ($agent_position) : ?>
                        <div style="font-size:0.85rem;color:#C9A46C;font-weight:600;margin-bottom:4px;"><?php echo esc_html($agent_position); ?></div>
                    <?php endif; ?>
                    <div class="phone">📞 <?php echo esc_html($agent_phone); ?></div>
                    <div class="email">✉️ <?php echo esc_html($agent_email); ?></div>
                    <a href="tel:<?php echo preg_replace('/[^0-9]/', '', $agent_phone); ?>" class="btn">Call Agent</a>
                </div>
                
                <!-- Brochure Download Button -->
                <div class="brochure-box">
                    <a href="<?php echo esc_url(class_exists('DG_RE_Property_Brochure') ? DG_RE_Property_Brochure::url($post_id) : home_url('/brochure/?property=' . $post_id . '&download=1')); ?>" target="_blank" rel="noopener" class="btn-brochure">
                        <i class="fas fa-file-pdf"></i> Download Brochure
                    </a>
                    <p class="brochure-note">PDF brochure with full property details</p>
                </div>
                
                <!-- Enquiry Form -->
                <div class="enquiry-form">
                    <h3>📩 Enquire About This Property</h3>
                    <?php if (isset($_GET['enquiry_sent'])) : ?>
                        <div style="background:#E8F5E9;border:1px solid #A5D6A7;color:#2E7D32;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
                            Thank you for your enquiry! We'll be in touch shortly.
                        </div>
                    <?php elseif (isset($_GET['enquiry_error'])) : ?>
                        <div style="background:#FFEBEE;border:1px solid #EF9A9A;color:#C62828;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
                            Something went wrong. Please try again or call us directly.
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <?php wp_nonce_field('dg_re_property_enquiry', 'dg_re_enquiry_nonce'); ?>
                        <input type="hidden" name="property_id" value="<?php echo (int) $post_id; ?>">
                        <input type="hidden" name="property_address" value="<?php echo esc_attr($full_address); ?>">
                        <input type="hidden" name="property_url" value="<?php echo get_permalink($post_id); ?>">
                        <label for="enquiry_name">Your Name *</label>
                        <input type="text" name="enquiry_name" id="enquiry_name" required placeholder="John Smith">
                        
                        <label for="enquiry_email">Your Email *</label>
                        <input type="email" name="enquiry_email" id="enquiry_email" required placeholder="john@example.com">
                        
                        <label for="enquiry_phone">Your Phone</label>
                        <input type="tel" name="enquiry_phone" id="enquiry_phone" placeholder="0412 345 678">
                        
                        <label for="enquiry_message">Message</label>
                        <textarea name="enquiry_message" id="enquiry_message" placeholder="I'm interested in this property and would like more information...">I'm interested in <?php echo esc_attr($full_address); ?></textarea>
                        
                        <button type="submit" name="submit_enquiry" class="btn-submit">Send Enquiry</button>
                    </form>
                </div>
                
                <!-- ===== FLOORPLANS (NOW IN SIDEBAR) ===== -->
                <?php if (!empty($floorplans)) : 
                    $floorplan_ids = array_map('trim', explode(',', $floorplans)); ?>
                    <div class="floorplans-section-sidebar">
                        <h3>Floorplans</h3>
                        <div class="floorplans-grid-sidebar">
                            <?php foreach ($floorplan_ids as $id) : 
                                $image_url = wp_get_attachment_image_url($id, 'medium');
                                $image_full = wp_get_attachment_image_url($id, 'full');
                                if ($image_url) : ?>
                                    <a href="<?php echo esc_url($image_full); ?>" class="floorplan-item-sidebar" data-fancybox="floorplans">
                                        <img src="<?php echo esc_url($image_url); ?>" alt="Floorplan" loading="lazy">
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- ===== MAP (NOW IN SIDEBAR) ===== -->
                <div class="property-map-sidebar">
                    <div class="map-label">📍 <?php echo esc_html($full_address); ?></div>
                    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
                    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
                    <div id="property-map-sidebar"></div>
                    <div class="map-attribution">
                        <a href="https://www.openstreetmap.org/copyright" target="_blank">© OpenStreetMap</a>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var address = '<?php echo esc_js($full_address); ?>';
                            var map = L.map('property-map-sidebar').setView([-28.1667, 153.4333], 13);
                            
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                maxZoom: 19,
                                attribution: '© OpenStreetMap'
                            }).addTo(map);
                            
                            fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(address))
                                .then(response => response.json())
                                .then(data => {
                                    if (data && data.length > 0) {
                                        var lat = parseFloat(data[0].lat);
                                        var lon = parseFloat(data[0].lon);
                                        map.setView([lat, lon], 15);
                                        L.marker([lat, lon]).addTo(map)
                                            .bindPopup('<?php echo esc_js($full_address); ?>')
                                            .openPopup();
                                    } else {
                                        map.setView([-28.1667, 153.4333], 13);
                                        L.marker([-28.1667, 153.4333]).addTo(map)
                                            .bindPopup('<?php echo esc_js($full_address); ?>');
                                    }
                                })
                                .catch(function() {
                                    map.setView([-28.1667, 153.4333], 13);
                                    L.marker([-28.1667, 153.4333]).addTo(map)
                                        .bindPopup('<?php echo esc_js($full_address); ?>');
                                });
                        });
                    </script>
                </div>
                
                <?php if (!empty($external_id)) : ?>
                    <div style="font-size:0.8rem;color:#999;text-align:center;padding:8px;border:1px solid #E0D6CC;border-radius:8px;background:#fff;">
                        Listing ID: <?php echo esc_html($external_id); ?>
                    </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
    
    <!-- ============================================================ -->
    <!-- BACK LINK -->
    <!-- ============================================================ -->
    <div class="back-link">
        <a href="/property">← Back to Property</a>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Fancybox.bind('[data-fancybox="property-gallery"]', {
                infinite: true,
                arrows: true,
                toolbar: {
                    display: {
                        left: ['infobar'],
                        middle: ['zoomIn', 'zoomOut', 'slideshow'],
                        right: ['close']
                    }
                },
                Thumbs: {
                    type: 'classic',
                    Carousel: {
                        show: true
                    }
                }
            });
            
            Fancybox.bind('[data-fancybox="floorplans"]', {
                infinite: true,
                arrows: true,
                Thumbs: {
                    type: 'classic'
                }
            });
        });
    </script>
    <?php
    return ob_get_clean();
}
