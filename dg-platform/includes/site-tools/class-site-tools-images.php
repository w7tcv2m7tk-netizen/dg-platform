<?php
/**
 * Image compression on upload — lightweight Smush replacement.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Site_Tools_Images {

    public static function init() {
        add_filter('wp_generate_attachment_metadata', [__CLASS__, 'optimize_on_upload'], 20, 2);
    }

    public static function optimize_on_upload($metadata, $attachment_id) {
        if (!DG_Site_Tools_Settings::is_enabled() || !DG_Site_Tools_Settings::get('compress_on_upload')) {
            return $metadata;
        }

        self::optimize_attachment($attachment_id, $metadata);
        return $metadata;
    }

    /**
     * @param array<string,mixed>|null $metadata
     * @return array{success:bool,message:string,saved_bytes?:int}
     */
    public static function optimize_attachment($attachment_id, $metadata = null) {
        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) {
            return ['success' => false, 'message' => 'File not found.'];
        }

        if ($metadata === null) {
            $metadata = wp_get_attachment_metadata($attachment_id);
        }

        $saved = 0;
        $files = [$file];

        if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            $dir = trailingslashit(dirname($file));
            foreach ($metadata['sizes'] as $size) {
                if (!empty($size['file'])) {
                    $files[] = $dir . $size['file'];
                }
            }
        }

        foreach ($files as $path) {
            if (!file_exists($path)) {
                continue;
            }
            $result = self::compress_file($path);
            if ($result['saved'] > 0) {
                $saved += $result['saved'];
            }
        }

        update_post_meta($attachment_id, '_dg_image_optimized', current_time('mysql'));
        update_post_meta($attachment_id, '_dg_image_saved_bytes', (int) get_post_meta($attachment_id, '_dg_image_saved_bytes', true) + $saved);

        return [
            'success' => true,
            'message' => $saved > 0 ? 'Saved ' . size_format($saved) . '.' : 'Already optimized.',
            'saved_bytes' => $saved,
        ];
    }

    /** @return array{saved:int} */
    private static function compress_file($path) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            return ['saved' => 0];
        }

        $before = filesize($path);
        $max_width = (int) DG_Site_Tools_Settings::get('max_image_width', 2560);
        $quality = max(50, min(95, (int) DG_Site_Tools_Settings::get('jpeg_quality', 82)));

        $editor = wp_get_image_editor($path);
        if (is_wp_error($editor)) {
            return ['saved' => 0];
        }

        $size = $editor->get_size();
        if ($max_width > 0 && !empty($size['width']) && $size['width'] > $max_width) {
            $editor->resize($max_width, null, false);
        }

        if (in_array($ext, ['jpg', 'jpeg'], true)) {
            $editor->set_quality($quality);
        } elseif ($ext === 'png') {
            $editor->set_quality(9 - (int) round(($quality / 100) * 9));
        }

        $saved = $editor->save($path);
        if (is_wp_error($saved)) {
            return ['saved' => 0];
        }

        clearstatcache(true, $path);
        $after = file_exists($path) ? filesize($path) : $before;
        return ['saved' => max(0, $before - $after)];
    }

    /** @return array{processed:int,saved_bytes:int,errors:int} */
    public static function bulk_optimize($limit = 25) {
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => ['image/jpeg', 'image/png'],
            'posts_per_page' => $limit,
            'post_status' => 'inherit',
            'meta_query' => [
                [
                    'key' => '_dg_image_optimized',
                    'compare' => 'NOT EXISTS',
                ],
            ],
            'fields' => 'ids',
        ]);

        $processed = 0;
        $saved = 0;
        $errors = 0;

        foreach ($attachments as $id) {
            $result = self::optimize_attachment((int) $id);
            if ($result['success']) {
                $processed++;
                $saved += (int) ($result['saved_bytes'] ?? 0);
            } else {
                $errors++;
            }
        }

        return [
            'processed' => $processed,
            'saved_bytes' => $saved,
            'errors' => $errors,
            'remaining' => self::count_unoptimized(),
        ];
    }

    public static function count_unoptimized() {
        $q = new WP_Query([
            'post_type' => 'attachment',
            'post_mime_type' => ['image/jpeg', 'image/png'],
            'posts_per_page' => 1,
            'post_status' => 'inherit',
            'meta_query' => [
                [
                    'key' => '_dg_image_optimized',
                    'compare' => 'NOT EXISTS',
                ],
            ],
            'fields' => 'ids',
        ]);
        return (int) $q->found_posts;
    }
}
