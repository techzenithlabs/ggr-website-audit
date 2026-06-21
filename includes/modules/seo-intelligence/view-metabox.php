<?php
if (! defined('ABSPATH')) {
    exit;
}

$score  = empty($keyword) ? 0 : $analysis['score'];
$checks = $analysis['checks'];
?>

<div class="ggr-seo-intelligence">

    <!-- Header -->

    <div class="ggr-seo-toggle">

        <div class="ggr-seo-toggle-left">

            <span class="ggr-icon">🎯</span>

            <div>

                <strong>GGR SEO Intelligence</strong>

                <div class="ggr-subtitle">
                    Real-Time SEO Analysis & Content Intelligence
                </div>

            </div>

        </div>

        <div class="ggr-seo-toggle-right">

            <span id="ggr-live-score" class="ggr-score">
                <?php echo esc_html($score); ?>/100
            </span>

            <span class="ggr-arrow">▼</span>

        </div>

    </div>

    <div class="ggr-seo-content">

        <!-- =========================================
             Focus Keyword Card
        ========================================== -->

        <div class="ggr-card">

            <div class="ggr-card-header">
                Focus Keyword
            </div>

            <div class="ggr-card-body">

                <input
                    type="text"
                    id="_ggrwa_focus_keyword"
                    name="_ggrwa_focus_keyword"
                    value="<?php echo esc_attr($keyword); ?>"
                    placeholder="Example: Affiliate Disclosure"
                    class="ggr-focus-input" />

                <input
                    type="hidden"
                    id="ggr_post_id"
                    value="<?php echo esc_attr($post->ID); ?>" />

                <div class="ggr-save-status"></div>

                <div
                    id="ggr-keyword-status"
                    class="ggr-keyword-status <?php echo empty($keyword) ? 'ggr-status-error' : 'ggr-status-success'; ?>">

                    <?php if (empty($keyword)) : ?>

                        ⚠ No focus keyword configured

                    <?php else : ?>

                        ✓ Focus keyword configured

                    <?php endif; ?>

                </div>

            </div>

        </div>

        <!-- =========================================
       Quick Fixes
    ========================================== -->

        <div class="ggr-card">

            <div class="ggr-card-header">
                Quick Fixes
            </div>

            <div class="ggr-card-body">

                <div id="ggr-quick-fixes" class="ggr-quick-fixes">

                    <div class="ggr-opportunity-card">

                        <div class="ggr-opportunity-header">

                            <div class="ggr-opportunity-title">
                                ⚡ <span id="ggr-opportunity-count">4</span>
                                SEO Opportunities Found
                            </div>

                            <div class="ggr-opportunity-badge">
                                ACTION REQUIRED
                            </div>

                        </div>

                        <div class="ggr-opportunity-desc">
                            Fix the issues below to improve rankings,
                            increase visibility and unlock a higher SEO score.
                        </div>

                        <ul class="ggr-opportunity-list">

                            <li id="ggr-fix-title">
                                ❌ Add focus keyword to title
                            </li>

                            <li id="ggr-fix-url">
                                ❌ Add focus keyword to URL
                            </li>

                            <li id="ggr-fix-content">
                                ❌ Add focus keyword to content
                            </li>

                            <li id="ggr-fix-meta">
                                ❌ Add focus keyword to meta description
                            </li>

                        </ul>

                        <div class="ggr-opportunity-footer">

                            <div class="ggr-opportunity-current-score">
                                Current Score:
                                <strong id="ggr-current-score">
                                    <?php echo esc_html($score); ?>/100
                                </strong>
                            </div>

                            <div class="ggr-opportunity-gain">

                                <span class="ggr-opportunity-gain-label">
                                    Potential Gain
                                </span>

                                <span
                                    id="ggr-potential-score"
                                    class="ggr-score-pill-success">
                                    +80
                                </span>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <!-- =========================================
             SEO Foundation
        ========================================== -->

        <div class="ggr-card">

            <div class="ggr-card-header">

                SEO Foundation

                <span
                    id="ggr-foundation-count"
                    class="ggr-section-badge">
                    0/5
                </span>

            </div>

            <div class="ggr-card-body">

                <div class="ggr-check-grid">

                    <div id="ggr-check-keyword" class="ggr-check-item neutral">
                        ○ Focus Keyword
                    </div>

                    <div id="ggr-check-title" class="ggr-check-item neutral">
                        ○ Keyword in Title
                    </div>

                    <div id="ggr-check-url" class="ggr-check-item neutral">
                        ○ Keyword in URL
                    </div>

                    <div id="ggr-check-content" class="ggr-check-item neutral">
                        ○ Keyword in Content
                    </div>

                    <div id="ggr-check-meta" class="ggr-check-item neutral">
                        ○ Meta Description
                    </div>

                </div>

            </div>

        </div>

        <!-- =========================================
             Content Analysis
        ========================================== -->

        <div class="ggr-card">

            <div class="ggr-card-header">
                Content Analysis
            </div>

            <div class="ggr-card-body">

                <div class="ggr-metric-grid">

                    <div class="ggr-metric-card">

                        <span class="ggr-metric-value" id="ggr-word-count">
                            0
                        </span>

                        <span class="ggr-metric-label">
                            Word Count
                        </span>

                    </div>

                    <div class="ggr-metric-card">

                        <span class="ggr-metric-value" id="ggr-keyword-occurrences">
                            0
                        </span>

                        <span class="ggr-metric-label">
                            Keyword Occurrences
                        </span>

                    </div>

                    <div class="ggr-metric-card">

                        <span class="ggr-metric-value" id="ggr-keyword-density">
                            0
                        </span>

                        <span class="ggr-metric-label">
                            Keyword Density
                        </span>

                    </div>

                </div>

            </div>

        </div>

        <!-- =========================================
             Link Analysis
        ========================================== -->

        <div class="ggr-card">

            <div class="ggr-card-header">
                Link Analysis
            </div>

            <div class="ggr-card-body">

                <div class="ggr-metric-grid">

                    <div class="ggr-metric-card">

                        <span class="ggr-metric-value" id="ggr-internal-links">
                            0
                        </span>

                        <span class="ggr-metric-label">
                            Internal Links
                        </span>

                    </div>

                    <div class="ggr-metric-card">

                        <span class="ggr-metric-value" id="ggr-external-links">
                            0
                        </span>

                        <span class="ggr-metric-label">
                            External Links
                        </span>

                    </div>

                </div>

            </div>

        </div>

        <!-- =========================================
        Media SEO
        ========================================== -->

        <div class="ggr-card">

            <div class="ggr-card-header">
                Media SEO
            </div>

            <div class="ggr-card-body">

                <div class="ggr-metric-grid">

                    <div class="ggr-metric-card">

                        <span class="ggr-metric-value" id="ggr-featured-image">
                            0
                        </span>

                        <span class="ggr-metric-label">
                            Featured Image
                        </span>

                    </div>

                    <div class="ggr-metric-card">

                        <span class="ggr-metric-value" id="ggr-images-count">
                            0
                        </span>

                        <span class="ggr-metric-label">
                            Images Found
                        </span>

                    </div>

                    <div class="ggr-metric-card">

                        <span class="ggr-metric-value" id="ggr-image-alt">
                            0
                        </span>

                        <span class="ggr-metric-label">
                            Missing ALT Tags
                        </span>

                    </div>

                </div>

            </div>

        </div>

        <!-- =========================================
             Content Structure
        ========================================== -->

        <div class="ggr-card">

            <div class="ggr-card-header">
                Content Structure
            </div>

            <div class="ggr-card-body">

                <div class="ggr-metric-grid">

                    <div class="ggr-metric-card">

                        <span class="ggr-metric-value" id="ggr-h2-count">
                            0
                        </span>

                        <span class="ggr-metric-label">
                            H2 Headings
                        </span>

                    </div>

                    <div class="ggr-metric-card">

                        <span class="ggr-metric-value" id="ggr-h3-count">
                            0
                        </span>

                        <span class="ggr-metric-label">
                            H3 Headings
                        </span>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>