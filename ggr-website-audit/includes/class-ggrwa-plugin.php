<?php
/**
 * Main plugin loader class.
 *
 * @package GGR_Website_Audit
 * @since   2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GGRWA_Plugin {

    /**
     * Bootstraps the plugin.
     *
     * @since 2.3.0
     */
    public function run() {

        $this->load_helpers();
        $this->load_admin();
        $this->load_engine();
        $this->load_frontend();
    }

    /**
     * Loads helper and utility functions.
     *
     * @since 2.3.0
     */
    private function load_helpers() {

        $helpers = GGRWA_PLUGIN_PATH . 'includes/helpers.php';

        if ( file_exists( $helpers ) ) {
            require_once $helpers;
        }

    }
    

    /**
     * Loads admin-related functionality.
     *
     * @since 2.3.0
     */
    private function load_admin() {

        if ( ! is_admin() ) {
            return;
        }

        $admin   = GGRWA_PLUGIN_PATH . 'includes/admin/class-ggrwa-admin.php';
        $setting = GGRWA_PLUGIN_PATH . 'includes/admin/class-ggrwa-settings.php';

        if ( file_exists( $admin ) ) {
            require_once $admin;
        }

        if ( file_exists( $setting ) ) {
            require_once $setting;
        }

        if ( class_exists( 'GGRWA_Admin' ) ) {
            new GGRWA_Admin();
        }

        if ( class_exists( 'GGRWA_Settings' ) ) {
            new GGRWA_Settings();
        }
    }
    

    /**
     * Loads engine and AJAX handlers.
     *
     * @since 2.3.0
     */
    private function load_engine() {

        $files = array(
            'includes/engine/class-ggrwa-analyzer.php',
            'includes/engine/class-ggrwa-pagespeed.php',          
            'includes/engine/class-ggrwa-ajax.php',
        );

        foreach ( $files as $file ) {
            $path = GGRWA_PLUGIN_PATH . $file;
            if ( file_exists( $path ) ) {
                require_once $path;
            }
        }

        if ( class_exists( 'GGRWA_Ajax' ) ) {
            new GGRWA_Ajax();
        }
    }

    /**
     * Loads frontend shortcode and assets.
     *
     * @since 2.3.0
     */
    private function load_frontend() {

        $enqueue   = GGRWA_PLUGIN_PATH . 'includes/frontend/enqueue.php';       

        if ( file_exists( $enqueue ) ) {
            require_once $enqueue;
        }
    }
}