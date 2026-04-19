<?php

if (! defined('ABSPATH')) exit;

class GGRWA_SEO_Audit {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Register submenu
     */
    public function register_menu() {
        add_submenu_page(
            'ggrwa-audit-dashboard',
            'SEO Audit',
            'SEO Audit',
            'manage_options',
            'ggrwa-seo-audit',
            [$this, 'render']
        );
    }

    /**
     * Render UI container only
     */
    public function render() {
        require GGRWA_PLUGIN_PATH . 'includes/modules/seo-audit/view.php';
    }

    /**
     * Load assets only on this page
     */
    public function enqueue_assets($hook) {

        if ($hook !== 'ggr-website-audit_page_ggrwa-seo-audit') return;

        wp_enqueue_style(
            'ggr-seo-audit-css',
            GGRWA_PLUGIN_URL . 'includes/modules/seo-audit/assets/seo-audit.css',
            [],
            GGRWA_VERSION
        );

        wp_enqueue_script(
            'ggr-seo-audit-js',
            GGRWA_PLUGIN_URL . 'includes/modules/seo-audit/assets/seo-audit.js',
            ['ggr-controller'], 
            GGRWA_VERSION,
            true
        );
    }
}