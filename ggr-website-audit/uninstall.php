<?php
/**
 * Uninstall handler for GGR Website Audit.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'ggrwa_settings' );
delete_option( 'ggrwa_enable_audit' );
delete_option( 'ggrwa_needs_setup' );