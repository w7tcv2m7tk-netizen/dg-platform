<?php
/**
 * llms.txt endpoint for AI crawlers.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_AI_Visibility_Llms {

    const QUERY_VAR = 'dg_llms_txt';

    public static function init() {
        add_action('init', [__CLASS__, 'register_rewrite'], 5);
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_action('template_redirect', [__CLASS__, 'render'], 0);
    }

    public static function register_rewrite() {
        add_rewrite_rule('^llms\.txt$', 'index.php?' . self::QUERY_VAR . '=1', 'top');
    }

    /** @param array<int,string> $vars */
    public static function query_vars($vars) {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    public static function render() {
        if (!get_query_var(self::QUERY_VAR) || !DG_AI_Visibility_Settings::get('llms_txt_enabled')) {
            return;
        }

        status_header(200);
        header('Content-Type: text/plain; charset=UTF-8');
        echo self::build();
        exit;
    }

    public static function build() {
        $s = DG_AI_Visibility_Settings::all();
        $lines = [];

        $lines[] = '# ' . $s['business_name'];
        $lines[] = '';
        $lines[] = '> ' . ($s['industry'] ?: 'Business website');
        if ($s['location']) {
            $lines[] = '> Location: ' . $s['location'];
        }
        $lines[] = '';
        $lines[] = '## About';
        $lines[] = $s['business_name'] . ' — ' . ($s['industry'] ?: 'services') . ' serving ' . ($s['location'] ?: 'Australia') . '.';
        $lines[] = 'Website: ' . ($s['website'] ?: home_url('/'));
        $lines[] = '';

        $lines[] = '## Key pages';
        $pages = get_posts([
            'post_type' => ['page'],
            'post_status' => 'publish',
            'posts_per_page' => 12,
            'orderby' => 'menu_order title',
            'order' => 'ASC',
        ]);
        foreach ($pages as $page) {
            $lines[] = '- [' . $page->post_title . '](' . get_permalink($page) . ')';
        }

        if (post_type_exists('property')) {
            $lines[] = '';
            $lines[] = '## Properties';
            foreach (get_posts(['post_type' => 'property', 'post_status' => 'publish', 'posts_per_page' => 10]) as $post) {
                $lines[] = '- [' . $post->post_title . '](' . get_permalink($post) . ')';
            }
        }

        if (post_type_exists('dg_accommodation')) {
            $lines[] = '';
            $lines[] = '## Stays';
            foreach (get_posts(['post_type' => 'dg_accommodation', 'post_status' => 'publish', 'posts_per_page' => 10]) as $post) {
                $lines[] = '- [' . $post->post_title . '](' . get_permalink($post) . ')';
            }
        }

        if ($s['llms_txt_extra']) {
            $lines[] = '';
            $lines[] = '## Additional';
            $lines[] = trim($s['llms_txt_extra']);
        }

        $lines[] = '';
        $lines[] = '## Sitemap';
        $lines[] = home_url('/sitemap_index.xml');

        return apply_filters('dg_ai_visibility/llms_txt', implode("\n", $lines));
    }
}
