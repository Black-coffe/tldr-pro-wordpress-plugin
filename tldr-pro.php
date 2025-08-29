<?php
/**
 * TL;DR Pro - AI-Powered Content Summary Engine
 *
 * @package           TLDR_Pro
 * @author            Your Name
 * @copyright         2025 Your Company
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       TL;DR Pro - AI Content Summaries
 * Plugin URI:        https://example.com/tldr-pro
 * Description:       Automatically generate intelligent summaries for WordPress posts and pages using AI. Improve user engagement with quick, scannable content previews.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Your Name
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tldr-pro
 * Domain Path:       /languages
 * Update URI:        https://example.com/tldr-pro-updates
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Current plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 */
define( 'TLDR_PRO_VERSION', '1.0.0' );

/**
 * Plugin file path
 */
define( 'TLDR_PRO_PLUGIN_FILE', __FILE__ );

/**
 * Plugin directory path
 */
define( 'TLDR_PRO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL
 */
define( 'TLDR_PRO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename
 */
define( 'TLDR_PRO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Database table names
 */
global $wpdb;
define( 'TLDR_PRO_TABLE_SUMMARIES', $wpdb->prefix . 'tldr_pro_summaries' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-tldr-pro-activator.php
 */
function tldr_pro_activate() {
	require_once TLDR_PRO_PLUGIN_DIR . 'includes/class-tldr-pro-activator.php';
	TLDR_Pro_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-tldr-pro-deactivator.php
 */
function tldr_pro_deactivate() {
	require_once TLDR_PRO_PLUGIN_DIR . 'includes/class-tldr-pro-deactivator.php';
	TLDR_Pro_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'tldr_pro_activate' );
register_deactivation_hook( __FILE__, 'tldr_pro_deactivate' );

/**
 * Load Composer autoloader if available
 */
if ( file_exists( TLDR_PRO_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once TLDR_PRO_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require TLDR_PRO_PLUGIN_DIR . 'includes/class-tldr-pro.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function tldr_pro_run() {
	$plugin = new TLDR_Pro();
	$plugin->run();
}

// Check PHP version
if ( version_compare( PHP_VERSION, '7.4', '>=' ) ) {
	tldr_pro_run();
} else {
	add_action( 'admin_notices', 'tldr_pro_php_version_notice' );
}

/**
 * Admin notice for minimum PHP version
 */
function tldr_pro_php_version_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'TL;DR Pro requires PHP 7.4 or higher. Please update your PHP version.', 'tldr-pro' ); ?></p>
	</div>
	<?php
}