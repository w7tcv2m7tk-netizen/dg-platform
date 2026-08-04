<?php
/**
 * Custom code snippets — replaces Fluent Snippets for small site-specific hooks.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Site_Tools_Snippets {

    const OPTION = 'dg_site_tools_snippets';

    public static function init() {
        add_action('plugins_loaded', [__CLASS__, 'run_active_snippets'], 20);
    }

    /** @return array<int,array<string,mixed>> */
    public static function all() {
        $snippets = get_option(self::OPTION, []);
        return is_array($snippets) ? $snippets : [];
    }

    /** @param array<int,array<string,mixed>> $snippets */
    public static function save_all(array $snippets) {
        update_option(self::OPTION, array_values($snippets));
    }

    /** @param array<string,mixed> $snippet */
    public static function upsert(array $snippet) {
        $all = self::all();
        $id = sanitize_key($snippet['id'] ?? '');
        if (!$id) {
            $id = sanitize_key('snip_' . wp_generate_password(12, false, false));
        }

        $row = [
            'id' => $id,
            'name' => sanitize_text_field($snippet['name'] ?? 'Untitled'),
            'hook' => sanitize_key($snippet['hook'] ?? 'init'),
            'priority' => (int) ($snippet['priority'] ?? 10),
            'code' => (string) ($snippet['code'] ?? ''),
            'active' => !empty($snippet['active']) ? 1 : 0,
            'updated_at' => current_time('mysql'),
        ];

        $found = false;
        foreach ($all as $i => $existing) {
            if (($existing['id'] ?? '') === $id) {
                $all[$i] = $row;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $all[] = $row;
        }

        self::save_all($all);
        return $id;
    }

    public static function delete($id) {
        $id = sanitize_key($id);
        if ($id === '') {
            return false;
        }

        $before = count(self::all());
        self::save_all(array_values(array_filter(self::all(), function ($s) use ($id) {
            return sanitize_key($s['id'] ?? '') !== $id;
        })));

        return count(self::all()) < $before;
    }

    public static function run_active_snippets() {
        if (defined('DG_PLATFORM_DISABLE_SNIPPETS') && DG_PLATFORM_DISABLE_SNIPPETS) {
            return;
        }
        if (!DG_Site_Tools_Settings::is_enabled()) {
            return;
        }

        foreach (self::all() as $snippet) {
            if (empty($snippet['active']) || empty($snippet['code'])) {
                continue;
            }
            $hook = sanitize_key($snippet['hook'] ?? 'init');
            $priority = (int) ($snippet['priority'] ?? 10);
            $code = $snippet['code'];

            add_action($hook, function () use ($code, $snippet) {
                try {
                    eval($code);
                } catch (Throwable $e) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('DG Site Tools snippet error (' . ($snippet['name'] ?? 'unknown') . '): ' . $e->getMessage());
                    }
                }
            }, $priority);
        }
    }

    public static function allowed_hooks() {
        return apply_filters('dg_site_tools_snippet_hooks', [
            'init' => 'init',
            'wp' => 'wp',
            'wp_head' => 'wp_head',
            'wp_footer' => 'wp_footer',
            'admin_init' => 'admin_init',
            'admin_head' => 'admin_head',
            'admin_footer' => 'admin_footer',
            'template_redirect' => 'template_redirect',
            'rest_api_init' => 'rest_api_init',
            'dg_platform_modules_loaded' => 'dg_platform_modules_loaded',
        ]);
    }
}
