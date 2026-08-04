<?php
/**
 * JSON-LD structured data output.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_SEO_Schema {

    public static function init() {
        if (is_admin()) {
            return;
        }
        add_action('wp_head', [__CLASS__, 'render'], 20);
    }

    public static function render() {
        if (!apply_filters('dg_seo/output_schema', true)) {
            return;
        }

        $graphs = [];
        $graphs[] = self::organization();
        $graphs[] = self::website();

        if (is_singular()) {
            $post = get_queried_object();
            if ($post instanceof WP_Post) {
                $graphs[] = self::webpage($post);
                $specific = self::post_type_schema($post);
                if ($specific) {
                    $graphs[] = $specific;
                }
            }
        }

        $graphs = array_values(array_filter($graphs));
        if (!$graphs) {
            return;
        }

        if (count($graphs) === 1) {
            $graphs[0]['@context'] = 'https://schema.org';
            $payload = $graphs[0];
        } else {
            $payload = ['@context' => 'https://schema.org', '@graph' => $graphs];
        }

        echo '<script type="application/ld+json" class="dg-seo-schema">' . wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    private static function organization() {
        $type = DG_SEO_Settings::get('organization_type', 'Organization');
        $data = [
            '@type' => $type,
            '@id' => home_url('/#organization'),
            'name' => DG_SEO_Settings::get('organization_name', get_bloginfo('name')),
            'url' => DG_SEO_Settings::get('organization_url', home_url('/')),
        ];

        $logo = DG_SEO_Settings::get('logo_url', '');
        if ($logo) {
            $data['logo'] = $logo;
        }

        $same_as = array_filter([
            DG_SEO_Settings::get('social_facebook', ''),
            DG_SEO_Settings::get('social_instagram', ''),
        ]);
        if ($same_as) {
            $data['sameAs'] = array_values($same_as);
        }

        return $data;
    }

    private static function website() {
        return [
            '@type' => 'WebSite',
            '@id' => home_url('/#website'),
            'url' => home_url('/'),
            'name' => DG_SEO_Settings::get('organization_name', get_bloginfo('name')),
            'publisher' => ['@id' => home_url('/#organization')],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => home_url('/?s={search_term_string}'),
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /** @param WP_Post $post */
    private static function webpage($post) {
        $seo = DG_SEO_Settings::get_post_seo($post->ID);
        return [
            '@type' => 'WebPage',
            '@id' => get_permalink($post) . '#webpage',
            'url' => get_permalink($post),
            'name' => $seo['title'] ?? $post->post_title,
            'description' => $seo['description'] ?? '',
            'isPartOf' => ['@id' => home_url('/#website')],
            'datePublished' => get_the_date('c', $post),
            'dateModified' => get_the_modified_date('c', $post),
        ];
    }

    /** @param WP_Post $post */
    private static function post_type_schema($post) {
        switch ($post->post_type) {
            case 'property':
                return self::real_estate_listing($post);
            case 'dg_accommodation':
                return self::lodging_business($post);
            case 'post':
                return self::article($post);
            default:
                return null;
        }
    }

    /** @param WP_Post $post */
    private static function real_estate_listing($post) {
        $address = trim(get_post_meta($post->ID, 'roe_property_address', true));
        $suburb = get_post_meta($post->ID, 'roe_property_suburb', true);
        $state = get_post_meta($post->ID, 'roe_property_state', true);
        $postcode = get_post_meta($post->ID, 'roe_property_postcode', true);
        $price = get_post_meta($post->ID, 'roe_property_price', true);
        $beds = get_post_meta($post->ID, 'roe_property_bedrooms', true);
        $baths = get_post_meta($post->ID, 'roe_property_bathrooms', true);
        $type = get_post_meta($post->ID, 'roe_property_type', true);
        $status = get_post_meta($post->ID, 'roe_property_status', true);
        $seo = DG_SEO_Settings::get_post_seo($post->ID);

        $schema = [
            '@type' => 'RealEstateListing',
            'name' => get_post_meta($post->ID, 'roe_property_title', true) ?: $post->post_title,
            'description' => $seo['description'] ?? '',
            'url' => get_permalink($post),
        ];

        if ($address || $suburb) {
            $schema['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $address,
                'addressLocality' => $suburb,
                'addressRegion' => $state,
                'postalCode' => $postcode,
                'addressCountry' => 'AU',
            ];
        }

        if ($price) {
            $schema['offers'] = [
                '@type' => 'Offer',
                'price' => (float) preg_replace('/[^0-9.]/', '', (string) $price),
                'priceCurrency' => 'AUD',
            ];
        }

        $accommodation = [];
        if ($beds) {
            $accommodation['numberOfBedrooms'] = (int) $beds;
        }
        if ($baths) {
            $accommodation['numberOfBathroomsTotal'] = (int) $baths;
        }
        if ($accommodation) {
            $schema['accommodationCategory'] = $type ?: 'House';
            $schema = array_merge($schema, $accommodation);
        }

        if ($status) {
            $schema['availability'] = stripos($status, 'sold') !== false ? 'SoldOut' : 'InStock';
        }

        $image = $seo['og_image'] ?? '';
        if ($image) {
            $schema['image'] = $image;
        }

        return $schema;
    }

    /** @param WP_Post $post */
    private static function lodging_business($post) {
        $seo = DG_SEO_Settings::get_post_seo($post->ID);
        $address = get_post_meta($post->ID, 'dg_address', true);
        $lat = get_post_meta($post->ID, 'dg_latitude', true);
        $lng = get_post_meta($post->ID, 'dg_longitude', true);
        $weekday = get_post_meta($post->ID, 'dg_weekday_rate', true);
        $weekend = get_post_meta($post->ID, 'dg_weekend_rate', true);
        $rate = $weekday ?: $weekend;

        $schema = [
            '@type' => 'LodgingBusiness',
            'name' => $post->post_title,
            'description' => $seo['description'] ?? '',
            'url' => get_permalink($post),
            'parentOrganization' => ['@id' => home_url('/#organization')],
        ];

        if ($address) {
            $schema['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $address,
                'addressCountry' => 'AU',
            ];
        }

        if ($lat && $lng) {
            $schema['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $lat,
                'longitude' => (float) $lng,
            ];
        }

        if ($rate) {
            $schema['priceRange'] = '$' . number_format((float) $rate) . '+';
        }

        $image = $seo['og_image'] ?? '';
        if ($image) {
            $schema['image'] = $image;
        }

        return $schema;
    }

    /** @param WP_Post $post */
    private static function article($post) {
        $seo = DG_SEO_Settings::get_post_seo($post->ID);
        $author = get_the_author_meta('display_name', (int) $post->post_author);

        return [
            '@type' => 'Article',
            'headline' => $post->post_title,
            'description' => $seo['description'] ?? '',
            'datePublished' => get_the_date('c', $post),
            'dateModified' => get_the_modified_date('c', $post),
            'author' => [
                '@type' => 'Person',
                'name' => $author,
            ],
            'publisher' => ['@id' => home_url('/#organization')],
            'mainEntityOfPage' => get_permalink($post),
            'image' => $seo['og_image'] ?? null,
        ];
    }
}
