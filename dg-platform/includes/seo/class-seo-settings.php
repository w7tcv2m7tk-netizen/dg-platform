<?php
/**
 * SEO settings and per-post meta resolution (with Rank Math fallback).
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_SEO_Settings {

    const OPTION = 'dg_seo_settings';
    const META_PREFIX = '_dg_seo_';

    /** @var array<string,mixed>|null */
    private static $cache = null;

    public static function init() {
        add_action('init', [__CLASS__, 'maybe_migrate_rank_math'], 20);
    }

    public static function defaults() {
        $site = class_exists('DG_Site_Profile') ? DG_Site_Profile::label() : get_bloginfo('name');
        $host = class_exists('DG_Site_Profile') ? DG_Site_Profile::hostname() : parse_url(home_url(), PHP_URL_HOST);

        $presets = [
            'digitalgate.com.au' => [
                'organization_name' => 'DigitalGate',
                'organization_type' => 'Organization',
                'home_title' => 'DigitalGate | Business Operating Platform & Growth Systems',
                'home_description' => 'AI-powered Business Operating Platform with optional Growth Systems for Australian businesses.',
                'title_separator' => '|',
            ],
            'roerealty.com.au' => [
                'organization_name' => 'Roe Realty',
                'organization_type' => 'RealEstateAgent',
                'home_title' => 'Roe Realty | Currumbin Valley Real Estate',
                'home_description' => 'Local real estate agency specialising in Currumbin Valley and Gold Coast property sales, appraisals, and buyer services.',
                'title_separator' => '|',
            ],
            'currumbinvalleyhideaway.com.au' => [
                'organization_name' => 'Currumbin Valley Hideaway',
                'organization_type' => 'LodgingBusiness',
                'home_title' => 'Currumbin Valley Hideaway | Luxury Dome Retreat',
                'home_description' => 'Boutique rainforest retreat in Currumbin Valley — luxury domes, private studio, and tiny home stays on the Gold Coast.',
                'title_separator' => '|',
            ],
            'aetherra.com.au' => [
                'organization_name' => 'Aetherra',
                'organization_type' => 'Person',
                'home_title' => 'Aetherra | Creator & Technology',
                'home_description' => 'Projects, content, and creative work from Aetherra.',
                'title_separator' => '|',
            ],
        ];

        $preset = $presets[$host] ?? [];

        return array_merge([
            'organization_name' => $site,
            'organization_type' => 'Organization',
            'organization_url' => home_url('/'),
            'logo_url' => '',
            'default_og_image' => '',
            'social_facebook' => '',
            'social_twitter' => '',
            'social_instagram' => '',
            'title_separator' => '|',
            'home_title' => $site . ' | ' . get_bloginfo('description'),
            'home_description' => get_bloginfo('description'),
            'sitemap_enabled' => 1,
            'noindex_archives' => 0,
            'noindex_search' => 1,
            'indexnow_auto' => 1,
            'indexnow_key' => '',
        ], $preset);
    }

    /** @return array<string,mixed> */
    public static function all() {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $saved = get_option(self::OPTION, []);
        if (!is_array($saved)) {
            $saved = [];
        }
        self::$cache = wp_parse_args($saved, self::defaults());
        return self::$cache;
    }

    public static function get($key, $default = '') {
        $all = self::all();
        return $all[$key] ?? $default;
    }

    /** @param array<string,mixed> $data */
    public static function save(array $data) {
        $clean = [];
        foreach (self::defaults() as $key => $default) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            if (in_array($key, ['sitemap_enabled', 'noindex_archives', 'noindex_search', 'indexnow_auto'], true)) {
                $clean[$key] = !empty($value) ? 1 : 0;
            } elseif ($key === 'indexnow_key') {
                $clean[$key] = preg_match('/^[a-zA-Z0-9-]{8,128}$/', (string) $value) ? (string) $value : '';
            } elseif (in_array($key, ['organization_url', 'logo_url', 'default_og_image', 'social_facebook', 'social_twitter', 'social_instagram'], true)) {
                $clean[$key] = esc_url_raw($value);
            } else {
                $clean[$key] = sanitize_text_field($value);
            }
        }
        update_option(self::OPTION, wp_parse_args($clean, wp_parse_args(get_option(self::OPTION, []), self::defaults())));
        self::$cache = null;
        update_option('dg_seo_needs_rewrite_flush', 1);
    }

    public static function post_types_with_seo() {
        $types = ['post', 'page'];
        foreach (['property', 'agent', 'dg_accommodation'] as $type) {
            if (post_type_exists($type)) {
                $types[] = $type;
            }
        }
        return apply_filters('dg_seo_post_types', array_unique($types));
    }

    /** Stored meta only — no auto-title, Rank Math, or global fallbacks. */
    public static function get_post_seo_stored($post_id) {
        return [
            'title' => (string) get_post_meta($post_id, self::META_PREFIX . 'title', true),
            'description' => (string) get_post_meta($post_id, self::META_PREFIX . 'description', true),
            'focus_keyword' => (string) get_post_meta($post_id, self::META_PREFIX . 'focus_keyword', true),
            'robots' => self::robots_value_from_meta($post_id),
        ];
    }

    /** @return array<string,mixed> */
    public static function get_post_seo($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return [];
        }

        $dg = [
            'title' => get_post_meta($post_id, self::META_PREFIX . 'title', true),
            'description' => get_post_meta($post_id, self::META_PREFIX . 'description', true),
            'canonical' => get_post_meta($post_id, self::META_PREFIX . 'canonical', true),
            'noindex' => (bool) get_post_meta($post_id, self::META_PREFIX . 'noindex', true),
            'nofollow' => (bool) get_post_meta($post_id, self::META_PREFIX . 'nofollow', true),
            'og_title' => get_post_meta($post_id, self::META_PREFIX . 'og_title', true),
            'og_description' => get_post_meta($post_id, self::META_PREFIX . 'og_description', true),
            'og_image' => get_post_meta($post_id, self::META_PREFIX . 'og_image', true),
        ];

        if (self::rank_math_active()) {
            if ($dg['title'] === '') {
                $dg['title'] = get_post_meta($post_id, 'rank_math_title', true);
            }
            if ($dg['description'] === '') {
                $dg['description'] = get_post_meta($post_id, 'rank_math_description', true);
            }
            if ($dg['canonical'] === '') {
                $dg['canonical'] = get_post_meta($post_id, 'rank_math_canonical_url', true);
            }
            if (!$dg['noindex'] || !$dg['nofollow']) {
                $robots = get_post_meta($post_id, 'rank_math_robots', true);
                if (is_array($robots)) {
                    if (!$dg['noindex']) {
                        $dg['noindex'] = in_array('noindex', $robots, true);
                    }
                    if (!$dg['nofollow']) {
                        $dg['nofollow'] = in_array('nofollow', $robots, true);
                    }
                }
            }
            if ($dg['og_image'] === '') {
                $fb = get_post_meta($post_id, 'rank_math_facebook_image', true);
                if ($fb) {
                    $dg['og_image'] = $fb;
                }
            }
        }

        if ($dg['title'] === '') {
            $dg['title'] = self::auto_title($post);
        }
        if ($dg['description'] === '') {
            $dg['description'] = self::auto_description($post);
        }
        if ($dg['canonical'] === '') {
            $dg['canonical'] = get_permalink($post);
        }
        if ($dg['og_title'] === '') {
            $dg['og_title'] = $dg['title'];
        }
        if ($dg['og_description'] === '') {
            $dg['og_description'] = $dg['description'];
        }
        if ($dg['og_image'] === '') {
            $dg['og_image'] = self::auto_image($post_id);
        }

        return $dg;
    }

    /** @param WP_Post $post */
    public static function auto_title($post) {
        $sep = ' ' . trim(self::get('title_separator', '|')) . ' ';
        $site = self::get('organization_name', get_bloginfo('name'));

        if (is_front_page() && (int) get_option('page_on_front') === (int) $post->ID) {
            return self::get('home_title', $site);
        }

        return $post->post_title . $sep . $site;
    }

    /** @param WP_Post $post */
    public static function auto_description($post) {
        if (is_front_page() && (int) get_option('page_on_front') === (int) $post->ID) {
            return self::get('home_description', get_bloginfo('description'));
        }

        if ($post->post_type === 'property') {
            $desc = get_post_meta($post->ID, 'roe_property_description', true);
            if ($desc) {
                return wp_trim_words(wp_strip_all_tags($desc), 30, '…');
            }
        }

        if ($post->post_type === 'dg_accommodation') {
            $desc = get_post_meta($post->ID, 'dg_description', true);
            if ($desc) {
                return wp_trim_words(wp_strip_all_tags($desc), 30, '…');
            }
        }

        if ($post->post_excerpt) {
            return wp_trim_words(wp_strip_all_tags($post->post_excerpt), 30, '…');
        }

        return wp_trim_words(wp_strip_all_tags($post->post_content), 30, '…');
    }

    public static function auto_image($post_id) {
        $thumb = get_the_post_thumbnail_url($post_id, 'large');
        if ($thumb) {
            return $thumb;
        }

        if (get_post_type($post_id) === 'property') {
            $gallery = get_post_meta($post_id, 'roe_property_gallery', true);
            if ($gallery) {
                $ids = array_filter(array_map('intval', explode(',', $gallery)));
                if (!empty($ids)) {
                    $url = wp_get_attachment_image_url($ids[0], 'large');
                    if ($url) {
                        return $url;
                    }
                }
            }
        }

        return self::get('default_og_image', '');
    }

    public static function rank_math_active() {
        return defined('RANK_MATH_VERSION') || class_exists('RankMath');
    }

    public static function maybe_migrate_rank_math() {
        if (get_option('dg_seo_rank_math_migrated')) {
            return;
        }
        if (!self::rank_math_active()) {
            update_option('dg_seo_rank_math_migrated', 1);
            return;
        }

        global $wpdb;
        $types = self::post_types_with_seo();
        $placeholders = implode(',', array_fill(0, count($types), '%s'));
        $sql = "SELECT p.ID FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} rm ON rm.post_id = p.ID AND rm.meta_key = 'rank_math_title' AND rm.meta_value != ''
            LEFT JOIN {$wpdb->postmeta} dg ON dg.post_id = p.ID AND dg.meta_key = %s
            WHERE p.post_type IN ($placeholders) AND p.post_status = 'publish' AND dg.meta_id IS NULL
            LIMIT 200";
        $params = array_merge([self::META_PREFIX . 'title'], $types);
        $ids = $wpdb->get_col($wpdb->prepare($sql, $params));

        foreach ($ids as $post_id) {
            $post_id = (int) $post_id;
            $map = [
                'title' => 'rank_math_title',
                'description' => 'rank_math_description',
                'canonical' => 'rank_math_canonical_url',
                'og_image' => 'rank_math_facebook_image',
            ];
            foreach ($map as $dg_key => $rm_key) {
                $val = get_post_meta($post_id, $rm_key, true);
                if ($val !== '' && $val !== null) {
                    update_post_meta($post_id, self::META_PREFIX . $dg_key, $val);
                }
            }
            $robots = get_post_meta($post_id, 'rank_math_robots', true);
            if (is_array($robots)) {
                if (in_array('noindex', $robots, true)) {
                    update_post_meta($post_id, self::META_PREFIX . 'noindex', 1);
                }
                if (in_array('nofollow', $robots, true)) {
                    update_post_meta($post_id, self::META_PREFIX . 'nofollow', 1);
                }
            }
        }

        $rm_redirects = get_option('rank_math_redirections', []);
        if (is_array($rm_redirects) && !empty($rm_redirects) && empty(get_option(DG_SEO_Redirects::OPTION))) {
            $converted = [];
            foreach ($rm_redirects as $row) {
                if (empty($row['sources'][0]['pattern']) || empty($row['url_to'])) {
                    continue;
                }
                $converted[] = [
                    'from' => $row['sources'][0]['pattern'],
                    'to' => $row['url_to'],
                    'code' => (int) ($row['header_code'] ?? 301),
                ];
            }
            if ($converted) {
                update_option(DG_SEO_Redirects::OPTION, $converted);
            }
        }

        update_option('dg_seo_rank_math_migrated', 1);
    }

    /** @return array<string,string> */
    public static function robots_options() {
        return [
            'index,follow' => __('Index, Follow', 'dg-platform'),
            'index,nofollow' => __('Index, Nofollow', 'dg-platform'),
            'noindex,follow' => __('Noindex, Follow', 'dg-platform'),
            'noindex,nofollow' => __('Noindex, Nofollow', 'dg-platform'),
        ];
    }

    public static function robots_value_from_meta($post_id) {
        $noindex = (bool) get_post_meta($post_id, self::META_PREFIX . 'noindex', true);
        $nofollow = (bool) get_post_meta($post_id, self::META_PREFIX . 'nofollow', true);
        $index = $noindex ? 'noindex' : 'index';
        $follow = $nofollow ? 'nofollow' : 'follow';

        return $index . ',' . $follow;
    }

    public static function apply_robots_value($post_id, $value) {
        $allowed = array_keys(self::robots_options());
        if (!in_array($value, $allowed, true)) {
            $value = 'index,follow';
        }

        $parts = explode(',', $value);
        $index_part = $parts[0] ?? 'index';
        $follow_part = $parts[1] ?? 'follow';

        update_post_meta($post_id, self::META_PREFIX . 'noindex', $index_part === 'noindex' ? 1 : 0);
        update_post_meta($post_id, self::META_PREFIX . 'nofollow', $follow_part === 'nofollow' ? 1 : 0);
    }
}
