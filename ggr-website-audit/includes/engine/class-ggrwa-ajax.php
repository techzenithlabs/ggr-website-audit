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

        /*
         * Keep the PHP process alive for the full audit duration.
         *
         * On shared hosts the default max_execution_time is 30s which can be
         * too short when wp_remote_get() has to wait for an external page.
         * ignore_user_abort() prevents PHP from dying if the browser closes
         * the connection before the AJAX response arrives.
         */
        @set_time_limit( 120 );
        ignore_user_abort( true );

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

        $total_score = isset( $score['total_score'] ) ? (int) $score['total_score'] : (int) $score;

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

        /*
         * Reconnect to the database before writing results.
         *
         * On live shared hosting, MySQL's wait_timeout can drop an idle
         * connection while wp_remote_get() is fetching an external URL.
         * Calling check_connection() here forces a reconnect if needed so
         * that update_option / update_post_meta never fail silently.
         */
        global $wpdb;
        if ( ! $wpdb->check_connection( false ) ) {
            // Dead connection — try once more before giving up.
            $wpdb->db_connect();
        }

        if ( $post_id ) {
            update_post_meta( $post_id, '_ggr_seo_score', $total_score );
        }

        update_option( 'ggrwa_last_audit_time', time() );
        update_option( 'ggrwa_last_audit_result', $response, false );

        wp_send_json_success( $response );
    }

    /**
     * Ensure the database connection is alive before running queries.
     *
     * Shared hosts often set MySQL's wait_timeout to a low value (60–300 s).
     * Any long external HTTP request can outlast that window, causing the
     * "No such file or directory" socket error when WordPress tries to write
     * back to the database afterwards.
     *
     * @since 2.5.0
     * @return bool True if connected (or reconnected), false otherwise.
     */
    private function ensure_db_connection() {
        global $wpdb;

        if ( $wpdb->check_connection( false ) ) {
            return true;
        }

        // Attempt a fresh connect using the same credentials WordPress uses.
        $wpdb->db_connect();

        return $wpdb->check_connection( false );
    }
}
