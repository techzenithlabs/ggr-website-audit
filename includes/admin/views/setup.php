<?php
if (! defined('ABSPATH')) exit;
?>

<div class="ggr-setup-container">

    <div class="ggr-setup-card">

        <h1>👋 Welcome to GGR SEO Audit</h1>
        <p class="subtitle">Let's get your SEO assistant ready in 30 seconds.</p>

        <!-- Image -->
        <div class="ggr-setup-image">
          <img src="<?php echo esc_url(GGRWA_PLUGIN_URL . 'assets/images/ggr-setup-illustration.png'); ?>" alt="GGR SEO Audit Setup Illustration">
        </div>


        <ul class="ggr-features">
            <li>Automated SEO Audit</li>
            <li>Actionable Insights</li>
            <li>Improve Rankings Faster</li>
        </ul>

        <!-- Buttons -->
        <div class="ggr-actions">
            <a href="#" class="button button-primary ggr-start-btn">
                Start Setup ⚡
            </a>

            <a href="<?php echo esc_url(admin_url('admin.php?page=ggrwa-audit-dashboard')); ?>" class="ggr-skip-link">
                Skip for now →
            </a>
        </div>

    </div>

</div>