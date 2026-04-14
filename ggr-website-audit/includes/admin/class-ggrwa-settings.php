<?php
/**
 * Settings controller for GGR Website Audit.
 *
 * Registers plugin settings and renders
 * settings sections and fields.
 *
 * @package GGR_Website_Audit
 * @since   2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GGRWA_Settings
 *
 * @since 2.3.0
 */
class GGRWA_Settings {

    /**
     * Render settings page wrapper.
     *
     * @since 2.3.0
     *
     * @return void
     */
    public function render() {
        ?>
        <div class="wrap ggr-admin ggr-settings">
            <h1><?php esc_html_e( 'GGR Website Audit – Settings', 'ggr-website-audit' ); ?></h1>

            <?php settings_errors(); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'ggrwa_settings_group' );
                do_settings_sections( 'ggrwa_settings_page' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register settings, sections, and fields.
     *
     * @since 2.3.0
     *
     * @return void
     */
    public static function register() {

        register_setting(
            'ggrwa_settings_group',
            'ggrwa_settings',
            array(
                'sanitize_callback' => array( __CLASS__, 'sanitize' ),
            )
        );

        add_settings_section(
            'ggrwa_general_section',
            __( 'General Settings', 'ggr-website-audit' ),
            '__return_false',
            'ggrwa_settings_page'
        );

        add_settings_field(
            'enabled',
            __( 'Enable Audit', 'ggr-website-audit' ),
            array( __CLASS__, 'render_enabled_field' ),
            'ggrwa_settings_page',
            'ggrwa_general_section'
        );
    }

    /**
     * Sanitize settings input.
     *
     * @param array $input Raw settings input.
     * @return array
     */
    public static function sanitize( $input ) {

        $output = array(
            'enabled' => ! empty( $input['enabled'] ) ? 1 : 0,
        );

        return $output;
    }

    /**
     * Render enable checkbox field.
     *
     * @return void
     */
    public static function render_enabled_field() {

        $options = get_option( 'ggrwa_settings', array() );
        ?>
        <label>
            <input type="checkbox" name="ggrwa_settings[enabled]" value="1"
                <?php checked( ! empty( $options['enabled'] ), 1 ); ?> />
            <?php esc_html_e( 'Enable website audit functionality', 'ggr-website-audit' ); ?>
        </label>
        <?php
    }
}
