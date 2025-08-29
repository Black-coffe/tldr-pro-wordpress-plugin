<?php
/**
 * Status tracking for API requests
 *
 * @package TLDR_Pro
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Status tracker class
 */
class TLDR_Pro_Status_Tracker {
	
	/**
	 * Status option key prefix
	 */
	const STATUS_KEY_PREFIX = 'tldr_pro_status_';
	
	/**
	 * Update status for a request
	 *
	 * @param string $request_id Unique request ID
	 * @param string $status Current status
	 * @param array $data Additional data
	 */
	public static function update_status( $request_id, $status, $data = array() ) {
		$status_data = array(
			'status' => $status,
			'timestamp' => current_time( 'timestamp' ),
			'message' => self::get_status_message( $status ),
			'data' => $data
		);
		
		// Store as transient for 5 minutes
		set_transient( self::STATUS_KEY_PREFIX . $request_id, $status_data, 300 );
		
		// Also log to debug if enabled
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			error_log( '[TLDR PRO STATUS] ' . $request_id . ': ' . $status . ' - ' . $status_data['message'] );
		}
	}
	
	/**
	 * Get status for a request
	 *
	 * @param string $request_id Unique request ID
	 * @return array|false Status data or false if not found
	 */
	public static function get_status( $request_id ) {
		return get_transient( self::STATUS_KEY_PREFIX . $request_id );
	}
	
	/**
	 * Clear status for a request
	 *
	 * @param string $request_id Unique request ID
	 */
	public static function clear_status( $request_id ) {
		delete_transient( self::STATUS_KEY_PREFIX . $request_id );
	}
	
	/**
	 * Get human-readable status message
	 *
	 * @param string $status Status code
	 * @return string Status message
	 */
	private static function get_status_message( $status ) {
		$messages = array(
			'initializing' => '🔄 Initializing request...',
			'validating' => '✅ Validating API key...',
			'preparing' => '📝 Preparing content for API...',
			'sending' => '📤 Sending request to API provider...',
			'waiting' => '⏳ Waiting for API response...',
			'processing' => '⚙️ Processing API response...',
			'streaming' => '📊 Receiving data stream...',
			'parsing' => '🔍 Parsing response data...',
			'saving' => '💾 Saving summary to database...',
			'completed' => '✅ Summary generated successfully!',
			'error' => '❌ Error occurred',
			'timeout' => '⏱️ Request timed out',
			'rate_limited' => '🚫 Rate limit exceeded',
			'retrying' => '🔄 Retrying request...'
		);
		
		return isset( $messages[ $status ] ) ? $messages[ $status ] : 'Processing...';
	}
	
	/**
	 * Generate unique request ID
	 *
	 * @param int $post_id Post ID
	 * @return string Request ID
	 */
	public static function generate_request_id( $post_id ) {
		return 'req_' . $post_id . '_' . wp_generate_password( 8, false );
	}
}