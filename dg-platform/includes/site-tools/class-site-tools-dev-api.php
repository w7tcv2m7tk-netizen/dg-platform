<?php
/**
 * Authenticated Site Tools REST endpoints for Gen 2 platform connector.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Site_Tools_Dev_API {

    public static function register_routes() {
        register_rest_route(DG_REST_NAMESPACE, '/site/health', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_site_health'],
            'permission_callback' => [__CLASS__, 'can_access'],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/site/content', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_site_content'],
            'permission_callback' => [__CLASS__, 'can_access'],
            'args' => [
                'include_posts' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
                'per_page' => [
                    'type' => 'integer',
                    'default' => 40,
                    'minimum' => 1,
                    'maximum' => 100,
                ],
            ],
        ]);
    }

    public static function can_access($request) {
        if (!class_exists('DG_Dev_API')) {
            return false;
        }
        return DG_Dev_API::verify_request($request);
    }

    public static function get_site_health($request) {
        if (!class_exists('DG_Site_Tools_Health')) {
            return new WP_Error('unavailable', 'Site Tools health unavailable.', ['status' => 503]);
        }

        $health = DG_Site_Tools_Health::run();
        $settings = class_exists('DG_Site_Tools_Settings') ? DG_Site_Tools_Settings::all() : [];

        $ssl = is_ssl() || (strpos(home_url(), 'https://') === 0);

        return rest_ensure_response([
            'site' => home_url(),
            'generated_at' => current_time('c'),
            'score' => (int) ($health['score'] ?? 0),
            'pass' => (int) ($health['pass'] ?? 0),
            'warn' => (int) ($health['warn'] ?? 0),
            'fail' => (int) ($health['fail'] ?? 0),
            'checks' => $health['checks'] ?? [],
            'pagespeed' => [
                'mobile' => isset($settings['pagespeed_mobile']) ? (int) $settings['pagespeed_mobile'] : null,
                'desktop' => isset($settings['pagespeed_desktop']) ? (int) $settings['pagespeed_desktop'] : null,
                'checked_at' => !empty($settings['pagespeed_checked_at']) ? $settings['pagespeed_checked_at'] : null,
            ],
            'ssl' => [
                'enabled' => $ssl,
            ],
        ]);
    }

    /**
     * Export pages (and optional posts) for Gen 2 Website import.
     * Content is rendered HTML with scripts stripped — not a theme/layout clone.
     */
    public static function get_site_content($request) {
        $include_posts = filter_var($request->get_param('include_posts'), FILTER_VALIDATE_BOOLEAN);
        $per_page = (int) $request->get_param('per_page');
        if ($per_page < 1) {
            $per_page = 40;
        }
        if ($per_page > 100) {
            $per_page = 100;
        }

        $front_id = (int) get_option('page_on_front');
        $blog_id = (int) get_option('page_for_posts');

        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => ['publish', 'draft', 'private'],
            'posts_per_page' => $per_page,
            'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC'],
            'order' => 'ASC',
        ]);

        $payload_pages = [];
        foreach ($pages as $post) {
            $payload_pages[] = self::format_content_post($post, [
                'is_front_page' => $front_id > 0 && (int) $post->ID === $front_id,
                'is_posts_page' => $blog_id > 0 && (int) $post->ID === $blog_id,
            ]);
        }

        // Prefer real front page first for Gen 2 home mapping.
        usort($payload_pages, static function ($a, $b) {
            if (!empty($a['is_front_page']) && empty($b['is_front_page'])) {
                return -1;
            }
            if (empty($a['is_front_page']) && !empty($b['is_front_page'])) {
                return 1;
            }
            return ((int) ($a['menu_order'] ?? 0)) <=> ((int) ($b['menu_order'] ?? 0));
        });

        $payload_posts = [];
        if ($include_posts) {
            $posts = get_posts([
                'post_type' => 'post',
                'post_status' => ['publish'],
                'posts_per_page' => min(20, $per_page),
                'orderby' => 'date',
                'order' => 'DESC',
            ]);
            foreach ($posts as $post) {
                $payload_posts[] = self::format_content_post($post, [
                    'is_front_page' => false,
                    'is_posts_page' => false,
                ]);
            }
        }

        return rest_ensure_response([
            'site' => home_url('/'),
            'name' => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'generated_at' => current_time('c'),
            'pages' => $payload_pages,
            'posts' => $payload_posts,
            'counts' => [
                'pages' => count($payload_pages),
                'posts' => count($payload_posts),
            ],
            'limitations' => [
                'Theme, Elementor/Divi layouts, menus, widgets, and plugins are not converted.',
                'Body HTML is flattened into Gen 2 Studio blocks (heading, paragraph, image, list, CTA, html).',
                'Media stays as remote URLs (hotlinked) in v0 — not re-hosted to DG CDN.',
                'Private/draft pages require this authenticated Connector endpoint.',
            ],
        ]);
    }

    private static function format_content_post($post, $flags = []) {
        $content = (string) apply_filters('the_content', $post->post_content);
        $content = self::strip_export_junk($content);

        $featured = get_the_post_thumbnail_url($post, 'full');
        if (!$featured) {
            $featured = null;
        }

        $seo = self::extract_seo_meta((int) $post->ID, $post);

        return [
            'id' => (int) $post->ID,
            'type' => $post->post_type,
            'title' => html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8'),
            'slug' => $post->post_name,
            'status' => $post->post_status,
            'link' => get_permalink($post),
            'menu_order' => (int) $post->menu_order,
            'is_front_page' => !empty($flags['is_front_page']),
            'is_posts_page' => !empty($flags['is_posts_page']),
            'excerpt' => html_entity_decode(wp_strip_all_tags(get_the_excerpt($post)), ENT_QUOTES, 'UTF-8'),
            'content_html' => $content,
            'featured_image' => $featured,
            'seo' => $seo,
            'modified_at' => get_post_modified_time('c', true, $post),
        ];
    }

    private static function strip_export_junk($html) {
        $html = preg_replace('#<script\b[^>]*>[\s\S]*?</script>#i', '', $html);
        $html = preg_replace('#<style\b[^>]*>[\s\S]*?</style>#i', '', $html);
        $html = preg_replace('#<noscript\b[^>]*>[\s\S]*?</noscript>#i', '', $html);
        $html = preg_replace('#<!--[\s\S]*?-->#', '', $html);
        $html = preg_replace('/\s+on\w+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $html);
        return trim((string) $html);
    }

    private static function extract_seo_meta($post_id, $post) {
        $title = '';
        $description = '';

        $yoast_title = get_post_meta($post_id, '_yoast_wpseo_title', true);
        $yoast_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        if (is_string($yoast_title) && $yoast_title !== '') {
            $title = $yoast_title;
        }
        if (is_string($yoast_desc) && $yoast_desc !== '') {
            $description = $yoast_desc;
        }

        if ($title === '') {
            $rank_title = get_post_meta($post_id, 'rank_math_title', true);
            if (is_string($rank_title) && $rank_title !== '') {
                $title = $rank_title;
            }
        }
        if ($description === '') {
            $rank_desc = get_post_meta($post_id, 'rank_math_description', true);
            if (is_string($rank_desc) && $rank_desc !== '') {
                $description = $rank_desc;
            }
        }

        if ($title === '') {
            $title = get_the_title($post);
        }
        if ($description === '') {
            $description = wp_strip_all_tags(get_the_excerpt($post));
        }

        return [
            'title' => html_entity_decode((string) $title, ENT_QUOTES, 'UTF-8'),
            'description' => html_entity_decode(wp_strip_all_tags((string) $description), ENT_QUOTES, 'UTF-8'),
        ];
    }
}

add_action('rest_api_init', function () {
    if (class_exists('DG_Site_Tools_Dev_API')) {
        DG_Site_Tools_Dev_API::register_routes();
    }
});
