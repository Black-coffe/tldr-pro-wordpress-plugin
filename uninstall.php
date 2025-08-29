<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * This file may be updated more in future version of the Boilerplate; however, this is the
 * general skeleton and outline for how the file should work.
 *
 * For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    TLDR_Pro
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * The core uninstall functionality.
 * 
 * Cleans up all plugin data from the database when the plugin is deleted.
 * This ensures no orphaned data is left behind.
 */
function tldr_pro_uninstall() {
	global $wpdb;

	// Check if we should remove data (allow users to keep data via option)
	$remove_data = get_option( 'tldr_pro_delete_data_on_uninstall', '0' );
	
	if ( $remove_data !== '1' ) {
		return;
	}

	// For Single site
	if ( ! is_multisite() ) {
		tldr_pro_delete_plugin_data();
	} 
	// For Multisite
	else {
		// Get all blog ids
		$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
		$original_blog_id = get_current_blog_id();

		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( $blog_id );
			tldr_pro_delete_plugin_data();
		}

		switch_to_blog( $original_blog_id );
	}
}

/**
 * Delete all plugin data for the current site.
 *
 * @since    1.0.0
 */
function tldr_pro_delete_plugin_data() {
	global $wpdb;

	// Delete database tables
	$table_name = $wpdb->prefix . 'tldr_pro_summaries';
	$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	
	// Delete test logs table (created dynamically during API testing)
	$test_logs_table = $wpdb->prefix . 'tldr_pro_test_logs';
	$wpdb->query( "DROP TABLE IF EXISTS {$test_logs_table}" );

	// Delete all plugin options
	$plugin_options = array(
		// Version tracking
		'tldr_pro_version',
		'tldr_pro_db_version',
		
		// AI Provider Settings
		'tldr_pro_active_provider',
		'tldr_pro_deepseek_api_key',
		'tldr_pro_gemini_api_key',
		'tldr_pro_enable_fallback',
		'tldr_pro_fallback_order',
		
		// Summary Settings
		'tldr_pro_max_summary_length',
		'tldr_pro_summary_style',
		'tldr_pro_summary_format',
		'tldr_pro_bullet_points_count',
		'tldr_pro_use_emojis',
		'tldr_pro_summary_language',
		
		// Display Settings
		'tldr_pro_button_position',
		'tldr_pro_button_text',
		'tldr_pro_enable_floating_button',
		'tldr_pro_modal_theme',
		
		// Performance Settings
		'tldr_pro_enable_caching',
		'tldr_pro_cache_expiry',
		'tldr_pro_enable_cdn',
		'tldr_pro_cdn_provider',
		'tldr_pro_enable_redis',
		'tldr_pro_enable_profiling',
		'tldr_pro_minify_assets',
		
		// Image Optimization
		'tldr_pro_optimize_images',
		'tldr_pro_image_quality',
		'tldr_pro_generate_webp',
		'tldr_pro_webp_quality',
		'tldr_pro_serve_webp',
		
		// Advanced Settings
		'tldr_pro_batch_size',
		'tldr_pro_batch_delay',
		'tldr_pro_debug_mode',
		'tldr_pro_auto_generate',
		'tldr_pro_enabled_post_types',
		'tldr_pro_min_word_count',
		'tldr_pro_delete_data_on_uninstall',
		
		// Prompt Templates
		'tldr_pro_prompt_templates',
		
		// API Validation for all providers
		'tldr_pro_deepseek_validated',
		'tldr_pro_deepseek_validated_at',
		'tldr_pro_deepseek_last_test_result',
		'tldr_pro_gemini_validated',
		'tldr_pro_gemini_validated_at',
		'tldr_pro_gemini_last_test_result',
		'tldr_pro_claude_validated',
		'tldr_pro_claude_validated_at',
		'tldr_pro_claude_last_test_result',
		'tldr_pro_gpt_validated',
		'tldr_pro_gpt_validated_at',
		'tldr_pro_gpt_last_test_result',
		
		// Statistics and cache
		'tldr_pro_stats_cache',
		'tldr_pro_provider_stats',
		'tldr_pro_total_summaries_generated',
		'tldr_pro_last_cleanup',
		'tldr_pro_stats',
	);

	foreach ( $plugin_options as $option ) {
		delete_option( $option );
	}

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

	// Delete user meta
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} 
			WHERE meta_key LIKE %s",
			'tldr_pro_%'
		)
	);

	// Delete post meta
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} 
			WHERE meta_key LIKE %s",
			'_tldr_pro_%'
		)
	);

	// Remove plugin upload directory
	$upload_dir = wp_upload_dir();
	$plugin_upload_dir = $upload_dir['basedir'] . '/tldr-pro';
	
	if ( is_dir( $plugin_upload_dir ) ) {
		tldr_pro_delete_directory( $plugin_upload_dir );
	}

	// Clear any scheduled hooks
	wp_clear_scheduled_hook( 'tldr_pro_daily_cleanup' );
	wp_clear_scheduled_hook( 'tldr_pro_weekly_stats' );

	// Clear object cache
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}
}

/**
 * Recursively delete a directory and its contents.
 *
 * @since    1.0.0
 * @param    string    $dir    Directory path to delete.
 * @return   bool              True on success, false on failure.
 */
function tldr_pro_delete_directory( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return false;
	}

	$files = array_diff( scandir( $dir ), array( '.', '..' ) );
	
	foreach ( $files as $file ) {
		$path = $dir . '/' . $file;
		
		if ( is_dir( $path ) ) {
			tldr_pro_delete_directory( $path );
		} else {
			unlink( $path );
		}
	}
	
	return rmdir( $dir );
}

// Run the uninstall function
tldr_pro_uninstall();