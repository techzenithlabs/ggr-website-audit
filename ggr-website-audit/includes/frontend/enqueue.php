<?php
/**
 * Frontend assets enqueue for GGR Website Audit.
 *
 * @package GGR_Website_Audit
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_enqueue_scripts', function () {

    wp_enqueue_script(
        'ggr-audit',
        GGRWA_PLUGIN_URL . 'assets/js/audit.js',
        array( 'jquery' ),
        GGRWA_VERSION,
        true
    );

}, 20 );

