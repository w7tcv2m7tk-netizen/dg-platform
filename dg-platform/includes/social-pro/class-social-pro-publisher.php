<?php
/**
 * Publish social posts to connected platforms.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Social_Pro_Publisher {

    /** @return array<string,class-string<DG_Social_Platform>> */
    public static function platform_classes() {
        return [
            'facebook' => 'DG_Social_Facebook',
            'instagram' => 'DG_Social_Instagram',
            'linkedin' => 'DG_Social_LinkedIn',
            'x' => 'DG_Social_X',
            'pinterest' => 'DG_Social_Pinterest',
        ];
    }

    /** @return DG_Social_Platform|null */
    public static function platform($key) {
        $classes = self::platform_classes();
        $key = sanitize_key($key);
        if (!isset($classes[$key]) || !class_exists($classes[$key])) {
            return null;
        }

        $definitions = DG_Social_Pro_Settings::platform_definitions();
        if (!isset($definitions[$key])) {
            return null;
        }

        $connection = DG_Social_Pro_Settings::connection($key);

        // Instagram uses Facebook token when not separately stored.
        if ($key === 'instagram' && empty($connection['access_token'])) {
            $connection = DG_Social_Pro_Settings::connection('facebook');
        }

        return new $classes[$key]($key, $connection, $definitions[$key]);
    }

    /**
     * @return array{status:string,results:array<string,array<string,mixed>>}
     */
    public static function publish_post($post_id, $platforms = null) {
        $post = DG_Social_Pro_Posts::get($post_id);
        if (!$post) {
            return ['status' => 'failed', 'results' => ['_error' => ['success' => false, 'message' => 'Post not found.']]];
        }

        $targets = $platforms ?: $post->platforms;
        if (empty($targets)) {
            return ['status' => 'failed', 'results' => ['_error' => ['success' => false, 'message' => 'No platforms selected.']]];
        }

        $results = [];
        $success_count = 0;
        $fail_count = 0;

        foreach ($targets as $platform_key) {
            $platform = self::platform($platform_key);
            if (!$platform) {
                $results[$platform_key] = ['success' => false, 'message' => 'Unknown platform.'];
                $fail_count++;
                continue;
            }

            if (!DG_Social_Pro_Settings::is_connected($platform_key) && $platform_key !== 'instagram') {
                $fb_ok = $platform_key === 'instagram' && DG_Social_Pro_Settings::is_connected('facebook');
                if (!$fb_ok) {
                    $results[$platform_key] = ['success' => false, 'message' => 'Not connected. Go to Connections tab.'];
                    $fail_count++;
                    continue;
                }
            }

            if ($platform_key === 'instagram' && !DG_Social_Pro_Settings::is_connected('facebook')) {
                $results[$platform_key] = ['success' => false, 'message' => 'Connect Facebook first (Instagram uses linked Business account).'];
                $fail_count++;
                continue;
            }

            $result = $platform->publish($post);
            $results[$platform_key] = $result;
            if (!empty($result['success'])) {
                $success_count++;
            } else {
                $fail_count++;
            }
        }

        if ($success_count > 0 && $fail_count === 0) {
            $status = 'published';
        } elseif ($success_count > 0) {
            $status = 'partial';
        } else {
            $status = 'failed';
        }

        DG_Social_Pro_Posts::update($post_id, [
            'status' => $status,
            'results' => $results,
            'published_at' => current_time('mysql'),
        ]);

        return ['status' => $status, 'results' => $results];
    }

    public static function process_due() {
        $due = DG_Social_Pro_Posts::due_scheduled();
        foreach ($due as $post) {
            self::publish_post($post->id);
        }
    }
}
