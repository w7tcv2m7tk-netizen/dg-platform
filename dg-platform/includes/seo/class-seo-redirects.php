<?php
/**
 * Simple 301/302 redirect manager.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_SEO_Redirects {

    const OPTION = 'dg_seo_redirects';

    public static function init() {
        add_action('template_redirect', [__CLASS__, 'maybe_redirect'], 0);
    }

    /** @return array<int,array{from:string,to:string,code:int}> */
    public static function all() {
        $rows = get_option(self::OPTION, []);
        return is_array($rows) ? $rows : [];
    }

    /** @param array<int,array{from:string,to:string,code:int}> $rows */
    public static function save(array $rows) {
        $clean = [];
        foreach ($rows as $row) {
            $from = isset($row['from']) ? trim($row['from']) : '';
            $to = isset($row['to']) ? trim($row['to']) : '';
            if ($from === '' || $to === '') {
                continue;
            }
            $code = (int) ($row['code'] ?? 301);
            if (!in_array($code, [301, 302, 307, 308], true)) {
                $code = 301;
            }
            $clean[] = [
                'from' => $from,
                'to' => $to,
                'code' => $code,
            ];
        }
        update_option(self::OPTION, $clean);
    }

    public static function maybe_redirect() {
        if (is_admin() || (defined('DOING_CRON') && DOING_CRON)) {
            return;
        }

        $path = self::request_path();
        if ($path === '') {
            return;
        }

        foreach (self::all() as $row) {
            $from = self::normalize_path($row['from']);
            if ($from === $path) {
                wp_safe_redirect($row['to'], (int) $row['code']);
                exit;
            }
        }
    }

    private static function request_path() {
        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $path = wp_parse_url($uri, PHP_URL_PATH);
        return self::normalize_path(is_string($path) ? $path : '/');
    }

    private static function normalize_path($path) {
        $path = '/' . ltrim((string) $path, '/');
        return untrailingslashit($path) ?: '/';
    }
}
