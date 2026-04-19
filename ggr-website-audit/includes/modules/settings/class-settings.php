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
    }

    public static function sanitize($input) {
        return [
            'enabled' => !empty($input['enabled']) ? 1 : 0,
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