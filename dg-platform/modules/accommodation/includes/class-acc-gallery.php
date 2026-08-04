<?php
/**
 * Accommodation gallery with Fancybox lightbox (same pattern as Roe Realty).
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Acc_Gallery {

    /** @var array<string, string> */
    private static $groups = [];

    public static function init() {
        add_action('wp_footer', [__CLASS__, 'enqueue_assets'], 5);
        add_action('wp_footer', [__CLASS__, 'print_bindings'], 25);
    }

    /**
     * @return int[]
     */
    public static function get_image_ids($post_id) {
        $post_id = (int) $post_id;
        $images = [];
        $featured = get_post_thumbnail_id($post_id);
        if ($featured) {
            $images[] = (int) $featured;
        }

        $gallery_ids = get_post_meta($post_id, 'dg_gallery', true);
        if (is_string($gallery_ids) && trim($gallery_ids) !== '') {
            foreach (array_map('trim', explode(',', $gallery_ids)) as $id) {
                $id = (int) $id;
                if ($id > 0 && !in_array($id, $images, true)) {
                    $images[] = $id;
                }
            }
        }

        return $images;
    }

    /**
     * @param int $post_id
     * @param array<string, mixed> $args
     */
    public static function render($post_id, $args = []) {
        $post_id = (int) $post_id;
        if (!$post_id || get_post_type($post_id) !== 'dg_accommodation') {
            return '';
        }

        $args = wp_parse_args($args, [
            'caption' => get_the_title($post_id),
            'class' => '',
        ]);

        $all_images = self::get_image_ids($post_id);
        if (empty($all_images)) {
            return '';
        }

        $group = 'accommodation-gallery-' . $post_id;
        self::$groups[$group] = $group;

        $title = (string) $args['caption'];
        $extra_class = $args['class'] ? ' ' . esc_attr($args['class']) : '';

        ob_start();
        ?>
        <div class="dg-acc-gallery<?php echo $extra_class; ?>">
            <div class="gallery-grid">
                <?php
                $first_image = array_shift($all_images);
                $image_url = wp_get_attachment_image_url($first_image, 'large');
                $image_full = wp_get_attachment_image_url($first_image, 'full');
                if ($image_url && $image_full) :
                    ?>
                    <a href="<?php echo esc_url($image_full); ?>" class="gallery-item gallery-main" data-fancybox="<?php echo esc_attr($group); ?>" data-caption="<?php echo esc_attr($title); ?>">
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
                    </a>
                <?php endif; ?>

                <div class="gallery-thumbs">
                    <?php
                    $count = 0;
                    $remaining = 0;
                    foreach ($all_images as $image_id) {
                        if ($count >= 2) {
                            break;
                        }
                        $image_url = wp_get_attachment_image_url($image_id, 'medium_large');
                        $image_full = wp_get_attachment_image_url($image_id, 'full');
                        if (!$image_url || !$image_full) {
                            continue;
                        }
                        ?>
                        <a href="<?php echo esc_url($image_full); ?>" class="gallery-item gallery-thumb" data-fancybox="<?php echo esc_attr($group); ?>" data-caption="<?php echo esc_attr($title); ?>">
                            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
                        </a>
                        <?php
                        $count++;
                    }

                    $remaining = count($all_images) - 2;
                    if ($remaining > 0) {
                        $remaining_images = array_slice($all_images, 2);
                        $next_image = (int) reset($remaining_images);
                        $next_full = wp_get_attachment_image_url($next_image, 'full');
                        $next_thumb = wp_get_attachment_image_url($next_image, 'medium_large');
                        if ($next_full && $next_thumb) {
                            ?>
                            <a href="<?php echo esc_url($next_full); ?>" class="gallery-item gallery-thumb gallery-more" data-fancybox="<?php echo esc_attr($group); ?>" data-caption="<?php echo esc_attr($title); ?>">
                                <img src="<?php echo esc_url($next_thumb); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
                                <div class="more-overlay">+ <?php echo (int) $remaining; ?></div>
                            </a>
                            <?php
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
        if (!empty($remaining) && $remaining > 0) {
            foreach (array_slice($all_images, 2) as $image_id) {
                $image_full = wp_get_attachment_image_url($image_id, 'full');
                if ($image_full) {
                    echo '<a href="' . esc_url($image_full) . '" data-fancybox="' . esc_attr($group) . '" data-caption="' . esc_attr($title) . '" style="display:none;" aria-hidden="true"></a>';
                }
            }
        }

        return ob_get_clean();
    }

    public static function enqueue_assets() {
        if (empty(self::$groups)) {
            return;
        }

        wp_enqueue_style(
            'fancybox',
            'https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css',
            [],
            '5.0'
        );
        wp_enqueue_script(
            'fancybox',
            'https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js',
            [],
            '5.0',
            true
        );

        $asset_base = DG_PLATFORM_PATH . 'modules/accommodation/';
        wp_enqueue_style(
            'dg-acc-gallery',
            plugins_url('assets/css/accommodation-gallery.css', $asset_base . 'accommodation.php'),
            ['fancybox'],
            DG_PLATFORM_VERSION
        );
    }

    public static function print_bindings() {
        if (empty(self::$groups)) {
            return;
        }
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Fancybox === 'undefined') return;
            <?php foreach (self::$groups as $group) : ?>
            Fancybox.bind('[data-fancybox="<?php echo esc_js($group); ?>"]', {
                infinite: true,
                arrows: true,
                toolbar: {
                    display: {
                        left: ['infobar'],
                        middle: ['zoomIn', 'zoomOut', 'slideshow'],
                        right: ['close']
                    }
                },
                Thumbs: {
                    type: 'classic',
                    Carousel: { show: true }
                }
            });
            <?php endforeach; ?>
        });
        </script>
        <?php
    }
}
