<?php
/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
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
 * Fired during plugin deactivation.
 *
 * @since      1.0.0
 * @package    TLDR_Pro
 * @subpackage TLDR_Pro/includes
 */
class TLDR_Pro_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		// Unschedule cron events
		self::unschedule_cron_events();

		// Clear plugin cache
		self::clear_plugin_cache();

		// Clear rewrite rules
		flush_rewrite_rules();

		// Log deactivation
		self::log_deactivation();
	}

	/**
	 * Unschedule all plugin cron events.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function unschedule_cron_events() {
		// Unschedule daily cleanup
		$timestamp = wp_next_scheduled( 'tldr_pro_daily_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'tldr_pro_daily_cleanup' );
		}

		// Unschedule weekly stats
		$timestamp = wp_next_scheduled( 'tldr_pro_weekly_stats' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'tldr_pro_weekly_stats' );
		}

		// Clear all scheduled hooks for safety
		wp_clear_scheduled_hook( 'tldr_pro_daily_cleanup' );
		wp_clear_scheduled_hook( 'tldr_pro_weekly_stats' );
	}

	/**
	 * Clear all plugin cache.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function clear_plugin_cache() {
		global $wpdb;

		// Delete all transients with our prefix
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} 
				WHERE option_name LIKE %s 
				OR option_name LIKE %s",
				'_transient_tldr_pro_%',
				'_transient_timeout_tldr_pro_%'
			)
		);

		// Clear object cache if available
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		// Clear any file-based cache
		$upload_dir = wp_upload_dir();
		$cache_dir = $upload_dir['basedir'] . '/tldr-pro/cache';
		
		if ( is_dir( $cache_dir ) ) {
			self::clear_directory( $cache_dir );
		}
	}

	/**
	 * Clear a directory of all files.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $dir    Directory path to clear.
	 */
	private static function clear_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		
		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			
			if ( is_dir( $path ) ) {
				self::clear_directory( $path );
			} else {
				// Only delete cache files, preserve .htaccess and index.php
				if ( ! in_array( $file, array( '.htaccess', 'index.php' ), true ) ) {
					unlink( $path );
				}
			}
		}
	}

	/**
	 * Log the deactivation event.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function log_deactivation() {
		// Store deactivation info for potential debugging
		update_option( 'tldr_pro_deactivated_at', current_time( 'mysql' ) );
		
		// Optional: Log to file if debug mode is enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$upload_dir = wp_upload_dir();
			$log_file = $upload_dir['basedir'] . '/tldr-pro/logs/deactivation.log';
			
			if ( is_writable( dirname( $log_file ) ) ) {
				$log_entry = sprintf(
					"[%s] Plugin deactivated. User: %s, IP: %s\n",
					current_time( 'mysql' ),
					wp_get_current_user()->user_login,
					$_SERVER['REMOTE_ADDR'] ?? 'Unknown'
				);
				
				file_put_contents( $log_file, $log_entry, FILE_APPEND | LOCK_EX );
			}
		}
	}
}