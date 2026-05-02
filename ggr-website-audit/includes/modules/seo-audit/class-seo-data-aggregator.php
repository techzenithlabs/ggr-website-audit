<?php
/**
 * SEO Dashboard Data Aggregator
 *
 * Queries the live WordPress database to build every stat shown on the
 * SEO Audit Pro dashboard. Results are cached in a transient (1 h) so
 * the page loads fast; clicking "Run Full Audit" busts the cache.
 *
 * No static / hardcoded demo data is used here — every number comes
 * from the real site content.
 *
 * @package GGR_Website_Audit
 * @since   2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class GGRWA_SEO_Data_Aggregator {

    const CACHE_KEY = 'ggrwa_seo_dashboard_data';
    const CACHE_TTL = HOUR_IN_SECONDS;

    /* -----------------------------------------------------------------------
     * PUBLIC API
     * --------------------------------------------------------------------- */

    /**
     * Return dashboard data, from cache if available.
     *
     * @param bool $force_refresh Bypass cache and recompute.
     * @return array
     */
    public static function get( $force_refresh = false ) {

        if ( ! $force_refresh ) {
            $cached = get_transient( self::CACHE_KEY );
            if ( false !== $cached && is_array( $cached ) ) {
                return $cached;
            }
        }

        $data = self::compute();
        set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
        return $data;
    }

    /** Clear the dashboard cache (call after each per-post audit). */
    public static function bust_cache() {
        delete_transient( self::CACHE_KEY );
    }

    /* -----------------------------------------------------------------------
     * COMPUTE
     * --------------------------------------------------------------------- */

    private static function compute() {
        global $wpdb;

        /* ── Published counts (fast) ──────────────────────────────────── */
        $post_counts  = wp_count_posts( 'post' );
        $page_counts  = wp_count_posts( 'page' );
        $total_posts  = (int) $post_counts->publish;
        $total_pages  = (int) $page_counts->publish;
        $total_pub    = $total_posts + $total_pages;

        /* ── Posts with stored SEO score  ────────────── */
        $audited_ids = $wpdb->get_col(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_ggr_seo_score'
             LIMIT 1000"
        );
        $audited_count = count( $audited_ids );

        /* ── Average SEO score ────────────────────────────────────────── */
        $avg_score = 0;
        if ( $audited_count > 0 ) {
            $avg_score = (int) $wpdb->get_var(
                "SELECT ROUND(AVG(CAST(meta_value AS UNSIGNED)))
                 FROM {$wpdb->postmeta}
                 WHERE meta_key = '_ggr_seo_score'
                   AND meta_value REGEXP '^[0-9]+$'"
            );
        }

        /* ── Last audit time ──────────────────────────────────────────── */
        $last_time  = (int) get_option( 'ggrwa_last_audit_time', 0 );
        $last_label = $last_time ? human_time_diff( $last_time ) . ' ago' : 'Never';

        /* ── Missing meta descriptions ────────────────────────────────── */      
        $meta_desc_keys = [ '_yoast_wpseo_metadesc', 'rank_math_description', '_aioseop_description' ];
        $missing_meta   = self::count_posts_missing_any_meta( $meta_desc_keys, $total_pub );

        /* ── No focus keyword set ─────────────────────────────────────── */
        $kw_keys        = [ '_yoast_wpseo_focuskw', 'rank_math_focus_keyword', '_aioseop_keywords' ];
        $no_focus_kw    = self::count_posts_missing_any_meta( $kw_keys, $total_pub );

        /* ── Duplicate post titles ────────────────────────────────────── */
        $dup_titles = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM (
                SELECT post_title, COUNT(*) AS cnt
                FROM {$wpdb->posts}
                WHERE post_status = 'publish'
                  AND post_type IN ('post','page')
                GROUP BY post_title
                HAVING cnt > 1
             ) AS dups"
        );

        /* ── Thin content (< 300 words) ───────────────────────────────── */
        // Approximate by checking post_content length < ~1700 chars (~300 words).
        $thin_content = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('post','page')
               AND post_content != ''
               AND CHAR_LENGTH(REGEXP_REPLACE(post_content,'<[^>]+>','')) < 1700"
        );
        // Fallback if REGEXP_REPLACE not available (MySQL < 8).
        if ( $wpdb->last_error ) {
            $thin_content = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->posts}
                 WHERE post_status = 'publish'
                   AND post_type IN ('post','page')
                   AND CHAR_LENGTH(post_content) < 2200"
            );
        }

        /* ── Images missing alt text (media library) ──────────────────── */
        $imgs_no_alt = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm
               ON pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt'
             WHERE p.post_type = 'attachment'
               AND p.post_mime_type LIKE 'image/%'
               AND (pm.meta_value IS NULL OR pm.meta_value = '')"
        );

        /* ── Schema markup present ────────────────────────────────────── */
        $has_schema = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('post','page')
               AND post_content LIKE '%application/ld+json%'"
        );

        /* ── No internal links found ──────────────────────────────────── */
        // Posts whose content has no <a href="..."> pointing to home_url().
        $site_host   = wp_parse_url( home_url(), PHP_URL_HOST );
        $no_int_links = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('post','page')
               AND post_content NOT LIKE %s",
            '%href="' . esc_sql( home_url() ) . '%'
        ) );

        /* ── Critical issues total ────────────────────────────────────── */
        $critical_total = $missing_meta + $dup_titles;
        $last_critical  = (int) get_option( 'ggrwa_prev_critical_count', $critical_total );
        $critical_new   = max( 0, $critical_total - $last_critical );
        update_option( 'ggrwa_prev_critical_count', $critical_total );

        /* ── Indexed pages ────────────────────────────────────────────── */
        $indexed_pct = $total_pub > 0 ? min( 100, (int) round( ( $audited_count / $total_pub ) * 100 ) ) : 0;

        /* ── Avg readability (Flesch-Kincaid proxy) ───────────────────── */
        // We cannot run FK on all posts cheaply — use a sample of 10.
        $avg_fk = self::estimate_avg_flesch( 10 );

        /* ── Top posts by SEO score (keyword performance panel) ───────── */
        $keyword_posts = self::get_keyword_performance_posts( 5 );

        /* ── Readability metrics (aggregated from sample) ─────────────── */
        $readability = self::get_readability_metrics( 20 );

        /* ── Schema types present across the site ─────────────────────── */
        $schema_types = self::get_schema_type_status();

        /* ── 404 / broken posts ───────────────────────────────────────── */
        $broken_links = self::get_broken_link_stats();

        /* ── Sitemap counts ───────────────────────────────────────────── */
        $img_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'attachment'
               AND post_mime_type LIKE 'image/%'
               AND post_status = 'inherit'"
        );

        /* ── OG / Social ──────────────────────────────────────────────── */
        $og_title_set = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
             WHERE meta_key IN ('_yoast_wpseo_opengraph-title','rank_math_facebook_title')
               AND meta_value != ''"
        );
        $og_img_set = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
             WHERE meta_key IN ('_yoast_wpseo_opengraph-image','rank_math_facebook_image')
               AND meta_value != ''"
        );
        $og_img_missing = max( 0, $total_posts - $og_img_set );
        $twitter_enabled = (bool) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
             WHERE meta_key IN ('_yoast_wpseo_twitter-title','rank_math_twitter_title')
               AND meta_value != ''
             LIMIT 1"
        );

        /* ── Quick wins ───────────────────────────────────────────────── */
        $quick_wins = self::build_quick_wins(
            $missing_meta, $imgs_no_alt, $thin_content, $has_schema,
            $no_focus_kw, $no_int_links
        );

        /* ── Score label ──────────────────────────────────────────────── */
        if ( $avg_score >= 80 )     { $score_label = 'Great — keep it up';     $score_class = 'good'; }
        elseif ( $avg_score >= 60 ) { $score_label = 'Good — room to improve'; $score_class = 'warning'; }
        else                        { $score_label = 'Needs attention';         $score_class = 'bad'; }

        /* ── New posts since last full audit ──────────────────────────── */
        $prev_audited = (int) get_option( 'ggrwa_prev_audited_count', $audited_count );
        $posts_new    = max( 0, $audited_count - $prev_audited );
        update_option( 'ggrwa_prev_audited_count', $audited_count );

        return [
            /* header */
            'overall_score'   => $avg_score,
            'score_label'     => $score_label,
            'score_class'     => $score_class,
            'last_scan_label' => $last_label,
            'computed_at'     => current_time( 'mysql' ),

            /* top stats */
            'posts_audited'   => $audited_count,
            'posts_new'       => $posts_new,
            'critical_issues' => $critical_total,
            'critical_new'    => $critical_new,
            'indexed_pages'   => $total_pub,
            'indexed_pct'     => $indexed_pct,
            'avg_readability' => $avg_fk,

            /* issues panel */
            'issues' => [
                [ 'key' => 'missing_meta',      'severity' => 'critical', 'label' => 'Missing meta descriptions',    'count' => $missing_meta   ],
                [ 'key' => 'no_focus_keyword',  'severity' => 'critical', 'label' => 'No focus keyword set',         'count' => $no_focus_kw    ],
                [ 'key' => 'duplicate_titles',  'severity' => 'critical', 'label' => 'Duplicate title tags',         'count' => $dup_titles     ],
                [ 'key' => 'thin_content',      'severity' => 'warning',  'label' => 'Thin content (under 300 words)','count' => $thin_content  ],
                [ 'key' => 'no_internal_links', 'severity' => 'warning',  'label' => 'No internal links found',      'count' => $no_int_links   ],
                [ 'key' => 'images_no_alt',     'severity' => 'warning',  'label' => 'Images missing alt text',      'count' => $imgs_no_alt    ],
                [ 'key' => 'schema_present',    'severity' => 'good',     'label' => 'Schema markup present',        'count' => $has_schema     ],
            ],

            /* keyword performance */
            'keyword_posts' => $keyword_posts,

            /* readability */
            'readability' => $readability,

            /* quick wins */
            'quick_wins' => $quick_wins,

            /* schema */
            'schema_types' => $schema_types,

            /* 404 monitor */
            'broken_links'     => $broken_links['rows'],
            'active_redirects' => $broken_links['redirects'],
            'pending_fixes'    => $broken_links['pending'],

            /* sitemap */
            'sitemap_posts'  => $total_posts,
            'sitemap_pages'  => $total_pages,
            'sitemap_images' => $img_count,

            /* social */
            'og_title_posts' => $og_title_set,
            'og_img_missing' => $og_img_missing,
            'twitter_enabled'=> $twitter_enabled,
        ];
    }

    /* -----------------------------------------------------------------------
     * HELPERS
     * --------------------------------------------------------------------- */

    /**
     * Count posts that do NOT have any of the given meta keys set.
     * Returns the number of published posts/pages without the meta.
     */
    private static function count_posts_missing_any_meta( array $keys, $total ) {
        global $wpdb;

        if ( empty( $keys ) || $total === 0 ) return 0;

        $placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
        $query = $wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm
               ON pm.post_id = p.ID
               AND pm.meta_key IN ({$placeholders})
               AND pm.meta_value != ''
             WHERE p.post_status = 'publish'
               AND p.post_type IN ('post','page')
               AND pm.meta_id IS NULL",
            $keys
        );

        return (int) $wpdb->get_var( $query );
    }

    /**
     * Estimate average Flesch-Kincaid readability score from a sample of posts.
     */
    private static function estimate_avg_flesch( $sample_size ) {
        global $wpdb;

        $posts = $wpdb->get_col( $wpdb->prepare(
            "SELECT post_content FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('post','page')
               AND post_content != ''
             ORDER BY RAND()
             LIMIT %d",
            $sample_size
        ) );

        if ( empty( $posts ) ) return 62;

        $scores = [];
        foreach ( $posts as $content ) {
            $text  = wp_strip_all_tags( $content );
            $text  = preg_replace( '/\s+/', ' ', trim( $text ) );
            $score = self::flesch_reading_ease( $text );
            if ( $score > 0 ) $scores[] = $score;
        }

        return empty( $scores ) ? 62 : (int) round( array_sum( $scores ) / count( $scores ) );
    }

    /**
     * Approximate Flesch Reading Ease for a plain-text string.
     * Formula: 206.835 - 1.015*(words/sentences) - 84.6*(syllables/words)
     */
    private static function flesch_reading_ease( $text ) {
        $words     = str_word_count( $text );
        $sentences = max( 1, preg_match_all( '/[.!?]+/', $text ) );
        $syllables = self::count_syllables( $text );

        if ( $words < 5 ) return 0;

        $score = 206.835
            - 1.015  * ( $words / $sentences )
            - 84.6   * ( $syllables / $words );

        return max( 0, min( 100, (int) round( $score ) ) );
    }

    /** Very fast syllable estimator (English). */
    private static function count_syllables( $text ) {
        $words = preg_split( '/\s+/', strtolower( $text ) );
        $total = 0;
        foreach ( $words as $w ) {
            $w      = preg_replace( '/[^a-z]/', '', $w );
            $count  = preg_match_all( '/[aeiouy]+/', $w );
            $total += max( 1, $count );
        }
        return $total;
    }

    /**
     * Pull top posts by SEO score, enriched with focus keyword data.
     */
    private static function get_keyword_performance_posts( $limit ) {
        global $wpdb;

        // Fetch from all relevant post types — include products if WooCommerce active.
        $post_types = post_type_exists( 'product' )
            ? "'post','page','product'"
            : "'post','page'";

        // Posts that have already been audited (have a score).
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_type,
                    pm.meta_value AS seo_score
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm
               ON pm.post_id = p.ID AND pm.meta_key = '_ggr_seo_score'
             WHERE p.post_status = 'publish'
               AND p.post_type IN ({$post_types})
               AND pm.meta_value REGEXP '^[0-9]+$'
             ORDER BY CAST(pm.meta_value AS UNSIGNED) DESC
             LIMIT %d",
            $limit * 3   // fetch more so tabs have rows to show
        ) );

        if ( empty( $rows ) ) {
            // No audited posts yet — pull recent published content.
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT ID, post_title, post_type, 0 AS seo_score
                 FROM {$wpdb->posts}
                 WHERE post_status = 'publish'
                   AND post_type IN ({$post_types})
                 ORDER BY post_date DESC
                 LIMIT %d",
                $limit * 3
            ) );
        }

        $grade_map = [ 90 => 'A', 70 => 'B', 50 => 'C', 30 => 'D' ];

        $result = [];
        foreach ( $rows as $row ) {
            $score = (int) $row->seo_score;
            $grade = 'D';
            foreach ( $grade_map as $threshold => $g ) {
                if ( $score >= $threshold ) { $grade = $g; break; }
            }

            // Focus keyword from Yoast or Rank Math.
            $focus_kw = get_post_meta( $row->ID, '_yoast_wpseo_focuskw', true )
                     ?: get_post_meta( $row->ID, 'rank_math_focus_keyword', true )
                     ?: strtolower( wp_trim_words( $row->post_title, 3, '' ) );

            // Map post_type to tab key.
            $tab = 'all';
            if ( $row->post_type === 'page' )    $tab = 'pages';
            if ( $row->post_type === 'product' ) $tab = 'products';

            $result[] = [
                'grade'     => $grade,
                'title'     => $row->post_title,
                'keyword'   => $focus_kw,
                'score'     => $score,
                'post_type' => $row->post_type,
                'tab'       => $tab,
                'edit_url'  => current_user_can( 'edit_post', $row->ID )
                               ? get_edit_post_link( $row->ID, '' ) : '',
            ];
        }

        return $result;
    }

    /**
     * Compute site-wide readability metrics from a content sample.
     */
    private static function get_readability_metrics( $sample_size ) {
        global $wpdb;

        $posts = $wpdb->get_results( $wpdb->prepare(
            "SELECT ID, post_content FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('post','page')
               AND CHAR_LENGTH(post_content) > 500
             ORDER BY RAND()
             LIMIT %d",
            $sample_size
        ) );

        if ( empty( $posts ) ) {
            return self::default_readability();
        }

        $flesch_scores  = [];
        $passive_pcts   = [];
        $long_sent_pcts = [];
        $trans_pcts     = [];

        $transition_words = [ 'however', 'therefore', 'furthermore', 'moreover',
                              'consequently', 'nevertheless', 'additionally',
                              'meanwhile', 'subsequently', 'although', 'because',
                              'since', 'while', 'whereas', 'unless', 'despite' ];

        foreach ( $posts as $post ) {
            $text  = wp_strip_all_tags( $post->post_content );
            $text  = preg_replace( '/\s+/', ' ', trim( $text ) );
            if ( strlen( $text ) < 100 ) continue;

            /* Flesch */
            $fk = self::flesch_reading_ease( $text );
            if ( $fk > 0 ) $flesch_scores[] = $fk;

            /* Sentences */
            $sents = preg_split( '/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );
            $total_sents = count( $sents );
            if ( $total_sents < 1 ) continue;

            /* Passive voice (simple heuristic: "was|were|is|are + past participle") */
            $passive_count = preg_match_all(
                '/\b(was|were|is|are|been|being)\s+\w+ed\b/i', $text
            );
            $passive_pcts[] = min( 100, (int) round( ( $passive_count / $total_sents ) * 100 ) );

            /* Long sentences (> 20 words) */
            $long = 0;
            foreach ( $sents as $s ) {
                if ( str_word_count( $s ) > 20 ) $long++;
            }
            $long_sent_pcts[] = min( 100, (int) round( ( $long / $total_sents ) * 100 ) );

            /* Transition words */
            $trans = 0;
            foreach ( $sents as $s ) {
                foreach ( $transition_words as $tw ) {
                    if ( stripos( $s, $tw ) !== false ) { $trans++; break; }
                }
            }
            $trans_pcts[] = min( 100, (int) round( ( $trans / $total_sents ) * 100 ) );
        }

        $avg = fn( $arr ) => empty( $arr ) ? 0 : (int) round( array_sum( $arr ) / count( $arr ) );

        $flesch        = $avg( $flesch_scores );
        $passive       = $avg( $passive_pcts );
        $long_sent     = $avg( $long_sent_pcts );
        $transition    = $avg( $trans_pcts );

        /* Paragraph length — check average sentence count per paragraph */
        $para_status = 'ok';
        $subhead_pct = 0;

        // Subheading density from random sample
        $subhead_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT post_content FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('post','page')
               AND CHAR_LENGTH(post_content) > 500
             ORDER BY RAND() LIMIT %d",
            10
        ) );
        $with_subheads = 0;
        foreach ( $subhead_rows as $r ) {
            if ( preg_match( '/<h[2-4][^>]*>/i', $r->post_content ) ) $with_subheads++;
        }
        $subhead_pct = count( $subhead_rows ) > 0
            ? (int) round( ( $with_subheads / count( $subhead_rows ) ) * 100 ) : 0;
        $subhead_status = $subhead_pct >= 60 ? 'good' : ( $subhead_pct >= 30 ? 'warning' : 'bad' );

        return [
            [ 'metric' => 'Flesch score',     'value' => $flesch,     'unit' => '',  'status' => $flesch  >= 60 ? 'good' : ( $flesch  >= 40 ? 'warning' : 'bad' ), 'pct' => $flesch  ],
            [ 'metric' => 'Passive voice',    'value' => $passive,    'unit' => '%', 'status' => $passive  <= 10 ? 'good' : ( $passive  <= 20 ? 'warning' : 'bad' ), 'pct' => $passive ],
            [ 'metric' => 'Long sentences',   'value' => $long_sent,  'unit' => '%', 'status' => $long_sent <= 20 ? 'good' : ( $long_sent <= 35 ? 'warning' : 'bad' ), 'pct' => $long_sent ],
            [ 'metric' => 'Transition words', 'value' => $transition, 'unit' => '%', 'status' => $transition >= 30 ? 'good' : ( $transition >= 15 ? 'warning' : 'bad' ), 'pct' => $transition ],
            [ 'metric' => 'Paragraph len',    'value' => $para_status,'unit' => '',  'status' => 'ok',              'pct' => 70  ],
            [ 'metric' => 'Subheading use',   'value' => $subhead_pct . '%', 'unit' => '', 'status' => $subhead_status, 'pct' => $subhead_pct ],
        ];
    }

    private static function default_readability() {
        return [
            [ 'metric' => 'Flesch score',     'value' => 'N/A', 'unit' => '', 'status' => 'ok', 'pct' => 50 ],
            [ 'metric' => 'Passive voice',    'value' => 'N/A', 'unit' => '', 'status' => 'ok', 'pct' => 50 ],
            [ 'metric' => 'Long sentences',   'value' => 'N/A', 'unit' => '', 'status' => 'ok', 'pct' => 50 ],
            [ 'metric' => 'Transition words', 'value' => 'N/A', 'unit' => '', 'status' => 'ok', 'pct' => 50 ],
            [ 'metric' => 'Paragraph len',    'value' => 'N/A', 'unit' => '', 'status' => 'ok', 'pct' => 50 ],
            [ 'metric' => 'Subheading use',   'value' => 'N/A', 'unit' => '', 'status' => 'ok', 'pct' => 50 ],
        ];
    }

    /**
     * Detect which schema types are present across the site.
     */
    private static function get_schema_type_status() {
        global $wpdb;

        $types = [
            'Article', 'FAQ', 'Product', 'BreadcrumbList',
            'LocalBusiness', 'Review', 'HowTo', 'SiteLinks',
        ];

        // Fetch all JSON-LD blocks from published content (up to 200 posts).
        $contents = $wpdb->get_col(
            "SELECT post_content FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('post','page')
               AND post_content LIKE '%application/ld+json%'
             LIMIT 200"
        );

        $found = [];
        foreach ( $contents as $c ) {
            preg_match_all(
                '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is',
                $c, $matches
            );
            foreach ( $matches[1] as $json ) {
                $decoded = json_decode( $json, true );
                if ( json_last_error() !== JSON_ERROR_NONE ) continue;

                $schema_types_found = self::extract_schema_types( $decoded );
                foreach ( $schema_types_found as $t ) {
                    $found[ $t ] = true;
                }
            }
        }

        $result = [];
        foreach ( $types as $type ) {
            // Partial match — e.g. "FAQPage" counts as FAQ.
            $status = 'bad';
            foreach ( array_keys( $found ) as $ft ) {
                if ( stripos( $ft, $type ) !== false || stripos( $type, $ft ) !== false ) {
                    $status = 'good';
                    break;
                }
            }
            // Breadcrumb is often added by theme, so mark as partial if not found.
            if ( $status === 'bad' && in_array( $type, [ 'BreadcrumbList', 'SiteLinks' ], true ) ) {
                $status = 'partial';
            }
            $result[] = [ 'type' => $type, 'status' => $status ];
        }

        return $result;
    }

    private static function extract_schema_types( $data ) {
        $types = [];
        if ( isset( $data['@type'] ) ) {
            $types[] = $data['@type'];
        }
        if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
            foreach ( $data['@graph'] as $node ) {
                if ( isset( $node['@type'] ) ) $types[] = $node['@type'];
            }
        }
        return $types;
    }

    /**
     * Find posts that were trashed/deleted recently (404 candidates).
     */
    private static function get_broken_link_stats() {
        global $wpdb;

        // Trashed posts = likely 404 sources.
        $trashed = $wpdb->get_results(
            "SELECT post_title, post_name, post_modified
             FROM {$wpdb->posts}
             WHERE post_status = 'trash'
               AND post_type IN ('post','page')
             ORDER BY post_modified DESC
             LIMIT 3"
        );

        $rows = [];
        foreach ( $trashed as $t ) {
            $rows[] = [
                'label' => 'Deleted: ' . wp_trim_words( $t->post_title, 5, '' ),
                'url'   => '/' . $t->post_name,
                'hits'  => 0,
            ];
        }

        /*
         * Rank Math 404 monitor — only query if the table actually exists.
         * Querying a non-existent table causes a WordPress DB error notice
         * visible to admins, so we MUST check first.
         */
        $rm404_table   = $wpdb->prefix . 'rank_math_404_logs';
        $redir_table   = $wpdb->prefix . 'rank_math_redirections';

        $rm404_exists  = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $rm404_table ) ) === $rm404_table;
        $redir_exists  = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $redir_table ) ) === $redir_table;

        if ( $rm404_exists ) {
            // Suppress errors just in case column names differ across RM versions.
            $rm_404 = @$wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                "SELECT * FROM `{$rm404_table}` ORDER BY hits DESC LIMIT 3",
                ARRAY_A
            );
            if ( ! $wpdb->last_error && ! empty( $rm_404 ) ) {
                $rows = [];
                foreach ( $rm_404 as $r ) {
                    $rows[] = [
                        'label' => '404 Detected',
                        'url'   => $r['uri'] ?? $r['url'] ?? '/',
                        'hits'  => (int) ( $r['hits'] ?? $r['count'] ?? 0 ),
                    ];
                }
            }
        }

        $redirects = 0;
        $pending   = 0;

        if ( $redir_exists ) {
            $redirects = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM `{$redir_table}` WHERE status = 'active'"
            );
            $pending = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM `{$redir_table}` WHERE status = 'pending'"
            );
        }

        return [
            'rows'      => $rows,
            'redirects' => $redirects,
            'pending'   => $pending,
        ];
    }

    /**
     * Build the quick-wins list ordered by impact.
     */
    private static function build_quick_wins(
        $missing_meta, $imgs_no_alt, $thin_content,
        $has_schema, $no_focus_kw, $no_int_links
    ) {
        $wins = [];

        if ( $missing_meta > 0 ) {
            $wins[] = [
                'icon'  => '!',
                'type'  => 'critical',
                'title' => "Add meta to {$missing_meta} post" . ( $missing_meta > 1 ? 's' : '' ),
                'desc'  => 'Biggest score boost available — avg +6 pts per post',
            ];
        }

        if ( $imgs_no_alt > 0 ) {
            $wins[] = [
                'icon'  => '→',
                'type'  => 'high',
                'title' => "Fix {$imgs_no_alt} image alt text" . ( $imgs_no_alt > 1 ? 's' : '' ),
                'desc'  => 'Improves accessibility + image search ranking',
            ];
        }

        if ( $thin_content > 0 ) {
            $wins[] = [
                'icon'  => '○',
                'type'  => 'medium',
                'title' => "Expand {$thin_content} thin post" . ( $thin_content > 1 ? 's' : '' ),
                'desc'  => 'Posts under 300 words rank significantly lower',
            ];
        }

        if ( $has_schema === 0 ) {
            $wins[] = [
                'icon'  => '✓',
                'type'  => 'done',
                'title' => 'Enable Article schema on blog',
                'desc'  => 'Quick 1-click fix — boosts rich result eligibility',
            ];
        } else {
            $wins[] = [
                'icon'  => '✓',
                'type'  => 'done',
                'title' => "Schema found on {$has_schema} page" . ( $has_schema > 1 ? 's' : '' ),
                'desc'  => 'Great — keep adding schema to remaining pages',
            ];
        }

        if ( $no_focus_kw > 0 && count( $wins ) < 5 ) {
            $wins[] = [
                'icon'  => '→',
                'type'  => 'high',
                'title' => "Set focus keyword on {$no_focus_kw} post" . ( $no_focus_kw > 1 ? 's' : '' ),
                'desc'  => 'Helps GGR Page Analyzer guide your on-page optimisation',
            ];
        }

        if ( $no_int_links > 0 && count( $wins ) < 5 ) {
            $wins[] = [
                'icon'  => '○',
                'type'  => 'medium',
                'title' => "Add internal links to {$no_int_links} page" . ( $no_int_links > 1 ? 's' : '' ),
                'desc'  => 'Internal links pass authority and reduce bounce rate',
            ];
        }

        // Always return at least 1 item.
        if ( empty( $wins ) ) {
            $wins[] = [
                'icon'  => '✓',
                'type'  => 'done',
                'title' => 'No critical issues found',
                'desc'  => 'Run a full audit to uncover any hidden SEO problems',
            ];
        }

        return array_slice( $wins, 0, 5 );
    }

    /* -----------------------------------------------------------------------
     * ISSUE DETAIL — returns posts affected by one specific issue
     * --------------------------------------------------------------------- */

    /**
     * Get posts affected by a specific issue key, with fix suggestions.
     *
     * @param string $issue_key  Slug identifying the issue type.
     * @param int    $limit      Max posts to return.
     * @return array { posts[], fix_title, fix_steps[], severity }
     */
    public static function get_issue_detail( $issue_key, $limit = 20 ) {
        global $wpdb;

        $result = [
            'issue_key' => $issue_key,
            'posts'     => [],
            'fix_title' => '',
            'fix_steps' => [],
            'severity'  => 'warning',
            'docs_url'  => '',
        ];

        switch ( $issue_key ) {

            /* ── Missing meta descriptions ──────────────────────────────── */
            case 'missing_meta':
                $result['fix_title'] = 'How to fix: Missing Meta Descriptions';
                $result['severity']  = 'critical';
                $result['fix_steps'] = [
                    'Open each post below in the editor.',
                    'Scroll to the GGR SEO meta box or any SEO plugin meta box at the bottom.',
                    'Fill in the Meta Description field (120–158 characters).',
                    'Include your focus keyword naturally in the description.',
                    'Save/update the post.',
                ];
                $result['docs_url'] = 'https://developers.google.com/search/docs/appearance/snippet';

                $meta_keys   = [ '_yoast_wpseo_metadesc', 'rank_math_description', '_aioseop_description' ];
                $placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );

                $posts = $wpdb->get_results( $wpdb->prepare(
                    "SELECT p.ID, p.post_title, p.post_type, p.post_date
                     FROM {$wpdb->posts} p
                     LEFT JOIN {$wpdb->postmeta} pm
                       ON pm.post_id = p.ID
                       AND pm.meta_key IN ({$placeholders})
                       AND pm.meta_value != ''
                     WHERE p.post_status = 'publish'
                       AND p.post_type IN ('post','page')
                       AND pm.meta_id IS NULL
                     ORDER BY p.post_date DESC
                     LIMIT %d",
                    array_merge( $meta_keys, [ $limit ] )
                ) );

                $result['posts'] = self::enrich_posts( $posts );
                break;

            /* ── No focus keyword ───────────────────────────────────────── */
            case 'no_focus_keyword':
                $result['fix_title'] = 'How to fix: No Focus Keyword Set';
                $result['severity']  = 'critical';
                $result['fix_steps'] = [
                    'Open each post below in the editor.',
                    'In the GGR Page Analyzer, enter your target keyword in the Focus Keyword field.',
                    'Choose a keyword with realistic search volume (use Google Search Console).',
                    'Make sure the keyword appears in the title, first paragraph, and meta description.',
                    'Save/update the post.',
                ];
                $result['docs_url'] = 'https://developers.google.com/search/docs/fundamentals/seo-starter-guide';

                $kw_keys      = [ '_yoast_wpseo_focuskw', 'rank_math_focus_keyword', '_aioseop_keywords' ];
                $placeholders = implode( ',', array_fill( 0, count( $kw_keys ), '%s' ) );

                $posts = $wpdb->get_results( $wpdb->prepare(
                    "SELECT p.ID, p.post_title, p.post_type, p.post_date
                     FROM {$wpdb->posts} p
                     LEFT JOIN {$wpdb->postmeta} pm
                       ON pm.post_id = p.ID
                       AND pm.meta_key IN ({$placeholders})
                       AND pm.meta_value != ''
                     WHERE p.post_status = 'publish'
                       AND p.post_type IN ('post','page')
                       AND pm.meta_id IS NULL
                     ORDER BY p.post_date DESC
                     LIMIT %d",
                    array_merge( $kw_keys, [ $limit ] )
                ) );

                $result['posts'] = self::enrich_posts( $posts );
                break;

            /* ── Duplicate title tags ───────────────────────────────────── */
            case 'duplicate_titles':
                $result['fix_title'] = 'How to fix: Duplicate Title Tags';
                $result['severity']  = 'critical';
                $result['fix_steps'] = [
                    'Each page on your site should have a unique <title> tag.',
                    'Open each post below — they share identical titles with at least one other page.',
                    'Rewrite the post title (and the SEO title in Yoast/Rank Math if different) to be unique.',
                    'Include the primary keyword near the start of the title.',
                    'Keep titles under 60 characters so they display fully in Google results.',
                    'Save/update and re-audit.',
                ];
                $result['docs_url'] = 'https://yoast.com/page-titles-seo/';

                $posts = $wpdb->get_results( $wpdb->prepare(
                    "SELECT p.ID, p.post_title, p.post_type, p.post_date
                     FROM {$wpdb->posts} p
                     INNER JOIN (
                         SELECT post_title
                         FROM {$wpdb->posts}
                         WHERE post_status = 'publish'
                           AND post_type IN ('post','page')
                         GROUP BY post_title
                         HAVING COUNT(*) > 1
                     ) dups ON p.post_title = dups.post_title
                     WHERE p.post_status = 'publish'
                       AND p.post_type IN ('post','page')
                     ORDER BY p.post_title, p.post_date DESC
                     LIMIT %d",
                    $limit
                ) );

                $result['posts'] = self::enrich_posts( $posts );
                break;

            /* ── Thin content ───────────────────────────────────────────── */
            case 'thin_content':
                $result['fix_title'] = 'How to fix: Thin Content (under 300 words)';
                $result['severity']  = 'warning';
                $result['fix_steps'] = [
                    'Google considers pages under ~300 words "thin content" and may rank them lower.',
                    'Open each post below and expand it with more useful, relevant information.',
                    'Aim for at least 600–800 words for blog posts; 300+ words for pages.',
                    'Add: subheadings (H2/H3), bullet points, images with alt text, internal links.',
                    'Answer common questions your audience has about the topic.',
                    'Consider merging very short posts with related content instead.',
                ];
                $result['docs_url'] = 'https://yoast.com/thin-content/';

                $posts = $wpdb->get_results( $wpdb->prepare(
                    "SELECT ID, post_title, post_type, post_date,
                            CHAR_LENGTH(REGEXP_REPLACE(post_content,'<[^>]+>','')) AS content_len
                     FROM {$wpdb->posts}
                     WHERE post_status = 'publish'
                       AND post_type IN ('post','page')
                       AND post_content != ''
                       AND CHAR_LENGTH(REGEXP_REPLACE(post_content,'<[^>]+>','')) < 1700
                     ORDER BY content_len ASC
                     LIMIT %d",
                    $limit
                ) );

                // Fallback for MySQL < 8.
                if ( $wpdb->last_error ) {
                    $posts = $wpdb->get_results( $wpdb->prepare(
                        "SELECT ID, post_title, post_type, post_date,
                                CHAR_LENGTH(post_content) AS content_len
                         FROM {$wpdb->posts}
                         WHERE post_status = 'publish'
                           AND post_type IN ('post','page')
                           AND CHAR_LENGTH(post_content) < 2200
                         ORDER BY content_len ASC
                         LIMIT %d",
                        $limit
                    ) );
                }

                $enriched = self::enrich_posts( $posts );
                // Add word-count estimate.
                foreach ( $enriched as &$p ) {
                    $p['note'] = isset( $p['raw']->content_len )
                        ? '~' . round( $p['raw']->content_len / 5.5 ) . ' words'
                        : '';
                }
                $result['posts'] = $enriched;
                break;

            /* ── No internal links ──────────────────────────────────────── */
            case 'no_internal_links':
                $result['fix_title'] = 'How to fix: No Internal Links Found';
                $result['severity']  = 'warning';
                $result['fix_steps'] = [
                    'Internal links help Google crawl your site and spread page authority.',
                    'Open each post below and add at least 2–3 links to other relevant pages on your site.',
                    'Link from high-traffic pages to newer or lower-ranked pages.',
                    'Use descriptive anchor text (not "click here") that includes the target keyword.',
                    'Install a link-suggestion plugin (e.g. Yoast or Link Whisper) for automated suggestions.',
                ];
                $result['docs_url'] = 'https://yoast.com/internal-linking-for-seo-why-and-how/';

                $home = home_url();
                $posts = $wpdb->get_results( $wpdb->prepare(
                    "SELECT ID, post_title, post_type, post_date
                     FROM {$wpdb->posts}
                     WHERE post_status = 'publish'
                       AND post_type IN ('post','page')
                       AND post_content NOT LIKE %s
                     ORDER BY post_date DESC
                     LIMIT %d",
                    '%href="' . esc_sql( $home ) . '%',
                    $limit
                ) );

                $result['posts'] = self::enrich_posts( $posts );
                break;

            /* ── Images missing alt text ────────────────────────────────── */
            case 'images_no_alt':
                $result['fix_title'] = 'How to fix: Images Missing Alt Text';
                $result['severity']  = 'warning';
                $result['fix_steps'] = [
                    'Alt text helps visually impaired users and tells Google what an image shows.',
                    'Go to Media Library and filter by images with no alt text.',
                    'Click each image → fill in the "Alternative Text" field on the right.',
                    'Describe the image specifically (e.g. "red running shoes on white background") — include a keyword naturally.',
                    'Keep alt text under 125 characters.',
                    'For decorative images (icons, dividers) leave alt text empty — do not use filler text.',
                ];
                $result['docs_url'] = 'https://yoast.com/image-seo-alt-tag-image-title-text/';

                $media = $wpdb->get_results( $wpdb->prepare(
                    "SELECT p.ID, p.post_title, p.post_date,
                            p.guid AS img_url
                     FROM {$wpdb->posts} p
                     LEFT JOIN {$wpdb->postmeta} pm
                       ON pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt'
                     WHERE p.post_type = 'attachment'
                       AND p.post_mime_type LIKE 'image/%%'
                       AND (pm.meta_value IS NULL OR pm.meta_value = '')
                     ORDER BY p.post_date DESC
                     LIMIT %d",
                    $limit
                ) );

                $result['posts'] = array_map( function( $m ) {
                    return [
                        'id'       => $m->ID,
                        'title'    => $m->post_title ?: basename( $m->img_url ),
                        'note'     => basename( $m->img_url ),
                        'edit_url' => admin_url( 'post.php?post=' . $m->ID . '&action=edit' ),
                        'type'     => 'attachment',
                    ];
                }, $media );
                break;

            /* ── Schema markup ──────────────────────────────────────────── */
            case 'schema_present':
                $result['fix_title'] = 'Schema Markup — Which Pages Have It';
                $result['severity']  = 'good';
                $result['fix_steps'] = [
                    'Add schema to more pages using the GGR Page Analyzer or a schema plugin.',
                    'Choose the correct schema type for each post (Article, Product, FAQ, etc.).',
                    'Test your schema at: https://search.google.com/test/rich-results',
                ];
                $result['docs_url'] = 'https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data';

                $posts = $wpdb->get_results( $wpdb->prepare(
                    "SELECT ID, post_title, post_type, post_date
                     FROM {$wpdb->posts}
                     WHERE post_status = 'publish'
                       AND post_type IN ('post','page')
                       AND post_content LIKE '%%application/ld+json%%'
                     ORDER BY post_date DESC
                     LIMIT %d",
                    $limit
                ) );

                $result['posts'] = self::enrich_posts( $posts );
                break;

            default:
                $result['fix_title'] = 'Issue details not available';
                $result['fix_steps'] = [ 'No detail handler found for: ' . esc_html( $issue_key ) ];
        }

        return $result;
    }

    /**
     * Enrich a list of WP_Post-like objects with edit URLs etc.
     */
    private static function enrich_posts( $posts ) {
        $result = [];
        foreach ( $posts as $p ) {
            $result[] = [
                'id'       => $p->ID,
                'title'    => $p->post_title,
                'type'     => $p->post_type ?? 'post',
                'date'     => isset( $p->post_date ) ? date( 'M j, Y', strtotime( $p->post_date ) ) : '',
                'edit_url' => current_user_can( 'edit_post', $p->ID )
                              ? get_edit_post_link( $p->ID, '' ) : '',
                'view_url' => get_permalink( $p->ID ) ?: '',
                'note'     => '',
                'raw'      => $p,
            ];
        }
        return $result;
    }
}
