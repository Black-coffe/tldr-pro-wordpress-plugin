<?php
/**
 * Error Handler class
 *
 * @package TLDR_Pro
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles errors and exceptions for the plugin
 *
 * @since 1.0.0
 */
class TLDR_Pro_Error_Handler {

	/**
	 * Instance of this class
	 *
	 * @var TLDR_Pro_Error_Handler
	 */
	private static $instance = null;

	/**
	 * Logger instance
	 *
	 * @var TLDR_Pro_Logger
	 */
	private $logger;

	/**
	 * Error messages
	 *
	 * @var array
	 */
	private $error_messages = array();

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->logger = TLDR_Pro_Logger::get_instance();
		$this->init_error_messages();
	}

	/**
	 * Get instance
	 *
	 * @return TLDR_Pro_Error_Handler
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize error messages
	 *
	 * @return void
	 */
	private function init_error_messages() {
		$this->error_messages = array(
			// API Errors
			'api_connection_failed' => __( 'Failed to connect to API service. Please check your internet connection.', 'tldr-pro' ),
			'api_invalid_key' => __( 'Invalid API key. Please check your settings.', 'tldr-pro' ),
			'api_rate_limit' => __( 'API rate limit exceeded. Please try again later.', 'tldr-pro' ),
			'api_timeout' => __( 'API request timed out. Please try again.', 'tldr-pro' ),
			'api_server_error' => __( 'API server error. Please try again later.', 'tldr-pro' ),
			'api_invalid_response' => __( 'Invalid response from API. Please contact support.', 'tldr-pro' ),
			
			// Content Errors
			'content_too_short' => __( 'Content is too short to generate a meaningful summary.', 'tldr-pro' ),
			'content_too_long' => __( 'Content exceeds maximum length. It will be split into chunks.', 'tldr-pro' ),
			'content_extraction_failed' => __( 'Failed to extract content from the post.', 'tldr-pro' ),
			'content_empty' => __( 'No content found to summarize.', 'tldr-pro' ),
			
			// Database Errors
			'db_connection_failed' => __( 'Database connection failed.', 'tldr-pro' ),
			'db_query_failed' => __( 'Database query failed.', 'tldr-pro' ),
			'db_insert_failed' => __( 'Failed to save summary to database.', 'tldr-pro' ),
			'db_update_failed' => __( 'Failed to update summary in database.', 'tldr-pro' ),
			
			// Permission Errors
			'permission_denied' => __( 'You do not have permission to perform this action.', 'tldr-pro' ),
			'nonce_failed' => __( 'Security check failed. Please refresh the page and try again.', 'tldr-pro' ),
			
			// Configuration Errors
			'no_api_key' => __( 'No API key configured. Please add your API key in settings.', 'tldr-pro' ),
			'no_provider_selected' => __( 'No AI provider selected. Please choose a provider in settings.', 'tldr-pro' ),
			'invalid_settings' => __( 'Invalid settings detected. Please review your configuration.', 'tldr-pro' ),
			
			// Processing Errors
			'generation_failed' => __( 'Failed to generate summary. Please try again.', 'tldr-pro' ),
			'processing_error' => __( 'An error occurred while processing your request.', 'tldr-pro' ),
			'unknown_error' => __( 'An unknown error occurred. Please contact support.', 'tldr-pro' ),
		);

		$this->error_messages = apply_filters( 'tldr_pro_error_messages', $this->error_messages );
	}

	/**
	 * Handle error
	 *
	 * @param string|WP_Error $error     Error code or WP_Error object.
	 * @param array           $context   Additional context.
	 * @param string          $severity  Error severity (error, warning, info).
	 * @return WP_Error Error object.
	 */
	public function handle_error( $error, $context = array(), $severity = 'error' ) {
		if ( is_wp_error( $error ) ) {
			$error_code = $error->get_error_code();
			$error_message = $error->get_error_message();
			$error_data = $error->get_error_data();
		} else {
			$error_code = $error;
			$error_message = $this->get_error_message( $error_code );
			$error_data = array();
		}

		// Log the error
		$this->log_error( $error_code, $error_message, $context, $severity );

		// Track error occurrence
		$this->track_error( $error_code );

		// Create or enhance error object
		if ( ! is_wp_error( $error ) ) {
			$error = new WP_Error( $error_code, $error_message );
		}

		// Add context data
		if ( ! empty( $context ) ) {
			$error->add_data( array_merge( $error_data, $context ) );
		}

		// Add user-friendly message if needed
		if ( $this->should_show_user_message( $error_code ) ) {
			$error->add( 
				'user_message',
				$this->get_user_friendly_message( $error_code )
			);
		}

		return $error;
	}

	/**
	 * Get error message
	 *
	 * @param string $error_code Error code.
	 * @return string Error message.
	 */
	public function get_error_message( $error_code ) {
		if ( isset( $this->error_messages[ $error_code ] ) ) {
			return $this->error_messages[ $error_code ];
		}
		
		return sprintf(
			/* translators: %s: error code */
			__( 'An error occurred: %s', 'tldr-pro' ),
			$error_code
		);
	}

	/**
	 * Get user-friendly message
	 *
	 * @param string $error_code Error code.
	 * @return string User-friendly message.
	 */
	private function get_user_friendly_message( $error_code ) {
		$user_messages = array(
			'api_connection_failed' => __( 'Unable to generate summary at this time. Please try again later.', 'tldr-pro' ),
			'api_invalid_key' => __( 'Configuration error. Please contact the administrator.', 'tldr-pro' ),
			'api_rate_limit' => __( 'Too many requests. Please wait a moment and try again.', 'tldr-pro' ),
			'content_too_short' => __( 'This content is too short for a summary.', 'tldr-pro' ),
			'permission_denied' => __( 'You are not authorized to perform this action.', 'tldr-pro' ),
		);

		return isset( $user_messages[ $error_code ] ) 
			? $user_messages[ $error_code ] 
			: __( 'Something went wrong. Please try again.', 'tldr-pro' );
	}

	/**
	 * Check if user message should be shown
	 *
	 * @param string $error_code Error code.
	 * @return bool True if should show user message.
	 */
	private function should_show_user_message( $error_code ) {
		$show_for = array(
			'api_connection_failed',
			'api_rate_limit',
			'content_too_short',
			'content_empty',
			'permission_denied',
			'nonce_failed',
		);

		return in_array( $error_code, $show_for, true );
	}

	/**
	 * Log error
	 *
	 * @param string $error_code    Error code.
	 * @param string $error_message Error message.
	 * @param array  $context       Additional context.
	 * @param string $severity      Error severity.
	 * @return void
	 */
	private function log_error( $error_code, $error_message, $context, $severity ) {
		$log_message = sprintf(
			'[%s] %s - %s',
			strtoupper( $error_code ),
			$error_message,
			wp_json_encode( $context )
		);

		$this->logger->log( $log_message, $severity );
	}

	/**
	 * Track error occurrence
	 *
	 * @param string $error_code Error code.
	 * @return void
	 */
	private function track_error( $error_code ) {
		$option_key = 'tldr_pro_error_stats';
		$stats = get_option( $option_key, array() );
		
		if ( ! isset( $stats[ $error_code ] ) ) {
			$stats[ $error_code ] = array(
				'count' => 0,
				'first_occurred' => current_time( 'mysql' ),
				'last_occurred' => '',
			);
		}
		
		$stats[ $error_code ]['count']++;
		$stats[ $error_code ]['last_occurred'] = current_time( 'mysql' );
		
		update_option( $option_key, $stats );
	}

	/**
	 * Get error statistics
	 *
	 * @return array Error statistics.
	 */
	public function get_error_stats() {
		return get_option( 'tldr_pro_error_stats', array() );
	}

	/**
	 * Clear error statistics
	 *
	 * @return bool Success status.
	 */
	public function clear_error_stats() {
		return delete_option( 'tldr_pro_error_stats' );
	}

	/**
	 * Handle AJAX error response
	 *
	 * @param WP_Error $error Error object.
	 * @return void
	 */
	public function send_ajax_error( $error ) {
		$response = array(
			'success' => false,
			'data' => array(
				'message' => $error->get_error_message(),
				'code' => $error->get_error_code(),
			),
		);

		// Add user message if available
		$user_messages = $error->get_error_messages( 'user_message' );
		if ( ! empty( $user_messages ) ) {
			$response['data']['user_message'] = $user_messages[0];
		}

		// Add debug info in development mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$response['data']['debug'] = $error->get_error_data();
		}

		wp_send_json( $response );
	}

	/**
	 * Handle REST API error response
	 *
	 * @param WP_Error $error Error object.
	 * @return WP_REST_Response REST response.
	 */
	public function send_rest_error( $error ) {
		$data = array(
			'code' => $error->get_error_code(),
			'message' => $error->get_error_message(),
			'data' => array(
				'status' => 400,
			),
		);

		// Add additional error data
		$error_data = $error->get_error_data();
		if ( ! empty( $error_data ) ) {
			$data['data'] = array_merge( $data['data'], $error_data );
		}

		return new WP_REST_Response( $data, 400 );
	}

	/**
	 * Format error for display
	 *
	 * @param WP_Error $error   Error object.
	 * @param string   $format  Output format (html, text, json).
	 * @return string Formatted error.
	 */
	public function format_error( $error, $format = 'html' ) {
		$message = $error->get_error_message();
		$code = $error->get_error_code();

		switch ( $format ) {
			case 'html':
				return sprintf(
					'<div class="tldr-pro-error"><strong>%s:</strong> %s</div>',
					esc_html( $code ),
					esc_html( $message )
				);

			case 'text':
				return sprintf( '%s: %s', $code, $message );

			case 'json':
				return wp_json_encode( array(
					'code' => $code,
					'message' => $message,
				) );

			default:
				return $message;
		}
	}

	/**
	 * Check if error is recoverable
	 *
	 * @param string $error_code Error code.
	 * @return bool True if recoverable.
	 */
	public function is_recoverable( $error_code ) {
		$recoverable = array(
			'api_timeout',
			'api_rate_limit',
			'api_connection_failed',
			'db_connection_failed',
		);

		return in_array( $error_code, $recoverable, true );
	}

	/**
	 * Get recovery suggestion
	 *
	 * @param string $error_code Error code.
	 * @return string Recovery suggestion.
	 */
	public function get_recovery_suggestion( $error_code ) {
		$suggestions = array(
			'api_timeout' => __( 'Try reducing the content length or increasing timeout in settings.', 'tldr-pro' ),
			'api_rate_limit' => __( 'Wait a few minutes before trying again.', 'tldr-pro' ),
			'api_connection_failed' => __( 'Check your internet connection and firewall settings.', 'tldr-pro' ),
			'api_invalid_key' => __( 'Verify your API key in the settings page.', 'tldr-pro' ),
			'no_api_key' => __( 'Add your API key in TL;DR Pro settings.', 'tldr-pro' ),
		);

		return isset( $suggestions[ $error_code ] ) 
			? $suggestions[ $error_code ] 
			: __( 'Please try again or contact support if the problem persists.', 'tldr-pro' );
	}
}