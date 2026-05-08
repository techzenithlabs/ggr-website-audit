<?php

/**
 * Helper functions for GGR Website Audit.
 *
 * This file contains reusable utility functions used across
 * frontend, AJAX handlers, and audit engine.
 *
 * IMPORTANT:
 * - No HTML output
 * - No hooks
 * - No direct execution logic
 *
 * @package GGR_Website_Audit
 * @since   2.3.0
 */

if (! defined('ABSPATH')) {
    exit;
}

/* -------------------------------------------------------------------------
 * URL VALIDATION
 * ------------------------------------------------------------------------- */

/**
 * Validate and normalize a website URL.
 *
 * Ensures the URL:
 * - Is not empty
 * - Has a valid scheme
 * - Is properly formatted
 *
 * @since 2.3.0
 *
 * @param string $url_raw Raw URL input.
 * @return string|\WP_Error
 */
function ggrwa_validate_and_normalize_url($url_raw)
{

    if (empty($url_raw)) {
        return new WP_Error(
            'ggrwa_empty_url',
            __('Please enter a website URL.', 'ggr-website-audit')
        );
    }

    $url = esc_url_raw(trim($url_raw));

    if (! preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }

    if (! filter_var($url, FILTER_VALIDATE_URL)) {
        return new WP_Error(
            'ggrwa_invalid_url',
            __('Please enter a valid website URL.', 'ggr-website-audit')
        );
    }

    return $url;
}

/* -------------------------------------------------------------------------
 * USER IDENTIFICATION
 * ------------------------------------------------------------------------- */

/**
 * Get a unique identifier for the current user or guest.
 *
 * Logged-in users use user ID.
 * Guests use a persistent cookie-based token.
 *
 * @since 2.3.0
 *
 * @return string
 */
function ggrwa_get_user_identifier()
{

    if (is_user_logged_in()) {
        return 'user_' . get_current_user_id();
    }

    if (! empty($_COOKIE['ggrwa_guest_token'])) {
        $token = sanitize_text_field(wp_unslash($_COOKIE['ggrwa_guest_token']));
    } else {
        $token = wp_generate_password(24, false);
        setcookie(
            'ggrwa_guest_token',
            $token,
            time() + MONTH_IN_SECONDS,
            COOKIEPATH,
            COOKIE_DOMAIN
        );
        $_COOKIE['ggrwa_guest_token'] = $token;
    }

    return 'guest_' . $token;
}

/* -------------------------------------------------------------------------
 * SCAN LIMITS & COOLDOWN
 * ------------------------------------------------------------------------- */

/**
 * Check whether a user is currently locked (scan in progress).
 *
 * @since 2.3.0
 *
 * @param string $identifier User identifier.
 * @return bool
 */
function ggrwa_is_scan_locked($identifier)
{
    return (bool) get_transient('ggrwa_scan_lock_' . $identifier);
}

/**
 * Lock scan for a user.
 *
 * @since 2.3.0
 *
 * @param string $identifier User identifier.
 * @return void
 */
function ggrwa_lock_scan($identifier)
{
    set_transient('ggrwa_scan_lock_' . $identifier, 1, 30);
}

/**
 * Unlock scan for a user.
 *
 * @since 2.3.0
 *
 * @param string $identifier User identifier.
 * @return void
 */
function ggrwa_unlock_scan($identifier)
{
    delete_transient('ggrwa_scan_lock_' . $identifier);
}

/**
 * Check whether the user can run a scan.
 *
 * Applies:
 * - Lock check
 * - Cooldown check
 *
 * @since 2.3.0
 *
 * @param string $identifier User identifier.
 * @param string $url        Website URL.
 * @param string $message    Error message reference.
 * @return bool
 */
function ggrwa_can_run_scan($identifier, $url, &$message = '')
{

    if (ggrwa_is_scan_locked($identifier)) {
        $message = __('A scan is already in progress. Please wait.', 'ggr-website-audit');
        return false;
    }

    $last_scan = (int) get_transient('ggrwa_last_scan_' . $identifier);

    if ($last_scan && (time() - $last_scan) < 60) {
        $message = __('Please wait before running another scan.', 'ggr-website-audit');
        return false;
    }

    set_transient('ggrwa_last_scan_' . $identifier, time(), HOUR_IN_SECONDS);

    return true;
}

/* -------------------------------------------------------------------------
 * JSON RESPONSE HELPERS
 * ------------------------------------------------------------------------- */

/**
 * Send JSON error response and exit.
 *
 * @since 2.3.0
 *
 * @param string $message Error message.
 * @return void
 */
function ggrwa_send_json_error($message)
{
    wp_send_json_error(
        [
            'message' => esc_html($message),
        ]
    );
}

/**
 * Send JSON success response and exit.
 *
 * @since 2.3.0
 *
 * @param array $data Response data.
 * @return void
 */
function ggrwa_send_json_success(array $data)
{
    wp_send_json_success($data);
}


/**
 * Normalize URL for WordPress permalink structure.
 *
 * @param string $url Input URL.
 * @return string Normalized URL.
 */
function ggrwa_normalize_url($url)
{

    $url = trim($url);

    $parsed = wp_parse_url($url);
    if (empty($parsed['path'])) {
        return trailingslashit($url);
    }

    // If path has no file extension, assume WP page
    if (! preg_match('/\.[a-zA-Z0-9]{1,5}$/', $parsed['path'])) {
        return trailingslashit($url);
    }

    return $url;
}


/**
 * Check if current user can perform an edit action.
 *
 * @return bool
 */
function ggrwa_can_edit_page()
{
    return current_user_can('edit_posts');
}



/**
 * Check if Website Audit feature is enabled.
 *
 * @since 2.3.0
 *
 * @return bool
 */
function ggrwa_is_audit_enabled() {
    $settings = get_option( 'ggrwa_settings', array() );
    return ! empty( $settings['enabled'] );
}



/**
 * Return all public post types available for per-page analysis.
 * Excludes attachments. Returns array of slug => label.
 *
 * @since 3.0.0
 * @return array
 */
function ggrwa_get_analyzable_post_types() {
    $types  = get_post_types( [ 'public' => true ], 'objects' );
    $result = [];
    foreach ( $types as $slug => $obj ) {
        if ( $slug === 'attachment' ) continue;
        $result[ $slug ] = $obj->labels->singular_name;
    }
    return $result;
}
