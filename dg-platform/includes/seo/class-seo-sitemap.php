<?php
/**
 * XML sitemap (Rank Math compatible URLs: sitemap_index.xml, {type}-sitemap.xml).
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_SEO_Sitemap {

    const QUERY_VAR = 'dg_seo_sitemap';

    /** @var array<string,array<string,mixed>> */
    private static $types = [];

    public static function init() {
        add_action('init', [__CLASS__, 'register_rewrites'], 5);
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_action('template_redirect', [__CLASS__, 'render'], 0);
        add_filter('robots_txt', [__CLASS__, 'robots_txt'], 10, 2);
        add_filter('wp_sitemaps_enabled', '__return_false');
        add_action('init', [__CLASS__, 'maybe_flush'], 99);
    }

    public static function register_rewrites() {
        add_rewrite_rule('^sitemap_index\.xml$', 'index.php?' . self::QUERY_VAR . '=index', 'top');
        add_rewrite_rule('^([^/]+?)-sitemap\.xml$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top');
    }

    /** @param array<int,string> $vars */
    public static function query_vars($vars) {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    public static function maybe_flush() {
        if (get_option('dg_seo_needs_rewrite_flush')) {
            flush_rewrite_rules(false);
            delete_option('dg_seo_needs_rewrite_flush');
        }
    }

    public static function render() {
        if (!DG_SEO_Settings::get('sitemap_enabled')) {
            return;
        }

        $type = get_query_var(self::QUERY_VAR);
        if (!$type) {
            return;
        }

        status_header(200);
        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex, follow');

        if ($type === 'index') {
            echo self::render_index();
        } else {
            echo self::render_type($type);
        }
        exit;
    }

    private static function render_index() {
        $types = self::get_types();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($types as $key => $config) {
            if (empty($config['count'])) {
                continue;
            }
            $xml .= '  <sitemap><loc>' . esc_url(home_url("/{$key}-sitemap.xml")) . '</loc></sitemap>' . "\n";
        }
        $xml .= '</sitemapindex>';
        return $xml;
    }

    private static function render_type($type) {
        $types = self::get_types();
        if (!isset($types[$type])) {
            status_header(404);
            return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
        }

        $posts = get_posts([
            'post_type' => $types[$type]['post_type'],
            'post_status' => 'publish',
            'posts_per_page' => 1000,
            'orderby' => 'modified',
            'order' => 'DESC',
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($posts as $post_id) {
            if (self::is_noindex_post((int) $post_id)) {
                continue;
            }
            $loc = get_permalink($post_id);
            if (!$loc) {
                continue;
            }
            $mod = get_post_modified_time('c', true, $post_id);
            $xml .= '  <url><loc>' . esc_url($loc) . '</loc><lastmod>' . esc_html($mod) . '</lastmod></url>' . "\n";
        }

        $xml .= '</urlset>';
        return $xml;
    }

    /** @return array<string,array<string,mixed>> */
    private static function get_types() {
        if (self::$types) {
            return self::$types;
        }

        $map = [
            'post' => 'post',
            'page' => 'page',
        ];
        if (post_type_exists('property')) {
            $map['property'] = 'property';
        }
        if (post_type_exists('agent')) {
            $map['agent'] = 'agent';
        }
        if (post_type_exists('dg_accommodation')) {
            $map['dg_accommodation'] = 'dg_accommodation';
        }

        foreach ($map as $slug => $post_type) {
            $count = wp_count_posts($post_type);
            self::$types[$slug] = [
                'post_type' => $post_type,
                'count' => isset($count->publish) ? (int) $count->publish : 0,
            ];
        }

        return apply_filters('dg_seo/sitemap_types', self::$types);
    }

    private static function is_noindex_post($post_id) {
        $seo = DG_SEO_Settings::get_post_seo($post_id);
        return !empty($seo['noindex']);
    }

    public static function robots_txt($output, $public) {
        if (!$public || !DG_SEO_Settings::get('sitemap_enabled')) {
            return $output;
        }
        $line = 'Sitemap: ' . home_url('/sitemap_index.xml');
        if (strpos($output, 'sitemap_index.xml') === false) {
            $output .= "\n" . $line . "\n";
        }
        return $output;
    }
}
