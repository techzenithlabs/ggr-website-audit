<?php
if (! defined('ABSPATH')) {
    exit;
}

$score = empty($keyword)   ? 0  : $analysis['score'];
$checks = $analysis['checks'];
?>

<div class="ggr-seo-intelligence">

    <div class="ggr-seo-toggle">

        <div class="ggr-seo-toggle-left">

            <span class="ggr-icon">🎯</span>

            <div>
                <strong>GGR SEO Intelligence</strong>
                <div class="ggr-subtitle">
                    Focus Keyword & SEO Analysis
                </div>
            </div>

        </div>

        <div class="ggr-seo-toggle-right">

            <span class="ggr-score">
                <?php echo esc_html($score); ?>/100
            </span>

            <span class="ggr-arrow">
                ▼
            </span>

        </div>

    </div>

    <div class="ggr-seo-content">

        <p>
            <label for="_ggrwa_focus_keyword">
                <strong>Focus Keyword</strong>
            </label>
        </p>

        <input
            type="text"
            id="_ggrwa_focus_keyword"
            name="_ggrwa_focus_keyword"
            value="<?php echo esc_attr($keyword); ?>"
            placeholder="Example: Angular SEO"
            class="ggr-focus-input" />

        <input
            type="hidden"
            id="ggr_post_id"
            value="<?php echo esc_attr($post->ID); ?>" />

        <div class="ggr-save-status"></div>    

        <div class="
	ggr-keyword-status
	<?php echo empty($keyword) ? 'ggr-status-error' : 'ggr-status-success'; ?>
">

            <?php if (empty($keyword)) : ?>

                ⚠ No focus keyword configured

            <?php else : ?>

                ✓ Focus keyword configured

            <?php endif; ?>

        </div>

        <div class="ggr-details-panel">

            <div class="ggr-check-grid">

                <!-- Focus Keyword -->
                <div class="ggr-check-item <?php echo empty($keyword) ? 'neutral' : ($checks['keyword'] ? 'success' : 'error'); ?>">

                    <?php
                    echo empty($keyword)
                        ? '○'
                        : ($checks['keyword'] ? '✓' : '✗');
                    ?>

                    Focus Keyword

                </div>

                <!-- Title -->
                <div class="ggr-check-item <?php echo empty($keyword) ? 'neutral' : ($checks['title'] ? 'success' : 'warning'); ?>">

                    <?php
                    echo empty($keyword)
                        ? '○'
                        : ($checks['title'] ? '✓' : '⚠');
                    ?>

                    Keyword in Title

                </div>

                <!-- URL -->
                <div class="ggr-check-item <?php echo empty($keyword) ? 'neutral' : ($checks['url'] ? 'success' : 'warning'); ?>">

                    <?php
                    echo empty($keyword)
                        ? '○'
                        : ($checks['url'] ? '✓' : '⚠');
                    ?>

                    Keyword in URL

                </div>

                <!-- Content -->
                <div class="ggr-check-item <?php echo empty($keyword) ? 'neutral' : ($checks['content'] ? 'success' : 'warning'); ?>">

                    <?php
                    echo empty($keyword)
                        ? '○'
                        : ($checks['content'] ? '✓' : '⚠');
                    ?>

                    Keyword in Content

                </div>

                <!-- Meta -->
                <div class="ggr-check-item <?php echo empty($keyword) ? 'neutral' : ($checks['meta'] ? 'success' : 'warning'); ?>">

                    <?php
                    echo empty($keyword)
                        ? '○'
                        : ($checks['meta'] ? '✓' : '⚠');
                    ?>

                    Meta Description

                </div>

            </div>

        </div>

    </div>

</div>