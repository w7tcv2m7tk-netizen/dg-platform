    <!-- ============================================ -->
    <!-- ACCOMMODATION DETAILS - FULL WIDTH HERO -->
    <!-- ============================================ -->
    <div class="dg-single-wrapper">
        
        <!-- ===== HERO SECTION - FULL WIDTH ===== -->
        <div class="dg-single-hero">
            <div class="dg-hero-image">
                <?php if (has_post_thumbnail($post_id)): ?>
                    <?php echo get_the_post_thumbnail($post_id, 'full', array('class' => 'dg-hero-img')); ?>
                <?php else: ?>
                    <div class="dg-hero-placeholder"></div>
                <?php endif; ?>
                <div class="dg-hero-overlay"></div>
            </div>
            
            <div class="dg-hero-content">
                <?php if ($type) : ?>
                    <div class="dg-hero-type"><?php echo esc_html($type); ?></div>
                <?php endif; ?>
                <h1 class="dg-hero-title"><?php echo esc_html(get_the_title($post_id)); ?></h1>
                <div class="dg-hero-price">
                    <span class="dg-price-amount"><?php echo $price_display; ?></span>
                    <?php if ($featured) : ?>
                        <span class="dg-featured-badge">⭐ Featured</span>
                    <?php endif; ?>
                </div>
                <div class="dg-hero-meta">
                    <?php if ($sleeps) : ?>
                        <span class="dg-meta-tag">🛏️ Sleeps <?php echo esc_html($sleeps); ?></span>
                    <?php endif; ?>
                    <?php if ($beds) : ?>
                        <span class="dg-meta-tag">🚪 <?php echo esc_html($beds); ?> Bedroom<?php echo $beds > 1 ? 's' : ''; ?></span>
                    <?php endif; ?>
                    <?php if ($baths) : ?>
                        <span class="dg-meta-tag">🛁 <?php echo esc_html($baths); ?> Bathroom<?php echo $baths > 1 ? 's' : ''; ?></span>
                    <?php endif; ?>
                    <?php if ($max_guests) : ?>
                        <span class="dg-meta-tag">👥 Max <?php echo esc_html($max_guests); ?> Guests</span>
                    <?php endif; ?>
                    <?php if ($min_nights) : ?>
                        <span class="dg-meta-tag">📅 Min <?php echo esc_html($min_nights); ?> Nights</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- ===== MAIN CONTENT ===== -->
        <div class="dg-single-body">
            
            <!-- ===== LEFT COLUMN ===== -->
            <div class="dg-single-main">

                <!-- Gallery first (Fancybox lightbox — same as Roe Realty) -->
                <?php if (!empty($gallery_ids) || has_post_thumbnail($post_id)) : ?>
                    <div class="dg-section dg-section-gallery">
                        <h2 class="dg-section-title">📸 Gallery</h2>
                        <?php
                        if (class_exists('DG_Acc_Gallery')) {
                            echo DG_Acc_Gallery::render($post_id);
                        }
                        ?>
                    </div>
                <?php endif; ?>
                
                <!-- Description & Calendar -->
                <div class="dg-section">
                    <h2 class="dg-section-title">About This Accommodation</h2>
                    <?php if ($description) : ?>
                        <div class="dg-section-content">
                            <?php echo wp_kses_post(nl2br($description)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($post->post_content)): ?>
                        <div class="dg-section-content" style="margin-top:1rem;">
                            <?php echo apply_filters('the_content', $post->post_content); ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Calendar directly under description -->
                    <?php if (!empty($bookable)) : ?>
                    <div class="dg-calendar-under-description">
                        <h3 style="font-family:'Cormorant Garamond',serif;font-size:1.2rem;font-weight:600;color:#2F2F2F;margin:1.5rem 0 0.75rem 0;padding-bottom:0.5rem;border-bottom:2px solid #B9A48A;">📅 Check Availability</h3>
                        <?php echo do_shortcode('[dg_calendar accommodation_id="' . (int) $post_id . '" mode="inline"]'); ?>
                    </div>
                    <?php elseif (!empty($listing_label)) : ?>
                    <div class="dg-calendar-under-description" style="background:#F7F4EE;border-radius:16px;padding:1.25rem;margin-top:1rem;text-align:center;">
                        <p style="margin:0;color:#4A5B59;font-size:0.95rem;">🔜 <strong><?php echo esc_html($listing_label); ?></strong> — register your interest and we will notify you when bookings open.</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Video Tour -->
                <?php if ($video_url) : ?>
                    <div class="dg-section">
                        <h2 class="dg-section-title">🎥 Video Tour</h2>
                        <div class="dg-video-wrapper">
                            <?php 
                            $embed_url = $video_url;
                            if (strpos($video_url, 'youtube.com/watch') !== false || strpos($video_url, 'youtu.be') !== false) {
                                $video_id = '';
                                parse_str(parse_url($video_url, PHP_URL_QUERY), $params);
                                if (isset($params['v'])) {
                                    $video_id = $params['v'];
                                } else {
                                    $path = parse_url($video_url, PHP_URL_PATH);
                                    $video_id = trim($path, '/');
                                }
                                if ($video_id) {
                                    $embed_url = 'https://www.youtube.com/embed/' . $video_id;
                                }
                            } elseif (strpos($video_url, 'vimeo.com') !== false) {
                                $video_id = preg_replace('/[^0-9]/', '', parse_url($video_url, PHP_URL_PATH));
                                if ($video_id) {
                                    $embed_url = 'https://player.vimeo.com/video/' . $video_id;
                                }
                            }
                            ?>
                            <iframe src="<?php echo esc_url($embed_url); ?>" allowfullscreen></iframe>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Guest Reviews (Airbnb via DG Platform) -->
                <?php if (class_exists('DG_Reviews_Airbnb')) :
                    $reviews_html = DG_Reviews_Airbnb::render_listing_reviews('', [
                        'accommodation_id' => (int) $post_id,
                        'limit' => 6,
                        'title' => 'What Guests Say',
                    ]);
                    if ($reviews_html) : ?>
                    <div class="dg-section dg-section-reviews">
                        <?php echo $reviews_html; ?>
                    </div>
                    <?php endif;
                endif; ?>
                
            </div>
            
            <!-- ===== RIGHT COLUMN (SIDEBAR) ===== -->
            <div class="dg-single-sidebar">
                
                <!-- Quick Details -->
                <div class="dg-sidebar-card">
                    <h3 class="dg-sidebar-title">📋 Quick Details</h3>
                    <div class="dg-details-list">
                        <?php if ($sleeps) : ?>
                            <div class="dg-detail-item"><span class="dg-detail-label">🛏️ Sleeps</span><span class="dg-detail-value"><?php echo esc_html($sleeps); ?></span></div>
                        <?php endif; ?>
                        <?php if ($beds) : ?>
                            <div class="dg-detail-item"><span class="dg-detail-label">🚪 Bedrooms</span><span class="dg-detail-value"><?php echo esc_html($beds); ?></span></div>
                        <?php endif; ?>
                        <?php if ($baths) : ?>
                            <div class="dg-detail-item"><span class="dg-detail-label">🛁 Bathrooms</span><span class="dg-detail-value"><?php echo esc_html($baths); ?></span></div>
                        <?php endif; ?>
                        <?php if ($max_guests) : ?>
                            <div class="dg-detail-item"><span class="dg-detail-label">👥 Max Guests</span><span class="dg-detail-value"><?php echo esc_html($max_guests); ?></span></div>
                        <?php endif; ?>
                        <?php if ($min_nights) : ?>
                            <div class="dg-detail-item"><span class="dg-detail-label">📅 Min Nights</span><span class="dg-detail-value"><?php echo esc_html($min_nights); ?></span></div>
                        <?php endif; ?>
                        <?php if ($size) : ?>
                            <div class="dg-detail-item"><span class="dg-detail-label">📐 Size</span><span class="dg-detail-value"><?php echo esc_html($size); ?> m²</span></div>
                        <?php endif; ?>
                        <?php if ($checkin_time) : ?>
                            <div class="dg-detail-item"><span class="dg-detail-label">⏰ Check-in</span><span class="dg-detail-value"><?php echo esc_html($checkin_time); ?></span></div>
                        <?php endif; ?>
                        <?php if ($checkout_time) : ?>
                            <div class="dg-detail-item"><span class="dg-detail-label">⏰ Check-out</span><span class="dg-detail-value"><?php echo esc_html($checkout_time); ?></span></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Features & Amenities - MOVED TO SIDEBAR -->
                <?php if (!empty($active_features)) : ?>
                    <div class="dg-sidebar-card">
                        <h3 class="dg-sidebar-title">✨ Features & Amenities</h3>
                        <div class="dg-features-sidebar">
                            <?php foreach ($active_features as $key => $value):
                                if ($value && isset($feature_labels[$key])):
                            ?>
                                <div class="dg-feature-item"><?php echo $feature_labels[$key]; ?></div>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Location - MOVED TO SIDEBAR -->
                <?php if ($address || ($latitude && $longitude)) : ?>
                    <div class="dg-sidebar-card">
                        <h3 class="dg-sidebar-title">📍 Location</h3>
                        <?php if ($address) : ?>
                            <p class="dg-address-text"><?php echo esc_html($address); ?></p>
                        <?php endif; ?>
                        <?php if ($latitude && $longitude) : ?>
                            <div class="dg-map-wrapper">
                                <iframe 
                                    src="https://www.google.com/maps?q=<?php echo $latitude; ?>,<?php echo $longitude; ?>&z=15&output=embed"
                                    allowfullscreen="" 
                                    loading="lazy">
                                </iframe>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Pricing Details -->
                <?php if ($weekday_rate > 0 || $weekend_rate > 0) : ?>
                    <div class="dg-sidebar-card">
                        <h3 class="dg-sidebar-title">💰 Pricing</h3>
                        <div class="dg-details-list">
                            <?php if ($weekday_rate > 0) : ?>
                                <div class="dg-detail-item"><span class="dg-detail-label">Weekday Rate</span><span class="dg-detail-value">$<?php echo number_format($weekday_rate, 2); ?></span></div>
                            <?php endif; ?>
                            <?php if ($weekend_rate > 0) : ?>
                                <div class="dg-detail-item"><span class="dg-detail-label">Weekend Rate</span><span class="dg-detail-value">$<?php echo number_format($weekend_rate, 2); ?></span></div>
                            <?php endif; ?>
                            <?php if ($weekday_peak > 0) : ?>
                                <div class="dg-detail-item"><span class="dg-detail-label">Weekday Peak</span><span class="dg-detail-value">$<?php echo number_format($weekday_peak, 2); ?></span></div>
                            <?php endif; ?>
                            <?php if ($weekend_peak > 0) : ?>
                                <div class="dg-detail-item"><span class="dg-detail-label">Weekend Peak</span><span class="dg-detail-value">$<?php echo number_format($weekend_peak, 2); ?></span></div>
                            <?php endif; ?>
                            <?php if ($security_deposit > 0) : ?>
                                <div class="dg-detail-item"><span class="dg-detail-label">🔒 Security Deposit</span><span class="dg-detail-value">$<?php echo number_format($security_deposit, 2); ?></span></div>
                            <?php endif; ?>
                            <?php if ($cleaning_fee > 0) : ?>
                                <div class="dg-detail-item"><span class="dg-detail-label">🧹 Cleaning Fee</span><span class="dg-detail-value">$<?php echo number_format($cleaning_fee, 2); ?></span></div>
                            <?php endif; ?>
                            <?php if ($extra_guest_fee > 0) : ?>
                                <div class="dg-detail-item"><span class="dg-detail-label">👤 Extra Guest Fee</span><span class="dg-detail-value">$<?php echo number_format($extra_guest_fee, 2); ?></span></div>
                            <?php endif; ?>
                            <?php if ($last_minute > 0) : ?>
                                <div class="dg-detail-item"><span class="dg-detail-label">⚡ Last Minute Discount</span><span class="dg-detail-value"><?php echo $last_minute; ?>%</span></div>
                            <?php endif; ?>
                            <?php if ($early_bird > 0) : ?>
                                <div class="dg-detail-item"><span class="dg-detail-label">🐦 Early Bird Discount</span><span class="dg-detail-value"><?php echo $early_bird; ?>%</span></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Book Your Stay -->
                <?php if (!empty($bookable)) : ?>
                <div class="dg-sidebar-card" id="dg-book-now-checkout">
                    <?php echo do_shortcode('[dg_accommodation_enquiry accommodation_id="' . (int) $post_id . '" layout="compact"]'); ?>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
    
    <style>
        /* ============================================ */
        /* ACCOMMODATION DETAILS - FULL WIDTH HERO */
        /* ============================================ */
        
        .dg-single-wrapper {
            max-width: 100%;
            margin: 0 auto;
            background: #F7F4EE;
        }
        
        /* ===== HERO SECTION - FULL WIDTH ===== */
        .dg-single-hero {
            position: relative;
            min-height: 550px;
            width: 100%;
            display: flex;
            align-items: flex-end;
            overflow: hidden;
        }
        
        .dg-hero-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }
        
        .dg-hero-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        
        .dg-hero-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #2F2F2F 0%, #4A5B59 100%);
        }
        
        .dg-hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
            background: linear-gradient(180deg, rgba(15,15,15,0.45) 0%, rgba(15,15,15,0.92) 100%) !important;
        }
        
        .dg-hero-content {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 1280px;
            margin: 0 auto;
            padding: 2.5rem;
            padding-bottom: 3rem;
            color: #fff !important;
        }
        
        .dg-hero-type {
            font-family: 'Cormorant Garamond', serif;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 3px;
            color: #B9A48A;
            margin-bottom: 0.5rem;
        }
        
        .dg-hero-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 3.2rem;
            font-weight: 600;
            margin: 0 0 0.5rem 0;
            color: #ffffff !important;
            text-shadow: 0 2px 24px rgba(0,0,0,0.55);
        }
        
        .dg-hero-price {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        
        .dg-price-amount {
            font-size: 1.2rem;
            font-weight: 600;
            color: #B9A48A;
        }
        
        .dg-featured-badge {
            background: #B9A48A;
            color: #FFFFFF;
            padding: 0.2rem 0.8rem;
            border-radius: 40px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .dg-hero-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .dg-meta-tag {
            background: rgba(255,255,255,0.15);
            color: #fff;
            padding: 0.25rem 0.8rem;
            border-radius: 40px;
            font-size: 0.75rem;
        }
        
        /* ===== BODY ===== */
        .dg-single-body {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            padding: 2rem;
            max-width: 1280px;
            margin: 0 auto;
        }
        
        /* ===== MAIN CONTENT ===== */
        .dg-single-main {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        
        .dg-section {
            background: #FFFFFF;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px -8px rgba(0,0,0,0.06);
        }
        
        .dg-section-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.4rem;
            font-weight: 600;
            color: #2F2F2F;
            margin: 0 0 0.75rem 0;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #B9A48A;
        }
        
        .dg-section-content {
            line-height: 1.8;
            color: #4A5B59;
            font-size: 0.95rem;
        }
        
        /* ===== CALENDAR UNDER DESCRIPTION ===== */
        .dg-calendar-under-description {
            margin-top: 0.5rem;
        }
        
        .dg-calendar-under-description h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.2rem;
            font-weight: 600;
            color: #2F2F2F;
            margin: 1.5rem 0 0.75rem 0;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #B9A48A;
        }
        
        /* ===== GALLERY ===== */
        .dg-gallery-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            margin-top: 0.5rem;
        }
        
        .dg-gallery-item {
            border-radius: 8px;
            overflow: hidden;
            aspect-ratio: 1/1;
            background: #F7F4EE;
        }
        
        .dg-gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .dg-gallery-item:hover img {
            transform: scale(1.05);
        }
        
        /* ===== VIDEO ===== */
        .dg-video-wrapper {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 0.5rem;
        }
        
        .dg-video-wrapper iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 0;
        }
        
        /* ===== SIDEBAR ===== */
        .dg-single-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .dg-sidebar-card {
            background: #FFFFFF;
            border-radius: 16px;
            padding: 1.25rem;
            box-shadow: 0 4px 20px -8px rgba(0,0,0,0.06);
        }
        
        .dg-sidebar-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.1rem;
            font-weight: 600;
            color: #2F2F2F;
            margin: 0 0 0.75rem 0;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #B9A48A;
            text-align: center;
        }
        
        /* ===== FEATURES SIDEBAR ===== */
        .dg-features-sidebar {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }
        
        .dg-features-sidebar .dg-feature-item {
            padding: 0.5rem 0.8rem;
            background: #F7F4EE;
            border-radius: 6px;
            font-size: 0.85rem;
            color: #2F2F2F;
        }
        
        /* ===== LOCATION SIDEBAR ===== */
        .dg-address-text {
            color: #4A5B59;
            font-size: 0.9rem;
            margin: 0.5rem 0;
            word-break: break-word;
        }
        
        .dg-map-wrapper {
            border-radius: 8px;
            overflow: hidden;
            margin-top: 0.5rem;
            aspect-ratio: 16/9;
            background: #E0D6CC;
        }
        
        .dg-map-wrapper iframe {
            width: 100%;
            height: 100%;
            border: 0;
        }
        
        /* ===== DETAILS LIST ===== */
        .dg-details-list {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .dg-detail-item {
            display: flex;
            justify-content: space-between;
            padding: 0.4rem 0;
            border-bottom: 1px solid #F0EBE4;
            font-size: 0.9rem;
        }
        
        .dg-detail-item:last-child {
            border-bottom: none;
        }
        
        .dg-detail-label {
            color: #5A6B67;
        }
        
        .dg-detail-value {
            font-weight: 600;
            color: #2F2F2F;
        }
        
        /* ============================================ */
        /* ===== RESPONSIVE ===== */
        /* ============================================ */
        
        @media (max-width: 992px) {
            .dg-single-body {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                padding: 1.5rem;
            }
            
            .dg-single-hero {
                min-height: 450px;
            }
            
            .dg-hero-title {
                font-size: 2.5rem;
            }
            
            .dg-gallery-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .dg-single-hero {
                min-height: 380px;
            }
            
            .dg-hero-content {
                padding: 1.5rem;
                padding-bottom: 2rem;
            }
            
            .dg-hero-title {
                font-size: 2rem;
            }
            
            .dg-hero-meta {
                gap: 0.4rem;
            }
            
            .dg-meta-tag {
                font-size: 0.65rem;
                padding: 0.2rem 0.6rem;
            }
            
            .dg-single-body {
                padding: 1rem;
                gap: 1rem;
            }
            
            .dg-section {
                padding: 1.25rem;
                border-radius: 12px;
            }
            
            .dg-section-title {
                font-size: 1.2rem;
            }
            
            .dg-gallery-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.5rem;
            }
            
            .dg-sidebar-card {
                padding: 1rem;
                border-radius: 12px;
            }
            
            .dg-detail-item {
                font-size: 0.85rem;
                padding: 0.3rem 0;
            }
        }
        
        @media (max-width: 480px) {
            .dg-single-hero {
                min-height: 320px;
            }
            
            .dg-hero-title {
                font-size: 1.6rem;
            }
            
            .dg-hero-content {
                padding: 1rem;
                padding-bottom: 1.5rem;
            }
            
            .dg-price-amount {
                font-size: 1rem;
            }
            
            .dg-single-body {
                padding: 0.75rem;
                gap: 0.75rem;
            }
            
            .dg-section {
                padding: 1rem;
                border-radius: 10px;
            }
            
            .dg-section-title {
                font-size: 1.1rem;
            }
            
            .dg-section-content {
                font-size: 0.85rem;
            }
            
            .dg-gallery-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.4rem;
            }
            
            .dg-sidebar-card {
                padding: 0.75rem;
                border-radius: 10px;
            }
            
            .dg-sidebar-title {
                font-size: 1rem;
            }
            
            .dg-detail-item {
                font-size: 0.8rem;
                padding: 0.25rem 0;
            }
            
            .dg-features-sidebar .dg-feature-item {
                font-size: 0.75rem;
                padding: 0.4rem 0.6rem;
            }
            
            .dg-calendar-under-description h3 {
                font-size: 1rem;
            }
        }
    </style>
