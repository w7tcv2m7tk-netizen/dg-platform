<?php
/**
 * Shared OpenAI / Gemini client for DG Platform AI features.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_AI_Client {

    /** @return bool */
    public static function available() {
        if (!class_exists('DG_Integrations')) {
            return false;
        }
        return (bool) (DG_Integrations::get_api_key('openai') || DG_Integrations::get_api_key('gemini'));
    }

    /**
     * @return array{provider:string,text:string}|WP_Error
     */
    public static function chat($system_prompt, $user_prompt, $max_tokens = 800) {
        if (!self::available()) {
            return new WP_Error(
                'no_ai_key',
                'Add an OpenAI or Gemini API key in DG Platform → API Settings.'
            );
        }

        $errors = [];
        $max_tokens = max(256, min(4096, (int) $max_tokens));

        $openai = DG_Integrations::get_api_key('openai');
        if ($openai) {
            $openai_result = self::chat_openai($openai, $system_prompt, $user_prompt, $max_tokens);
            if (!is_wp_error($openai_result)) {
                return $openai_result;
            }
            $errors[] = 'OpenAI: ' . $openai_result->get_error_message();
        }

        $gemini = DG_Integrations::get_api_key('gemini');
        if ($gemini) {
            $gemini_result = self::chat_gemini($gemini, $system_prompt, $user_prompt, $max_tokens);
            if (!is_wp_error($gemini_result)) {
                return $gemini_result;
            }
            $errors[] = 'Gemini: ' . $gemini_result->get_error_message();
        }

        if ($errors) {
            return new WP_Error('ai_failed', implode(' ', $errors));
        }

        return new WP_Error('ai_failed', 'AI request failed. Check your API key and try again.');
    }

    /**
     * @return array{provider:string,text:string}|WP_Error
     */
    private static function chat_openai($api_key, $system_prompt, $user_prompt, $max_tokens) {
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => apply_filters('dg_ai_openai_model', 'gpt-4o-mini'),
                'messages' => [
                    ['role' => 'system', 'content' => $system_prompt],
                    ['role' => 'user', 'content' => $user_prompt],
                ],
                'temperature' => 0.35,
                'max_tokens' => $max_tokens,
            ]),
            'timeout' => 90,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('openai_failed', $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code >= 400) {
            return new WP_Error('openai_failed', self::format_openai_error($data, $code));
        }

        $message = is_array($data) ? ($data['choices'][0]['message'] ?? []) : [];
        $text = trim((string) ($message['content'] ?? ''));
        if ($text !== '') {
            return ['provider' => 'openai', 'text' => $text];
        }

        if (!empty($message['refusal'])) {
            return new WP_Error('openai_refused', 'OpenAI declined to respond: ' . sanitize_text_field((string) $message['refusal']));
        }

        return new WP_Error('openai_empty', 'OpenAI returned no content.');
    }

    /**
     * @return array{provider:string,text:string}|WP_Error
     */
    private static function chat_gemini($api_key, $system_prompt, $user_prompt, $max_tokens) {
        $models = apply_filters('dg_ai_gemini_models', [
            'gemini-2.0-flash',
            'gemini-1.5-flash',
            'gemini-1.5-flash-latest',
        ]);

        $errors = [];
        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $system_prompt]],
            ],
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $user_prompt]],
            ]],
            'generationConfig' => [
                'temperature' => 0.35,
                'maxOutputTokens' => $max_tokens,
            ],
        ];

        foreach ($models as $model) {
            $model = sanitize_key((string) $model);
            if ($model === '') {
                continue;
            }

            $response = wp_remote_post(
                'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($api_key),
                [
                    'headers' => ['Content-Type' => 'application/json'],
                    'body' => wp_json_encode($payload),
                    'timeout' => 90,
                ]
            );

            if (is_wp_error($response)) {
                $errors[] = $model . ': ' . $response->get_error_message();
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if ($code >= 400) {
                $errors[] = $model . ': ' . self::format_gemini_error($data, $code);
                continue;
            }

            $text = self::extract_gemini_text($data);
            if ($text !== '') {
                return ['provider' => 'gemini', 'text' => $text];
            }

            $errors[] = $model . ': ' . self::gemini_empty_reason($data);
        }

        return new WP_Error(
            'gemini_failed',
            $errors ? implode(' ', $errors) : 'Gemini returned no content.'
        );
    }

    /** @param array<string,mixed>|null $data */
    private static function extract_gemini_text($data) {
        if (!is_array($data)) {
            return '';
        }

        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        if (!is_array($parts)) {
            return '';
        }

        $text = '';
        foreach ($parts as $part) {
            if (is_array($part) && !empty($part['text'])) {
                $text .= (string) $part['text'];
            }
        }

        return trim($text);
    }

    /** @param array<string,mixed>|null $data */
    private static function gemini_empty_reason($data) {
        if (!is_array($data)) {
            return 'empty response body';
        }

        if (!empty($data['promptFeedback']['blockReason'])) {
            return 'blocked (' . sanitize_text_field((string) $data['promptFeedback']['blockReason']) . ')';
        }

        $finish = $data['candidates'][0]['finishReason'] ?? '';
        if ($finish !== '' && $finish !== 'STOP') {
            return 'finish reason ' . sanitize_text_field((string) $finish);
        }

        return 'empty response';
    }

    /** @param array<string,mixed>|null $data */
    private static function format_openai_error($data, $code) {
        if (is_array($data) && !empty($data['error']['message'])) {
            return sanitize_text_field((string) $data['error']['message']);
        }

        return 'HTTP ' . (int) $code;
    }

    /** @param array<string,mixed>|null $data */
    private static function format_gemini_error($data, $code) {
        if (is_array($data) && !empty($data['error']['message'])) {
            return sanitize_text_field((string) $data['error']['message']);
        }

        return 'HTTP ' . (int) $code;
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    public static function chat_json($system_prompt, $user_prompt, $max_tokens = 800) {
        $system = $system_prompt . ' Reply with valid JSON only — no markdown fences.';
        $raw = self::chat($system, $user_prompt, $max_tokens);
        if (is_wp_error($raw)) {
            return $raw;
        }

        $parsed = self::extract_json($raw['text']);
        if (!is_array($parsed)) {
            $snippet = wp_html_excerpt(trim((string) $raw['text']), 120, '…');
            $detail = $snippet !== '' ? ' Response started with: "' . $snippet . '"' : '';
            return new WP_Error('ai_parse_failed', 'Could not parse AI response as JSON.' . $detail);
        }

        $parsed['provider'] = $raw['provider'];
        return $parsed;
    }

    /** @return array<string,mixed>|null */
    public static function extract_json($text) {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $text, $fence)) {
            $text = trim($fence[1]);
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $json = json_decode($m[0], true);
            if (is_array($json)) {
                return $json;
            }
        }

        return null;
    }

    public static function site_context() {
        $site = class_exists('DG_Site_Profile') ? DG_Site_Profile::label() : get_bloginfo('name');
        $host = class_exists('DG_Site_Profile') ? DG_Site_Profile::hostname() : parse_url(home_url(), PHP_URL_HOST);
        return [
            'site_name' => $site,
            'host' => $host,
            'home_url' => home_url('/'),
        ];
    }
}
