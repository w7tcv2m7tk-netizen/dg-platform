<?php
/**
 * AI Visibility recommendations based on scan results.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_AI_Visibility_Recommendations {

    /** @param array<string,mixed> $data @return array<int,string> */
    public static function generate(array $data) {
        $tips = [];
        $openai = (int) ($data['openai_score'] ?? 0);
        $gemini = (int) ($data['gemini_score'] ?? 0);
        $technical = (int) ($data['technical_score'] ?? 0);
        $signals = $data['technical'] ?? [];

        if ($openai < 50 || $gemini < 50) {
            $tips[] = 'Publish authoritative content answering the questions your customers ask AI assistants (FAQs, guides, suburb pages).';
            $tips[] = 'Build third-party mentions: local directories, industry listings, and partner backlinks increase AI training signal.';
            $tips[] = 'Ensure your business name, location, and services appear consistently across your website and Google Business Profile.';
        } else {
            $tips[] = 'Maintain AI visibility with monthly content updates and fresh case studies or property/accommodation listings.';
        }

        if (empty($signals['has_llms_txt'])) {
            $tips[] = 'Enable llms.txt (AI Visibility → Settings) so AI crawlers can discover your key pages.';
        }
        if (empty($signals['has_schema'])) {
            $tips[] = 'Add structured data (Organization, LocalBusiness, RealEstateListing) — DG SEO outputs this automatically.';
        }
        if (empty($signals['has_meta_description'])) {
            $tips[] = 'Set a clear homepage meta description summarising who you are and what you offer.';
        }
        if (empty($signals['has_sitemap'])) {
            $tips[] = 'Enable your XML sitemap so search engines and AI crawlers can index all public pages.';
        }
        if (empty($signals['recent_content'])) {
            $tips[] = 'Publish or update content in the last 90 days — stale sites rank lower in AI recommendations.';
        }
        if ($technical < 60) {
            $tips[] = 'Improve technical AI readiness: llms.txt, schema, sitemap, and fresh content together boost discoverability.';
        }

        $competitors = DG_AI_Visibility_Settings::get('competitors', '');
        if ($competitors && ($openai < 70 || $gemini < 70)) {
            $tips[] = 'Monitor competitors (' . $competitors . ') — create comparison and local expertise content they lack.';
        }

        return array_slice(array_unique($tips), 0, 8);
    }
}
