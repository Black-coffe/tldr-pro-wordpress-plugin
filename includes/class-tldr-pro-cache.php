<?php
/**
 * Cache handler for TL;DR Pro plugin.
 *
 * Implements object caching and transient API for better performance.
 *
 * @package    TLDR_Pro
 * @subpackage TLDR_Pro/includes
 * @since      2.2.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache management class.
 */
class TLDR_Pro_Cache {

	/**
	 * Cache group name.
	 */
	const CACHE_GROUP = 'tldr_pro';

	/**
	 * Default cache expiration time (1 hour).
	 */
	const DEFAULT_EXPIRATION = 3600;

	/**
	 * Get cached value.
	 *
	 * @param string $key Cache key.
	 * @return mixed|false Cached value or false if not found.
	 */
	public static function get( $key ) {
		// Try object cache first
		$value = wp_cache_get( $key, self::CACHE_GROUP );
		
		if ( false === $value ) {
			// Fallback to transient API
			$value = get_transient( 'tldr_pro_' . $key );
		}
		
		return $value;
	}

	/**
	 * Set cache value.
	 *
	 * @param string $key        Cache key.
	 * @param mixed  $value      Value to cache.
	 * @param int    $expiration Expiration time in seconds.
	 * @return bool True on success, false on failure.
	 */
	public static function set( $key, $value, $expiration = self::DEFAULT_EXPIRATION ) {
		// Set in object cache
		$cached = wp_cache_set( $key, $value, self::CACHE_GROUP, $expiration );
		
		// Also set as transient for persistent caching
		set_transient( 'tldr_pro_' . $key, $value, $expiration );
		
		return $cached;
	}

	/**
	 * Delete cached value.
	 *
	 * @param string $key Cache key.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( $key ) {
		// Delete from object cache
		$deleted = wp_cache_delete( $key, self::CACHE_GROUP );
		
		// Delete transient
		delete_transient( 'tldr_pro_' . $key );
		
		return $deleted;
	}

	/**
	 * Flush all plugin caches.
	 *
	 * @return bool True on success.
	 */
	public static function flush() {
		// Flush object cache group
		wp_cache_flush_group( self::CACHE_GROUP );
		
		// Delete all plugin transients
		global $wpdb;
		$wpdb->query( 
			$wpdb->prepare( 
				"DELETE FROM {$wpdb->options} 
				WHERE option_name LIKE %s 
				OR option_name LIKE %s",
				'_transient_tldr_pro_%',
				'_transient_timeout_tldr_pro_%'
			)
		);
		
		return true;
	}

	/**
	 * Get cached summary by post ID.
	 *
	 * @param int $post_id Post ID.
	 * @return object|false Cached summary or false.
	 */
	public static function get_summary( $post_id ) {
		return self::get( 'summary_' . $post_id );
	}

	/**
	 * Cache summary for a post.
	 *
	 * @param int    $post_id Post ID.
	 * @param object $summary Summary data.
	 * @param int    $expiration Cache expiration time.
	 * @return bool True on success.
	 */
	public static function set_summary( $post_id, $summary, $expiration = self::DEFAULT_EXPIRATION ) {
		return self::set( 'summary_' . $post_id, $summary, $expiration );
	}

	/**
	 * Delete cached summary.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True on success.
	 */
	public static function delete_summary( $post_id ) {
		return self::delete( 'summary_' . $post_id );
	}

	/**
	 * Cache warmup for frequently accessed summaries.
	 *
	 * @param int $limit Number of posts to warm up.
	 * @return int Number of summaries cached.
	 */
	public static function warmup( $limit = 10 ) {
		global $wpdb;
		
		// Get most viewed posts (assuming you track views)
		$posts = get_posts( array(
			'post_type'      => 'post',
			'posts_per_page' => $limit,
			'orderby'        => 'comment_count', // Or use custom view count
			'order'          => 'DESC',
			'meta_key'       => '_tldr_pro_has_summary',
			'meta_value'     => '1'
		) );
		
		$cached = 0;
		$database = TLDR_Pro_Database::get_instance();
		
		foreach ( $posts as $post ) {
			$summary = $database->get_summary( $post->ID );
			if ( $summary ) {
				self::set_summary( $post->ID, $summary );
				$cached++;
			}
		}
		
		return $cached;
	}

	/**
	 * Setup cache invalidation hooks.
	 */
	public static function setup_hooks() {
		// Clear cache when post is updated
		add_action( 'save_post', array( __CLASS__, 'invalidate_post_cache' ), 10, 1 );
		add_action( 'delete_post', array( __CLASS__, 'invalidate_post_cache' ), 10, 1 );
		add_action( 'tldr_pro_summary_generated', array( __CLASS__, 'invalidate_post_cache' ), 10, 1 );
	}

	/**
	 * Invalidate cache for a specific post.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function invalidate_post_cache( $post_id ) {
		self::delete_summary( $post_id );
	}
}

// Setup cache hooks
add_action( 'init', array( 'TLDR_Pro_Cache', 'setup_hooks' ) );