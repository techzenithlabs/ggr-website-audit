<?php
if (! defined('ABSPATH')) {
    exit;
}
global $wp_rewrite;
$permalink_structure = $wp_rewrite->permalink_structure;
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

            <div class="ggr-card-header ggr-section-toggle">
                <span>Focus Keyword</span>
                <span class="ggr-section-arrow">▼</span>
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

                <input
                    type="hidden"
                    id="ggr_permalink_structure"
                    value="<?php echo esc_attr($permalink_structure); ?>">

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

            <div class="ggr-card-header ggr-section-toggle">
                <span>Quick Fixes</span>
                <span class="ggr-section-arrow">▼</span>
            </div>

            <div class="ggr-card-body">

                <div id="ggr-quick-fixes" class="ggr-quick-fixes">

                    <div class="ggr-opportunity-card">

                        <div class="ggr-opportunity-header">

                            <div class="ggr-opportunity-title">
                                ⚡ <span id="ggr-opportunity-count">4</span>
                                SEO Opportunities Found
                            </div>

                            <div class="ggr-opportunity-badge" id="ggr-opportunity-badge">
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

                            <li id="ggr-fix-meta-title">
                                ❌ Add focus keyword to meta title
                            </li>

                            <li id="ggr-fix-meta-description">
                                ❌ Add focus keyword to meta description
                            </li>

                            <li id="ggr-fix-featured-image">
                                ❌ Add Featured Image
                            </li>

                            <li id="ggr-fix-category">
                                ❌ Assign a relevant category
                            </li>

                        </ul>
                        <div
                            id="ggr-permalink-warning"
                            class="ggr-permalink-warning"
                            style="display:none;">
                        </div>

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

            <div class="ggr-card-header ggr-section-toggle">
                <span> SEO Foundation</span>

                <div class="ggr-section-header-right">

                    <span
                        id="ggr-foundation-count"
                        class="ggr-section-badge">
                        0/8
                    </span>

                    <span class="ggr-section-arrow">
                        ▼
                    </span>

                </div>

            </div>

            <div class="ggr-card-body">

                <div class="ggr-check-grid">

                    <div id="ggr-check-keyword" class="ggr-check-item neutral">

                        <span class="ggr-check-label">
                            ○ Focus Keyword Optimized
                        </span>
                        <span
                            id="ggr-keyword-toggle"
                            class="ggr-why-link">
                            Why?
                        </span>


                    </div>
                    <div
                        id="ggr-keyword-details"
                        class="ggr-keyword-details">
                    </div>



                    <div id="ggr-check-title" class="ggr-check-item neutral">

                        <span class="ggr-check-label">
                            ○ Keyword in Title
                        </span>

                    </div>

                    <div id="ggr-check-url" class="ggr-check-item neutral">

                        <span class="ggr-check-label">
                            ○ Keyword in URL
                        </span>

                    </div>

                    <div id="ggr-check-content" class="ggr-check-item neutral">

                        <span class="ggr-check-label">
                            ○ Keyword in Content
                        </span>

                    </div>

                    <div id="ggr-check-meta-title" class="ggr-check-item neutral">

                        <span class="ggr-check-label">
                            ○ Meta Title
                        </span>

                    </div>

                    <div id="ggr-check-meta-description" class="ggr-check-item neutral">

                        <span class="ggr-check-label">
                            ○ Meta Description
                        </span>

                    </div>

                    <div id="ggr-check-featured-image" class="ggr-check-item neutral">

                        <span class="ggr-check-label">
                            ○ Featured Image
                        </span>

                    </div>

                    <div id="ggr-check-category" class="ggr-check-item neutral">

                        <span class="ggr-check-label">
                            ○ Category Assigned
                        </span>

                    </div>

                </div>

            </div>

        </div>

        <div class="ggr-card">

            <div class="ggr-card-header ggr-section-toggle">
                <span> SEO Snippet Optimization</span>
                <div class="ggr-section-header-right">

                    <span
                        id="ggr-snippet-score"
                        class="ggr-section-badge">
                        0/2
                    </span>

                    <span class="ggr-section-arrow">
                        ▼
                    </span>

                </div>

            </div>

            <div class="ggr-card-body">

                <!-- Meta Title -->

                <div class="ggr-form-group">

                    <label class="ggr-label">

                        Meta Title

                    </label>

                    <input
                        type="text"
                        id="ggr-meta-title"
                        class="ggr-input" name="_ggrwa_meta_title" value="<?php echo esc_attr(
                                                                                get_post_meta(
                                                                                    $post->ID,
                                                                                    '_ggrwa_meta_title',
                                                                                    true
                                                                                )
                                                                            ); ?>"
                        placeholder="Enter SEO Meta Title">

                    <div id="ggr-meta-title-save-status" class="ggr-save-status"></div>

                    <div class="ggr-input-meta">
                        <span
                            id="ggr-meta-title-status"
                            class="ggr-snippet-status warning">
                            Too Short
                        </span>

                    </div>

                </div>

                <!-- Meta Description -->

                <div class="ggr-form-group">

                    <label class="ggr-label">

                        Meta Description

                    </label>

                    <textarea
                        id="ggr-meta-description"
                        class="ggr-textarea"
                        name="_ggrwa_meta_description"
                        rows="5"
                        placeholder="Write a compelling meta description..."><?php
                                                                                echo esc_textarea(
                                                                                    get_post_meta(
                                                                                        $post->ID,
                                                                                        '_ggrwa_meta_description',
                                                                                        true
                                                                                    )
                                                                                );
                                                                                ?></textarea>
                    <div id="ggr-meta-description-save-status" class="ggr-save-status"></div>

                    <div class="ggr-input-meta">

                        <span
                            id="ggr-meta-description-status"
                            class="ggr-snippet-status warning">
                            Too Short
                        </span>

                    </div>

                </div>

                <!-- Google Preview -->

                <div class="ggr-serp-preview">

                    <div
                        id="ggr-serp-url"
                        class="ggr-serp-url">

                        <?php echo esc_url(get_permalink($post->ID)); ?>

                    </div>

                    <div
                        id="ggr-serp-title"
                        class="ggr-serp-title">

                        Your SEO Title Preview

                    </div>

                    <div
                        id="ggr-serp-description"
                        class="ggr-serp-description">

                        Your meta description preview will appear here.

                    </div>

                </div>

            </div>

        </div>

        <!-- =========================================
             Content Analysis
        ========================================== -->

        <div class="ggr-card">

            <div class="ggr-card-header ggr-section-toggle">
                <span> Content Analysis</span>
                <span class="ggr-section-arrow">▼</span>
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

            <div class="ggr-card-header ggr-section-toggle">
                <span>Link Analysis</span>
                <span class="ggr-section-arrow">▼</span>
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

            <div class="ggr-card-header ggr-section-toggle">
                <span>Media SEO</span>
                <span class="ggr-section-arrow">▼</span>
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

            <div class="ggr-card-header ggr-section-toggle">
                <span>Content Structure</span>
                <span class="ggr-section-arrow">▼</span>
            </div>

            <div class="ggr-card-body">

                <div class="ggr-metric-grid">
                    <div class="ggr-metric-card">

                        <span class="ggr-metric-value" id="ggr-h1-count">
                            0
                        </span>

                        <span class="ggr-metric-label">
                            H1 Headings
                        </span>

                    </div>

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