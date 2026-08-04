<?php
/**
 * Property brochure page — /brochure/?property={id}
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_RE_Property_Brochure {

    /** @var bool */
    private static $booted = false;

    public static function init() {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        add_action('template_redirect', [__CLASS__, 'maybe_render'], 5);
    }

    /**
     * @param int $post_id
     * @param bool $download When true, opens print dialog on load.
     */
    public static function url($post_id, $download = true) {
        $args = ['property' => (int) $post_id];
        if ($download) {
            $args['download'] = '1';
        }
        return add_query_arg($args, home_url('/brochure/'));
    }

    public static function maybe_render() {
        $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        $home_path = trim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');
        if ($home_path && strpos($path, $home_path . '/') === 0) {
            $path = substr($path, strlen($home_path) + 1);
        } elseif ($home_path && $path === $home_path) {
            $path = '';
        }

        if ($path !== 'brochure' && !preg_match('#^brochure/?$#', $path)) {
            return;
        }

        $property_id = isset($_GET['property']) ? (int) $_GET['property'] : 0;
        if (!$property_id || get_post_type($property_id) !== 'property') {
            wp_die(
                esc_html__('Invalid property. Please go back and try again.', 'dg-platform'),
                esc_html__('Brochure unavailable', 'dg-platform'),
                ['response' => 404]
            );
        }

        if (get_post_status($property_id) !== 'publish') {
            wp_die(
                esc_html__('This property is not available.', 'dg-platform'),
                esc_html__('Brochure unavailable', 'dg-platform'),
                ['response' => 404]
            );
        }

        $uploaded_pdf = get_post_meta($property_id, 'roe_property_brochure', true);
        if (!empty($uploaded_pdf) && filter_var($uploaded_pdf, FILTER_VALIDATE_URL)) {
            wp_safe_redirect($uploaded_pdf);
            exit;
        }

        $data = self::collect($property_id);
        $data['auto_print'] = !empty($_GET['download']);

        $template = __DIR__ . '/../templates/property-brochure.php';
        if (!file_exists($template)) {
            wp_die('Brochure template missing.');
        }

        include $template;
        exit;
    }

    /**
     * @return array<string,mixed>
     */
    public static function collect($post_id) {
        $post_id = (int) $post_id;

        $title = get_the_title($post_id);
        $marketing_title = get_post_meta($post_id, 'roe_property_title', true);
        if ($marketing_title === '') {
            $marketing_title = $title;
        }

        $price = get_post_meta($post_id, 'roe_property_price', true);
        $status = get_post_meta($post_id, 'roe_property_status', true);
        $address = get_post_meta($post_id, 'roe_property_address', true);
        $suburb = get_post_meta($post_id, 'roe_property_suburb', true);
        $state = get_post_meta($post_id, 'roe_property_state', true);
        $postcode = get_post_meta($post_id, 'roe_property_postcode', true);
        $beds = get_post_meta($post_id, 'roe_property_bedrooms', true);
        $baths = get_post_meta($post_id, 'roe_property_bathrooms', true);
        $cars = get_post_meta($post_id, 'roe_property_car_spaces', true);
        $land = get_post_meta($post_id, 'roe_property_land_size', true);
        $building = get_post_meta($post_id, 'roe_property_building_size', true);
        $year_built = get_post_meta($post_id, 'roe_property_year_built', true);
        $property_type = get_post_meta($post_id, 'roe_property_type', true);
        $description = get_post_meta($post_id, 'roe_property_description', true);
        $features_raw = get_post_meta($post_id, 'roe_property_features', true);
        $inspection_times = get_post_meta($post_id, 'roe_property_inspection_times', true);
        $agent_name = get_post_meta($post_id, 'roe_property_agent_name', true);
        $agent_phone = get_post_meta($post_id, 'roe_property_agent_phone', true);
        $agent_email = get_post_meta($post_id, 'roe_property_agent_email', true);
        $agent_id = (int) get_post_meta($post_id, 'roe_property_agent_id', true);

        $full_address = function_exists('roe_property_full_address')
            ? roe_property_full_address($post_id)
            : implode(', ', array_filter([$address, $suburb, $state, $postcode]));

        $formatted_price = function_exists('roe_format_price')
            ? roe_format_price($price)
            : ($price ? '$' . number_format((float) preg_replace('/[^0-9.]/', '', (string) $price)) : 'Contact for Price');

        $status_colors = [
            'For Sale' => '#2E7D32',
            'Under Contract' => '#F57C00',
            'Sold' => '#C62828',
            'Withdrawn' => '#666666',
        ];
        $status_color = $status_colors[$status] ?? '#666666';

        $images = self::gallery_urls($post_id);
        $floorplans = self::attachment_urls($post_id, 'roe_property_floorplans', 'large');

        $features = [];
        if ($features_raw !== '') {
            foreach (preg_split('/\r\n|\r|\n/', $features_raw) as $line) {
                $line = trim(ltrim(trim($line), '-*•'));
                if ($line !== '') {
                    $features[] = $line;
                }
            }
        }

        $agent_photo = get_post_meta($post_id, 'roe_property_agent_photo', true);
        $agent_position = '';
        if ($agent_id && has_post_thumbnail($agent_id)) {
            $agent_photo = get_the_post_thumbnail_url($agent_id, 'medium');
            $agent_position = get_post_meta($agent_id, 'roe_agent_position', true);
        } elseif ($agent_name && !$agent_photo) {
            $agent_query = get_posts([
                'post_type' => 'agent',
                'title' => $agent_name,
                'posts_per_page' => 1,
                'post_status' => 'publish',
            ]);
            if (!empty($agent_query)) {
                $agent_post = $agent_query[0];
                if (has_post_thumbnail($agent_post->ID)) {
                    $agent_photo = get_the_post_thumbnail_url($agent_post->ID, 'medium');
                }
                if ($agent_position === '') {
                    $agent_position = get_post_meta($agent_post->ID, 'roe_agent_position', true);
                }
            }
        }

        $logo_url = '';
        if (class_exists('DG_SEO_Settings')) {
            $logo_url = (string) DG_SEO_Settings::get('logo_url', '');
        }
        if ($logo_url === '') {
            $custom_logo_id = get_theme_mod('custom_logo');
            if ($custom_logo_id) {
                $logo_url = (string) wp_get_attachment_image_url($custom_logo_id, 'medium');
            }
        }

        $org_name = class_exists('DG_SEO_Settings')
            ? (string) DG_SEO_Settings::get('organization_name', get_bloginfo('name'))
            : get_bloginfo('name');

        return [
            'post_id' => $post_id,
            'title' => $marketing_title,
            'permalink' => get_permalink($post_id),
            'price' => $formatted_price,
            'status' => $status,
            'status_color' => $status_color,
            'full_address' => $full_address,
            'beds' => $beds,
            'baths' => $baths,
            'cars' => $cars,
            'land' => $land,
            'building' => $building,
            'year_built' => $year_built,
            'property_type' => $property_type,
            'description' => $description,
            'features' => $features,
            'inspection_times' => $inspection_times,
            'images' => $images,
            'floorplans' => $floorplans,
            'agent' => [
                'name' => $agent_name,
                'phone' => $agent_phone,
                'email' => $agent_email,
                'photo' => $agent_photo,
                'position' => $agent_position,
            ],
            'logo_url' => $logo_url,
            'org_name' => $org_name,
        ];
    }

    /** @return string[] */
    private static function gallery_urls($post_id) {
        $urls = [];
        $seen = [];

        $featured = get_the_post_thumbnail_url($post_id, 'large');
        if ($featured) {
            $urls[] = $featured;
            $seen[$featured] = true;
        }

        $gallery_ids = get_post_meta($post_id, 'roe_property_gallery', true);
        if ($gallery_ids !== '') {
            foreach (array_map('trim', explode(',', $gallery_ids)) as $id) {
                if ($id === '') {
                    continue;
                }
                $url = wp_get_attachment_image_url((int) $id, 'large');
                if ($url && empty($seen[$url])) {
                    $urls[] = $url;
                    $seen[$url] = true;
                }
            }
        }

        return $urls;
    }

    /** @return string[] */
    private static function attachment_urls($post_id, $meta_key, $size = 'large') {
        $urls = [];
        $raw = get_post_meta($post_id, $meta_key, true);
        if ($raw === '') {
            return $urls;
        }

        foreach (array_map('trim', explode(',', $raw)) as $id) {
            if ($id === '') {
                continue;
            }
            $url = wp_get_attachment_image_url((int) $id, $size);
            if ($url) {
                $urls[] = $url;
            }
        }

        return $urls;
    }
}
