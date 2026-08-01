<?php

if (!defined("ABSPATH")) { exit; }

function roe_format_price($price) {
    if ($price === '' || $price === null) {
        return 'Contact for Price';
    }
    $numeric = preg_replace('/[^0-9.]/', '', (string) $price);
    if ($numeric === '') {
        return 'Contact for Price';
    }
    return '$' . number_format((float) $numeric);
}

function roe_property_field($field_name, $post_id = null) {
    if (!$post_id) $post_id = get_the_ID();
    return get_post_meta($post_id, $field_name, true);
}

function roe_property_price($post_id = null) {
    return roe_format_price(roe_property_field('roe_property_price', $post_id));
}

function roe_property_full_address($post_id = null) {
    if (!$post_id) $post_id = get_the_ID();
    $parts = array_filter(array(
        get_post_meta($post_id, 'roe_property_address', true),
        get_post_meta($post_id, 'roe_property_suburb', true),
        get_post_meta($post_id, 'roe_property_state', true),
        get_post_meta($post_id, 'roe_property_postcode', true)
    ));
    return implode(', ', $parts);
}

function roe_property_status_badge($post_id = null) {
    if (!$post_id) $post_id = get_the_ID();
    $status = roe_property_field('roe_property_status', $post_id);
    $colors = array(
        'For Sale' => '#2E7D32',
        'Under Contract' => '#F57C00',
        'Sold' => '#C62828',
        'Withdrawn' => '#666'
    );
    $color = isset($colors[$status]) ? $colors[$status] : '#666';
    return '<span style="background:' . $color . ';color:#fff;padding:4px 16px;border-radius:40px;font-size:12px;font-weight:600;text-transform:uppercase;display:inline-block;">' . esc_html($status) . '</span>';
}


// ============================================================
// 5. PROPERTY SHORTCODE
// ============================================================

function roe_properties_shortcode($atts) {
    $atts = shortcode_atts(array(
        'posts_per_page' => 9, 'status' => '', 'property_type' => '', 'suburb' => '',
        'orderby' => 'date', 'order' => 'DESC'
    ), $atts);
    
    $args = array(
        'post_type' => 'property',
        'posts_per_page' => intval($atts['posts_per_page']),
        'orderby' => $atts['orderby'],
        'order' => $atts['order']
    );
    
    if (!empty($atts['status'])) $args['meta_query'][] = array('key' => 'roe_property_status', 'value' => $atts['status'], 'compare' => '=');
    if (!empty($atts['property_type'])) $args['meta_query'][] = array('key' => 'roe_property_type', 'value' => $atts['property_type'], 'compare' => '=');
    if (!empty($atts['suburb'])) $args['meta_query'][] = array('key' => 'roe_property_suburb', 'value' => $atts['suburb'], 'compare' => '=');
    
    $query = new WP_Query($args);
    
    if (!$query->have_posts()) return '<p style="text-align:center;padding:40px 0;">No properties found.</p>';
    
    ob_start(); ?>
    <style>
        .roe-property-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 30px;
            padding: 40px 20px;
            max-width: 1280px;
            margin: 0 auto;
            background: #F5F2EF;
        }
        .roe-property-card {
            background: #fff;
            border: 1px solid #E0D6CC;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
            border-radius: 16px;
        }
        .roe-property-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        }
        .roe-property-card .card-image {
            height: 220px;
            background: #f0edea;
            overflow: hidden;
            position: relative;
            flex-shrink: 0;
        }
        .roe-property-card .card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.3s ease;
        }
        .roe-property-card:hover .card-image img {
            transform: scale(1.02);
        }
        .roe-property-card .card-status {
            position: absolute;
            top: 12px;
            left: 12px;
            display: inline-block;
            padding: 4px 14px;
            border-radius: 40px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: #fff;
            z-index: 2;
        }
        .roe-property-card .card-content {
            padding: 18px 20px 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .roe-property-card .card-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0 0 4px 0;
            font-family: 'Sora', sans-serif;
        }
        .roe-property-card .card-title a {
            color: #1C2B2A;
            text-decoration: none;
        }
        .roe-property-card .card-title a:hover {
            color: #C9A46C;
        }
        .roe-property-card .card-address {
            font-size: 0.85rem;
            color: #6B7A78;
            margin: 0 0 8px 0;
        }
        .roe-property-card .card-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: #C9A46C;
            margin: 0 0 12px 0;
        }
        .roe-property-card .card-specs {
            display: flex;
            gap: 16px;
            font-size: 0.85rem;
            color: #4A5B59;
            border-top: 1px solid #E0D6CC;
            padding-top: 12px;
            margin-top: auto;
            flex-wrap: wrap;
        }
        .roe-property-card .card-specs span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .roe-property-card .card-specs .icon {
            color: #C9A46C;
            font-size: 16px;
        }
        .roe-property-card .card-link {
            display: inline-block;
            margin-top: 12px;
            color: #8B6914;
            font-weight: 600;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .roe-property-card .card-link:hover {
            text-decoration: underline;
        }
        @media (max-width: 768px) {
            .roe-property-grid {
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                padding: 20px 15px;
            }
            .roe-property-card .card-image {
                height: 180px;
            }
            .roe-property-card {
                border-radius: 12px;
            }
        }
        @media (max-width: 480px) {
            .roe-property-grid {
                grid-template-columns: 1fr;
                gap: 20px;
                padding: 15px;
            }
            .roe-property-card .card-image {
                height: 200px;
            }
            .roe-property-card {
                border-radius: 10px;
            }
        }
    </style>
    <div class="roe-property-grid">
        <?php while ($query->have_posts()) : $query->the_post(); 
            $price = roe_property_price();
            $beds = roe_property_field('roe_property_bedrooms');
            $baths = roe_property_field('roe_property_bathrooms');
            $cars = roe_property_field('roe_property_car_spaces');
            $land = roe_property_field('roe_property_land_size');
            $year_built = roe_property_field('roe_property_year_built');
            $address = roe_property_full_address();
            $status = get_post_meta(get_the_ID(), 'roe_property_status', true);
            $image = has_post_thumbnail() ? get_the_post_thumbnail_url(get_the_ID(), 'medium_large') : '';
            
            $status_colors = array(
                'For Sale' => '#2E7D32',
                'Under Contract' => '#F57C00',
                'Sold' => '#C62828',
                'Withdrawn' => '#666'
            );
            $status_color = isset($status_colors[$status]) ? $status_colors[$status] : '#666';
        ?>
        <div class="roe-property-card">
            <div class="card-image">
                <?php if ($image) : ?>
                    <img src="<?php echo esc_url($image); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
                <?php else : ?>
                    <div style="background:#E0D6CC;height:100%;display:flex;align-items:center;justify-content:center;color:#6B7A78;font-size:0.9rem;">No Image</div>
                <?php endif; ?>
                <?php if ($status) : ?>
                    <span class="card-status" style="background:<?php echo $status_color; ?>"><?php echo esc_html($status); ?></span>
                <?php endif; ?>
            </div>
            <div class="card-content">
                <h3 class="card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                <?php if ($address) : ?>
                    <p class="card-address"><?php echo esc_html($address); ?></p>
                <?php endif; ?>
                <p class="card-price"><?php echo $price; ?></p>
                <div class="card-specs">
                    <?php if ($beds) : ?><span><span class="icon">🛏</span> <?php echo esc_html($beds); ?></span><?php endif; ?>
                    <?php if ($baths) : ?><span><span class="icon">🛁</span> <?php echo esc_html($baths); ?></span><?php endif; ?>
                    <?php if ($cars) : ?><span><span class="icon">🚗</span> <?php echo esc_html($cars); ?></span><?php endif; ?>
                    <?php if ($land) : ?><span><span class="icon">📐</span> <?php echo esc_html($land); ?>m²</span><?php endif; ?>
                    <?php if ($year_built) : ?><span><span class="icon">🏗️</span> <?php echo esc_html($year_built); ?></span><?php endif; ?>
                </div>
                <a href="<?php the_permalink(); ?>" class="card-link">View Property →</a>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php wp_reset_postdata();
    return ob_get_clean();
}
