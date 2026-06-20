<?php

/**
 * SEO Intelligence bootstrap.
 *
 * @package GGR_Website_Audit
 */

if (! defined('ABSPATH')) {
    exit;
}

class GGRWA_SEO_Intelligence
{

    public function __construct()
    {

        require_once GGRWA_PLUGIN_PATH .
            'includes/modules/seo-intelligence/class-seo-intelligence-metabox.php';

        require_once
            GGRWA_PLUGIN_PATH .
            'includes/modules/seo-intelligence/class-seo-intelligence-analyzer.php';

        new GGRWA_SEO_Intelligence_Metabox();

        add_action(
            'admin_enqueue_scripts',
            array($this, 'enqueue_assets')
        );

        add_action(
            'wp_ajax_ggr_live_keyword_analysis',
            array($this, 'live_keyword_analysis')
        );
    }


    public function enqueue_assets()
    {

        $screen = get_current_screen();

        if (
            ! $screen ||
            'post' !== $screen->base
        ) {
            return;
        }

        wp_enqueue_style(
            'ggr-seo-intelligence',
            GGRWA_PLUGIN_URL .
                'includes/modules/seo-intelligence/assets/seo-intelligence.css',
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'ggr-seo-intelligence',
            GGRWA_PLUGIN_URL .
                'includes/modules/seo-intelligence/assets/seo-intelligence.js',
            array('jquery'),
            '1.0.0',
            true
        );
    }

    public function live_keyword_analysis()
    {

        $post_id = absint($_POST['post_id'] ?? 0);
        $keyword = sanitize_text_field(
            $_POST['keyword'] ?? ''
        );

        $analyzer = new GGRWA_SEO_Intelligence_Analyzer();

        $result = $analyzer->analyze_live(
            $post_id,
            $keyword
        );

        wp_send_json_success(
            $result
        );
    }
}

new GGRWA_SEO_Intelligence();
