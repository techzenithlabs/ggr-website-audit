<?php
/**
 * Conversion Audit Module
 *
 * Analyses your site for CRO signals:
 *  - CTA presence (buttons/links with action words)
 *  - Forms detected (contact, lead-gen, checkout)
 *  - Pages with no CTA
 *  - Above-the-fold text length
 *  - Trust signals (testimonials, reviews, badges)
 *  - WooCommerce product metrics if active
 *  - Page-speed proxy (content weight)
 *
 * @package GGR_Website_Audit
 * @since   2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* =========================================================================
   DATA LAYER
   ========================================================================= */

class GGRWA_Conversion_Audit_Data {

    const CACHE_KEY = 'ggrwa_conversion_audit_data';
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

        $post_types = post_type_exists( 'product' )
            ? "'post','page','product'" : "'post','page'";

        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_content, post_type, post_date, post_excerpt
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ({$post_types})
             ORDER BY post_date DESC
             LIMIT 300"
        );

        /* CTA keyword patterns */
        $cta_patterns = [
            'buy_now'       => '/\b(buy now|order now|shop now|purchase)\b/i',
            'get_started'   => '/\b(get started|start free|start now|try free|try now)\b/i',
            'contact'       => '/\b(contact us|get in touch|reach out|talk to us)\b/i',
            'learn_more'    => '/\b(learn more|read more|find out more|discover)\b/i',
            'download'      => '/\b(download|get the guide|free download|grab)\b/i',
            'subscribe'     => '/\b(subscribe|sign up|join us|join free|newsletter)\b/i',
            'book'          => '/\b(book a call|book now|schedule|book a demo|request demo)\b/i',
        ];

        /* Trust-signal patterns */
        $trust_patterns = [
            'testimonial' => '/(testimonial|review|rated|stars|customer said|client said)/i',
            'guarantee'   => '/(money.back|guarantee|satisfaction|risk.free)/i',
            'security'    => '/(secure|ssl|encrypted|safe checkout|trusted)/i',
            'award'       => '/(award|featured in|as seen on|certified)/i',
        ];

        /* Form detection */
        $form_patterns = [
            'contact_form'  => '/(contact.?form|wpcf7|gravityforms|wpforms)/i',
            'woo_checkout'  => '/class=["\'][^"\']*checkout[^"\']*["\']/i',
            'optin_form'    => '/(mailchimp|convertkit|klaviyo|optin|opt-in|lead)/i',
        ];

        $rows          = [];
        $total_ctas    = 0;
        $no_cta_count  = 0;
        $trust_count   = 0;
        $form_count    = 0;
        $cta_type_dist = array_fill_keys( array_keys( $cta_patterns ), 0 );

        /* WooCommerce stats */
        $woo_active     = post_type_exists( 'product' );
        $woo_stats      = [];
        if ( $woo_active ) {
            $woo_stats = self::get_woo_stats();
        }

        foreach ( $posts as $p ) {
            $html  = $p->post_content;
            $plain = wp_strip_all_tags( $html );

            /* CTAs */
            $ctas_found = [];
            foreach ( $cta_patterns as $key => $pattern ) {
                if ( preg_match( $pattern, $html ) || preg_match( $pattern, $plain ) ) {
                    $ctas_found[]      = $key;
                    $cta_type_dist[$key]++;
                }
            }
            $has_cta = ! empty( $ctas_found );
            if ( $has_cta ) $total_ctas++;
            else            $no_cta_count++;

            /* Button count */
            $button_count = preg_match_all( '/<(a|button)[^>]*(class|href)[^>]*>/i', $html );

            /* Trust signals */
            $trust_signals = [];
            foreach ( $trust_patterns as $key => $pat ) {
                if ( preg_match( $pat, $html ) || preg_match( $pat, $plain ) ) {
                    $trust_signals[] = $key;
                }
            }
            if ( ! empty( $trust_signals ) ) $trust_count++;

            /* Forms */
            $forms_detected = [];
            foreach ( $form_patterns as $key => $pat ) {
                if ( preg_match( $pat, $html ) ) {
                    $forms_detected[] = $key;
                }
            }
            if ( ! empty( $forms_detected ) ) $form_count++;

            /* Above-the-fold proxy: first 200 chars of stripped content */
            $atf_text  = mb_substr( $plain, 0, 300 );
            $atf_words = str_word_count( $atf_text );

            /* Content weight (rough proxy for page speed) */
            $html_kb = round( strlen( $html ) / 1024, 1 );

            /* Conversion score 0-100 */
            $score = 0;
            if ( $has_cta )                $score += 30;
            if ( ! empty($trust_signals) ) $score += 20;
            if ( ! empty($forms_detected)) $score += 15;
            if ( $button_count >= 2 )      $score += 10;
            if ( $atf_words >= 20 )        $score += 10;
            if ( $html_kb < 100 )          $score += 15;

            /* Issues */
            $issues = [];
            if ( ! $has_cta )              $issues[] = 'no_cta';
            if ( empty($trust_signals) )   $issues[] = 'no_trust';
            if ( empty($forms_detected) && $p->post_type !== 'product' ) $issues[] = 'no_form';
            if ( $atf_words < 10 )         $issues[] = 'weak_atf';
            if ( $html_kb > 150 )          $issues[] = 'heavy_page';

            /* Grade */
            $grade = $score >= 70 ? 'A' : ( $score >= 50 ? 'B' : ( $score >= 30 ? 'C' : 'D' ) );

            $rows[] = [
                'id'             => (int) $p->ID,
                'title'          => $p->post_title,
                'type'           => $p->post_type,
                'date'           => date( 'M j, Y', strtotime( $p->post_date ) ),
                'has_cta'        => $has_cta,
                'ctas'           => $ctas_found,
                'button_count'   => $button_count,
                'trust_signals'  => $trust_signals,
                'forms'          => $forms_detected,
                'atf_words'      => $atf_words,
                'html_kb'        => $html_kb,
                'score'          => $score,
                'grade'          => $grade,
                'issues'         => $issues,
                'edit_url'       => current_user_can( 'edit_post', $p->ID ) ? get_edit_post_link( $p->ID, '' ) : '',
                'view_url'       => get_permalink( $p->ID ) ?: '',
            ];
        }

        $total = count( $rows );
        $avg_score = $total ? (int) round( array_sum( array_column( $rows, 'score' ) ) / $total ) : 0;

        /* Grade distribution */
        $grade_dist = [ 'A' => 0, 'B' => 0, 'C' => 0, 'D' => 0 ];
        foreach ( $rows as $r ) $grade_dist[ $r['grade'] ]++;

        /* Top issues */
        $issue_counts = [
            'no_cta'    => $no_cta_count,
            'no_trust'  => count( array_filter( $rows, fn($r) => in_array('no_trust',  $r['issues']) ) ),
            'no_form'   => count( array_filter( $rows, fn($r) => in_array('no_form',   $r['issues']) ) ),
            'weak_atf'  => count( array_filter( $rows, fn($r) => in_array('weak_atf',  $r['issues']) ) ),
            'heavy_page'=> count( array_filter( $rows, fn($r) => in_array('heavy_page',$r['issues']) ) ),
        ];

        return [
            'computed_at'    => current_time( 'mysql' ),
            'total'          => $total,
            'avg_score'      => $avg_score,
            'total_ctas'     => $total_ctas,
            'no_cta_count'   => $no_cta_count,
            'trust_count'    => $trust_count,
            'form_count'     => $form_count,
            'cta_type_dist'  => $cta_type_dist,
            'grade_dist'     => $grade_dist,
            'issue_counts'   => $issue_counts,
            'woo_active'     => $woo_active,
            'woo_stats'      => $woo_stats,
            'posts'          => $rows,
        ];
    }

    /* ── WooCommerce stats ─────────────────────────────────────────────── */
    private static function get_woo_stats() {
        global $wpdb;

        $total_products = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'product' AND post_status = 'publish'"
        );

        /* Products missing description */
        $no_desc = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'product'
               AND post_status = 'publish'
               AND (post_content = '' OR CHAR_LENGTH(post_content) < 50)"
        );

        /* Products missing featured image */
        $no_img = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_thumbnail_id'
             WHERE p.post_type = 'product' AND p.post_status = 'publish'
               AND pm.meta_value IS NULL"
        );

        /* Products missing price */
        $no_price = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_price'
             WHERE p.post_type = 'product' AND p.post_status = 'publish'
               AND (pm.meta_value IS NULL OR pm.meta_value = '')"
        );

        /* Products missing short description */
        $no_short = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'product' AND post_status = 'publish'
               AND (post_excerpt = '' OR post_excerpt IS NULL)"
        );

        return [
            'total'     => $total_products,
            'no_desc'   => $no_desc,
            'no_img'    => $no_img,
            'no_price'  => $no_price,
            'no_short'  => $no_short,
        ];
    }
}


/* =========================================================================
   MODULE CLASS
   ========================================================================= */

class GGRWA_Conversion_Audit {

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'register_menu'  ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public static function register_ajax() {
        add_action( 'wp_ajax_ggrwa_conversion_refresh', [ __CLASS__, 'ajax_refresh' ] );
    }

    public function register_menu() {
        add_submenu_page(
            'ggrwa-audit-dashboard',
            'Conversion Audit',
            'Conversion Audit',
            'manage_options',
            'ggrwa-conversion-audit',
            [ $this, 'render' ]
        );
    }

    public function render() {
        $d = GGRWA_Conversion_Audit_Data::get();
        include __DIR__ . '/view-conversion-audit.php';
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'ggrwa-conversion-audit' ) === false ) return;

        wp_enqueue_style(  'ggrwa-conv-css', GGRWA_PLUGIN_URL . 'includes/modules/conversion-audit/assets/conversion-audit.css', [], GGRWA_VERSION );
        wp_enqueue_script( 'ggrwa-conv-js',  GGRWA_PLUGIN_URL . 'includes/modules/conversion-audit/assets/conversion-audit.js',  [ 'jquery' ], GGRWA_VERSION, true );

        wp_localize_script( 'ggrwa-conv-js', 'ggrwa_conv', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ggrwa_conversion_audit' ),
        ] );
    }

    public static function ajax_refresh() {
        check_ajax_referer( 'ggrwa_conversion_audit', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

        @set_time_limit( 120 );
        ignore_user_abort( true );
        global $wpdb;
        if ( ! $wpdb->check_connection( false ) ) $wpdb->db_connect();

        wp_send_json_success( GGRWA_Conversion_Audit_Data::get( true ) );
    }
}
