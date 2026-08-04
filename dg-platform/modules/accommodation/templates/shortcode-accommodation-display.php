    
    <div class="dg-accommodation-grid-wrapper">
        <div class="dg-accommodation-grid" style="display:grid;grid-template-columns:repeat(<?php echo intval($atts['columns']); ?>,1fr);gap:2rem;max-width:1200px;margin:0 auto;padding:2rem 0;">
            <?php while ($query->have_posts()) : $query->the_post(); 
                $meta = get_post_meta(get_the_ID(), '_dg_meta_data', true);
                $meta = is_array($meta) ? $meta : array();
                
                $price = DG_Acc_Shortcode_Render::card_price(get_the_ID());
                $sleeps = DG_Acc_Shortcode_Render::field('dg_sleeps');
                $beds = DG_Acc_Shortcode_Render::field('dg_bedrooms');
                $baths = DG_Acc_Shortcode_Render::field('dg_bathrooms');
                $max_guests = DG_Acc_Shortcode_Render::field('dg_max_guests');
                $min_nights = DG_Acc_Shortcode_Render::field('dg_min_nights');
                $size = DG_Acc_Shortcode_Render::field('dg_size');
                $featured = DG_Acc_Shortcode_Render::field('dg_featured');
                $description = DG_Acc_Shortcode_Render::field('dg_description');
                $image = has_post_thumbnail() ? get_the_post_thumbnail_url(get_the_ID(), 'medium_large') : '';
                $terms = get_the_terms(get_the_ID(), 'dg_accommodation_type');
                $type = $terms && !is_wp_error($terms) ? $terms[0]->name : '';
                $perma = get_permalink();
                
                $features = get_post_meta(get_the_ID(), 'dg_features', true);
                $features = is_array($features) ? $features : array();
                $active_features = array_filter($features);
                $feature_list = array_slice(array_keys($active_features), 0, 6);
                $post_id = get_the_ID();
                $coming_soon = class_exists('DG_Acc_Listing_Status') ? DG_Acc_Listing_Status::is_coming_soon($post_id) : false;
                $listing_label = class_exists('DG_Acc_Listing_Status') ? DG_Acc_Listing_Status::public_label($post_id) : '';
                $bookable = class_exists('DG_Acc_Listing_Status') ? DG_Acc_Listing_Status::is_bookable($post_id) : true;
                if (!$description && class_exists('DG_Acc_Frontend')) {
                    $description = DG_Acc_Frontend::get_description($post_id);
                }
                $card_class = 'dg-accommodation-card' . ($coming_soon ? ' dg-acc-card-coming-soon' : '');
            ?>
            <div class="<?php echo esc_attr($card_class); ?>" style="background:#FFFFFF;border-radius:24px;overflow:hidden;border:1px solid #E0D6CC;transition:all 0.3s ease;display:flex;flex-direction:column;width:100%;box-sizing:border-box;<?php echo $coming_soon ? 'opacity:0.94;' : ''; ?>">
                <div class="dg-card-image" style="height:240px;overflow:hidden;position:relative;width:100%;">
                    <?php if ($image) : ?>
                        <a href="<?php echo esc_url($perma); ?>" style="display:block;width:100%;height:100%;">
                            <img src="<?php echo esc_url($image); ?>" alt="<?php the_title_attribute(); ?>" style="width:100%;height:100%;object-fit:cover;transition:transform 0.4s ease;display:block;<?php echo $coming_soon ? 'filter:grayscale(30%) brightness(0.94);' : ''; ?>" loading="lazy">
                        </a>
                    <?php else : ?>
                        <div style="background:#E0D6CC;height:100%;display:flex;align-items:center;justify-content:center;color:#6B7A78;font-size:0.9rem;">No Image</div>
                    <?php endif; ?>
                    <?php if ($coming_soon && $listing_label) : ?>
                        <span style="position:absolute;top:1rem;left:1rem;background:linear-gradient(135deg,#1C2B2A 0%,#2D4A2E 100%);color:#E8DFD0;padding:0.45rem 1rem;border-radius:40px;font-size:0.65rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;z-index:2;"><?php echo esc_html($listing_label); ?></span>
                    <?php elseif ($listing_label) : ?>
                        <span style="position:absolute;top:1rem;left:1rem;background:#1C2B2A;color:#fff;padding:0.3rem 0.8rem;border-radius:40px;font-size:0.7rem;z-index:2;"><?php echo esc_html($listing_label); ?></span>
                    <?php elseif ($featured) : ?>
                        <span style="position:absolute;top:1rem;right:1rem;background:#B9A48A;color:#FFFFFF;padding:0.3rem 0.8rem;border-radius:40px;font-size:0.7rem;font-weight:600;z-index:2;">⭐ Featured</span>
                    <?php endif; ?>
                    <?php if ($bookable && !$coming_soon && $price && $price !== 'Contact for Price') : ?>
                        <span style="position:absolute;bottom:1rem;left:1rem;background:rgba(44,62,80,0.9);color:#fff;padding:0.4rem 1rem;border-radius:40px;font-size:0.85rem;font-weight:600;z-index:2;"><?php echo esc_html($price); ?></span>
                    <?php endif; ?>
                </div>
                <div class="dg-card-content" style="padding:1.5rem;text-align:center;flex:1;display:flex;flex-direction:column;">
                    <?php if ($type) : ?>
                        <div style="font-family:'Cormorant Garamond',serif;font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:2px;color:#B9A48A;margin-bottom:0.25rem;"><?php echo esc_html($type); ?></div>
                    <?php endif; ?>
                    <h3 class="dg-card-title" style="font-family:'Cormorant Garamond',serif;font-size:1.4rem;font-weight:600;color:#2F2F2F;margin:0 0 0.5rem 0;">
                        <a href="<?php echo $perma; ?>" style="color:#2F2F2F;text-decoration:none;transition:color 0.3s ease;"><?php the_title(); ?></a>
                    </h3>
                    
                    <?php if ($description) : ?>
                        <p style="font-size:0.85rem;line-height:1.5;color:#4A5B59;margin:0 0 1rem 0;flex:1;"><?php echo esc_html(wp_trim_words($description, 20)); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($feature_list)) : ?>
                        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin:0.5rem 0 1rem 0;justify-content:center;">
                            <?php foreach ($feature_list as $feature_key) : 
                                $icon = isset($feature_icons[$feature_key]) ? $feature_icons[$feature_key] : '✓';
                                $label = isset($feature_labels[$feature_key]) ? $feature_labels[$feature_key] : $feature_key;
                            ?>
                                <span style="display:inline-flex;align-items:center;gap:4px;background:#F7F4EE;padding:0.3rem 0.8rem;border-radius:40px;font-size:0.7rem;color:#2F2F2F;white-space:nowrap;">
                                    <span style="font-size:0.7rem;"><?php echo $icon; ?></span> <?php echo $label; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;justify-content:center;margin:0.5rem 0 1rem 0;">
                        <?php if ($sleeps) : ?>
                            <span style="display:inline-flex;align-items:center;gap:4px;font-size:0.7rem;color:#5A6B67;background:#F7F4EE;padding:0.2rem 0.6rem;border-radius:40px;">🛏️ Sleeps <?php echo esc_html($sleeps); ?></span>
                        <?php endif; ?>
                        <?php if ($beds) : ?>
                            <span style="display:inline-flex;align-items:center;gap:4px;font-size:0.7rem;color:#5A6B67;background:#F7F4EE;padding:0.2rem 0.6rem;border-radius:40px;">🚪 <?php echo esc_html($beds); ?> bed<?php echo $beds > 1 ? 's' : ''; ?></span>
                        <?php endif; ?>
                        <?php if ($baths) : ?>
                            <span style="display:inline-flex;align-items:center;gap:4px;font-size:0.7rem;color:#5A6B67;background:#F7F4EE;padding:0.2rem 0.6rem;border-radius:40px;">🛁 <?php echo esc_html($baths); ?> bath<?php echo $baths > 1 ? 's' : ''; ?></span>
                        <?php endif; ?>
                        <?php if ($max_guests) : ?>
                            <span style="display:inline-flex;align-items:center;gap:4px;font-size:0.7rem;color:#5A6B67;background:#F7F4EE;padding:0.2rem 0.6rem;border-radius:40px;">👥 Max <?php echo esc_html($max_guests); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($coming_soon) : ?>
                        <p style="font-size:0.85rem;line-height:1.5;color:#6B7A78;margin:0 0 1rem 0;flex:1;">Opening soon — register your interest on the property page.</p>
                        <a href="<?php echo esc_url($perma); ?>" class="dg-btn dg-btn-soon" style="display:inline-flex;align-items:center;justify-content:center;gap:8px;background:transparent;color:#1C2B2A;border:2px solid #B9A48A;font-weight:600;font-size:0.85rem;padding:0.65rem 1.5rem;border-radius:40px;text-decoration:none;transition:all 0.3s ease;margin-top:auto;align-self:center;letter-spacing:0.04em;">COMING SOON →</a>
                    <?php else : ?>
                    <a href="<?php echo esc_url($perma); ?>" class="dg-btn" style="display:inline-flex;align-items:center;justify-content:center;gap:8px;background:#B9A48A;color:#FFFFFF;font-weight:600;font-size:0.9rem;padding:0.7rem 1.5rem;border-radius:40px;text-decoration:none;transition:all 0.3s ease;margin-top:auto;border:none;cursor:pointer;align-self:center;">
                        View Details →
                    </a>
                    <?php endif; ?>
                </div>
                <?php
                $airbnb_id = get_post_meta($post_id, 'dg_airbnb_id', true);
                if (!$airbnb_id && class_exists('DG_Reviews_Airbnb')) {
                    $airbnb_id = DG_Reviews_Airbnb::resolve_listing_id('', $post_id);
                }
                if ($airbnb_id && class_exists('DG_Reviews_Airbnb') && method_exists('DG_Reviews_Airbnb', 'render_listing_reviews')) :
                    ?>
                    <div class="dg-card-reviews-wrap">
                        <?php echo DG_Reviews_Airbnb::render_listing_reviews($airbnb_id, [
                            'limit' => 4,
                            'title' => 'What guests say',
                            'accommodation_id' => $post_id,
                        ]); ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    
    <style>
        .dg-accommodation-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 30px -12px rgba(0, 0, 0, 0.1);
            border-color: #B9A48A;
        }
        .dg-accommodation-card:hover .dg-card-image img {
            transform: scale(1.03);
        }
        .dg-card-title a:hover {
            color: #B9A48A !important;
        }
        .dg-btn:hover {
            background: #A8947A !important;
            transform: translateY(-2px);
        }
        .dg-acc-card-coming-soon:hover {
            transform: none;
            box-shadow: 0 12px 24px -12px rgba(28,43,42,0.12);
        }
        .dg-btn-soon:hover {
            background: #F7F4EE !important;
        }
        @media (max-width: 992px) {
            .dg-accommodation-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 1.5rem !important;
                padding: 1.5rem 0 !important;
            }
        }
        @media (max-width: 768px) {
            .dg-accommodation-grid {
                grid-template-columns: 1fr !important;
                gap: 1.5rem !important;
                padding: 1rem 0 !important;
                max-width: 100% !important;
            }
            .dg-card-image {
                height: 200px !important;
            }
            .dg-card-content {
                padding: 1.25rem !important;
            }
            .dg-card-title {
                font-size: 1.2rem !important;
            }
        }
        @media (max-width: 480px) {
            .dg-card-image {
                height: 180px !important;
            }
            .dg-card-content {
                padding: 1rem !important;
            }
            .dg-card-title {
                font-size: 1.1rem !important;
            }
            .dg-btn {
                font-size: 0.8rem !important;
                padding: 0.6rem 1.2rem !important;
                width: 100% !important;
            }
        }
    </style>
