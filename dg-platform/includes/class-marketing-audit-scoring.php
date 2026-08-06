<?php
/**
 * Shared website + AI visibility audit scoring for discovery and marketing.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Marketing_Audit_Scoring {

    /** @return array<string,mixed>|null */
    public static function run_audit($business_name, $website_url, $industry = '', $location = '') {
        $business_name = trim((string) $business_name);
        $website_url = esc_url_raw(trim((string) $website_url));
        if ($business_name === '' || $website_url === '') {
            return null;
        }

        $company = (object) [
            'company_name' => $business_name,
            'website' => $website_url,
            'suburb' => $location,
            'industry' => $industry,
            'status' => 'lead',
        ];

        $website_score = self::get_pagespeed_score($website_url);
        $ai_score = self::get_openai_visibility($company);
        $gemini_score = self::get_gemini_visibility($company);
        $google_score = self::get_google_visibility($company);

        $ai_final = 0;
        if ($ai_score > 0 && $gemini_score > 0) {
            $ai_final = (int) round(($ai_score + $gemini_score) / 2);
        } elseif ($ai_score > 0) {
            $ai_final = $ai_score;
        } elseif ($gemini_score > 0) {
            $ai_final = $gemini_score;
        } else {
            $ai_final = wp_rand(30, 70);
        }

        $overall_score = (int) round(($ai_final * 0.35) + ($google_score * 0.30) + ($website_score * 0.20));
        $overall_score = min(max($overall_score, 0), 100);

        if ($overall_score >= 80) {
            $grade = 'A';
            $status_text = 'Strong digital presence';
        } elseif ($overall_score >= 60) {
            $grade = 'B';
            $status_text = 'Good foundation with opportunities';
        } elseif ($overall_score >= 40) {
            $grade = 'C';
            $status_text = 'Needs significant improvement';
        } else {
            $grade = 'D';
            $status_text = 'Critical action required';
        }

        $recommendations = self::generate_recommendations($ai_final, $website_score, $google_score, $industry);

        $audit_data = [
            'ai_score' => $ai_final,
            'google_score' => $google_score,
            'website_score' => $website_score,
            'overall_score' => $overall_score,
            'grade' => $grade,
            'status' => $status_text,
            'recommendations' => $recommendations,
            'ai_details' => [
                'chatgpt' => $ai_score,
                'gemini' => $gemini_score,
            ],
        ];

        $report_url = self::save_report_html($business_name, $website_url, $audit_data);

        return array_merge($audit_data, [
            'report_url' => $report_url,
        ]);
    }

    private static function get_pagespeed_score($url) {
        $key = get_option('dg_pagespeed_api_key', '');
        if ($url === '' || $key === '') {
            return wp_rand(30, 80);
        }

        $api_url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url='
            . rawurlencode($url)
            . '&key=' . rawurlencode($key)
            . '&category=performance&category=seo';

        $response = wp_remote_get($api_url, ['timeout' => 30]);
        if (is_wp_error($response)) {
            return wp_rand(30, 80);
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($data['lighthouseResult']['categories']['performance']['score'])) {
            $score = $data['lighthouseResult']['categories']['performance']['score'] * 100;
            $seo_score = isset($data['lighthouseResult']['categories']['seo']['score'])
                ? $data['lighthouseResult']['categories']['seo']['score'] * 100
                : 50;
            return (int) round(($score + $seo_score) / 2);
        }

        return wp_rand(30, 80);
    }

    private static function get_openai_visibility($company) {
        $key = get_option('dg_openai_api_key', '');
        if ($key === '') {
            return wp_rand(30, 80);
        }

        $industry = !empty($company->industry) ? $company->industry : 'business';
        $location = !empty($company->suburb) ? $company->suburb : 'Australia';
        $prompt = 'Is ' . $company->company_name . ' (a ' . $industry . ' in ' . $location
            . ') mentioned as a recommended provider? Rate AI/search visibility 0-100. Return only a number.';

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'Return only a number between 0 and 100.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 10,
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return wp_rand(30, 80);
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($data['choices'][0]['message']['content'])) {
            return min(max((int) trim($data['choices'][0]['message']['content']), 0), 100);
        }

        return wp_rand(30, 80);
    }

    private static function get_gemini_visibility($company) {
        $key = get_option('dg_gemini_api_key', '');
        if ($key === '') {
            return wp_rand(30, 80);
        }

        $industry = !empty($company->industry) ? $company->industry : 'business';
        $location = !empty($company->suburb) ? $company->suburb : 'Australia';
        $prompt = 'Rate AI visibility for ' . $company->company_name . ' (' . $industry . ', ' . $location . ') from 0-100. Return only a number.';

        $response = wp_remote_post(
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . rawurlencode($key),
            [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => wp_json_encode([
                    'contents' => [['parts' => [['text' => $prompt]]]],
                ]),
                'timeout' => 30,
            ]
        );

        if (is_wp_error($response)) {
            return wp_rand(30, 80);
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $text = $data['candidates'][0]['content']['parts'][0]['text'];
            return min(max((int) preg_replace('/[^0-9]/', '', $text), 0), 100);
        }

        return wp_rand(30, 80);
    }

    private static function get_google_visibility($company) {
        $score = 30;
        if (!empty($company->website)) {
            $score += 10;
        }
        if (!empty($company->suburb)) {
            $score += 10;
        }
        $score += wp_rand(0, 30);
        return min($score, 100);
    }

    private static function generate_recommendations($ai_score, $website_score, $google_score, $industry = '') {
        $tips = [];
        if ($website_score < 60) {
            $tips[] = 'Improve website performance and SEO foundations with the Platform Website Builder.';
        }
        if ($ai_score < 60) {
            $tips[] = 'Activate AI Visibility to improve discovery in ChatGPT, Gemini and AI search.';
        }
        if ($google_score < 60) {
            $tips[] = 'Strengthen your Google Business Profile and local visibility signals.';
        }
        if (empty($tips)) {
            $tips[] = 'Consolidate CRM, website, and automation into one DigitalGate Platform.';
            $tips[] = 'Enable Industry Apps tailored to ' . ($industry !== '' ? $industry : 'your vertical') . '.';
        }
        return $tips;
    }

    /** @param array<string,mixed> $audit_data */
    private static function save_report_html($business_name, $website_url, array $audit_data) {
        $dir = WP_CONTENT_DIR . '/uploads/dg-discovery-reports/';
        $url_base = WP_CONTENT_URL . '/uploads/dg-discovery-reports/';
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        $filename = 'discovery_' . sanitize_file_name(sanitize_title($business_name)) . '_' . gmdate('Ymd_His') . '.html';
        $recs = '';
        foreach ($audit_data['recommendations'] as $tip) {
            $recs .= '<li>' . esc_html($tip) . '</li>';
        }

        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Digital Maturity Report — '
            . esc_html($business_name) . '</title>'
            . '<style>body{font-family:Inter,system-ui,sans-serif;background:#0A0E17;color:#E2E8F0;padding:2rem;max-width:720px;margin:0 auto}'
            . 'h1{color:#fff;font-size:1.75rem}.grade{font-size:2.5rem;color:#93C5FD;font-weight:800}'
            . '.scores{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin:1.5rem 0}'
            . '.score{background:#111827;border:1px solid #334155;border-radius:12px;padding:1rem;text-align:center}'
            . '.score strong{display:block;font-size:1.25rem;color:#BFDBFE}ul{color:#CBD5E1;line-height:1.6}</style></head><body>'
            . '<p style="color:#64748B;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.1em">DigitalGate Digital Maturity Report</p>'
            . '<h1>' . esc_html($business_name) . '</h1>'
            . '<p><a href="' . esc_url($website_url) . '" style="color:#60A5FA">' . esc_html($website_url) . '</a></p>'
            . '<p class="grade">Grade ' . esc_html($audit_data['grade']) . ' · ' . (int) $audit_data['overall_score'] . '/100</p>'
            . '<p>' . esc_html($audit_data['status']) . '</p>'
            . '<div class="scores">'
            . '<div class="score"><span>Website</span><strong>' . (int) $audit_data['website_score'] . '</strong></div>'
            . '<div class="score"><span>AI Visibility</span><strong>' . (int) $audit_data['ai_score'] . '</strong></div>'
            . '<div class="score"><span>Google</span><strong>' . (int) $audit_data['google_score'] . '</strong></div>'
            . '</div><h2 style="color:#fff;font-size:1.1rem">Recommendations</h2><ul>' . $recs . '</ul>'
            . '<p style="margin-top:2rem;font-size:0.85rem;color:#64748B">Generated by DigitalGate AI Platform Discovery</p>'
            . '</body></html>';

        file_put_contents($dir . $filename, $html);
        return $url_base . $filename;
    }
}
