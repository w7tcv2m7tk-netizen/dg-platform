<?php
/**
 * IndexNow — notify Bing, Yandex, and partners when URLs are updated.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_SEO_IndexNow {

    const META_LAST = '_dg_seo_indexnow_at';
    const QUERY_VAR = 'dg_seo_indexnow_key';

    public static function init() {
        add_action('init', [__CLASS__, 'register_rewrites'], 5);
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_action('template_redirect', [__CLASS__, 'serve_key_file'], 0);
        add_action('save_post', [__CLASS__, 'maybe_auto_submit'], 25, 3);
    }

    public static function ensure_key() {
        $saved = get_option(DG_SEO_Settings::OPTION, []);
        if (!is_array($saved)) {
            $saved = [];
        }
        $key = $saved['indexnow_key'] ?? '';
        if ($key !== '' && preg_match('/^[a-zA-Z0-9-]{8,128}$/', $key)) {
            return $key;
        }

        $key = bin2hex(random_bytes(16));
        $saved['indexnow_key'] = $key;
        update_option(DG_SEO_Settings::OPTION, wp_parse_args($saved, DG_SEO_Settings::defaults()));
        update_option('dg_seo_needs_rewrite_flush', 1);

        return $key;
    }

    public static function key_location() {
        return home_url('/' . self::ensure_key() . '.txt');
    }

    public static function host() {
        $parts = wp_parse_url(home_url('/'));
        return isset($parts['host']) ? (string) $parts['host'] : '';
    }

    public static function auto_enabled() {
        return (bool) DG_SEO_Settings::get('indexnow_auto', 1);
    }

    public static function register_rewrites() {
        $key = self::ensure_key();
        add_rewrite_rule('^' . preg_quote($key, '/') . '\.txt$', 'index.php?' . self::QUERY_VAR . '=1', 'top');
    }

    /** @param string[] $vars */
    public static function query_vars($vars) {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    public static function serve_key_file() {
        if (!get_query_var(self::QUERY_VAR)) {
            return;
        }

        status_header(200);
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex');
        echo self::ensure_key();
        exit;
    }

    public static function is_indexable($post_id) {
        $post = get_post((int) $post_id);
        if (!$post || $post->post_status !== 'publish') {
            return false;
        }
        if (!in_array($post->post_type, DG_SEO_Settings::post_types_with_seo(), true)) {
            return false;
        }
        if (strpos(DG_SEO_Settings::robots_value_from_meta($post_id), 'noindex') !== false) {
            return false;
        }

        $permalink = get_permalink($post_id);
        return $permalink && !is_wp_error($permalink);
    }

    public static function post_url($post_id) {
        $url = get_permalink((int) $post_id);
        return ($url && !is_wp_error($url)) ? esc_url_raw($url) : '';
    }

    /** @return int[] */
    public static function all_indexable_post_ids($post_type = 'page') {
        $types = $post_type === 'all'
            ? DG_SEO_Settings::post_types_with_seo()
            : [$post_type];

        $ids = get_posts([
            'post_type' => $types,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'suppress_filters' => false,
        ]);

        $indexable = [];
        foreach ($ids as $post_id) {
            if (self::is_indexable((int) $post_id)) {
                $indexable[] = (int) $post_id;
            }
        }

        return $indexable;
    }

    /**
     * @param int[] $post_ids
     * @return array<string,mixed>|WP_Error
     */
    public static function submit_posts(array $post_ids) {
        $urls = [];
        $valid_ids = [];

        foreach ($post_ids as $post_id) {
            $post_id = (int) $post_id;
            if (!$post_id || !self::is_indexable($post_id)) {
                continue;
            }
            $url = self::post_url($post_id);
            if ($url === '') {
                continue;
            }
            $urls[] = $url;
            $valid_ids[] = $post_id;
        }

        return self::submit_urls($urls, $valid_ids);
    }

    /**
     * @param string[] $urls
     * @param int[]    $post_ids
     * @return array<string,mixed>|WP_Error
     */
    public static function submit_urls(array $urls, array $post_ids = []) {
        $urls = array_values(array_unique(array_filter($urls)));
        if ($urls === []) {
            return new WP_Error('no_urls', __('No indexable URLs to submit.', 'dg-platform'));
        }

        if (count($urls) > 10000) {
            $urls = array_slice($urls, 0, 10000);
        }

        $response = wp_remote_post('https://api.indexnow.org/indexnow', [
            'timeout' => 20,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => wp_json_encode([
                'host' => self::host(),
                'key' => self::ensure_key(),
                'keyLocation' => self::key_location(),
                'urlList' => $urls,
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if (!in_array($code, [200, 202], true)) {
            $body = trim((string) wp_remote_retrieve_body($response));
            return new WP_Error(
                'indexnow_failed',
                sprintf(
                    /* translators: 1: HTTP status code, 2: response body */
                    __('IndexNow returned HTTP %1$d%2$s', 'dg-platform'),
                    $code,
                    $body !== '' ? ': ' . $body : ''
                )
            );
        }

        $now = current_time('mysql');
        foreach ($post_ids as $post_id) {
            if ((int) $post_id > 0) {
                update_post_meta((int) $post_id, self::META_LAST, $now);
            }
        }
        update_option('dg_seo_indexnow_last_at', $now);

        return [
            'success' => true,
            'code' => $code,
            'count' => count($urls),
            'submitted_at' => $now,
        ];
    }

    public static function last_site_submit_at() {
        return (string) get_option('dg_seo_indexnow_last_at', '');
    }

    /** @param WP_Post $post */
    public static function maybe_auto_submit($post_id, $post, $update) {
        if (!self::auto_enabled()) {
            return;
        }
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }
        if (!$post instanceof WP_Post || $post->post_status !== 'publish') {
            return;
        }
        if (!in_array($post->post_type, DG_SEO_Settings::post_types_with_seo(), true)) {
            return;
        }
        if (!self::is_indexable($post_id)) {
            return;
        }

        $last = get_post_meta($post_id, self::META_LAST, true);
        if ($last && (time() - strtotime($last)) < 300) {
            return;
        }

        self::submit_posts([(int) $post_id]);
    }

    public static function last_indexed_label($post_id) {
        $last = get_post_meta((int) $post_id, self::META_LAST, true);
        if (!$last) {
            return '';
        }

        return sprintf(
            /* translators: %s: human-readable time difference */
            __('%s ago', 'dg-platform'),
            human_time_diff(strtotime($last), current_time('timestamp'))
        );
    }

    public static function render_index_cell($post_id) {
        $post_id = (int) $post_id;
        $indexable = self::is_indexable($post_id);
        $last = self::last_indexed_label($post_id);

        if (!$indexable) {
            echo '<span class="dg-seo-indexnow-muted" title="' . esc_attr__('Noindex or not published', 'dg-platform') . '">—</span>';
            return;
        }

        echo '<button type="button" class="button button-small dg-seo-indexnow-btn" data-post-id="' . esc_attr((string) $post_id) . '">';
        esc_html_e('Index Now', 'dg-platform');
        echo '</button>';
        if ($last !== '') {
            echo '<div class="dg-seo-indexnow-last" title="' . esc_attr($last) . '">' . esc_html($last) . '</div>';
        }
    }
}
