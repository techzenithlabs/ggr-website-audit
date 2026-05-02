<?php

if (!defined('ABSPATH')) exit;

class GGRWA_Settings {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [__CLASS__, 'register']); // 🔥 IMPORTANT
    }

    public function register_menu() {
        add_submenu_page(
            'ggrwa-audit-dashboard',
            'Settings',
            'Settings',
            'manage_options',
            'ggrwa-audit-settings',
            [$this, 'render']
        );
    }

    public function render() {
        ?>
        <div class="wrap ggr-admin ggr-settings">
            <h1><?php esc_html_e('GGR Website Audit – Settings', 'ggr-website-audit'); ?></h1>

            <?php settings_errors(); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('ggrwa_settings_group');
                do_settings_sections('ggrwa_settings_page');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public static function register() {

        register_setting(
            'ggrwa_settings_group',
            'ggrwa_settings',
            [
                'sanitize_callback' => [__CLASS__, 'sanitize'],
            ]
        );

        add_settings_section(
            'ggrwa_general_section',
            __('General Settings', 'ggr-website-audit'),
            '__return_false',
            'ggrwa_settings_page'
        );

        add_settings_field(
            'enabled',
            __('Enable Audit', 'ggr-website-audit'),
            [__CLASS__, 'render_enabled_field'],
            'ggrwa_settings_page',
            'ggrwa_general_section'
        );

        // PageSpeed API section.
        add_settings_section(
            'ggrwa_pagespeed_section',
            __('Google PageSpeed Insights API', 'ggr-website-audit'),
            [__CLASS__, 'render_pagespeed_section_desc'],
            'ggrwa_settings_page'
        );

        add_settings_field(
            'pagespeed_api_key',
            __('API Key', 'ggr-website-audit'),
            [__CLASS__, 'render_pagespeed_key_field'],
            'ggrwa_settings_page',
            'ggrwa_pagespeed_section'
        );
    }

    public static function render_pagespeed_section_desc() {
        echo '<p>Enter your free Google PageSpeed Insights API key to enable Core Web Vitals analysis. '
           . '<a href="https://developers.google.com/speed/docs/insights/v5/get-started" target="_blank" rel="noopener">Get a free key →</a> '
           . '(25,000 requests/day free)</p>';
    }

    public static function render_pagespeed_key_field() {
        $options = get_option( 'ggrwa_settings', [] );
        $key     = esc_attr( $options['pagespeed_api_key'] ?? '' );
        echo '<input type="text" name="ggrwa_settings[pagespeed_api_key]" value="' . $key . '" '
           . 'class="regular-text" placeholder="AIza..." autocomplete="off" />';
        if ( ! empty( $key ) ) {
            echo ' <span style="color:#16a34a;font-weight:600;">✓ Key saved</span>';
        }
    }

    public static function sanitize($input) {
        return [
            'enabled'            => !empty($input['enabled']) ? 1 : 0,
            'pagespeed_api_key'  => sanitize_text_field( $input['pagespeed_api_key'] ?? '' ),
        ];
    }

    public static function render_enabled_field() {
        $options = get_option('ggrwa_settings', []);
        ?>
        <label>
            <input type="checkbox" name="ggrwa_settings[enabled]" value="1"
                <?php checked(!empty($options['enabled']), 1); ?> />
            <?php esc_html_e('Enable website audit functionality', 'ggr-website-audit'); ?>
        </label>
        <?php
    }
}