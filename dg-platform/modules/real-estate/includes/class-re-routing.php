<?php
/**
 * Roe Realty CPT routing — property/agent single URLs + rewrite flush.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_RE_Routing {

    const FLUSH_OPTION = 'dg_re_needs_rewrite_flush';
    const VERSION_OPTION = 'dg_re_routing_version';
    const ROUTING_VERSION = '1.0.0';

    /** @var bool */
    private static $booted = false;

    public static function init() {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        add_action('init', [__CLASS__, 'register_rewrite_rules'], 11);
        add_action('init', [__CLASS__, 'maybe_flush_rewrites'], 99);
        add_action('parse_request', [__CLASS__, 'parse_cpt_request'], 1);
        add_filter('pre_handle_404', [__CLASS__, 'pre_handle_404'], 10, 2);
    }

    public static function register_rewrite_rules() {
        add_rewrite_rule('^property/([^/]+)/?$', 'index.php?post_type=property&name=$matches[1]', 'top');
        add_rewrite_rule('^agent/([^/]+)/?$', 'index.php?post_type=agent&name=$matches[1]', 'top');
    }

    public static function flag_flush() {
        update_option(self::FLUSH_OPTION, 1, false);
    }

    public static function maybe_flush_rewrites() {
        $needs_flush = get_option(self::FLUSH_OPTION);
        $version = get_option(self::VERSION_OPTION, '');

        if (!$needs_flush && $version === self::ROUTING_VERSION) {
            return;
        }

        flush_rewrite_rules(false);
        delete_option(self::FLUSH_OPTION);
        update_option(self::VERSION_OPTION, self::ROUTING_VERSION, false);
    }

    /**
     * Fix /property/{slug}/ and /agent/{slug}/ when a Page slug conflicts or rules are stale.
     *
     * @param WP $wp
     */
    public static function parse_cpt_request($wp) {
        if (is_admin() || empty($wp)) {
            return;
        }

        $path = self::request_path();
        if ($path === '') {
            return;
        }

        $resolved = self::resolve_path($path);
        if (!$resolved) {
            return;
        }

        $wp->query_vars = [
            'post_type' => $resolved['post_type'],
            'name' => $resolved['slug'],
        ];
    }

    /**
     * @param WP_Query $wp_query
     */
    public static function pre_handle_404($preempt, $wp_query) {
        if ($preempt || is_admin() || !$wp_query->is_main_query()) {
            return $preempt;
        }

        $resolved = self::resolve_path(self::request_path());
        if (!$resolved) {
            return $preempt;
        }

        $wp_query->query_vars['post_type'] = $resolved['post_type'];
        $wp_query->query_vars['name'] = $resolved['slug'];
        unset(
            $wp_query->query_vars['pagename'],
            $wp_query->query_vars['page'],
            $wp_query->query_vars['error']
        );

        $wp_query->is_single = true;
        $wp_query->is_singular = true;
        $wp_query->is_404 = false;
        $wp_query->is_page = false;
        $wp_query->is_home = false;

        return true;
    }

    /** @return array{post_type:string,slug:string,post:WP_Post}|null */
    private static function resolve_path($path) {
        $patterns = [
            '#^property/([^/]+)/?$#' => 'property',
            '#^agent/([^/]+)/?$#' => 'agent',
        ];

        foreach ($patterns as $pattern => $post_type) {
            if (!preg_match($pattern, $path, $matches)) {
                continue;
            }

            $slug = sanitize_title($matches[1]);
            $post = self::find_post_by_slug($post_type, $slug);
            if (!$post) {
                return null;
            }

            return [
                'post_type' => $post_type,
                'slug' => $slug,
                'post' => $post,
            ];
        }

        return null;
    }

    /** @return WP_Post|null */
    private static function find_post_by_slug($post_type, $slug) {
        $post = get_page_by_path($slug, OBJECT, $post_type);
        if ($post && $post->post_status === 'publish') {
            return $post;
        }

        $posts = get_posts([
            'post_type' => $post_type,
            'name' => $slug,
            'post_status' => 'publish',
            'posts_per_page' => 1,
        ]);

        return !empty($posts) ? $posts[0] : null;
    }

    private static function request_path() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $path = trim((string) parse_url($uri, PHP_URL_PATH), '/');
        $home_path = trim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');

        if ($home_path && strpos($path, $home_path . '/') === 0) {
            $path = substr($path, strlen($home_path) + 1);
        } elseif ($home_path && $path === $home_path) {
            $path = '';
        }

        return $path;
    }
}
