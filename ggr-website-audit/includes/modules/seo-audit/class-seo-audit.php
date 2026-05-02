<?php
/**
 * SEO Audit module — menu, assets, and AJAX handlers.
 *
 * AJAX actions are registered via the static register_ajax() method so they
 * work even on AJAX requests where admin_menu never fires.
 * The instance (new GGRWA_SEO_Audit) is created by GGRWA_Admin::load_modules()
 * for the admin menu — we never create it here to avoid duplicates.
 *
 * @package GGR_Website_Audit
 * @since   2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class GGRWA_SEO_Audit {

    /* -----------------------------------------------------------------------
     * AJAX REGISTRATION (static — called early from plugin loader)
     * --------------------------------------------------------------------- */

    /**
     * Register wp_ajax_ hooks.
     * Called statically from GGRWA_Plugin::load_seo_module() so it runs
     * before admin_menu and therefore works for AJAX requests too.
     */
    public static function register_ajax() {
        add_action( 'wp_ajax_ggrwa_seo_dashboard_data',  [ __CLASS__, 'ajax_dashboard_data'  ] );
        add_action( 'wp_ajax_ggrwa_run_seo_full_audit',  [ __CLASS__, 'ajax_run_full_audit'  ] );
        add_action( 'wp_ajax_ggrwa_seo_issue_detail',    [ __CLASS__, 'ajax_issue_detail'    ] );
    }

    /* -----------------------------------------------------------------------
     * AJAX HANDLERS (static — callable without an instance)
     * --------------------------------------------------------------------- */

    /** Return cached (or fresh) dashboard data as JSON. */
    public static function ajax_dashboard_data() {
        check_ajax_referer( 'ggrwa_seo_dashboard', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }

        wp_send_json_success( GGRWA_SEO_Data_Aggregator::get( false ) );
    }

    /** Return posts affected by a specific issue key + fix guide. */
    public static function ajax_issue_detail() {
        check_ajax_referer( 'ggrwa_seo_dashboard', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }

        $issue_key = sanitize_key( $_POST['issue_key'] ?? '' );
        if ( empty( $issue_key ) ) {
            wp_send_json_error( [ 'message' => 'Missing issue_key' ] );
        }

        $data = GGRWA_SEO_Data_Aggregator::get_issue_detail( $issue_key, 20 );
        wp_send_json_success( $data );
    }

    /** Bust cache, recompute, return fresh data. */
    public static function ajax_run_full_audit() {
        check_ajax_referer( 'ggrwa_seo_dashboard', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }

        @set_time_limit( 120 );
        ignore_user_abort( true );

        global $wpdb;
        if ( ! $wpdb->check_connection( false ) ) {
            $wpdb->db_connect();
        }

        $data = GGRWA_SEO_Data_Aggregator::get( true ); // force_refresh

        update_option( 'ggrwa_last_audit_time', time() );

        // Reconnect after long computation.
        if ( ! $wpdb->check_connection( false ) ) {
            $wpdb->db_connect();
        }

        wp_send_json_success( $data );
    }

    /* -----------------------------------------------------------------------
     * INSTANCE — admin menu + asset enqueue
     * Instantiated once by GGRWA_Admin::load_modules().
     * --------------------------------------------------------------------- */

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'register_menu'   ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets'  ] );
    }

    public function register_menu() {
        add_submenu_page(
            'ggrwa-audit-dashboard',
            'SEO Audit',
            'SEO Audit',
            'manage_options',
            'ggrwa-seo-audit',
            [ $this, 'render' ]
        );
    }

    public function render() {
        require GGRWA_PLUGIN_PATH . 'includes/modules/seo-audit/view.php';
    }

    public function enqueue_assets( $hook ) {
        if ( $hook !== 'ggr-website-audit_page_ggrwa-seo-audit' ) return;

        wp_enqueue_style(
            'ggr-seo-audit-css',
            GGRWA_PLUGIN_URL . 'includes/modules/seo-audit/assets/seo-audit.css',
            [],
            GGRWA_VERSION
        );

        wp_enqueue_script(
            'ggr-seo-audit-js',
            GGRWA_PLUGIN_URL . 'includes/modules/seo-audit/assets/seo-audit.js',
            [ 'jquery' ],
            GGRWA_VERSION,
            true
        );

        // Pass AJAX URL + nonce to JS.
        wp_localize_script( 'ggr-seo-audit-js', 'ggrwa_seo_dashboard', [
            'ajax_url'  => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'ggrwa_seo_dashboard' ),
            'site_name' => get_bloginfo( 'name' ),
        ] );
    }
}
