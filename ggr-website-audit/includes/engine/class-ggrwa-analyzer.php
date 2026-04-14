<?php

/**
 * Backward-compatible analyzer wrapper.
 *
 * This class exists ONLY to satisfy the new GGRWA_* namespace
 * while preserving the original, battle-tested GGR_Analyzer logic.
 *
 * IMPORTANT:
 * - Do NOT add logic here
 * - Do NOT override methods
 * - Do NOT remove GGR_Analyzer
 *
 * @package GGR_Website_Audit
 * @since 2.4.2
 */

if (! defined('ABSPATH')) {
    exit;
}

class GGRWA_Analyzer
{

    /**
     * Run a free audit scan for a given URL.
     *
     * @param string $url Absolute URL to scan.
     * @return array|\WP_Error
     */
    public function run_free_scan($input)
    {
        $start_time = microtime(true);

        /**
         * ----------------------------------------
         * MODE DETECTION (ID + URL → POST ID)
         * ----------------------------------------
         */

        $post_id = 0;
        $url     = '';

        if (is_numeric($input)) {
            $post_id = intval($input);
        } else {
            $url = esc_url_raw($input);

           
           

            if (!empty($url)) {           
                $post_id = url_to_postid($url);
     
                if (!$post_id && preg_match('#/archives/(\d+)#', $url, $m)) {
                    $post_id = intval($m[1]);
                }
           
                if (!$post_id && preg_match('#[?&]p=(\d+)#', $url, $m)) {
                    $post_id = intval($m[1]);
                }
            }
        }

        

        /**
         * ----------------------------------------
         * POST MODE (BEST CASE)
         * ----------------------------------------
         */

        if ($post_id > 0) {

            $post = get_post($post_id);

            if (!$post) {
                return new WP_Error('ggrwa_invalid_post', __('Invalid post.', 'ggr-website-audit'));
            }

            /**
             * ----------------------------------------
             * STATUS VALIDATION 
             * ----------------------------------------
             */

            $status = $post->post_status;

            if (in_array($status, ['draft', 'pending', 'trash', 'auto-draft'])) {
                return [
                    'meta' => [
                        'url'    => '',
                        'status' => $status,
                    ],
                    'audit' => [
                        'working_well'      => [],
                        'needs_improvement' => [],
                        'critical_issues'   => [
                            "Post is not published (Status: {$status})"
                        ],
                    ],
                    'score' => 0,
                ];
            }

            if ($status === 'private') {
                return [
                    'audit' => [
                        'critical_issues' => [
                            'Post is private and not publicly accessible'
                        ],
                    ],
                    'score' => 0,
                ];
            }

            if (!empty($post->post_password)) {
                return [
                    'audit' => [
                        'critical_issues' => [
                            'Post is password protected'
                        ],
                    ],
                    'score' => 0,
                ];
            }
       
            $html       = apply_filters('the_content', $post->post_content);
            $plain_text = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($html)));
            $url        = get_permalink($post_id);
        } else {

            /**
             * ----------------------------------------
             * URL MODE (Fallback)
             * ----------------------------------------
             */

            if (empty($url)) {
                return new WP_Error('ggrwa_invalid_url', __('Invalid URL.', 'ggr-website-audit'));
            }

            $normalized_url = ggrwa_normalize_url($url);
            $html           = $this->fetch_html($normalized_url);

            if (empty($html)) {
                return new WP_Error('ggrwa_fetch_failed', __('Unable to retrieve page content.', 'ggr-website-audit'));
            }

            $plain_text = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($html)));
            $url        = $normalized_url;
        }

        /**
         * ----------------------------------------
         * CONTENT VALIDATION
         * ----------------------------------------
         */

        $word_count  = str_word_count($plain_text);
        $total_chars = strlen($plain_text);
        $alpha_chars = preg_match_all('/[a-zA-Z]/', $plain_text);
        $alpha_ratio = $total_chars > 0 ? ($alpha_chars / $total_chars) : 0;

        if ($word_count < 10) {
            return [
                'meta' => [
                    'url'        => esc_url_raw($url),
                    'scanned_at' => current_time('mysql'),
                ],
                'audit' => [
                    'working_well'      => [],
                    'needs_improvement' => [],
                    'critical_issues'   => [
                        __('No meaningful content found. Add real content before SEO audit.', 'ggr-website-audit'),
                    ],
                ],
                'score' => 0,
            ];
        }

        $words = preg_split('/\s+/', strtolower($plain_text));
        $unique_ratio = count(array_unique($words)) / max(1, count($words));

        $non_letter_chars = preg_match_all('/[^a-zA-Z\s]/', $plain_text);
        $noise_ratio = $total_chars > 0 ? ($non_letter_chars / $total_chars) : 0;

        $has_sentence_structure = preg_match('/[.!?]/', $plain_text);
        $has_repeated_pattern   = preg_match('/(.)\1{4,}/', $plain_text);

        if (
            $unique_ratio < 0.25 ||
            $noise_ratio > 0.3 ||
            !$has_sentence_structure ||
            $has_repeated_pattern
        ) {
            return [
                'meta' => [
                    'url'        => esc_url_raw($url),
                    'scanned_at' => current_time('mysql'),
                ],
                'audit' => [
                    'working_well'      => [],
                    'needs_improvement' => [],
                    'critical_issues'   => [
                        __('Content appears low quality or auto-generated. Improve readability.', 'ggr-website-audit'),
                    ],
                ],
                'score' => 0,
            ];
        }

        /**
         * ----------------------------------------
         * CORE SEO CHECKS
         * ----------------------------------------
         */

        $seo   = $this->get_seo_checks($html, $url, $plain_text);
        $links = $this->get_link_checks($html);
        $media = $this->get_media_checks($html);
        $tech  = $this->get_technical_checks($html);

        $analysis = $this->build_audit_results($seo, $links, $media, $tech);
        $score    = $this->calculate_audit_score($analysis);

        /**
         * ----------------------------------------
         * SAVE SCORE
         * ----------------------------------------
         */

        if (!empty($post_id)) {
            update_post_meta($post_id, '_ggr_seo_score', $score);
        }

        /**
         * ----------------------------------------
         * FINAL RESPONSE
         * ----------------------------------------
         */

        return [
            'meta' => [
                'url'         => esc_url_raw($url),
                'post_id'     => $post_id,
                'post_type'   => $post_id ? get_post_type($post_id) : 'external',
                'status'      => $post_id ? get_post_status($post_id) : 'external',
                'scanned_at'  => current_time('mysql'),
                'duration_ms' => (int) round((microtime(true) - $start_time) * 1000),
            ],
            'audit' => $analysis,
            'score' => $score,
        ];
    }

    /* ---------------------------------------------------------------------
     * FETCH
     * --------------------------------------------------------------------- */

    protected function fetch_html($url)
    {

        $response = wp_remote_get(
            $url,
            [
                'timeout' => 15,
                'headers' => [
                    'User-Agent' => 'GGR Website Audit',
                ],
            ]
        );

        if (is_wp_error($response)) {
            return '';
        }

        return (string) wp_remote_retrieve_body($response);
    }


    /**
     * Get only post/page content from a URL (own site).
     *
     * @since 2.3.0
     *
     * @param string $url
     * @return string
     */
    protected function get_post_content_by_url($url)
    {

        // Resolve post ID from URL
        $post_id = url_to_postid($url);

        // Homepage fallback
        if (! $post_id && untrailingslashit($url) === untrailingslashit(home_url())) {
            $post_id = (int) get_option('page_on_front');
        }

        if (! $post_id) {
            return '';
        }

        $post = get_post($post_id);

        if (! $post) {
            return '';
        }

        // Raw editor content
        $content = $post->post_content;

        // Apply WP content pipeline (blocks, shortcodes, embeds)
        $content = apply_filters('the_content', $content);

        // Strip HTML tags
        $text = wp_strip_all_tags($content);

        // Normalize whitespace
        $text = preg_replace('/\s+/u', ' ', trim($text));

        return $text;
    }


    /* ---------------------------------------------------------------------
     * SEO CHECKS
     * --------------------------------------------------------------------- */

    protected function get_seo_checks($html, $url = '', $plain_text = '')
    {

        /**
         * ------------------------------------------------------------------
         * 1. Extract main content (best-effort, theme-agnostic)
         * ------------------------------------------------------------------
         */
        $content_html = '';

        if (preg_match('/<article[^>]*>(.*?)<\/article>/is', $html, $m)) {
            $content_html = $m[1];
        } elseif (preg_match('/<main[^>]*>(.*?)<\/main>/is', $html, $m)) {
            $content_html = $m[1];
        } else {
            $content_html = $html;
        }

        /**
         * ------------------------------------------------------------------
         * 2. Content text & word count (CONTENT ONLY)
         * ------------------------------------------------------------------
         */
        $content_text = trim(
            preg_replace(
                '/\s+/',
                ' ',
                wp_strip_all_tags($plain_text ?: $content_html)
            )
        );

        $words      = str_word_count(strtolower($content_text), 1);
        $word_count = count($words);

        /**
         * ------------------------------------------------------------------
         * 3. Page title & meta description
         * ------------------------------------------------------------------
         */
        preg_match('/<title>(.*?)<\/title>/is', $html, $m);
        $title = isset($m[1]) ? wp_strip_all_tags($m[1]) : '';

        preg_match(
            '/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/i',
            $html,
            $md
        );
        $description = $md[1] ?? '';

        $title_length       = strlen($title);
        $description_length = strlen($description);

        /**
         * ------------------------------------------------------------------
         * 4. URL context (URL-based audit = GGR advantage)
         * ------------------------------------------------------------------
         */
        $url_path   = '';
        $url_length = 0;

        if ($url) {
            $parsed   = wp_parse_url($url);
            $url_path = isset($parsed['path']) ? trim($parsed['path'], '/') : '';
            $url_length = strlen($url_path);
        }

        /**
         * ------------------------------------------------------------------
         * 5. Headings analysis (DOM-safe)
         * ------------------------------------------------------------------
         */
        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);

        $all_h1_count = $dom->getElementsByTagName('h1')->length;

        // WordPress assumption:
        // First H1 = title, rest = content H1s
        $page_h1_count    = $all_h1_count > 0 ? 1 : 0;
        $content_h1_count = max(0, $all_h1_count - 1);

        preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $content_html, $h2s);
        preg_match_all('/<h3[^>]*>(.*?)<\/h3>/is', $content_html, $h3s);
        preg_match_all('/<h4[^>]*>(.*?)<\/h4>/is', $content_html, $h4s);

        /**
         * ------------------------------------------------------------------
         * 6. Primary keyword (AUTO-DERIVED, NO USER INPUT)
         * ------------------------------------------------------------------
         */
        $primary_keyword = '';

        if (! empty($title)) {
            // First meaningful chunk of title
            $primary_keyword = strtolower(trim(strtok($title, '|-')));
        }

        /**
         * ------------------------------------------------------------------
         * 7. Keyword intelligence (DATA ONLY)
         * ------------------------------------------------------------------
         */
        $keyword_hits = 0;

        if ($primary_keyword && $word_count > 0) {
            foreach ($words as $word) {
                if ($word === $primary_keyword) {
                    $keyword_hits++;
                }
            }
        }

        $keyword_density = $word_count > 0
            ? round(($keyword_hits / $word_count) * 100, 2)
            : 0;

        // Keyword in title
        $keyword_in_title = false;
        $keyword_at_start = false;

        if ($primary_keyword && $title) {
            $pos = stripos($title, $primary_keyword);
            if ($pos !== false) {
                $keyword_in_title = true;
                $keyword_at_start = ($pos === 0);
            }
        }

        // Keyword in URL
        $keyword_in_url = false;

        if ($primary_keyword && $url_path) {
            $keyword_in_url = stripos($url_path, $primary_keyword) !== false;
        }

        // Keyword in first 10% of content
        $keyword_in_first_10 = false;

        if ($primary_keyword && $word_count > 0) {
            $first_chunk_words = array_slice(
                $words,
                0,
                max(1, floor($word_count * 0.1))
            );
            $keyword_in_first_10 = in_array($primary_keyword, $first_chunk_words, true);
        }

        /**
         * ------------------------------------------------------------------
         * 8. Return structured SEO data (NO JUDGEMENT HERE)
         * ------------------------------------------------------------------
         */
        return [
            // Meta
            'title'                => $title,
            'title_length'         => $title_length,
            'description'          => $description,
            'description_length'   => $description_length,

            // URL
            'url_path'             => $url_path,
            'url_length'           => $url_length,

            // Headings
            'h1' => [
                'page'    => $page_h1_count,
                'content' => $content_h1_count,
            ],
            'h2' => count($h2s[0]),
            'h3' => count($h3s[0]),
            'h4' => count($h4s[0]),

            // Content
            'word_count'           => $word_count,

            // Keyword intelligence
            'primary_keyword'      => $primary_keyword,
            'keyword_density'      => $keyword_density,
            'keyword_in_title'     => $keyword_in_title,
            'keyword_at_start'     => $keyword_at_start,
            'keyword_in_url'       => $keyword_in_url,
            'keyword_in_first_10'  => $keyword_in_first_10,
        ];
    }



    /* ---------------------------------------------------------------------
     * LINK CHECKS
     * --------------------------------------------------------------------- */

    protected function get_link_checks($html)
    {
        /**
         * ------------------------------------------------------------
         * 1. Extract main content (same strategy as SEO checks)
         * ------------------------------------------------------------
         */
        $content_html = '';

        if (preg_match('/<article[^>]*>(.*?)<\/article>/is', $html, $m)) {
            $content_html = $m[1];
        } elseif (preg_match('/<main[^>]*>(.*?)<\/main>/is', $html, $m)) {
            $content_html = $m[1];
        } else {
            $content_html = $html;
        }

        /**
         * ------------------------------------------------------------
         * 2. Build map of EXISTING internal URLs (published only)
         * ------------------------------------------------------------
         */
        $published_ids = get_posts([
            'post_type'      => ['post', 'page'],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        $internal_url_map = [];

        foreach ($published_ids as $pid) {
            $permalink = untrailingslashit(get_permalink($pid));
            if ($permalink) {
                $internal_url_map[$permalink] = true;
            }
        }

        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);

        /**
         * ------------------------------------------------------------
         * 3. Extract links from CONTENT only
         * ------------------------------------------------------------
         */
        preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\']/i', $content_html, $matches);

        $internal_links        = 0;
        $broken_internal_links = 0;
        $external_links        = 0;

        foreach ($matches[1] as $link) {

            // Skip empty, anchors, JS, mail, tel
            if (
                empty($link) ||
                strpos($link, '#') === 0 ||
                stripos($link, 'javascript:') === 0 ||
                stripos($link, 'mailto:') === 0 ||
                stripos($link, 'tel:') === 0
            ) {
                continue;
            }

            // Normalize link
            $parsed = wp_parse_url($link);

            /**
             * --------------------------------------------------------
             * Relative URL (possible internal)
             * --------------------------------------------------------
             */
            if (empty($parsed['host']) && isset($parsed['path'])) {

                $normalized = untrailingslashit(home_url($parsed['path']));

                if (isset($internal_url_map[$normalized])) {
                    $internal_links++;
                } else {
                    $broken_internal_links++;
                }

                continue;
            }

            /**
             * --------------------------------------------------------
             * Absolute URL
             * --------------------------------------------------------
             */
            if (! empty($parsed['host'])) {

                // Internal absolute URL
                if (strcasecmp($parsed['host'], $site_host) === 0) {

                    $normalized = untrailingslashit($parsed['scheme'] . '://' . $parsed['host'] . ($parsed['path'] ?? ''));

                    if (isset($internal_url_map[$normalized])) {
                        $internal_links++;
                    } else {
                        $broken_internal_links++;
                    }
                } else {
                    // External / outbound link
                    $external_links++;
                }
            }
        }

        return [
            'internal_links'        => $internal_links,
            'broken_internal_links' => $broken_internal_links,
            'external_links'        => $external_links,
        ];
    }



    /* ---------------------------------------------------------------------
     * MEDIA CHECKS
     * --------------------------------------------------------------------- */

    protected function get_media_checks($html, $post_id = 0)
    {
        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);

        $images = $dom->getElementsByTagName('img');

        $total    = 0;
        $with_alt = 0;

        foreach ($images as $img) {
            $total++;

            if (
                $img->hasAttribute('alt') &&
                trim($img->getAttribute('alt')) !== ''
            ) {
                $with_alt++;
            }
        }

        $has_featured = $post_id ? has_post_thumbnail($post_id) : false;

        return [
            'images'          => $total,
            'with_alt'        => $with_alt,
            'featured_image'  => $has_featured,
        ];
    }


    /* ---------------------------------------------------------------------
     * TECHNICAL CHECKS
     * --------------------------------------------------------------------- */

    protected function get_technical_checks($html)
    {

        // Canonical
        $has_canonical = (bool) preg_match(
            '/<link[^>]+rel=["\']canonical["\'][^>]*>/i',
            $html
        );

        // Noindex
        $has_noindex = (bool) preg_match(
            '/<meta[^>]+content=["\'][^"\']*noindex[^"\']*["\']/i',
            $html
        );

        // Schema (JSON-LD only, safe check)
        $schema_blocks = [];

        if (preg_match_all(
            '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is',
            $html,
            $matches
        )) {
            foreach ($matches[1] as $json) {
                $decoded = json_decode(trim($json), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $schema_blocks[] = $decoded;
                }
            }
        }

        return [
            'canonical'      => $has_canonical,
            'noindex'        => $has_noindex,
            'schema_present' => ! empty($schema_blocks),
            'schema_count'   => count($schema_blocks),
        ];
    }

    /* ---------------------------------------------------------------------
     * AUDIT DECISION ENGINE (IMPORTANT)
     * --------------------------------------------------------------------- */

    protected function build_audit_results($seo, $links, $media, $tech)
    {

        $sections = [
            'search_basics' => [
                'working_well'      => [],
                'needs_improvement' => [],
                'critical_issues'   => [],
            ],
            'content_structure' => [
                'working_well'      => [],
                'needs_improvement' => [],
                'critical_issues'   => [],
            ],
            'title_serp' => [
                'working_well'      => [],
                'needs_improvement' => [],
                'critical_issues'   => [],
            ],
            'readability_ux' => [
                'working_well'      => [],
                'needs_improvement' => [],
                'critical_issues'   => [],
            ],
        ];

        /**
         * SEARCH ENGINE BASICS
         */

        if (! empty($seo['description']) && strlen($seo['description']) >= 70) {

            $sections['search_basics']['working_well'][] =
                __('Meta description is present and readable.', 'ggr-website-audit');
        } else {

            $sections['search_basics']['needs_improvement'][] =
                __('<strong>Meta description</strong> is missing or could be improved.', 'ggr-website-audit');
        }

        if (! empty($tech['schema_present'])) {

            $sections['search_basics']['working_well'][] = sprintf(
                /* translators: %d: number of schema blocks detected */
                __(
                    'Structured data is present on the page (%d schema block(s)). This helps search engines better understand your content.',
                    'ggr-website-audit'
                ),
                (int) $tech['schema_count']
            );
        } else {

            $sections['search_basics']['needs_improvement'][] =
                __('No <strong>structured data</strong> detected. Adding <strong>schema</strong> can improve how your page appears in search results.', 'ggr-website-audit');
        }

        if (! empty($tech['noindex'])) {

            $sections['search_basics']['critical_issues'][] =
                __('This page is marked as <strong>noindex</strong> and may not appear in search results.', 'ggr-website-audit');
        }

        if (! empty($tech['canonical'])) {

            $sections['search_basics']['working_well'][] =
                __('Canonical URL is specified, helping avoid duplicate content issues.', 'ggr-website-audit');
        } else {

            $sections['search_basics']['needs_improvement'][] =
                __('No <strong>canonical URL</strong> detected. Adding one can help prevent duplicate content problems.', 'ggr-website-audit');
        }

        /**
         * CONTENT STRUCTURE
         */

        if (empty($media['featured_image'])) {

            $sections['content_structure']['needs_improvement'][] =
                __('No <strong>featured image</strong> is set for this page.', 'ggr-website-audit');
        } else {

            $sections['content_structure']['working_well'][] =
                __('Featured image is set for this page.', 'ggr-website-audit');
        }

        if ($seo['word_count'] >= 800) {

            $sections['content_structure']['working_well'][] = sprintf(
                /* translators: %d: word count */
                __('Content length is strong (%d words). This page meets the recommended minimum for SEO.', 'ggr-website-audit'),
                (int) $seo['word_count']
            );
        } elseif ($seo['word_count'] >= 400) {

            $sections['content_structure']['needs_improvement'][] = sprintf(
                /* translators: %d: word count */
                __('Content length is <strong>%d words</strong>. For competitive pages aim for at least <strong>800–1,200 words</strong>.', 'ggr-website-audit'),
                (int) $seo['word_count']
            );
        } elseif ($seo['word_count'] >= 300) {

            $sections['content_structure']['needs_improvement'][] = sprintf(
                /* translators: %d: word count */
                __('Content length is <strong>%d words</strong>, which is considered thin for indexable pages.', 'ggr-website-audit'),
                (int) $seo['word_count']
            );
        } else {

            $sections['content_structure']['critical_issues'][] = sprintf(
                /* translators: %d: word count */
                __('Content is very short <strong>(%d words)</strong>. Expand this page with detailed sections or FAQs.', 'ggr-website-audit'),
                (int) $seo['word_count']
            );
        }

        if ($links['internal_links'] >= 3) {

            $sections['content_structure']['working_well'][] = sprintf(
                /* translators: %d: number of internal links */
                __('%d internal links found. This helps search engines understand page relationships.', 'ggr-website-audit'),
                (int) $links['internal_links']
            );
        } elseif ($links['internal_links'] >= 1) {

            $sections['content_structure']['needs_improvement'][] = sprintf(
                /* translators: %d: number of internal links */
                __('Only <strong>%d internal link</strong> found. Consider adding more contextual links.', 'ggr-website-audit'),
                (int) $links['internal_links']
            );
        } else {

            $sections['content_structure']['needs_improvement'][] =
                __('No <strong>internal links</strong> found. Add links to related pages.', 'ggr-website-audit');
        }

        /**
         * TITLE & SERP
         */

        if ($seo['title_length'] >= 50 && $seo['title_length'] <= 60) {

            $sections['title_serp']['working_well'][] = sprintf(
                /* translators: %d: title length */
                __('SEO title length is ideal (%d characters).', 'ggr-website-audit'),
                (int) $seo['title_length']
            );
        } elseif ($seo['title_length'] > 60) {

            $sections['title_serp']['needs_improvement'][] = sprintf(
                /* translators: %d: title length */
                __('SEO title is too long <strong>(%d characters)</strong>. It may get truncated.', 'ggr-website-audit'),
                (int) $seo['title_length']
            );
        } else {

            $sections['title_serp']['needs_improvement'][] = sprintf(
                /* translators: %d: title length */
                __('SEO title is short <strong>(%d characters)</strong>. Consider expanding it.', 'ggr-website-audit'),
                (int) $seo['title_length']
            );
        }

        /**
         * READABILITY & UX
         */

        if ($media['images'] === 0) {

            $sections['readability_ux']['needs_improvement'][] =
                __('<strong>No images</strong> found inside the content.', 'ggr-website-audit');
        } elseif ($media['with_alt'] === $media['images']) {

            $sections['readability_ux']['working_well'][] =
                __('All content images include alternative text.', 'ggr-website-audit');
        } else {

            $sections['readability_ux']['needs_improvement'][] =
                __('Some <strong>content images</strong> are missing <strong>alt attributes</strong>.', 'ggr-website-audit');
        }

        return [
            'sections' => $sections,
        ];
    }


    /* ---------------------------------------------------------------------
     * HELPERS
     * --------------------------------------------------------------------- */

    protected function page_exists($url)
    {

        $response = wp_remote_head($url, ['timeout' => 10]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        return $code >= 200 && $code < 300;
    }

    protected function is_own_domain($url)
    {

        $host = wp_parse_url($url, PHP_URL_HOST);
        $site = wp_parse_url(home_url(), PHP_URL_HOST);

        return strtolower($host) === strtolower($site);
    }

    /**
     * Calculate audit score based on audit buckets.
     *
     * Incremental, explainable scoring aligned with
     * Google Search signals and leading SEO tools.
     *
     * @since 2.6.0
     *
     * @param array    $audit Audit buckets.
     * @param int|null $previous_score Optional previous score for trend.
     * @return array
     */
    protected function calculate_audit_score($audit, $previous_score = null)
    {
        if (empty($audit['sections'])) {
            return [
                'total_score' => 0,
                'confidence'  => 'low',
                'priority'    => null,
                'trend'       => null,
                'sections'    => [],
            ];
        }

        $sections = $audit['sections'];

        /* ------------------------------------------------------------
        * SECTION MAX WEIGHTS (RELATIVE SEO IMPORTANCE)
        * ------------------------------------------------------------ */
        $section_max = [
            'search_basics'     => 30,
            'content_structure' => 40,
            'title_serp'        => 20,
            'readability_ux'    => 10,
        ];

        /* ------------------------------------------------------------
        * CHECK-LEVEL WEIGHTS (NON-RANDOM, CONSENSUS BASED)
        * ------------------------------------------------------------ */
        $check_weights = [
            'critical' => [
                'search_basics'     => 25,
                'content_structure' => 20,
                'title_serp'        => 15,
                'readability_ux'    => 10,
            ],
            'needs_improvement' => [
                'search_basics'     => 3,
                'content_structure' => 3,
                'title_serp'        => 2,
                'readability_ux'    => 1,
            ],
            'working_well' => [
                'search_basics'     => 4,
                'content_structure' => 4,
                'title_serp'        => 3,
                'readability_ux'    => 2,
            ],
        ];

        $section_scores = [];
        $total_score    = 0;
        $caps           = [];

        /* ------------------------------------------------------------
        * HARD CAPS (GOOGLE REALITY CHECKS)
        * ------------------------------------------------------------ */

        // Noindex present → strong cap
        if (!empty($sections['search_basics']['critical_issues'])) {
            foreach ($sections['search_basics']['critical_issues'] as $issue) {
                if (stripos($issue, 'noindex') !== false) {
                    $caps[] = 20;
                    break;
                }
            }
        }

        // Very short / thin content → cap
        if (!empty($sections['content_structure']['critical_issues'])) {
            foreach ($sections['content_structure']['critical_issues'] as $issue) {
                if (stripos($issue, 'short') !== false) {
                    $caps[] = 40;
                    break;
                }
            }
        }

        // Any critical in search basics → moderate cap
        if (!empty($sections['search_basics']['critical_issues'])) {
            $caps[] = 50;
        }

        /* ------------------------------------------------------------
        * SECTION SCORING (INCREMENTAL, CHECK-LEVEL)
        * ------------------------------------------------------------ */
        foreach ($section_max as $key => $max_weight) {

            if (empty($sections[$key])) {
                continue;
            }

            $working  = count($sections[$key]['working_well'] ?? []);
            $needs    = count($sections[$key]['needs_improvement'] ?? []);
            $critical = count($sections[$key]['critical_issues'] ?? []);

            $score = 0;

            // Critical penalties (blocking signals)
            if ($critical > 0) {
                $score -= ($critical * $check_weights['critical'][$key]);
            }

            // Needs improvement rewards (small but visible)
            if ($needs > 0) {
                $score += ($needs * $check_weights['needs_improvement'][$key]);
            }

            // Working well rewards (higher density)
            if ($working > 0) {
                $score += ($working * $check_weights['working_well'][$key]);
            }

            // Clamp section score
            $score = max(0, min($score, $max_weight));

            $percent = $max_weight > 0
                ? round(($score / $max_weight) * 100)
                : 0;

            if ($percent >= 80) {
                $status = 'strong';
            } elseif ($percent >= 40) {
                $status = 'needs_improvement';
            } else {
                $status = 'critical';
            }

            $section_scores[$key] = [
                'score'   => (int) round($score),
                'max'     => $max_weight,
                'percent' => $percent,
                'status'  => $status,
            ];

            $total_score += $score;
        }

        /* ------------------------------------------------------------
        * CATEGORY SHIFT BONUS (QUALITY TIER JUMP)
        * ------------------------------------------------------------ */
        $has_critical = false;

        foreach ($sections as $section) {
            if (!empty($section['critical_issues'])) {
                $has_critical = true;
                break;
            }
        }

        // Critical → Needs Improvement
        if (!$has_critical) {
            $total_score += 3;
        }

        // Needs Improvement → Working Well
        if (!$has_critical && $total_score >= 70) {
            $total_score += 5;
        }

        /* ------------------------------------------------------------
        * APPLY HARD CAPS
        * ------------------------------------------------------------ */
        if (!empty($caps)) {
            $total_score = min($total_score, min($caps));
        }

        $total_score = max(0, min(100, (int) round($total_score)));

        /* ------------------------------------------------------------
        * CONFIDENCE LABEL
        * ------------------------------------------------------------ */
        if ($total_score >= 75) {
            $confidence = 'high';
        } elseif ($total_score >= 45) {
            $confidence = 'medium';
        } else {
            $confidence = 'low';
        }

        /* ------------------------------------------------------------
        * AUTO PRIORITY (WHAT TO FIX FIRST)
        * ------------------------------------------------------------ */
        $priority_section = null;
        $lowest_percent   = 101;

        foreach ($section_scores as $key => $data) {
            if ($data['percent'] < $lowest_percent) {
                $lowest_percent   = $data['percent'];
                $priority_section = $key;
            }
        }

        /* ------------------------------------------------------------
        * SCORE TREND (OPTIONAL)
        * ------------------------------------------------------------ */
        $trend = null;

        if (is_int($previous_score)) {
            $diff = $total_score - $previous_score;

            if ($diff > 0) {
                $trend = ['direction' => 'up', 'change' => $diff];
            } elseif ($diff < 0) {
                $trend = ['direction' => 'down', 'change' => abs($diff)];
            } else {
                $trend = ['direction' => 'flat', 'change' => 0];
            }
        }

        /* ------------------------------------------------------------
        * FINAL RESPONSE
        * ------------------------------------------------------------ */
        return [
            'total_score' => $total_score,
            'confidence'  => $confidence,
            'priority'    => $priority_section,
            'trend'       => $trend,
            'sections'    => $section_scores,
        ];
    }


    protected function get_fix_impact_map()
    {
        return [
            'search_basics' => [
                'noindex' => [
                    'impact'     => 'very_high',
                    'score_range' => [20, 30],
                    'confidence' => 'high',
                ],
                'missing_canonical' => [
                    'impact'     => 'medium',
                    'score_range' => [5, 8],
                    'confidence' => 'medium',
                ],
                'missing_schema' => [
                    'impact'     => 'low',
                    'score_range' => [3, 6],
                    'confidence' => 'medium',
                ],
            ],
            'content_structure' => [
                'thin_content' => [
                    'impact'     => 'very_high',
                    'score_range' => [15, 25],
                    'confidence' => 'high',
                ],
                'no_internal_links' => [
                    'impact'     => 'high',
                    'score_range' => [8, 12],
                    'confidence' => 'high',
                ],
                'missing_headings' => [
                    'impact'     => 'medium',
                    'score_range' => [5, 8],
                    'confidence' => 'medium',
                ],
            ],
            'title_serp' => [
                'poor_title_length' => [
                    'impact'     => 'medium',
                    'score_range' => [5, 10],
                    'confidence' => 'medium',
                ],
                'keyword_missing_title' => [
                    'impact'     => 'high',
                    'score_range' => [8, 12],
                    'confidence' => 'high',
                ],
            ],
            'readability_ux' => [
                'missing_images' => [
                    'impact'     => 'low',
                    'score_range' => [2, 4],
                    'confidence' => 'low',
                ],
            ],
        ];
    }

    /**
     * Estimates the potential SEO score impact for fixing
     * detected critical issues.
     *
     * The estimator provides ranges and confidence levels only.
     * It does not alter the actual audit score.
     *
     * @since 2.5.0
     *
     * @param array $audit Final audit result array.
     * @return array Estimated fix impacts.
     */
    protected function estimate_fix_impact($audit)
    {
        if (empty($audit['sections'])) {
            return [];
        }

        $impact_map = $this->get_fix_impact_map();
        $estimates  = [];

        foreach ($audit['sections'] as $section_key => $section) {

            if (empty($impact_map[$section_key])) {
                continue;
            }

            if (empty($section['critical_issues'])) {
                continue;
            }

            foreach ($section['critical_issues'] as $issue_text) {

                foreach ($impact_map[$section_key] as $issue_key => $impact) {

                    // Simple keyword-based mapping.
                    if (stripos($issue_text, str_replace('_', ' ', $issue_key)) !== false) {

                        $estimates[] = [
                            'section'     => $section_key,
                            'issue'       => $issue_text,
                            'impact'      => $impact['impact'],
                            'score_range' => $impact['score_range'],
                            'confidence'  => $impact['confidence']
                        ];
                    }
                }
            }
        }

        return $estimates;
    }


    /**
     *
     * Free users receive qualitative impact only.
     *
     * @since 2.5.0
     *
     * @param array $fix_impact Raw fix impact data.
     * @return array
     */
    protected function format_fix_impact_output($fix_impact)
    {
        return $fix_impact;
    }
}
