<?php

/**
 * Admin controller for GGR Website Audit.
 *
 * Handles registration of admin menus and rendering
 * of admin-facing pages (Dashboard, Settings).
 *
 * @package GGR_Website_Audit
 * @since   2.3.0
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class GGRWA_Admin
 *
 * Responsible for:
 * - Registering admin menu pages
 * - Rendering dashboard and settings views
 *
 * @since 2.3.0
 */
class GGRWA_Admin
{

    /**
     * Constructor.
     *
     * Registers required WordPress hooks.
     *
     * @since 2.3.0
     */
    public function __construct()
    {

        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'register_settings'));

        // Handle controlled Edit Page redirects.
        add_action('admin_init', array($this, 'handle_edit_redirect'));

        // Admin assets (JS/CSS).
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Settings link on Plugins page.
        add_filter(
            'plugin_action_links_' . plugin_basename(GGRWA_PLUGIN_FILE),
            array($this, 'add_settings_link')
        );

        add_action('admin_bar_menu', array($this, 'add_admin_bar_audit_button'), 100);
    }

    /**
     * Add Settings link on Plugins page.
     *
     * @param array $links Existing plugin links.
     * @return array
     */
    public function add_settings_link($links)
    {

        $settings_url = admin_url('admin.php?page=ggrwa-audit-settings');

        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url($settings_url),
            esc_html__('Settings', 'ggr-website-audit')
        );

        array_unshift($links, $settings_link);

        return $links;
    }

    /**
     * Handle controlled Edit Page redirect.
     *
     * @since 2.4.0
     *
     * @return void
     */
    public function handle_edit_redirect()
    {

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        if ($page !== 'ggrwa-edit-redirect') {
            return;
        }

        $nonce = isset($_GET['_wpnonce'])
            ? sanitize_text_field(wp_unslash($_GET['_wpnonce']))
            : '';

        // Nonce verification.
        if (! wp_verify_nonce($nonce, 'ggrwa_edit_redirect')) {
            wp_die(
                esc_html__('Security check failed. Please try again.', 'ggr-website-audit'),
                esc_html__('Security Error', 'ggr-website-audit'),
                array('response' => 403)
            );
        }

        // Capability check.
        if (! current_user_can('edit_pages')) {
            wp_die(
                esc_html__('You do not have permission to edit pages.', 'ggr-website-audit'),
                esc_html__('Permission Denied', 'ggr-website-audit'),
                array('response' => 403)
            );
        }

        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;

        // Resolve from source URL if post_id is missing.
        if (! $post_id && isset($_GET['source'])) {

            $source     = sanitize_text_field(wp_unslash($_GET['source']));
            $source_url = esc_url_raw($source);
            $home_url   = trailingslashit(home_url());

            if ($source_url) {

                $resolved_id = url_to_postid($source_url);
                if ($resolved_id) {
                    $post_id = (int) $resolved_id;
                }

                if (
                    ! $post_id &&
                    untrailingslashit($source_url) === untrailingslashit($home_url)
                ) {
                    $front_page_id = (int) get_option('page_on_front');
                    if ($front_page_id) {
                        $post_id = $front_page_id;
                    }
                }
            }
        }

        if (! $post_id) {
            wp_die(
                esc_html__('Invalid page reference.', 'ggr-website-audit'),
                esc_html__('Invalid Request', 'ggr-website-audit'),
                array('response' => 400)
            );
        }

        $edit_link = get_edit_post_link($post_id, '');

        if (! $edit_link) {
            wp_die(
                esc_html__('Unable to open the editor for this page.', 'ggr-website-audit'),
                esc_html__('Editor Error', 'ggr-website-audit'),
                array('response' => 500)
            );
        }

        wp_safe_redirect($edit_link);
        exit;
    }

    /**
     * Register admin menu pages.
     *
     * @return void
     */
    public function register_menu()
    {

        add_menu_page(
            esc_html__('GGR Website Audit', 'ggr-website-audit'),
            esc_html__('GGR Website Audit', 'ggr-website-audit'),
            'manage_options',
            'ggrwa-audit-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-chart-area',
            58
        );

        add_submenu_page(
            'ggrwa-audit-dashboard',
            esc_html__('Dashboard', 'ggr-website-audit'),
            esc_html__('Dashboard', 'ggr-website-audit'),
            'manage_options',
            'ggrwa-audit-dashboard',
            array($this, 'render_dashboard')
        );

        add_submenu_page(
            'ggrwa-audit-dashboard',
            esc_html__('Settings', 'ggr-website-audit'),
            esc_html__('Settings', 'ggr-website-audit'),
            'manage_options',
            'ggrwa-audit-settings',
            array($this, 'render_settings')
        );

        add_submenu_page(
            null,
            esc_html__('GGR Setup', 'ggr-website-audit'),
            esc_html__('GGR Setup', 'ggr-website-audit'),
            'manage_options',
            'ggrwa-setup',
            array($this, 'render_setup_page')
        );

        // Hidden redirect handler page.
        add_submenu_page(
            null,
            esc_html__('GGR Edit Redirect', 'ggr-website-audit'),
            esc_html__('GGR Edit Redirect', 'ggr-website-audit'),
            'edit_pages',
            'ggrwa-edit-redirect',
            '__return_null'
        );
    }

    /**
     * Render setup (onboarding) page.
     *
     * Loads the setup view shown after plugin activation.
     * This helps users quickly understand and start using the plugin.
     *
     * @return void
     */

    public function render_setup_page()
    {
        require_once GGRWA_PLUGIN_PATH . 'includes/admin/views/setup.php';
    }

    /**
     * Render dashboard page.
     *
     * @return void
     */
    public function render_dashboard()
    {
        require GGRWA_PLUGIN_PATH . 'includes/admin/views/dashboard.php';
    }

    /**
     * Render settings page.
     *
     * @return void
     */
    public function render_settings()
    {

        require_once GGRWA_PLUGIN_PATH . 'includes/admin/class-ggrwa-settings.php';

        $settings = new GGRWA_Settings();
        $settings->render();
    }

    /**
     * Register plugin settings.
     *
     * @return void
     */
    public function register_settings()
    {

        require_once GGRWA_PLUGIN_PATH . 'includes/admin/class-ggrwa-settings.php';
        GGRWA_Settings::register();
    }

    /**
     * Add scan button to admin bar (top toolbar).
     *
     * @param WP_Admin_Bar $wp_admin_bar Admin bar object.
     * @return void
     */
    public function add_admin_bar_audit_button($wp_admin_bar)
    {
        if (! is_admin()) {
            return;
        }

        global $pagenow, $post;

        if ($pagenow !== 'post.php' || ! $post) {
            return;
        }

        $allowed_types = apply_filters(
            'ggrwa_allowed_post_types',
            get_post_types(['public' => true], 'names')
        );

        $is_supported = in_array($post->post_type, $allowed_types, true);


        $score  = get_post_meta($post->ID, '_ggr_seo_score', true);
        $status = get_post_status($post->ID);


        if ($score === '' || $score === null) {
            $title = '⚡ Analyze Page';
            $class = 'ggr-score-none';
        } else {
            $score = (int) $score;

            if ($score <= 30) {
                $color = '#ef4444';
            } elseif ($score <= 70) {
                $color = '#f59e0b';
            } else {
                $color = '#22c55e';
            }

            $title = 'SEO Score <span class="ggr-admin-bar-score-badge" style="background:' . $color . ';padding:2px 6px;border-radius:3px;color:#fff;">' . $score . '</span>';
            $class = 'ggr-score-has';
        }

        $type_obj = get_post_type_object($post->post_type);
        if ($type_obj && isset($type_obj->labels->singular_name)) {
            $title .= ' <span style="background:#3b82f6;color:#fff;padding:2px 6px;margin-left:5px;font-size:10px;border-radius:3px;">'
                . esc_html($type_obj->labels->singular_name) .
                '</span>';
        }


        if ($status !== 'publish') {
            $title .= ' <span style="background:#6b7280;color:#fff;padding:2px 6px;margin-left:5px;font-size:10px;border-radius:3px;">'
                . ucfirst($status) .
                '</span>';
        }


        $permalink = get_permalink($post->ID);
        if ($permalink && strpos($permalink, '?p=') !== false) {
            $title .= ' <span style="color:#f59e0b;font-size:10px;margin-left:5px;">⚠️ Bad URL</span>';
        }


        if (! $is_supported) {
            $settings_url = admin_url('options-permalink.php');

            $title .= ' <span style="color:#ef4444;font-size:10px;margin-left:5px;">❌ Unsupported</span>';
            $title .= ' <a href="' . esc_url($settings_url) . '" style="margin-left:5px;font-size:10px;color:#3b82f6;">Fix</a>';
        }


        $wp_admin_bar->add_node([
            'id'    => 'ggrwa-scan-page',
            'title' => $title,
            'href'  => 'javascript:void(0);',
            'meta'  => [
                'class' => 'ggrwa-adminbar-btn ggrwa-no-click ' . $class,
                'html'  => true
            ]
        ]);

        if ($score !== '' && $score !== null && $is_supported) {
            $wp_admin_bar->add_node([
                'id'    => 'ggrwa-rescan-page',
                'title' => '<span class="ab-item">🔄 Rescan</span>',
                'href'  => admin_url('admin-ajax.php?action=ggr_run_audit&post_id=' . $post->ID),
                'meta'  => [
                    'class' => 'ggrwa-rescan-btn',
                    'html'  => true,
                ]
            ]);
        }
    }


    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_admin_assets($hook)
    {
        /**
         * -------------------------------------------------
         * 1. GLOBAL JS 
         * Needed for Admin Bar "Scan Page" button
         * Load on ALL admin pages
         * -------------------------------------------------
         */
        wp_enqueue_script(
            'ggrwa-admin-js',
            GGRWA_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            GGRWA_VERSION,
            true
        );

        wp_add_inline_style(
            'admin-bar',
            '
            #wp-admin-bar-ggrwa-scan-page.ggr-score-has > a {
            pointer-events: none !important;
            cursor: default !important;
        }
            '
        );


        wp_localize_script(
            'ggrwa-admin-js',
            'ggrwa_admin',
            array(
                'ajaxUrl'    => admin_url('admin-ajax.php'),
                'auditNonce' => wp_create_nonce('ggrwa_run_audit'),
                'scanNonce'   => wp_create_nonce('ggrwa_scan_action'),
                'dashboardUrl' => admin_url('admin.php?page=ggrwa-audit-dashboard'),
                'postId' => get_the_ID(),
            )
        );

        /**
         * -------------------------------------------------
         * 2. PAGE-SPECIFIC STYLES (Dashboard etc.)
         * -------------------------------------------------
         */
        if (in_array($hook, array(
            'toplevel_page_ggrwa-audit-dashboard',
            'ggrwa-audit-dashboard_page_ggrwa-audit-settings',
            'admin_page_ggrwa-setup'
        ), true)) {

            wp_enqueue_style(
                'ggrwa-admin-css',
                GGRWA_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                GGRWA_VERSION
            );

            wp_enqueue_script(
                'ggrwa-audit',
                GGRWA_PLUGIN_URL . 'assets/js/audit.js',
                array('jquery'),
                GGRWA_VERSION,
                true
            );
        }

        /**
         * -------------------------------------------------
         * 3. SETUP PAGE ONLY
         * -------------------------------------------------
         */
        if ($hook === 'admin_page_ggrwa-setup') {

            wp_enqueue_style(
                'ggrwa-setup-css',
                GGRWA_PLUGIN_URL . 'assets/css/setup.css',
                array(),
                GGRWA_VERSION
            );

            wp_enqueue_script(
                'ggrwa-setup-js',
                GGRWA_PLUGIN_URL . 'assets/js/setup.js',
                array('jquery'),
                GGRWA_VERSION,
                true
            );

            wp_localize_script(
                'ggrwa-setup-js',
                'ggrwa_setup',
                array(
                    'ajaxUrl'      => admin_url('admin-ajax.php'),
                    'auditNonce'   => wp_create_nonce('ggrwa_run_audit'),
                    'dashboardUrl' => admin_url('admin.php?page=ggrwa-audit-dashboard'),
                    'homeUrl'      => home_url(),
                )
            );
        }
    }
}
