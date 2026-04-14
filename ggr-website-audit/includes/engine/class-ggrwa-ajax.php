<?php
/**
 * AJAX handler for GGR Website Audit.
 *
 * Handles admin-side audit execution and returns
 * structured, explainable JSON responses.
 *
 * @package GGR_Website_Audit
 * @since   2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GGRWA_Ajax
 *
 * Responsible for registering and processing
 * AJAX actions for the audit engine.
 *
 * @since 2.3.0
 */
class GGRWA_Ajax {

    /**
     * Constructor.
     *
     * Registers AJAX hooks.
     *
     * @since 2.3.0
     */
    public function __construct() {

        add_action( 'wp_ajax_ggrwa_run_audit', array( $this, 'handle_run_audit' ) );
        add_action( 'wp_ajax_nopriv_ggrwa_run_audit', array( $this, 'handle_run_audit' ) );

        // PDF download intentionally disabled for WordPress.org release.
    }

    /**
     * Handle the "Run Website Audit" AJAX request.
     *
     * @since 2.3.0
     *
     * @return void
     */
    public function handle_run_audit() {

        // Verify nonce.
        if ( ! check_ajax_referer( 'ggrwa_run_audit', 'nonce', false ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'Security check failed. Please refresh the page and try again.', 'ggr-website-audit' ),
                )
            );
            return;
        }

        // Capability check.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'You are not allowed to perform this action.', 'ggr-website-audit' ),
                )
            );
            return;
        }

        // Check if audit is enabled.
        if ( ! ggrwa_is_audit_enabled() ) {
            wp_send_json_error(
                array(
                    'message' => __( 'Website Audit is disabled. Please enable it from settings.', 'ggr-website-audit' ),
                )
            );
            return;
        }

        // Determine audit URL.
        $url = isset( $_POST['url'] )
            ? esc_url_raw( wp_unslash( $_POST['url'] ) )
            : '';

        if ( empty( $url ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'Invalid URL provided.', 'ggr-website-audit' ),
                )
            );
            return;
        }

                // Run analyzer.
        $analyzer = new GGRWA_Analyzer();
        $scan     = $analyzer->run_free_scan( $url );

        if ( is_wp_error( $scan ) ) {
            wp_send_json_error(
                array(
                    'message' => $scan->get_error_message(),
                )
            );
            return;
        }

        $post_id = 0;
        if ( ! empty( $scan['meta']['url'] ) ) {
            $post_id = url_to_postid( $scan['meta']['url'] );
        }

        $score = $scan['score'];

        $total_score = isset($score['total_score'])   ? (int) $score['total_score'] : (int) $score;

        $response = array(
            'meta'    => $scan['meta'],
            'summary' => __( 'Audit completed using real on-page signals.', 'ggr-website-audit' ),
            'audit'   => $scan['audit'],
            'page_actions' => array(
                'post_id'        => $post_id,
                'view_url'       => $post_id ? get_permalink( $post_id ) : '',
                'edit_url'       => ( $post_id && current_user_can( 'edit_post', $post_id ) )
                    ? get_edit_post_link( $post_id, '' )
                    : '',
                'is_home'        => $post_id === (int) get_option( 'page_on_front' ),
                'is_static_home' => (int) get_option( 'page_on_front' ) > 0,
            ),
            'score' => array(
                'total'      => isset( $score['total_score'] ) ? (int) $score['total_score'] : (int) $score,
                'confidence' => $score['confidence'] ?? null,
                'priority'   => $score['priority'] ?? null,
                'trend'      => $score['trend'] ?? null,
                'sections'   => $score['sections'] ?? array(),
            ),
        );

        if ( $post_id ) {
            update_post_meta( $post_id, '_ggr_seo_score', $total_score );
         }

        update_option( 'ggrwa_last_audit_time', time() );
        update_option( 'ggrwa_last_audit_result', $response, false );

        wp_send_json_success( $response );
    }
}
