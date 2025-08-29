<?php
/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    TLDR_Pro
 * @subpackage TLDR_Pro/includes
 * @author     Your Name <your-email@example.com>
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin activation.
 *
 * @since      1.0.0
 * @package    TLDR_Pro
 * @subpackage TLDR_Pro/includes
 */
class TLDR_Pro_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		// Check PHP version
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			deactivate_plugins( plugin_basename( TLDR_PRO_PLUGIN_FILE ) );
			wp_die(
				esc_html__( 'TL;DR Pro requires PHP version 7.4 or higher. Please update your PHP version before activating this plugin.', 'tldr-pro' ),
				esc_html__( 'Plugin Activation Error', 'tldr-pro' ),
				array( 'back_link' => true )
			);
		}

		// Check WordPress version
		if ( version_compare( get_bloginfo( 'version' ), '5.8', '<' ) ) {
			deactivate_plugins( plugin_basename( TLDR_PRO_PLUGIN_FILE ) );
			wp_die(
				esc_html__( 'TL;DR Pro requires WordPress version 5.8 or higher. Please update WordPress before activating this plugin.', 'tldr-pro' ),
				esc_html__( 'Plugin Activation Error', 'tldr-pro' ),
				array( 'back_link' => true )
			);
		}

		// Create database tables
		self::create_tables();

		// Set default options
		self::set_default_options();

		// Create upload directories
		self::create_upload_directories();

		// Schedule cron events
		self::schedule_cron_events();

		// Clear rewrite rules
		flush_rewrite_rules();

		// Set activation flag
		set_transient( 'tldr_pro_activated', true, 5 );
	}

	/**
	 * Create plugin database tables.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function create_tables() {
		// Load database class
		require_once TLDR_PRO_PLUGIN_DIR . 'includes/class-tldr-pro-database.php';
		
		// Create tables using database class
		$database = TLDR_Pro_Database::get_instance();
		$result = $database->create_tables();
		
		if ( ! $result ) {
			wp_die(
				esc_html__( 'Failed to create database tables. Please check your database permissions.', 'tldr-pro' ),
				esc_html__( 'Plugin Activation Error', 'tldr-pro' ),
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * Set default plugin options.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function set_default_options() {
		// Load Settings Manager if not already loaded
		if ( ! class_exists( 'TLDR_Pro_Settings_Manager' ) ) {
			require_once TLDR_PRO_PLUGIN_DIR . 'includes/class-tldr-pro-settings-manager.php';
		}
		
		// Initialize all default settings
		TLDR_Pro_Settings_Manager::init_defaults();
		
		// Initialize default prompts
		if ( ! class_exists( 'TLDR_Pro_Prompt_Manager' ) ) {
			require_once TLDR_PRO_PLUGIN_DIR . 'includes/ai/class-tldr-pro-prompt-manager.php';
		}
		// Set default prompts if not exists
		$prompts = TLDR_Pro_Prompt_Manager::get_prompts();
		if ( empty( $prompts ) ) {
			// Will return defaults automatically
			TLDR_Pro_Prompt_Manager::reset_all_to_defaults();
		}
		
		// Also set some legacy options for backward compatibility
		$legacy_options = array(
			'tldr_pro_version' => TLDR_PRO_VERSION,
			'tldr_pro_api_provider' => 'deepseek', // Legacy, now tldr_pro_active_provider
			'tldr_pro_button_text' => __( 'Show TL;DR', 'tldr-pro' ),
			'tldr_pro_enable_cache' => true,
			'tldr_pro_cache_duration' => 86400, // 24 hours
			'tldr_pro_prompt_template' => 'Please provide a concise summary of the following content. Focus on the key takeaways and main ideas.',
			'tldr_pro_enable_analytics' => false,
		);

		foreach ( $legacy_options as $option_name => $option_value ) {
			if ( false === get_option( $option_name ) ) {
				add_option( $option_name, $option_value );
			}
		}
	}

	/**
	 * Create upload directories for the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function create_upload_directories() {
		$upload_dir = wp_upload_dir();
		$plugin_upload_dir = $upload_dir['basedir'] . '/tldr-pro';
		$logs_dir = $plugin_upload_dir . '/logs';
		$cache_dir = $plugin_upload_dir . '/cache';

		// Create main plugin upload directory
		if ( ! file_exists( $plugin_upload_dir ) ) {
			wp_mkdir_p( $plugin_upload_dir );
			
			// Add index.php for security
			$index_file = $plugin_upload_dir . '/index.php';
			if ( ! file_exists( $index_file ) ) {
				file_put_contents( $index_file, '<?php // Silence is golden' );
			}
		}

		// Create logs directory
		if ( ! file_exists( $logs_dir ) ) {
			wp_mkdir_p( $logs_dir );
			
			// Add .htaccess to deny direct access
			$htaccess_file = $logs_dir . '/.htaccess';
			if ( ! file_exists( $htaccess_file ) ) {
				file_put_contents( $htaccess_file, 'Deny from all' );
			}
		}

		// Create cache directory
		if ( ! file_exists( $cache_dir ) ) {
			wp_mkdir_p( $cache_dir );
			
			// Add index.php for security
			$index_file = $cache_dir . '/index.php';
			if ( ! file_exists( $index_file ) ) {
				file_put_contents( $index_file, '<?php // Silence is golden' );
			}
		}
	}

	/**
	 * Schedule cron events.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function schedule_cron_events() {
		// Schedule daily cleanup
		if ( ! wp_next_scheduled( 'tldr_pro_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'tldr_pro_daily_cleanup' );
		}

		// Schedule weekly stats compilation
		if ( ! wp_next_scheduled( 'tldr_pro_weekly_stats' ) ) {
			wp_schedule_event( time(), 'weekly', 'tldr_pro_weekly_stats' );
		}
	}
}