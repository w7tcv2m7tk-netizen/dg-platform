<?php

if (!defined("ABSPATH")) { exit; }

function roe_agents_shortcode($atts) {
    $atts = shortcode_atts(array(
        'posts_per_page' => -1,
        'orderby' => 'menu_order',
        'order' => 'ASC'
    ), $atts);
    
    $args = array(
        'post_type' => 'agent',
        'posts_per_page' => intval($atts['posts_per_page']),
        'orderby' => $atts['orderby'],
        'order' => $atts['order'],
    );
    
    $query = new WP_Query($args);
    
    if (!$query->have_posts()) {
        return '<p style="text-align:center;padding:40px 0;">No agents found. <a href="/wp-admin/post-new.php?post_type=agent">Add your first agent</a>.</p>';
    }
    
    ob_start(); ?>
    <style>
        .roe-agents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 24px;
            padding: 40px 20px;
            max-width: 1280px;
            margin: 0 auto;
            background: #F5F2EF;
        }
        .roe-agent-card {
            background: #fff;
            border: 1px solid #E0D6CC;
            overflow: hidden;
            text-align: center;
            padding: 24px 16px 20px;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
            min-height: 280px;
            max-height: 360px;
            border-radius: 16px;
        }
        .roe-agent-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 24px rgba(0,0,0,0.08);
        }
        .roe-agent-card .avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 12px;
            background: #C9A46C;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: #fff;
            font-weight: 700;
            font-family: 'Sora', sans-serif;
            overflow: hidden;
            flex-shrink: 0;
        }
        .roe-agent-card .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .roe-agent-card .name {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1C2B2A;
            font-family: 'Sora', sans-serif;
            margin: 0 0 2px 0;
            line-height: 1.3;
        }
        .roe-agent-card .title {
            font-size: 0.8rem;
            color: #C9A46C;
            font-weight: 600;
            margin: 0 0 6px 0;
        }
        .roe-agent-card .phone {
            font-size: 0.85rem;
            color: #4A5B59;
            margin: 0 0 2px 0;
        }
        .roe-agent-card .email {
            font-size: 0.8rem;
            color: #4A5B59;
            margin: 0 0 8px 0;
            word-break: break-all;
        }
        .roe-agent-card .bio {
            font-size: 0.85rem;
            color: #4A5B59;
            line-height: 1.5;
            margin: 0 0 12px 0;
            flex-grow: 1;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .roe-agent-card .btn {
            display: inline-block;
            background: #C9A46C;
            color: #fff;
            padding: 10px 24px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            transition: background 0.2s;
            margin-top: auto;
            width: 100%;
            text-align: center;
            flex-shrink: 0;
        }
        .roe-agent-card .btn:hover {
            background: #B48B56;
        }
        @media (max-width: 768px) {
            .roe-agents-grid {
                grid-template-columns: 1fr 1fr;
                gap: 16px;
                padding: 20px 15px;
            }
            .roe-agent-card {
                min-height: 260px;
                max-height: 320px;
                padding: 20px 12px 16px;
                border-radius: 12px;
            }
            .roe-agent-card .avatar {
                width: 80px;
                height: 80px;
                font-size: 2rem;
            }
        }
        @media (max-width: 480px) {
            .roe-agents-grid {
                grid-template-columns: 1fr;
                gap: 16px;
                padding: 15px;
            }
            .roe-agent-card {
                min-height: 240px;
                max-height: 300px;
                border-radius: 10px;
            }
        }
    </style>
    <div class="roe-agents-grid">
        <?php while ($query->have_posts()) : $query->the_post();
            $title = get_post_meta(get_the_ID(), 'roe_agent_title', true);
            $position = get_post_meta(get_the_ID(), 'roe_agent_position', true);
            $phone = get_post_meta(get_the_ID(), 'roe_agent_phone', true);
            $email = get_post_meta(get_the_ID(), 'roe_agent_email', true);
            $bio = get_post_meta(get_the_ID(), 'roe_agent_bio', true);
            $image = has_post_thumbnail() ? get_the_post_thumbnail_url(get_the_ID(), 'medium') : '';
            $initial = substr(get_the_title(), 0, 1);
            $permalink = get_permalink();
        ?>
        <div class="roe-agent-card">
            <div class="avatar">
                <?php if ($image) : ?>
                    <img src="<?php echo esc_url($image); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
                <?php else : ?>
                    <?php echo esc_html($initial); ?>
                <?php endif; ?>
            </div>
            <h3 class="name"><?php the_title(); ?></h3>
            <?php if ($position) : ?><p class="title"><?php echo esc_html($position); ?></p><?php endif; ?>
            <?php if ($phone) : ?><p class="phone">📞 <?php echo esc_html($phone); ?></p><?php endif; ?>
            <?php if ($email) : ?><p class="email">✉️ <?php echo esc_html($email); ?></p><?php endif; ?>
            <?php if ($bio) : ?><p class="bio"><?php echo esc_html($bio); ?></p><?php endif; ?>
            <a href="<?php echo esc_url($permalink); ?>" class="btn">View Profile</a>
        </div>
        <?php endwhile; ?>
    </div>
    <?php wp_reset_postdata(); return ob_get_clean();
}


// ============================================================
// 5. AGENT PROFILE SHORTCODE (SINGLE AGENT PAGE)
// ============================================================

function roe_agent_profile_shortcode() {
    ob_start();
    
    // Get agent data
    $agent_title = get_post_meta(get_the_ID(), 'roe_agent_title', true);
    $agent_position = get_post_meta(get_the_ID(), 'roe_agent_position', true);
    $agent_phone = get_post_meta(get_the_ID(), 'roe_agent_phone', true);
    $agent_email = get_post_meta(get_the_ID(), 'roe_agent_email', true);
    $agent_bio = get_post_meta(get_the_ID(), 'roe_agent_bio', true);
    $agent_facebook = get_post_meta(get_the_ID(), 'roe_agent_facebook', true);
    $agent_instagram = get_post_meta(get_the_ID(), 'roe_agent_instagram', true);
    $agent_linkedin = get_post_meta(get_the_ID(), 'roe_agent_linkedin', true);
    $agent_twitter = get_post_meta(get_the_ID(), 'roe_agent_twitter', true);
    $agent_youtube = get_post_meta(get_the_ID(), 'roe_agent_youtube', true);
    ?>
    <style>
        .agent-profile-single {
            max-width: 820px;
            margin: 0 auto;
            padding: 140px 20px 60px;
            background: #F5F2EF;
            font-family: 'Inter', sans-serif;
        }
        .agent-profile-single .profile-card {
            background: #fff;
            border: 1px solid #E0D6CC;
            padding: 40px 30px 30px;
            text-align: center;
            border-radius: 16px;
        }
        .agent-profile-single .avatar {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            margin: 0 auto 20px;
            background: #C9A46C;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
            color: #fff;
            font-weight: 700;
            font-family: 'Sora', sans-serif;
            overflow: hidden;
        }
        .agent-profile-single .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .agent-profile-single .name {
            font-size: 2rem;
            font-weight: 700;
            color: #1C2B2A;
            font-family: 'Sora', sans-serif;
            margin: 0 0 4px 0;
        }
        .agent-profile-single .position {
            font-size: 1rem;
            color: #C9A46C;
            font-weight: 600;
            margin: 0 0 16px 0;
        }
        .agent-profile-single .title {
            font-size: 0.9rem;
            color: #6B7A78;
            font-weight: 500;
            margin: 0 0 12px 0;
        }
        .agent-profile-single .contact-row {
            display: flex;
            justify-content: center;
            gap: 24px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .agent-profile-single .contact-row a {
            color: #4A5B59;
            text-decoration: none;
            font-size: 0.95rem;
        }
        .agent-profile-single .contact-row a:hover {
            color: #C9A46C;
        }
        .agent-profile-single .social-row {
            display: flex;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 12px;
        }
        .agent-profile-single .social-row a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #F5F2EF;
            color: #1C2B2A;
            text-decoration: none;
            font-size: 1.1rem;
            transition: all 0.2s;
            border: 1px solid #E0D6CC;
        }
        .agent-profile-single .social-row a:hover {
            background: #C9A46C;
            color: #fff;
            border-color: #C9A46C;
            transform: translateY(-2px);
        }
        .agent-profile-single .bio-section {
            background: #fff;
            border: 1px solid #E0D6CC;
            padding: 30px;
            margin-top: 24px;
            text-align: left;
            border-radius: 16px;
        }
        .agent-profile-single .bio-section h2 {
            font-family: 'Sora', sans-serif;
            color: #1C2B2A;
            font-size: 1.3rem;
            margin: 0 0 12px 0;
        }
        .agent-profile-single .bio-section p {
            color: #4A5B59;
            line-height: 1.8;
            margin: 0;
        }
        .agent-profile-single .listings-section {
            background: #fff;
            border: 1px solid #E0D6CC;
            padding: 30px;
            margin-top: 24px;
            text-align: left;
            border-radius: 16px;
        }
        .agent-profile-single .listings-section h2 {
            font-family: 'Sora', sans-serif;
            color: #1C2B2A;
            font-size: 1.3rem;
            margin: 0 0 15px 0;
        }
        .agent-profile-single .listings-section .listings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .agent-profile-single .listings-section .listings-grid a {
            color: #1C2B2A;
            text-decoration: none;
            font-size: 0.9rem;
            padding: 8px 12px;
            border: 1px solid #E0D6CC;
            border-radius: 4px;
            background: #F9F7F5;
            transition: background 0.2s;
            text-align: center;
        }
        .agent-profile-single .listings-section .listings-grid a:hover {
            background: #C9A46C;
            color: #fff;
            border-color: #C9A46C;
        }
        .agent-profile-single .back-link {
            display: inline-block;
            margin-top: 30px;
            color: #8B6914;
            font-weight: 600;
            text-decoration: none;
        }
        .agent-profile-single .back-link:hover {
            text-decoration: underline;
        }
        @media (max-width: 768px) {
            .agent-profile-single { padding: 120px 15px 40px; }
            .agent-profile-single .profile-card { padding: 30px 20px 20px; }
            .agent-profile-single .avatar { width: 120px; height: 120px; font-size: 2.8rem; }
            .agent-profile-single .name { font-size: 1.6rem; }
            .agent-profile-single .contact-row { flex-direction: column; align-items: center; gap: 6px; }
            .agent-profile-single .bio-section { padding: 20px; }
            .agent-profile-single .listings-section { padding: 20px; }
            .agent-profile-single .listings-section .listings-grid { grid-template-columns: 1fr; }
            .agent-profile-single .social-row { gap: 12px; }
        }
        @media (max-width: 480px) {
            .agent-profile-single { padding: 100px 15px 30px; }
            .agent-profile-single .social-row a { width: 36px; height: 36px; font-size: 1rem; }
        }
    </style>
    <div class="agent-profile-single">
        
        <div class="profile-card">
            <div class="avatar">
                <?php if (has_post_thumbnail()) : ?>
                    <?php echo get_the_post_thumbnail(get_the_ID(), 'medium', array('style' => 'width:100%;height:100%;object-fit:cover;')); ?>
                <?php else : ?>
                    <?php echo substr(get_the_title(), 0, 1); ?>
                <?php endif; ?>
            </div>
            <h1 class="name"><?php the_title(); ?></h1>
            <?php if ($agent_position) : ?>
                <p class="position"><?php echo esc_html($agent_position); ?></p>
            <?php endif; ?>
            <?php if ($agent_title) : ?>
                <p class="title"><?php echo esc_html($agent_title); ?></p>
            <?php endif; ?>
            <div class="contact-row">
                <?php if ($agent_phone) : ?>
                    <a href="tel:<?php echo preg_replace('/[^0-9]/', '', $agent_phone); ?>">📞 <?php echo esc_html($agent_phone); ?></a>
                <?php endif; ?>
                <?php if ($agent_email) : ?>
                    <a href="mailto:<?php echo esc_attr($agent_email); ?>">✉️ <?php echo esc_html($agent_email); ?></a>
                <?php endif; ?>
            </div>
            
            <!-- Social Links -->
            <?php if ($agent_facebook || $agent_instagram || $agent_linkedin || $agent_twitter || $agent_youtube) : ?>
                <div class="social-row">
                    <?php if ($agent_facebook) : ?>
                        <a href="<?php echo esc_url($agent_facebook); ?>" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($agent_instagram) : ?>
                        <a href="<?php echo esc_url($agent_instagram); ?>" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($agent_linkedin) : ?>
                        <a href="<?php echo esc_url($agent_linkedin); ?>" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($agent_twitter) : ?>
                        <a href="<?php echo esc_url($agent_twitter); ?>" target="_blank" rel="noopener noreferrer" aria-label="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($agent_youtube) : ?>
                        <a href="<?php echo esc_url($agent_youtube); ?>" target="_blank" rel="noopener noreferrer" aria-label="YouTube">
                            <i class="fab fa-youtube"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($agent_bio) : ?>
            <div class="bio-section">
                <h2>About <?php the_title(); ?></h2>
                <p><?php echo nl2br(esc_html($agent_bio)); ?></p>
            </div>
        <?php endif; ?>

        <?php
        // Show properties listed by this agent (Active Listings)
        $agent_name = get_the_title();
        $active_listings = new WP_Query(array(
            'post_type' => 'property',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'roe_property_agent_name',
                    'value' => $agent_name,
                    'compare' => '='
                )
            )
        ));
        
        if ($active_listings->have_posts()) : ?>
            <div class="listings-section">
                <h2>Properties by <?php the_title(); ?></h2>
                <div class="listings-grid">
                    <?php while ($active_listings->have_posts()) : $active_listings->the_post(); ?>
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php wp_reset_postdata(); 
        endif; ?>

        <a href="/agents" class="back-link">← Back to Agents</a>

    </div>
    <?php
    return ob_get_clean();
}
