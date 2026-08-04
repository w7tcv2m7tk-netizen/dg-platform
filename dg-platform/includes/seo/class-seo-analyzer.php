<?php
/**
 * SEO page scoring and improvement suggestions.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_SEO_Analyzer {

    /** @return array<int,array{id:int,title:string,type:string,type_label:string,url:string,edit_url:string}> */
    public static function list_pages($post_type = '') {
        $types = $post_type !== ''
            ? [sanitize_key($post_type)]
            : DG_SEO_Settings::post_types_with_seo();

        $pages = [];
        foreach ($types as $type) {
            if (!post_type_exists($type)) {
                continue;
            }
            $object = get_post_type_object($type);
            $posts = get_posts([
                'post_type' => $type,
                'post_status' => ['publish', 'draft', 'pending', 'private'],
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
            ]);
            foreach ($posts as $post) {
                $pages[] = [
                    'id' => (int) $post->ID,
                    'title' => $post->post_title ?: ('(no title #' . $post->ID . ')'),
                    'type' => $type,
                    'type_label' => $object->labels->singular_name ?? $type,
                    'status' => $post->post_status,
                    'url' => get_permalink($post) ?: '',
                    'edit_url' => get_edit_post_link($post->ID, 'raw') ?: '',
                ];
            }
        }

        usort($pages, function ($a, $b) {
            return strcasecmp($a['title'], $b['title']);
        });

        return $pages;
    }

    /**
     * Pages in hierarchical menu order (for SEO overview tables).
     *
     * @return array<int,array{post:WP_Post,depth:int,score:int|null}>
     */
    public static function list_pages_hierarchical($post_type = 'page') {
        if (!post_type_exists($post_type)) {
            return [];
        }

        $all = get_pages([
            'post_type' => $post_type,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'sort_column' => 'menu_order,post_title',
            'sort_order' => 'ASC',
            'number' => 0,
        ]);

        $flat = [];
        $walk = function ($parent_id, $depth) use (&$walk, $all, &$flat) {
            foreach ($all as $page) {
                if ((int) $page->post_parent !== (int) $parent_id) {
                    continue;
                }
                $score = null;
                if (class_exists('DG_SEO_Analyzer')) {
                    try {
                        $analysis = self::analyze($page->ID);
                        $score = isset($analysis['score']) ? (int) $analysis['score'] : null;
                    } catch (Throwable $e) {
                        $score = null;
                    }
                }
                $flat[] = [
                    'post' => $page,
                    'depth' => $depth,
                    'score' => $score,
                ];
                $walk($page->ID, $depth + 1);
            }
        };

        $walk(0, 0);

        return $flat;
    }

    /**
     * @return array<string,mixed>
     */
    public static function analyze($post_id) {
        $post_id = (int) $post_id;
        $post = get_post($post_id);
        if (!$post) {
            return ['error' => 'Page not found.'];
        }

        $prefix = DG_SEO_Settings::META_PREFIX;
        $resolved = DG_SEO_Settings::get_post_seo($post_id);
        $stored = [
            'title' => (string) get_post_meta($post_id, $prefix . 'title', true),
            'description' => (string) get_post_meta($post_id, $prefix . 'description', true),
            'focus_keyword' => (string) get_post_meta($post_id, $prefix . 'focus_keyword', true),
            'og_title' => (string) get_post_meta($post_id, $prefix . 'og_title', true),
            'og_description' => (string) get_post_meta($post_id, $prefix . 'og_description', true),
            'og_image' => (string) get_post_meta($post_id, $prefix . 'og_image', true),
            'canonical' => (string) get_post_meta($post_id, $prefix . 'canonical', true),
            'noindex' => (bool) get_post_meta($post_id, $prefix . 'noindex', true),
            'nofollow' => (bool) get_post_meta($post_id, $prefix . 'nofollow', true),
        ];

        $title = $stored['title'] !== '' ? $stored['title'] : (string) ($resolved['title'] ?? '');
        $description = $stored['description'] !== '' ? $stored['description'] : (string) ($resolved['description'] ?? '');
        $keyword = trim($stored['focus_keyword']);
        $content_text = wp_strip_all_tags($post->post_content);
        $word_count = self::word_count($content_text);
        $slug = $post->post_name;
        $has_thumb = (bool) get_post_thumbnail_id($post_id);
        $og_image = $stored['og_image'] !== '' ? $stored['og_image'] : (string) ($resolved['og_image'] ?? '');
        $internal_links = preg_match_all('/href=["\']' . preg_quote(home_url(), '/') . '[^"\']*["\']/i', $post->post_content, $m);

        $checks = [];
        $checks[] = self::check_focus_keyword($keyword);
        $checks[] = self::check_title($title, $keyword, $stored['title'] === '');
        $checks[] = self::check_description($description, $keyword, $stored['description'] === '');
        $checks[] = self::check_keyword_in_title($title, $keyword);
        $checks[] = self::check_keyword_in_description($description, $keyword);
        $checks[] = self::check_content_length($word_count, $post->post_type);
        $checks[] = self::check_featured_image($has_thumb, $og_image);
        $checks[] = self::check_social($stored, $title, $description);
        $checks[] = self::check_slug($slug);
        $checks[] = self::check_noindex($stored['noindex'], $post);
        $checks[] = self::check_internal_links($internal_links);
        $checks[] = self::check_excerpt($post, $word_count);

        $score = self::score_from_checks($checks);
        $grade = self::grade_for_score($score);

        return [
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'post_type' => $post->post_type,
            'post_status' => $post->post_status,
            'permalink' => get_permalink($post) ?: '',
            'edit_url' => get_edit_post_link($post_id, 'raw') ?: '',
            'score' => $score,
            'grade' => $grade,
            'checks' => $checks,
            'fields' => [
                'focus_keyword' => $keyword,
                'title' => $stored['title'],
                'description' => $stored['description'],
                'og_title' => $stored['og_title'],
                'og_description' => $stored['og_description'],
                'og_image' => $stored['og_image'],
                'canonical' => $stored['canonical'],
                'noindex' => $stored['noindex'],
                'nofollow' => $stored['nofollow'],
                'robots' => DG_SEO_Settings::robots_value_from_meta($post_id),
            ],
            'resolved' => [
                'title' => $title,
                'description' => $description,
            ],
            'suggestions' => [
                'title' => self::suggest_title($post, $keyword),
                'description' => self::suggest_description($post, $keyword),
                'focus_keyword' => self::suggest_keyword($post),
            ],
            'stats' => [
                'word_count' => $word_count,
                'title_length' => strlen($title),
                'description_length' => strlen($description),
                'internal_links' => (int) $internal_links,
                'has_featured_image' => $has_thumb,
            ],
        ];
    }

    /** @param array<int,array<string,mixed>> $checks */
    private static function score_from_checks(array $checks) {
        $score = 100;
        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                $score -= (int) ($check['weight'] ?? 12);
            } elseif ($check['status'] === 'warn') {
                $score -= (int) ($check['weight_warn'] ?? 5);
            }
        }
        return max(0, min(100, $score));
    }

    private static function grade_for_score($score) {
        if ($score >= 90) {
            return ['label' => 'Excellent', 'color' => '#059669'];
        }
        if ($score >= 75) {
            return ['label' => 'Good', 'color' => '#2563EB'];
        }
        if ($score >= 50) {
            return ['label' => 'Needs work', 'color' => '#D97706'];
        }
        return ['label' => 'Poor', 'color' => '#DC2626'];
    }

    private static function word_count($text) {
        $text = trim(preg_replace('/\s+/', ' ', (string) $text));
        if ($text === '') {
            return 0;
        }
        return count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY));
    }

    /** @return array<string,mixed> */
    private static function check($id, $label, $status, $message, $suggestion, $weight = 12, $weight_warn = 5) {
        return compact('id', 'label', 'status', 'message', 'suggestion') + [
            'weight' => $weight,
            'weight_warn' => $weight_warn,
        ];
    }

    private static function check_focus_keyword($keyword) {
        if ($keyword === '') {
            return self::check(
                'focus_keyword',
                'Focus keyword',
                'fail',
                'No focus keyword set.',
                'Choose one primary phrase people would search for (e.g. "Currumbin Valley accommodation" or "Private studio rainforest retreat").',
                15
            );
        }
        if (str_word_count($keyword) > 6) {
            return self::check(
                'focus_keyword',
                'Focus keyword',
                'warn',
                'Keyword is quite long.',
                'Use a shorter, specific phrase (2–4 words) that matches search intent.',
                8,
                4
            );
        }
        return self::check('focus_keyword', 'Focus keyword', 'pass', 'Focus keyword is set.', '', 0, 0);
    }

    private static function check_title($title, $keyword, $is_auto) {
        $len = strlen($title);
        if ($title === '') {
            return self::check('seo_title', 'SEO title', 'fail', 'Missing SEO title.', 'Add a unique title under 60 characters that includes your focus keyword near the start.');
        }
        if ($is_auto) {
            return self::check('seo_title', 'SEO title', 'warn', 'Using auto-generated title (not customised).', 'Write a custom SEO title — auto titles are OK but customised titles convert better in search.', 6, 5);
        }
        if ($len > 60) {
            return self::check('seo_title', 'SEO title', 'fail', "Title is {$len} characters (max 60).", 'Shorten to 50–60 characters so Google does not truncate it.', 10);
        }
        if ($len < 30) {
            return self::check('seo_title', 'SEO title', 'warn', "Title is only {$len} characters.", 'Expand to 30–60 characters with location or benefit keywords.', 6, 5);
        }
        return self::check('seo_title', 'SEO title', 'pass', "Title length is good ({$len} chars).", '', 0, 0);
    }

    private static function check_description($description, $keyword, $is_auto) {
        $len = strlen($description);
        if ($description === '') {
            return self::check('meta_description', 'Meta description', 'fail', 'Missing meta description.', 'Write 120–160 characters that summarise the page and include a call to action.', 15);
        }
        if ($is_auto) {
            return self::check('meta_description', 'Meta description', 'warn', 'Using auto-generated description.', 'Customise the meta description for better click-through rates from Google.', 6, 5);
        }
        if ($len > 160) {
            return self::check('meta_description', 'Meta description', 'fail', "Description is {$len} characters (max 160).", 'Trim to 150–160 characters.', 10);
        }
        if ($len < 120) {
            return self::check('meta_description', 'Meta description', 'warn', "Description is only {$len} characters.", 'Expand to 120–160 characters with benefits and a subtle CTA.', 6, 5);
        }
        return self::check('meta_description', 'Meta description', 'pass', "Description length is good ({$len} chars).", '', 0, 0);
    }

    private static function check_keyword_in_title($title, $keyword) {
        if ($keyword === '' || $title === '') {
            return self::check('keyword_title', 'Keyword in title', 'warn', 'Cannot verify keyword in title.', 'Set a focus keyword and ensure it appears in the SEO title.', 0, 4);
        }
        if (stripos($title, $keyword) !== false) {
            return self::check('keyword_title', 'Keyword in title', 'pass', 'Focus keyword appears in the SEO title.', '', 0, 0);
        }
        return self::check(
            'keyword_title',
            'Keyword in title',
            'warn',
            'Focus keyword not found in SEO title.',
            'Include "' . $keyword . '" naturally near the beginning of the title.',
            0,
            6
        );
    }

    private static function check_keyword_in_description($description, $keyword) {
        if ($keyword === '' || $description === '') {
            return self::check('keyword_desc', 'Keyword in description', 'warn', 'Cannot verify keyword in description.', 'Add the focus keyword once in the meta description.', 0, 3);
        }
        if (stripos($description, $keyword) !== false) {
            return self::check('keyword_desc', 'Keyword in description', 'pass', 'Focus keyword appears in the meta description.', '', 0, 0);
        }
        return self::check(
            'keyword_desc',
            'Keyword in description',
            'warn',
            'Focus keyword not in meta description.',
            'Weave "' . $keyword . '" into the description naturally.',
            0,
            5
        );
    }

    private static function check_content_length($word_count, $post_type) {
        $min = $post_type === 'page' ? 300 : 200;
        $ideal = 600;
        if ($word_count < $min) {
            return self::check(
                'content_length',
                'Content length',
                'fail',
                "Only {$word_count} words on page (aim for {$min}+).",
                'Add more unique, helpful content — headings, benefits, FAQs, and local details help rankings.',
                12
            );
        }
        if ($word_count < $ideal) {
            return self::check(
                'content_length',
                'Content length',
                'warn',
                "{$word_count} words — good start, more depth helps.",
                'Aim for 600+ words with structured headings (H2/H3) and clear sections.',
                0,
                5
            );
        }
        return self::check('content_length', 'Content length', 'pass', "{$word_count} words — solid content depth.", '', 0, 0);
    }

    private static function check_featured_image($has_thumb, $og_image) {
        if ($has_thumb || $og_image !== '') {
            return self::check('featured_image', 'Featured / social image', 'pass', 'Page has an image for sharing.', '', 0, 0);
        }
        return self::check(
            'featured_image',
            'Featured / social image',
            'warn',
            'No featured image or social image.',
            'Set a featured image (1200×630px ideal) for better social previews and rich results.',
            0,
            6
        );
    }

    private static function check_social($stored, $title, $description) {
        if ($stored['og_title'] !== '' && $stored['og_description'] !== '') {
            return self::check('social', 'Social meta', 'pass', 'Custom social title and description set.', '', 0, 0);
        }
        return self::check(
            'social',
            'Social meta',
            'warn',
            'Using defaults for Facebook / LinkedIn previews.',
            'Optional: set Social tab fields for tailored Open Graph title, description, and image.',
            0,
            3
        );
    }

    private static function check_slug($slug) {
        if (strlen($slug) > 50) {
            return self::check('slug', 'URL slug', 'warn', 'URL slug is long.', 'Shorten the permalink slug to 3–5 words.', 0, 4);
        }
        if (preg_match('/\d{4,}/', $slug)) {
            return self::check('slug', 'URL slug', 'warn', 'Slug contains long numbers.', 'Use descriptive words instead of IDs in the URL.', 0, 3);
        }
        return self::check('slug', 'URL slug', 'pass', 'URL slug looks clean.', '', 0, 0);
    }

    /** @param WP_Post $post */
    private static function check_noindex($noindex, $post) {
        if (!$noindex) {
            return self::check('noindex', 'Indexable', 'pass', 'Page is indexable (not set to noindex).', '', 0, 0);
        }
        if ($post->post_status !== 'publish') {
            return self::check('noindex', 'Indexable', 'pass', 'Noindex is fine for non-published content.', '', 0, 0);
        }
        return self::check(
            'noindex',
            'Indexable',
            'fail',
            'Page is set to noindex — hidden from Google.',
            'Remove noindex unless this page should deliberately stay out of search results.',
            20
        );
    }

    private static function check_internal_links($count) {
        if ($count >= 2) {
            return self::check('internal_links', 'Internal links', 'pass', "{$count} internal link(s) found.", '', 0, 0);
        }
        if ($count === 1) {
            return self::check(
                'internal_links',
                'Internal links',
                'warn',
                'Only 1 internal link.',
                'Link to 2–3 related pages on your site (other stays, location, booking).',
                0,
                4
            );
        }
        return self::check(
            'internal_links',
            'Internal links',
            'warn',
            'No internal links in content.',
            'Add links to related pages — helps Google understand site structure and keeps visitors browsing.',
            0,
            5
        );
    }

    /** @param WP_Post $post */
    private static function check_excerpt($post, $word_count) {
        if ($post->post_excerpt !== '' || $word_count >= 300) {
            return self::check('excerpt', 'Excerpt / summary', 'pass', 'Page has enough summary content.', '', 0, 0);
        }
        return self::check(
            'excerpt',
            'Excerpt / summary',
            'warn',
            'No excerpt and thin content.',
            'Add an excerpt or opening paragraph that summarises the page in 1–2 sentences.',
            0,
            4
        );
    }

    /** @param WP_Post $post */
    private static function suggest_title($post, $keyword) {
        $site = DG_SEO_Settings::get('organization_name', get_bloginfo('name'));
        $sep = trim(DG_SEO_Settings::get('title_separator', '|'));
        $base = $keyword !== '' ? ucwords($keyword) : $post->post_title;
        $title = $base . ' ' . $sep . ' ' . $site;
        if (strlen($title) > 60) {
            $title = wp_trim_words($base, 6, '') . ' ' . $sep . ' ' . wp_trim_words($site, 2, '');
        }
        return substr($title, 0, 60);
    }

    /** @param WP_Post $post */
    private static function suggest_description($post, $keyword) {
        $raw = DG_SEO_Settings::auto_description($post);
        if ($keyword !== '' && stripos($raw, $keyword) === false) {
            $raw = ucfirst($keyword) . '. ' . $raw;
        }
        if (strlen($raw) > 155) {
            $raw = substr($raw, 0, 152) . '…';
        }
        if (strlen($raw) < 120) {
            $raw .= ' Book direct for the best rates and local hospitality.';
        }
        return substr($raw, 0, 160);
    }

    /** @param WP_Post $post */
    private static function suggest_keyword($post) {
        $title = strtolower($post->post_title);
        $slug = str_replace('-', ' ', $post->post_name);

        if ($post->post_type === 'dg_accommodation') {
            return trim($title . ' currumbin valley');
        }
        if ($post->post_type === 'property') {
            return trim($title . ' real estate');
        }
        if ($slug && $slug !== $title) {
            return $slug;
        }
        return wp_trim_words($title, 4, '');
    }
}
