<?php
/**
 * Google PageSpeed Insights API integration.
 *
 * Free tier: 25,000 requests/day. Results cached 24h per URL.
 * Requires an API key stored in ggrwa_settings['pagespeed_api_key'].
 *
 * @package GGR_Website_Audit
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class GGRWA_PageSpeed {

    const CACHE_PREFIX = 'ggrwa_psi_';
    const CACHE_TTL    = DAY_IN_SECONDS;
    const API_ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    /**
     * Get PageSpeed data for a URL.
     * Returns cached result if available, otherwise fetches from API.
     *
     * @param string $url      The URL to analyze.
     * @param string $strategy 'mobile' or 'desktop'.
     * @param bool   $force    Bypass cache.
     * @return array|WP_Error
     */
    public static function get( $url, $strategy = 'mobile', $force = false ) {
        $cache_key = self::CACHE_PREFIX . md5( $url . $strategy );

        if ( ! $force ) {
            $cached = get_transient( $cache_key );
            if ( false !== $cached ) return $cached;
        }

        $api_key = self::get_api_key();
        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', 'No PageSpeed API key configured.' );
        }

        $request_url = add_query_arg( [
            'url'      => urlencode( $url ),
            'strategy' => $strategy,
            'key'      => $api_key,
            'category' => 'performance',
        ], self::API_ENDPOINT );

        $response = wp_remote_get( $request_url, [
            'timeout'   => 30,
            'sslverify' => true,
        ] );

        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return new WP_Error( 'api_error', 'PageSpeed API returned HTTP ' . $code );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'parse_error', 'Could not parse PageSpeed response.' );
        }

        $parsed = self::parse( $body, $strategy );
        set_transient( $cache_key, $parsed, self::CACHE_TTL );
        return $parsed;
    }

    /**
     * Parse the raw PSI response into a clean array.
     */
    private static function parse( array $body, $strategy ) {
        $cats    = $body['lighthouseResult']['categories'] ?? [];
        $audits  = $body['lighthouseResult']['audits']    ?? [];
        $metrics = $audits['metrics']['details']['items'][0] ?? [];

        $perf_score = isset( $cats['performance']['score'] )
            ? (int) round( $cats['performance']['score'] * 100 ) : 0;

        // Core Web Vitals.
        $lcp = self::ms_to_s( $metrics['largestContentfulPaint'] ?? 0 );
        $cls = round( $metrics['cumulativeLayoutShift'] ?? 0, 3 );
        $fid = $metrics['maxPotentialFid'] ?? $metrics['totalBlockingTime'] ?? 0;
        $fcp = self::ms_to_s( $metrics['firstContentfulPaint'] ?? 0 );
        $tti = self::ms_to_s( $metrics['interactive'] ?? 0 );
        $tbt = $metrics['totalBlockingTime'] ?? 0;
        $si  = self::ms_to_s( $metrics['speedIndex'] ?? 0 );

        // CWV pass/fail thresholds (Google's official).
        $lcp_status = $lcp <= 2.5 ? 'good' : ( $lcp <= 4.0 ? 'warning' : 'critical' );
        $cls_status = $cls <= 0.1 ? 'good' : ( $cls <= 0.25 ? 'warning' : 'critical' );
        $fid_status = $fid <= 100 ? 'good' : ( $fid <= 300 ? 'warning' : 'critical' );

        // Opportunities (top 3 improvements).
        $opportunities = [];
        $opp_keys = [
            'render-blocking-resources',
            'unused-css-rules',
            'unused-javascript',
            'uses-optimized-images',
            'uses-webp-images',
            'uses-text-compression',
            'efficient-animated-content',
            'offscreen-images',
        ];
        foreach ( $opp_keys as $key ) {
            if ( ! isset( $audits[ $key ] ) ) continue;
            $a = $audits[ $key ];
            if ( ( $a['score'] ?? 1 ) >= 0.9 ) continue; // already passing
            $savings = $a['details']['overallSavingsMs'] ?? 0;
            $opportunities[] = [
                'title'    => $a['title'] ?? $key,
                'desc'     => $a['description'] ?? '',
                'savings'  => $savings > 0 ? round( $savings / 1000, 1 ) . 's saved' : '',
                'severity' => ( $a['score'] ?? 1 ) < 0.5 ? 'critical' : 'warning',
            ];
        }
        usort( $opportunities, fn( $a, $b ) => strcmp( $b['severity'], $a['severity'] ) );

        return [
            'strategy'     => $strategy,
            'perf_score'   => $perf_score,
            'lcp'          => $lcp,
            'lcp_status'   => $lcp_status,
            'cls'          => $cls,
            'cls_status'   => $cls_status,
            'fid_ms'       => $fid,
            'fid_status'   => $fid_status,
            'fcp'          => $fcp,
            'tti'          => $tti,
            'tbt_ms'       => $tbt,
            'speed_index'  => $si,
            'opportunities'=> array_slice( $opportunities, 0, 5 ),
            'fetched_at'   => current_time( 'mysql' ),
        ];
    }

    private static function ms_to_s( $ms ) {
        return round( $ms / 1000, 2 );
    }

    public static function get_api_key() {
        $settings = get_option( 'ggrwa_settings', [] );
        return trim( $settings['pagespeed_api_key'] ?? '' );
    }

    public static function has_api_key() {
        return ! empty( self::get_api_key() );
    }

    /** Register AJAX handlers. */
    public static function register_ajax() {
        add_action( 'wp_ajax_ggrwa_get_pagespeed', [ __CLASS__, 'ajax_get_pagespeed' ] );
    }

    public static function ajax_get_pagespeed() {
        check_ajax_referer( 'ggrwa_seo_dashboard', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

        $url      = esc_url_raw( $_POST['url'] ?? '' );
        $strategy = in_array( $_POST['strategy'] ?? 'mobile', [ 'mobile', 'desktop' ], true )
                    ? $_POST['strategy'] : 'mobile';

        if ( empty( $url ) ) wp_send_json_error( [ 'message' => 'No URL provided' ] );

        $result = self::get( $url, $strategy );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( $result );
    }
}
