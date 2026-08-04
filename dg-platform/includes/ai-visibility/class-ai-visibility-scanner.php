<?php
/**
 * AI Visibility scanner — OpenAI, Gemini, and on-site technical signals.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_AI_Visibility_Scanner {

    /** @return array<string,mixed>|WP_Error */
    public static function run($source = 'manual') {
        if (!DG_AI_Visibility_Settings::is_enabled()) {
            return new WP_Error('not_enabled', 'AI Visibility Pro is not enabled on this plan.');
        }

        $ctx = DG_AI_Visibility_Settings::business_context();
        $openai = self::query_openai($ctx);
        $gemini = self::query_gemini($ctx);
        $technical = self::technical_score();

        $recommendations = DG_AI_Visibility_Recommendations::generate([
            'openai_score' => $openai['score'],
            'gemini_score' => $gemini['score'],
            'technical_score' => $technical['score'],
            'openai_summary' => $openai['summary'],
            'gemini_summary' => $gemini['summary'],
            'technical' => $technical['signals'],
        ]);

        $host = class_exists('DG_Site_Profile') ? DG_Site_Profile::hostname() : parse_url(home_url(), PHP_URL_HOST);

        $scan_id = DG_AI_Visibility_History::record([
            'site_host' => $host,
            'business_name' => $ctx['name'],
            'openai_score' => $openai['score'],
            'gemini_score' => $gemini['score'],
            'technical_score' => $technical['score'],
            'openai_summary' => $openai['summary'],
            'gemini_summary' => $gemini['summary'],
            'recommendations' => $recommendations,
            'scan_source' => $source,
        ]);

        return [
            'id' => $scan_id,
            'openai_score' => $openai['score'],
            'gemini_score' => $gemini['score'],
            'technical_score' => $technical['score'],
            'combined_score' => (int) round(($openai['score'] + $gemini['score'] + $technical['score']) / 3),
            'grade' => DG_AI_Visibility_History::grade_for_score((int) round(($openai['score'] + $gemini['score'] + $technical['score']) / 3)),
            'openai_summary' => $openai['summary'],
            'gemini_summary' => $gemini['summary'],
            'recommendations' => $recommendations,
        ];
    }

    /** @param array<string,string> $ctx */
    private static function query_openai($ctx) {
        $key = DG_Integrations::get_api_key('openai');
        if (!$key) {
            return self::fallback_llm_score($ctx, 'openai');
        }

        $prompt = self::build_prompt($ctx);
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'You assess how visible a business is when people ask AI assistants for recommendations. Reply with JSON only: {"score":0-100,"summary":"one sentence"}'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.2,
                'max_tokens' => 200,
            ]),
            'timeout' => 45,
        ]);

        return self::parse_llm_response($response, $ctx, 'openai');
    }

    /** @param array<string,string> $ctx */
    private static function query_gemini($ctx) {
        $key = DG_Integrations::get_api_key('gemini');
        if (!$key) {
            return self::fallback_llm_score($ctx, 'gemini');
        }

        $prompt = self::build_prompt($ctx);
        $response = wp_remote_post(
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . rawurlencode($key),
            [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => wp_json_encode([
                    'contents' => [['parts' => [['text' => 'Reply with JSON only: {"score":0-100,"summary":"one sentence"}\n\n' . $prompt]]]],
                ]),
                'timeout' => 45,
            ]
        );

        return self::parse_gemini_response($response, $ctx);
    }

    /** @param array<string,string> $ctx */
    private static function build_prompt($ctx) {
        $queries = $ctx['queries'] ?: 'services like ' . $ctx['industry'];
        return sprintf(
            "Business: %s\nIndustry: %s\nLocation: %s\nWebsite: %s\n\nWhen users ask AI assistants about: %s\n\nHow likely is this business to be mentioned or recommended? Score 0-100 (0=never mentioned, 100=frequently recommended as a top choice).",
            $ctx['name'],
            $ctx['industry'] ?: 'general business',
            $ctx['location'],
            $ctx['website'],
            $queries
        );
    }

    /** @param array<string,string> $ctx */
    private static function parse_llm_response($response, $ctx, $provider) {
        if (is_wp_error($response)) {
            return self::fallback_llm_score($ctx, $provider);
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $text = $data['choices'][0]['message']['content'] ?? '';
        return self::parse_json_score($text, $ctx, $provider);
    }

    /** @param array<string,string> $ctx */
    private static function parse_gemini_response($response, $ctx) {
        if (is_wp_error($response)) {
            return self::fallback_llm_score($ctx, 'gemini');
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        return self::parse_json_score($text, $ctx, 'gemini');
    }

    /** @param array<string,string> $ctx */
    private static function parse_json_score($text, $ctx, $provider) {
        if (preg_match('/\{[^}]+\}/s', $text, $m)) {
            $json = json_decode($m[0], true);
            if (is_array($json) && isset($json['score'])) {
                return [
                    'score' => min(100, max(0, (int) $json['score'])),
                    'summary' => sanitize_text_field($json['summary'] ?? ''),
                ];
            }
        }

        $score = (int) preg_replace('/[^0-9]/', '', $text);
        if ($score > 100) {
            $score = min(100, (int) substr((string) $score, 0, 3));
        }

        if ($score <= 0) {
            return self::fallback_llm_score($ctx, $provider);
        }

        return [
            'score' => $score,
            'summary' => wp_trim_words(wp_strip_all_tags($text), 25, '…'),
        ];
    }

    /** @param array<string,string> $ctx */
    private static function fallback_llm_score($ctx, $provider) {
        $technical = self::technical_score();
        $base = (int) round($technical['score'] * 0.85);
        $summary = $provider === 'openai'
            ? 'OpenAI API key not configured — score estimated from on-site AI readiness signals.'
            : 'Gemini API key not configured — score estimated from on-site AI readiness signals.';

        return ['score' => $base, 'summary' => $summary];
    }

    /** @return array{score:int,signals:array<string,bool>} */
    public static function technical_score() {
        $signals = [
            'has_llms_txt' => (bool) DG_AI_Visibility_Settings::get('llms_txt_enabled'),
            'has_schema' => self::site_has_schema(),
            'has_meta_description' => self::homepage_has_meta(),
            'has_sitemap' => self::sitemap_available(),
            'recent_content' => self::has_recent_content(),
            'structured_pages' => self::count_key_pages() >= 3,
        ];

        $score = 0;
        foreach ($signals as $ok) {
            if ($ok) {
                $score += (int) round(100 / count($signals));
            }
        }

        return ['score' => min(100, $score), 'signals' => $signals];
    }

    private static function site_has_schema() {
        $home = wp_remote_get(home_url('/'), ['timeout' => 10, 'sslverify' => false]);
        if (is_wp_error($home)) {
            return class_exists('DG_SEO_Schema');
        }
        $body = wp_remote_retrieve_body($home);
        return strpos($body, 'application/ld+json') !== false;
    }

    private static function homepage_has_meta() {
        if (class_exists('DG_SEO_Settings')) {
            $desc = DG_SEO_Settings::get('home_description', '');
            return $desc !== '';
        }
        $home = wp_remote_get(home_url('/'), ['timeout' => 10, 'sslverify' => false]);
        if (is_wp_error($home)) {
            return false;
        }
        return (bool) preg_match('/<meta[^>]+name=["\']description["\']/i', wp_remote_retrieve_body($home));
    }

    private static function sitemap_available() {
        $resp = wp_remote_head(home_url('/sitemap_index.xml'), ['timeout' => 8, 'sslverify' => false]);
        return !is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200;
    }

    private static function has_recent_content() {
        $posts = get_posts([
            'post_type' => ['post', 'page', 'property', 'dg_accommodation'],
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'date_query' => [['after' => '90 days ago']],
            'fields' => 'ids',
        ]);
        return !empty($posts);
    }

    private static function count_key_pages() {
        return (int) wp_count_posts('page')->publish + (int) (post_type_exists('property') ? wp_count_posts('property')->publish : 0);
    }
}
