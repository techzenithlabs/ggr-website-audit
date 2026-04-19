<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Admin Controller (Modular Loader)
 */
class GGRWA_Admin
{

    public function __construct()
    {
        //  parent menu first
        add_action('admin_menu', [$this, 'register_menu'], 5);

        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_bar_menu', [$this, 'add_admin_bar_audit_button'], 100);
    }

    /**
     * -------------------------------------------------
     * MAIN MENU
     * -------------------------------------------------
     */
    public function register_menu()
    {
        add_menu_page(
            'GGR Website Audit',
            'GGR Website Audit',
            'manage_options',
            'ggrwa-audit-dashboard',
            [$this, 'render_dashboard'],
            'dashicons-chart-area',
            58
        );

        add_submenu_page(
            'ggrwa-audit-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'ggrwa-audit-dashboard',
            [$this, 'render_dashboard']
        );

        // Load modules AFTER parent menu
        $this->load_modules();
    }

    /**
     * -------------------------------------------------
     * MAIN PAGE (Required for submenu hover)
     * -------------------------------------------------
     */      

    public function render_dashboard() {
        require GGRWA_PLUGIN_PATH . 'includes/admin/views/dashboard.php';
    }

    /**
     * -------------------------------------------------
     * LOAD MODULES
     * -------------------------------------------------
     */
    private function load_modules()
    {
        $modules = [        
            'seo-audit',
            'content-analyzer',
            'conversion-audit',
            'settings'
        ];

        foreach ($modules as $module) {

            $file = GGRWA_PLUGIN_PATH . "includes/modules/{$module}/class-{$module}.php";

            if (file_exists($file)) {

                require_once $file;

                // Convert slug → class name
                $class = 'GGRWA_' . str_replace(' ', '_', ucwords(str_replace('-', ' ', $module)));

                if (class_exists($class)) {
                    new $class(); // each module handles its own menu
                }
            }
        }
    }

    /**
     * -------------------------------------------------
     * ADMIN BAR BUTTON
     * -------------------------------------------------
     */
    public function add_admin_bar_audit_button($wp_admin_bar)
    {
        if (! is_admin()) return;

        global $pagenow, $post;

        if ($pagenow !== 'post.php' || ! $post) return;

        $score = get_post_meta($post->ID, '_ggr_seo_score', true);

        $title = ($score)
            ? "SEO Score: {$score}"
            : "⚡ Analyze Page";

        $wp_admin_bar->add_node([
            'id'    => 'ggrwa-scan-page',
            'title' => $title,
            'href'  => 'javascript:void(0);',
        ]);
    }

    /**
     * -------------------------------------------------
     * ENQUEUE ADMIN ASSETS
     * -------------------------------------------------
     */
    public function enqueue_admin_assets($hook)
    {
        // Utils
        wp_enqueue_script(
            'ggr-utils',
            GGRWA_PLUGIN_URL . 'assets/js/ggr-utils.js',
            ['jquery'],
            GGRWA_VERSION,
            true
        );

        // Analyzer
        wp_enqueue_script(
            'ggr-analyzer',
            GGRWA_PLUGIN_URL . 'assets/js/ggr-analyzer.js',
            ['ggr-utils'],
            GGRWA_VERSION,
            true
        );

        // Engine
        wp_enqueue_script(
            'ggr-engine',
            GGRWA_PLUGIN_URL . 'assets/js/ggr-engine.js',
            ['ggr-analyzer'],
            GGRWA_VERSION,
            true
        );

        // UI
        wp_enqueue_script(
            'ggr-ui',
            GGRWA_PLUGIN_URL . 'assets/js/ggr-ui.js',
            ['ggr-engine'],
            GGRWA_VERSION,
            true
        );

        // Controller
        wp_enqueue_script(
            'ggr-controller',
            GGRWA_PLUGIN_URL . 'assets/js/ggr-controller.js',
            ['ggr-ui'],
            GGRWA_VERSION,
            true
        );

        // Entry
        wp_enqueue_script(
            'ggr-admin',
            GGRWA_PLUGIN_URL . 'assets/js/admin.js',
            ['ggr-controller'],
            GGRWA_VERSION,
            true
        );

        /**
         * POST ID FIX
         */
        $post_id = 0;

        if (isset($_GET['post'])) {
            $post_id = intval($_GET['post']);
        } elseif (isset($_GET['post_id'])) {
            $post_id = intval($_GET['post_id']);
        }

        /**
         * LOCALIZE
         */
        wp_localize_script(
            'ggr-admin',
            'ggrwa_admin',
            [
                'ajaxUrl'    => admin_url('admin-ajax.php'),
                'auditNonce' => wp_create_nonce('ggrwa_run_audit'),
                'postId'     => $post_id,
                'timestamp'  => time(),
            ]
        );

        /**
         * LOAD CSS ONLY ON GGR PAGES
         */
        if (strpos($hook, 'ggrwa') !== false) {
            wp_enqueue_style(
                'ggr-admin-css',
                GGRWA_PLUGIN_URL . 'assets/css/admin.css',
                [],
                GGRWA_VERSION
            );
        }
    }
}