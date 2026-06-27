<?php
/**
 * Content Analyzer Module
 *
 * Analyses every published post/page for:
 *  - Word count & readability (Flesch-Kincaid)
 *  - Heading structure (H1/H2/H3 usage)
 *  - Keyword density
 *  - Internal / external links per post
 *  - Images & alt text
 *  - Duplicate & thin content clusters
 *
 * Data flows: DB → GGRWA_Content_Analyzer_Data → JSON → JS → DOM
 *
 * @package GGR_Website_Audit
 * @since   2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* =========================================================================
   DATA LAYER
   ========================================================================= */

class GGRWA_Content_Analyzer_Data {

    const CACHE_KEY = 'ggrwa_content_analyzer_data';
    const CACHE_TTL = HOUR_IN_SECONDS;

    public static function get( $force = false ) {
        if ( ! $force ) {
            $c = get_transient( self::CACHE_KEY );
            if ( $c ) return $c;
        }
        $d = self::compute();
        set_transient( self::CACHE_KEY, $d, self::CACHE_TTL );
        return $d;
    }

    public static function bust() { delete_transient( self::CACHE_KEY ); }

    /* -----------------------------------------------------------------------
       COMPUTE
    ----------------------------------------------------------------------- */
    private static function compute() {
        global $wpdb;

        /* All published posts + pages (cap 500 for performance) */
        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_content, post_type, post_date
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('post','page')
             ORDER BY post_date DESC
             LIMIT 500"
        );

        $total      = count( $posts );
        $words_all  = [];
        $flesch_all = [];
        $rows       = [];

        /* Transition words list for readability calc */
        $trans_words = [ 'however','therefore','furthermore','moreover','consequently',
                         'additionally','meanwhile','although','because','since',
                         'while','whereas','unless','despite','nevertheless' ];

        $site_host = wp_parse_url( home_url(), PHP_URL_HOST );

        foreach ( $posts as $p ) {
            $plain    = wp_strip_all_tags( $p->post_content );
            $plain    = preg_replace( '/\s+/', ' ', trim( $plain ) );
            $wc       = str_word_count( $plain );
            $words_all[] = $wc;

            /* Flesch */
            $fk = self::flesch( $plain );
            $flesch_all[] = $fk;

            /* Headings */
            $h1 = preg_match_all( '/<h1[^>]*>/i', $p->post_content );
            $h2 = preg_match_all( '/<h2[^>]*>/i', $p->post_content );
            $h3 = preg_match_all( '/<h3[^>]*>/i', $p->post_content );

            /* Images */
            $imgs_total = preg_match_all( '/<img[^>]+>/i', $p->post_content );
            $imgs_alt   = preg_match_all( '/<img[^>]+alt=["\'][^"\']+["\'][^>]*>/i', $p->post_content );
            $imgs_no_alt= max( 0, $imgs_total - $imgs_alt );

            /* Links */
            preg_match_all( '/href=["\']([^"\']+)["\']/i', $p->post_content, $lm );
            $int_links = 0; $ext_links = 0;
            foreach ( $lm[1] as $href ) {
                $parsed = wp_parse_url( $href );
                if ( empty( $parsed['host'] ) ) { $int_links++; continue; }
                if ( strcasecmp( $parsed['host'], $site_host ) === 0 ) $int_links++;
                else $ext_links++;
            }

            /* Keyword density (title keyword in content) */
            $title_words = preg_split( '/\s+/', strtolower( $p->post_title ) );
            $kw = '';
            foreach ( $title_words as $tw ) {
                if ( strlen( $tw ) > 4 ) { $kw = $tw; break; }
            }
            $density = 0;
            if ( $kw && $wc > 0 ) {
                $hits    = substr_count( strtolower( $plain ), $kw );
                $density = round( ( $hits / $wc ) * 100, 2 );
            }

            /* Focus keyword from SEO plugin */
            $focus_kw = get_post_meta( $p->ID, '_yoast_wpseo_focuskw', true )
                     ?: get_post_meta( $p->ID, 'rank_math_focus_keyword', true )
                     ?: $kw;

            /* Passive voice estimate */
            $sents   = preg_split( '/(?<=[.!?])\s+/', $plain, -1, PREG_SPLIT_NO_EMPTY );
            $passive = 0;
            foreach ( $sents as $s ) {
                if ( preg_match( '/\b(was|were|is|are|been)\s+\w+ed\b/i', $s ) ) $passive++;
            }
            $passive_pct = count( $sents ) > 0 ? round( ( $passive / count( $sents ) ) * 100 ) : 0;

            /* Content grade */
            if      ( $wc >= 1200 && $fk >= 60 ) $grade = 'A';
            elseif  ( $wc >= 600  && $fk >= 50 ) $grade = 'B';
            elseif  ( $wc >= 300               ) $grade = 'C';
            else                                   $grade = 'D';

            /* Issues */
            $issues = [];
            if ( $wc < 300 )        $issues[] = 'thin';
            if ( $h1 === 0 )        $issues[] = 'no_h1';
            if ( $h2 === 0 )        $issues[] = 'no_h2';
            if ( $imgs_no_alt > 0 ) $issues[] = 'missing_alt';
            if ( $int_links === 0 ) $issues[] = 'no_int_links';
            if ( $density > 4 )     $issues[] = 'keyword_stuffing';
            if ( $density === 0 && $focus_kw ) $issues[] = 'no_keyword';

            $rows[] = [
                'id'          => (int) $p->ID,
                'title'       => $p->post_title,
                'type'        => $p->post_type,
                'date'        => date( 'M j, Y', strtotime( $p->post_date ) ),
                'word_count'  => $wc,
                'flesch'      => $fk,
                'grade'       => $grade,
                'h1'          => $h1,
                'h2'          => $h2,
                'h3'          => $h3,
                'imgs_total'  => $imgs_total,
                'imgs_no_alt' => $imgs_no_alt,
                'int_links'   => $int_links,
                'ext_links'   => $ext_links,
                'density'     => $density,
                'focus_kw'    => $focus_kw,
                'passive_pct' => $passive_pct,
                'issues'      => $issues,
                'edit_url'    => current_user_can( 'edit_post', $p->ID ) ? get_edit_post_link( $p->ID, '' ) : '',
                'view_url'    => get_permalink( $p->ID ) ?: '',
            ];
        }

        /* Aggregates */
        $avg_words  = $total ? (int) round( array_sum( $words_all )  / $total ) : 0;
        $avg_flesch = $total ? (int) round( array_sum( $flesch_all ) / $total ) : 0;

        $thin       = count( array_filter( $words_all, fn($w) => $w < 300  ) );
        $good       = count( array_filter( $words_all, fn($w) => $w >= 800 ) );
        $no_h2      = count( array_filter( $rows, fn($r) => $r['h2'] === 0 ) );
        $no_alt     = count( array_filter( $rows, fn($r) => $r['imgs_no_alt'] > 0 ) );
        $no_kw      = count( array_filter( $rows, fn($r) => in_array('no_keyword', $r['issues']) ) );

        /* Grade distribution */
        $grade_dist = [ 'A' => 0, 'B' => 0, 'C' => 0, 'D' => 0 ];
        foreach ( $rows as $r ) $grade_dist[ $r['grade'] ]++;

        /* Word count histogram buckets */
        $wc_hist = [ '<300' => 0, '300–600' => 0, '600–1000' => 0, '1000–2000' => 0, '2000+' => 0 ];
        foreach ( $words_all as $w ) {
            if      ( $w < 300  ) $wc_hist['<300']++;
            elseif  ( $w < 600  ) $wc_hist['300–600']++;
            elseif  ( $w < 1000 ) $wc_hist['600–1000']++;
            elseif  ( $w < 2000 ) $wc_hist['1000–2000']++;
            else                  $wc_hist['2000+']++;
        }

        return [
            'computed_at'  => current_time( 'mysql' ),
            'total'        => $total,
            'avg_words'    => $avg_words,
            'avg_flesch'   => $avg_flesch,
            'thin_count'   => $thin,
            'good_count'   => $good,
            'no_h2_count'  => $no_h2,
            'no_alt_count' => $no_alt,
            'no_kw_count'  => $no_kw,
            'grade_dist'   => $grade_dist,
            'wc_hist'      => $wc_hist,
            'posts'        => $rows,
        ];
    }

    /* -----------------------------------------------------------------------
       FLESCH READING EASE (fast approximation)
    ----------------------------------------------------------------------- */
    private static function flesch( $text ) {
        $words     = str_word_count( $text );
        if ( $words < 5 ) return 0;
        $sentences = max( 1, preg_match_all( '/[.!?]+/', $text ) );
        $syllables = 0;
        foreach ( preg_split( '/\s+/', strtolower( $text ) ) as $w ) {
            $w = preg_replace( '/[^a-z]/', '', $w );
            $syllables += max( 1, preg_match_all( '/[aeiouy]+/', $w ) );
        }
        $score = 206.835 - 1.015 * ( $words / $sentences ) - 84.6 * ( $syllables / $words );
        return max( 0, min( 100, (int) round( $score ) ) );
    }
}


/* =========================================================================
   MODULE CLASS
   ========================================================================= */

class GGRWA_Content_Analyzer {

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'register_menu'  ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public static function register_ajax() {
        add_action( 'wp_ajax_ggrwa_content_analyzer_data',    [ __CLASS__, 'ajax_data'       ] );
        add_action( 'wp_ajax_ggrwa_content_analyzer_refresh', [ __CLASS__, 'ajax_refresh'    ] );
    }

    /* ── Menu ──────────────────────────────────────────────────────────── */
    public function register_menu() {
        add_submenu_page(
            'ggrwa-audit-dashboard',
            'Content Analyzer',
            'Content Analyzer',
            'manage_options',
            'ggrwa-content-analyzer',
            [ $this, 'render' ]
        );
    }

    public function render() {
        $d = GGRWA_Content_Analyzer_Data::get();
        include __DIR__ . '/view-content-analyzer.php';
    }

    /* ── Assets ────────────────────────────────────────────────────────── */
    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'ggrwa-content-analyzer' ) === false ) return;

        wp_enqueue_style(  'ggrwa-ca-css', GGRWA_PLUGIN_URL . 'includes/modules/content-analyzer/assets/content-analyzer.css', [], GGRWA_VERSION );
        wp_enqueue_script( 'ggrwa-ca-js',  GGRWA_PLUGIN_URL . 'includes/modules/content-analyzer/assets/content-analyzer.js',  [ 'jquery' ], GGRWA_VERSION, true );

        wp_localize_script( 'ggrwa-ca-js', 'ggrwa_ca', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ggrwa_content_analyzer' ),
        ] );
    }

    /* ── AJAX handlers ─────────────────────────────────────────────────── */
    public static function ajax_data() {
        check_ajax_referer( 'ggrwa_content_analyzer', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
        wp_send_json_success( GGRWA_Content_Analyzer_Data::get( false ) );
    }

    public static function ajax_refresh() {
        check_ajax_referer( 'ggrwa_content_analyzer', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

        @set_time_limit( 120 );
        ignore_user_abort( true );
        global $wpdb;
        if ( ! $wpdb->check_connection( false ) ) $wpdb->db_connect();

        wp_send_json_success( GGRWA_Content_Analyzer_Data::get( true ) );
    }
}
