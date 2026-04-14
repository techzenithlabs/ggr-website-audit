<?php
/**
 * SEO Audit & Analyzer by GGR
 *
 * @package GGR_Website_Audit
 * @since   1.0.0
 *
 * @wordpress-plugin
 * Plugin Name: SEO Audit & Analyzer by GGR
 * Plugin URI: https://techzenithlabs.com/
 * Description: Run a complete SEO audit, detect on-page issues, and improve rankings with a fast and lightweight SEO analyzer tool.
 * Version: 2.4.6
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 7.2
 * Author: Yogesh Kumar Raghav
 * Author URI: https://www.getgenuinereview.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ggr-website-audit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Minimum requirements.
 */
define('GGRWA_MIN_PHP', '7.2');
define('GGRWA_VERSION', '2.4.6');
define('GGRWA_MIN_WP', '6.0' );

/**
 * Compatibility check.
 */
function ggrwa_is_environment_compatible() {
    global $wp_version;

    if ( version_compare( PHP_VERSION, GGRWA_MIN_PHP, '<' ) ) {
        return false;
    }

    if ( version_compare( $wp_version, GGRWA_MIN_WP, '<' ) ) {
        return false;
    }

    return true;
}

/**
 * Admin notice for incompatible environments.
 */
function ggrwa_admin_notice_incompatible() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong>GGR Website Audit</strong> requires
            PHP <?php echo esc_html( GGRWA_MIN_PHP ); ?>+
            and WordPress <?php echo esc_html( GGRWA_MIN_WP ); ?>+.
        </p>
    </div>
    <?php
}

/**
 * Stop plugin if environment is incompatible.
 */
if ( ! ggrwa_is_environment_compatible() ) {
    add_action( 'admin_notices', 'ggrwa_admin_notice_incompatible' );
    return;
}

/**
 * Constants.
 */

define( 'GGRWA_PLUGIN_FILE', __FILE__ );
define( 'GGRWA_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'GGRWA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );


/**
 * Activation hook.
 */
register_activation_hook( GGRWA_PLUGIN_FILE, 'ggrwa_activate_defaults' );

function ggrwa_activate_defaults() {

    if ( ! get_option( 'ggrwa_settings' ) ) {

        add_option(
            'ggrwa_settings',
            array(
                'enabled' => 1,
            )
        );
  
        add_option('ggrwa_do_activation_redirect', true);
    }
  
    update_option('ggrwa_version', GGRWA_VERSION);
}


/**
 * Detect plugin updates and show notice.
 */
add_action('plugins_loaded', function () {

    $saved_version = get_option('ggrwa_version');

    if ($saved_version !== GGRWA_VERSION) {

        update_option('ggrwa_version', GGRWA_VERSION);

        set_transient('ggrwa_show_update_notice', true, 60);
    }

});


/**
 * Show update notice in admin.
 */
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (!get_transient('ggrwa_show_update_notice')) {
        return;
    }

    delete_transient('ggrwa_show_update_notice');

    $url = admin_url('admin.php?page=ggrwa-audit-dashboard');

    echo '<div class="notice notice-success is-dismissible">';
    echo '<p><strong>🚀 GGR SEO Audit Updated!</strong> ';
    echo 'New improvements available. ';
    echo '<a href="' . esc_url($url) . '">Run a new audit →</a></p>';
    echo '</div>';
});

/**
 * Redirect to setup page after plugin activation.
 *
 * This runs on admin_init and checks if the plugin was just activated.
 * If so, it redirects the user to the setup/onboarding page.
 */

add_action('admin_init', 'ggrwa_redirect_after_activation');

function ggrwa_redirect_after_activation() {

    if (get_option('ggrwa_do_activation_redirect', false)) {

        delete_option('ggrwa_do_activation_redirect');

        if (!isset($_GET['activate-multi'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended

            wp_safe_redirect(admin_url('admin.php?page=ggrwa-setup'));
            exit;
        }
    }
}

/**
 * Load core plugin.
 */
require_once GGRWA_PLUGIN_PATH . 'includes/class-ggrwa-plugin.php';

/**
 * Run plugin safely.
 */
function ggrwa_run_plugin() {
    if ( class_exists   ( 'GGRWA_Plugin' ) ) {
        $plugin = new GGRWA_Plugin();
        $plugin->run();
    }
}

ggrwa_run_plugin();
