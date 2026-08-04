<?php
/**
 * SEO Pro — AI page optimisation (delegates to DG_AI_Assist).
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_SEO_AI_Optimizer {

    /** @return bool */
    public static function available() {
        return class_exists('DG_AI_Assist') && DG_AI_Assist::available();
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    public static function optimize($post_id) {
        return class_exists('DG_AI_Assist')
            ? DG_AI_Assist::seo_optimize($post_id)
            : new WP_Error('missing', 'AI assist unavailable.');
    }
}
