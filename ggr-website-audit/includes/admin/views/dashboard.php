<?php

/**
 * Admin Dashboard view for GGR Website Audit.
 *
 * Acts as the primary scan center where administrators
 * can run a website audit and view high-level results.
 *
 * @package GGR_Website_Audit
 * @since   2.3.0
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Determine current site information.
 */
$ggrwa_site_url  = get_site_url();
$ggrwa_site_name = get_bloginfo('name');



/**
 * Fetch last audit data (placeholder).
 * Engine integration will replace this.
 */
$ggrwa_last_audit_time = get_option('ggrwa_last_audit_time', false);


/**
 * -------------------------------------------------------
 * Timezone Detection
 * -------------------------------------------------------
 * Determine whether the site is using the default UTC timezone.
 *
 * Why this matters:
 * - UTC can cause confusing timestamps for non-technical users
 * - We recommend setting a local timezone (e.g., Asia/Kolkata)
 *
 * Logic:
 * - If no timezone string is set AND GMT offset is 0 → it's UTC
 */
$ggrwa_timezone_string = get_option('timezone_string'); // e.g., 'Asia/Kolkata'
$ggrwa_gmt_offset      = get_option('gmt_offset');      // e.g., 5.5 for IST

$ggrwa_is_utc = empty($ggrwa_timezone_string) && (float) $ggrwa_gmt_offset === 0.0;

$ggrwa_auto_scan_url = '';


if (isset($_GET['scan_url'], $_GET['ggrwa_nonce'])) {

    $ggrwa_nonce = sanitize_text_field(wp_unslash($_GET['ggrwa_nonce']));
if (wp_verify_nonce($ggrwa_nonce, 'ggrwa_scan_action')) {
    $ggrwa_raw_url = isset($_GET['scan_url'])
        ? sanitize_text_field(wp_unslash($_GET['scan_url']))
        : '';
    $ggrwa_decoded_url = urldecode($ggrwa_raw_url);
    $ggrwa_auto_scan_url = esc_url_raw($ggrwa_decoded_url);
}
}
?>

<div class="wrap ggr-admin ggr-dashboard">
    <?php if ($ggrwa_is_utc && !get_user_meta(get_current_user_id(), 'ggrwa_hide_timezone_notice', true)) : ?>
        <div class="ggr-timezone-notice ggr-attention" id="ggr-timezone-notice">

            <span class="ggr-notice-text">
                <span class="ggr-badge">Important</span>
                <p>⚠ Your timezone is set to <strong>UTC</strong>. This may cause incorrect audit timestamps.</p>
                <a href="<?php echo esc_url(admin_url('options-general.php')); ?>">
                    Fix it in Settings →
                </a>
            </span>

            <button class="ggr-notice-close" type="button">×</button>

        </div>
    <?php endif; ?>

    <h1 class="wp-heading-inline">
        <?php esc_html_e('GGR Website Audit', 'ggr-website-audit'); ?>
    </h1>

    <p class="description">
        <?php esc_html_e(
            'Run an intelligence-based audit of your website to identify critical issues and priorities.',
            'ggr-website-audit'
        ); ?>
    </p>

    <hr class="wp-header-end" />

    <!-- Website Information -->
    <div class="ggr-card ggr-site-info">
        <h2><?php esc_html_e('Website Information', 'ggr-website-audit'); ?></h2>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Site Name', 'ggr-website-audit'); ?></th>
                <td><?php echo esc_html($ggrwa_site_name); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Site URL', 'ggr-website-audit'); ?></th>
                <td>
                    <code><?php echo esc_url($ggrwa_site_url); ?></code>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Last Audit', 'ggr-website-audit'); ?></th>
                <td>
                    <?php
                    if ($ggrwa_last_audit_time) {
                        $ggrwa_time = date_i18n(
                            get_option('date_format') . ' ' . get_option('time_format'),
                            $ggrwa_last_audit_time
                        );

                        echo '<span class="ggr-last-audit">✔ ' . esc_html($ggrwa_time) . '</span>';
                    } else {
                        esc_html_e('No audit has been run yet.', 'ggr-website-audit');
                    }
                    ?>
                </td>
            </tr>
        </table>
    </div>

    <!-- Run Audit Section -->
    <div class="ggr-card ggr-run-audit">
        <h2><?php esc_html_e('Run Website Audit', 'ggr-website-audit'); ?></h2>
        <p>
            <?php esc_html_e(
                'Enter a page URL to analyze. By default, your homepage will be audited.',
                'ggr-website-audit'
            ); ?>
        </p>

        <input
            type="url"
            id="ggr-audit-url"
            class="regular-text"
            value="<?php echo esc_url($ggrwa_auto_scan_url ? $ggrwa_auto_scan_url : home_url('/')); ?>"
            placeholder="https://example.com/" />

        <p>
            <?php esc_html_e(
                'Click the button below to analyze your website and generate a priority-based audit report.',
                'ggr-website-audit'
            ); ?>
        </p>

        <button
            type="button"
            class="button button-primary button-large ggr-run-audit-btn">
            <?php esc_html_e('Run Website Audit', 'ggr-website-audit'); ?>
        </button>



        <span class="ggr-audit-status" style="margin-left:10px;"></span>
    </div>

    <!-- Website Health Score -->
    <div class="ggr-card ggr-analyzer-header" style="display:none;">

        <div class="ggr-analyzer-left">
            <div class="ggr-score-pill">
                <div class="ggr-score-inner">
                <div class="ggr-score-liquid">
                    <div class="ggr-score-liquid-fill"></div>
                </div>
               </div>
                <span class="ggr-score-number">--</span>
                <small><?php esc_html_e('Health Score', 'ggr-website-audit'); ?></small>
            </div>
        </div>

        <div class="ggr-scan-progress" style="display:none;">
            <div class="ggr-progress-bar">
                <span></span>
            </div>
            <small class="ggr-progress-text">
                <?php esc_html_e('Scanning website signals…', 'ggr-website-audit'); ?>
            </small>
        </div>

        <div class="ggr-analyzer-right">
            <h2 class="ggr-analyzer-status">
                <?php esc_html_e('Analyzing website health…', 'ggr-website-audit'); ?>
            </h2>

            <p class="ggr-analyzer-summary"></p>
        </div>

    </div>

    <!-- Score Breakdown Invite -->
    <div class="ggr-score-invite" style="display:none;">
        <p>
            <?php esc_html_e(
                'We found detailed insights behind this score.',
                'ggr-website-audit'
            ); ?>
        </p>

        <a href="#" class="button button-secondary ggr-toggle-score-details">
            <?php esc_html_e('View score breakdown', 'ggr-website-audit'); ?>
        </a>
    </div>


    <!-- Score Breakdown Panel -->
    <div class="ggr-card ggr-score-breakdown" style="display:none;">

        <h3><?php esc_html_e('How this score was calculated', 'ggr-website-audit'); ?></h3>

        <div class="ggr-score-sections">

            <!-- Passed checks -->
            <div class="ggr-score-section ggr-score-pass">
                <h4>✔ <?php esc_html_e('Working well', 'ggr-website-audit'); ?></h4>
                <ul class="ggr-score-pass-list">
                    <!-- JS will inject -->
                </ul>
            </div>

            <!-- Improvements -->
            <div class="ggr-score-section ggr-score-fix">
                <h4>⚠ <?php esc_html_e('Needs improvement', 'ggr-website-audit'); ?></h4>
                <ul class="ggr-score-fix-list">
                    <!-- JS will inject -->
                </ul>
            </div>

        </div>

        <p class="ggr-score-note">
            <?php esc_html_e(
                'This audit analyzes the selected page using real technical and on-page checks.',
                'ggr-website-audit'
            ); ?>
        </p>

    </div>



    <!-- Priority Issues -->
    <div class="ggr-card ggr-priority-card" style="display:none;">
        <h2><?php esc_html_e('Page Health Overview', 'ggr-website-audit'); ?></h2>

        <ul class="ggr-priority-list"></ul>
    </div>


    <!-- Quick Insights -->
    <div class="ggr-card ggr-stats-card" style="display:none;">
        <h2><?php esc_html_e('Quick Insights', 'ggr-website-audit'); ?></h2>

        <div class="ggr-stats">

            <div class="ggr-stat-item">
                <strong class="ggr-stat-pages">--</strong>
                <span class="ggr-stat-label"><?php esc_html_e('Pages', 'ggr-website-audit'); ?></span>
            </div>

            <div class="ggr-stat-item">
                <strong class="ggr-stat-posts">--</strong>
                <span class="ggr-stat-label"><?php esc_html_e('Posts', 'ggr-website-audit'); ?></span>
            </div>

            <div class="ggr-stat-item">
                <strong class="ggr-stat-critical">--</strong>
                <span class="ggr-stat-label"><?php esc_html_e('Critical Issues', 'ggr-website-audit'); ?></span>
            </div>

            <div class="ggr-stat-item">
                <strong class="ggr-stat-warnings">--</strong>
                <span class="ggr-stat-label"><?php esc_html_e('Warnings', 'ggr-website-audit'); ?></span>
            </div>

            <div class="ggr-stat-item">
                <strong class="ggr-stat-plugins">--</strong>
                <span class="ggr-stat-label"><?php esc_html_e('Plugins', 'ggr-website-audit'); ?></span>
            </div>

            <div class="ggr-stat-item">
                <strong class="ggr-stat-theme">--</strong>
                <span class="ggr-stat-label"><?php esc_html_e('Theme', 'ggr-website-audit'); ?></span>
            </div>

        </div>

        <p class="ggr-stats-note">
            <?php esc_html_e(
                'Hover over the numbers to see detailed breakdowns.',
                'ggr-website-audit'
            ); ?>
        </p>
    </div>
</div>