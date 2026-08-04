<?php
/**
 * Recipient first-name helpers for outgoing email personalization.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Email_Names {

    public static function init() {
        add_filter('wp_mail', [__CLASS__, 'filter_wp_mail'], 20);
    }

    /**
     * Extract a recipient's first name from a string, contact, user, or context array.
     *
     * @param mixed  $value
     * @param string $fallback
     */
    public static function first_name($value, $fallback = 'there') {
        if (is_object($value)) {
            if ($value instanceof WP_User) {
                if (!empty($value->first_name)) {
                    return sanitize_text_field($value->first_name);
                }
                $value = $value->display_name ?: $value->user_email;
            } elseif (isset($value->first_name) && $value->first_name !== '') {
                return sanitize_text_field((string) $value->first_name);
            } elseif (isset($value->name) && $value->name !== '') {
                $value = $value->name;
            } elseif (isset($value->full_name) && $value->full_name !== '') {
                $value = $value->full_name;
            } elseif (isset($value->contact_name) && $value->contact_name !== '') {
                $value = $value->contact_name;
            } else {
                $value = '';
            }
        }

        if (is_array($value)) {
            if (!empty($value['first_name'])) {
                return sanitize_text_field((string) $value['first_name']);
            }
            $value = $value['full_name'] ?? $value['name'] ?? $value['contact_name'] ?? '';
        }

        $value = trim((string) $value);
        if ($value === '') {
            return $fallback;
        }

        if (strpos($value, '@') !== false) {
            $local = explode('@', $value)[0];
            $local = str_replace(['.', '_'], ' ', $local);
            $parts = preg_split('/\s+/', trim($local), 2);
            return sanitize_text_field($parts[0] ?? $fallback);
        }

        $parts = preg_split('/\s+/', $value, 2);
        return sanitize_text_field($parts[0] ?? $fallback);
    }

    /**
     * Ensure template vars include first_name derived from full_name/name when missing.
     *
     * @param array<string,mixed> $vars
     * @return array<string,mixed>
     */
    public static function enrich_template_vars(array $vars) {
        if (empty($vars['first_name'])) {
            $source = $vars['full_name'] ?? $vars['name'] ?? $vars['contact_name'] ?? '';
            if ($source !== '') {
                $vars['first_name'] = self::first_name($source);
            }
        }

        if (empty($vars['full_name'])) {
            $source = $vars['name'] ?? $vars['contact_name'] ?? $vars['first_name'] ?? '';
            if ($source !== '') {
                $vars['full_name'] = trim((string) $source);
            }
        }

        return $vars;
    }

    /**
     * Normalize automation / workflow context so {{name}} resolves to first name only.
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public static function normalize_context(array $context) {
        if (!empty($context['first_name'])) {
            $context['name'] = self::first_name($context['first_name']);
            return $context;
        }

        if (!empty($context['name'])) {
            $first = self::first_name($context['name']);
            $context['first_name'] = $first;
            $context['name'] = $first;
        }

        return $context;
    }

    /**
     * Rewrite Hi/Dear greetings that use a multi-word name to first name only.
     */
    public static function personalize_message($message) {
        if (!is_string($message) || $message === '') {
            return $message;
        }

        return preg_replace_callback(
            '/(\b(?:Hi|Dear)\s+)([\w\-\'\.]+(?:\s+[\w\-\'\.]+)+)(\s*,|\n|<\/|\s*<\/)/u',
            function ($matches) {
                return $matches[1] . self::first_name($matches[2]) . $matches[3];
            },
            $message
        );
    }

    /**
     * @param array<string,mixed> $atts
     * @return array<string,mixed>
     */
    public static function filter_wp_mail($atts) {
        if (!empty($atts['message']) && is_string($atts['message'])) {
            $atts['message'] = self::personalize_message($atts['message']);
        }
        return $atts;
    }
}

DG_Email_Names::init();
