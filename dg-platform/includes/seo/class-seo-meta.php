<?php
/**
 * Frontend meta tags: title, description, canonical, robots, OG, Twitter.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_SEO_Meta {

    public static function init() {
        if (is_admin()) {
            return;
        }

        add_filter('pre_get_document_title', [__CLASS__, 'filter_document_title'], 20);
        add_filter('document_title_separator', [__CLASS__, 'title_separator']);
        add_action('wp_head', [__CLASS__, 'render_head_tags'], 1);
    }

    public static function title_separator($sep) {
        $custom = trim(DG_SEO_Settings::get('title_separator', '|'));
        return $custom !== '' ? ' ' . $custom . ' ' : $sep;
    }

    public static function filter_document_title($title) {
        if (!self::should_output()) {
            return $title;
        }

        $seo = self::current_seo();
        if (!empty($seo['title'])) {
            return wp_strip_all_tags($seo['title']);
        }

        return $title;
    }

    public static function render_head_tags() {
        if (!self::should_output()) {
            return;
        }

        $seo = self::current_seo();
        if (empty($seo)) {
            return;
        }

        if (!empty($seo['description'])) {
            echo '<meta name="description" content="' . esc_attr($seo['description']) . '" />' . "\n";
        }

        $robots = [];
        if (!empty($seo['noindex'])) {
            $robots[] = 'noindex';
        }
        if (!empty($seo['nofollow'])) {
            $robots[] = 'nofollow';
        }
        if (self::is_noindex_context()) {
            $robots[] = 'noindex';
        }
        $robots = array_unique($robots);
        if ($robots) {
            echo '<meta name="robots" content="' . esc_attr(implode(', ', $robots)) . '" />' . "\n";
        }

        if (!empty($seo['canonical'])) {
            echo '<link rel="canonical" href="' . esc_url($seo['canonical']) . '" />' . "\n";
        }

        $og_title = $seo['og_title'] ?? $seo['title'] ?? '';
        $og_desc = $seo['og_description'] ?? $seo['description'] ?? '';
        $og_image = $seo['og_image'] ?? '';
        $og_url = $seo['canonical'] ?? self::current_url();
        $og_type = is_singular('post') ? 'article' : 'website';
        $site = DG_SEO_Settings::get('organization_name', get_bloginfo('name'));

        if ($og_title) {
            echo '<meta property="og:title" content="' . esc_attr($og_title) . '" />' . "\n";
        }
        if ($og_desc) {
            echo '<meta property="og:description" content="' . esc_attr($og_desc) . '" />' . "\n";
        }
        echo '<meta property="og:type" content="' . esc_attr($og_type) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url($og_url) . '" />' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr($site) . '" />' . "\n";
        if ($og_image) {
            echo '<meta property="og:image" content="' . esc_url($og_image) . '" />' . "\n";
        }

        $twitter = DG_SEO_Settings::get('social_twitter', '');
        if ($twitter) {
            echo '<meta name="twitter:site" content="' . esc_attr($twitter) . '" />' . "\n";
        }
        echo '<meta name="twitter:card" content="' . esc_attr($og_image ? 'summary_large_image' : 'summary') . '" />' . "\n";
        if ($og_title) {
            echo '<meta name="twitter:title" content="' . esc_attr($og_title) . '" />' . "\n";
        }
        if ($og_desc) {
            echo '<meta name="twitter:description" content="' . esc_attr($og_desc) . '" />' . "\n";
        }
        if ($og_image) {
            echo '<meta name="twitter:image" content="' . esc_url($og_image) . '" />' . "\n";
        }
    }

    private static function should_output() {
        if (is_feed() || is_trackback()) {
            return false;
        }
        return apply_filters('dg_seo/output_meta', true);
    }

    /** @return array<string,mixed> */
    private static function current_seo() {
        if (is_singular()) {
            return DG_SEO_Settings::get_post_seo(get_queried_object_id());
        }

        if (is_front_page()) {
            $front_id = (int) get_option('page_on_front');
            if ($front_id) {
                return DG_SEO_Settings::get_post_seo($front_id);
            }
            return [
                'title' => DG_SEO_Settings::get('home_title', get_bloginfo('name')),
                'description' => DG_SEO_Settings::get('home_description', get_bloginfo('description')),
                'canonical' => home_url('/'),
                'og_title' => DG_SEO_Settings::get('home_title', get_bloginfo('name')),
                'og_description' => DG_SEO_Settings::get('home_description', get_bloginfo('description')),
                'og_image' => DG_SEO_Settings::get('default_og_image', ''),
                'noindex' => false,
                'nofollow' => false,
            ];
        }

        if (is_home()) {
            $posts_page = (int) get_option('page_for_posts');
            if ($posts_page) {
                return DG_SEO_Settings::get_post_seo($posts_page);
            }
        }

        return [
            'title' => wp_get_document_title(),
            'description' => DG_SEO_Settings::get('home_description', get_bloginfo('description')),
            'canonical' => self::current_url(),
            'og_image' => DG_SEO_Settings::get('default_og_image', ''),
            'noindex' => self::is_noindex_context(),
            'nofollow' => false,
        ];
    }

    private static function is_noindex_context() {
        if (is_search() && DG_SEO_Settings::get('noindex_search')) {
            return true;
        }
        if ((is_date() || is_author() || is_tag()) && DG_SEO_Settings::get('noindex_archives')) {
            return true;
        }
        return false;
    }

    private static function current_url() {
        global $wp;
        return home_url(add_query_arg([], $wp->request ?? ''));
    }
}
