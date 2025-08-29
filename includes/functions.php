<?php
/**
 * Core plugin functions.
 *
 * General utility functions for the TL;DR Pro plugin.
 *
 * @package    TLDR_Pro
 * @subpackage TLDR_Pro/includes
 * @author     Your Name <your-email@example.com>
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the plugin instance.
 *
 * @since  1.0.0
 * @return TLDR_Pro The main plugin instance.
 */
function tldr_pro() {
	return TLDR_Pro::get_instance();
}

/**
 * Log debug messages.
 *
 * @since  1.0.0
 * @param  string $message The message to log.
 * @param  string $level   The log level (debug, info, warning, error, fatal).
 * @param  array  $context Additional context data.
 */
function tldr_pro_log( $message, $level = 'info', $context = array() ) {
	// Load logger class if not loaded
	if ( ! class_exists( 'TLDR_Pro_Logger' ) ) {
		require_once TLDR_PRO_PLUGIN_DIR . 'includes/class-tldr-pro-logger.php';
	}
	
	$logger = TLDR_Pro_Logger::get_instance();
	$logger->log( $message, $level, $context );
}

/**
 * Log debug message.
 *
 * @since  1.0.0
 * @param  string $message The message to log.
 * @param  array  $context Additional context data.
 */
function tldr_pro_debug( $message, $context = array() ) {
	tldr_pro_log( $message, 'debug', $context );
}

/**
 * Log error message.
 *
 * @since  1.0.0
 * @param  string $message The message to log.
 * @param  array  $context Additional context data.
 */
function tldr_pro_error( $message, $context = array() ) {
	tldr_pro_log( $message, 'error', $context );
}

/**
 * Check if debug mode is enabled.
 *
 * @since  1.0.0
 * @return bool True if debug mode is enabled.
 */
function tldr_pro_is_debug() {
	// Check plugin option
	if ( get_option( 'tldr_pro_enable_debug', false ) ) {
		return true;
	}
	
	// Check WordPress debug
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		return true;
	}
	
	// Check plugin constant
	if ( defined( 'TLDR_PRO_DEBUG' ) && TLDR_PRO_DEBUG ) {
		return true;
	}
	
	return false;
}

/**
 * Get summary for a post.
 *
 * @since  1.0.0
 * @param  int $post_id The post ID.
 * @return string|false The summary text or false if not found.
 */
function tldr_pro_get_summary( $post_id ) {
	// Load database class if not loaded
	if ( ! class_exists( 'TLDR_Pro_Database' ) ) {
		require_once TLDR_PRO_PLUGIN_DIR . 'includes/class-tldr-pro-database.php';
	}
	
	$database = TLDR_Pro_Database::get_instance();
	$summary = $database->get_summary( $post_id );
	
	return $summary ? $summary->summary_text : false;
}

/**
 * Check if post should have summary.
 *
 * @since  1.0.0
 * @param  int $post_id The post ID.
 * @return bool True if post should have summary.
 */
function tldr_pro_should_have_summary( $post_id = null ) {
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	
	if ( ! $post_id ) {
		return false;
	}
	
	// Check post type
	$enabled_post_types = get_option( 'tldr_pro_enabled_post_types', array( 'post', 'page' ) );
	$post_type = get_post_type( $post_id );
	
	if ( ! in_array( $post_type, $enabled_post_types, true ) ) {
		return false;
	}
	
	// Check word count
	$min_word_count = get_option( 'tldr_pro_min_word_count', 300 );
	$content = get_post_field( 'post_content', $post_id );
	$word_count = str_word_count( strip_tags( $content ) );
	
	if ( $word_count < $min_word_count ) {
		return false;
	}
	
	return true;
}

/**
 * Get plugin version.
 *
 * @since  1.0.0
 * @return string The plugin version.
 */
function tldr_pro_get_version() {
	return TLDR_PRO_VERSION;
}

/**
 * Get plugin URL.
 *
 * @since  1.0.0
 * @param  string $path Optional path to append.
 * @return string The plugin URL.
 */
function tldr_pro_get_plugin_url( $path = '' ) {
	$url = TLDR_PRO_PLUGIN_URL;
	
	if ( $path ) {
		$url .= ltrim( $path, '/' );
	}
	
	return $url;
}

/**
 * Get plugin path.
 *
 * @since  1.0.0
 * @param  string $path Optional path to append.
 * @return string The plugin path.
 */
function tldr_pro_get_plugin_path( $path = '' ) {
	$plugin_path = TLDR_PRO_PLUGIN_DIR;
	
	if ( $path ) {
		$plugin_path .= ltrim( $path, '/' );
	}
	
	return $plugin_path;
}

/**
 * Sanitize and validate API key.
 *
 * @since  1.0.0
 * @param  string $api_key The API key to validate.
 * @return string|false The sanitized API key or false if invalid.
 */
function tldr_pro_validate_api_key( $api_key ) {
	$api_key = sanitize_text_field( $api_key );
	
	if ( empty( $api_key ) ) {
		return false;
	}
	
	// Basic validation - adjust based on actual API key format
	if ( strlen( $api_key ) < 20 ) {
		return false;
	}
	
	return $api_key;
}

/**
 * Format summary with proper HTML and emojis.
 *
 * @since  1.0.0
 * @param  string $summary The raw summary text.
 * @return string The formatted summary HTML.
 */
function tldr_pro_format_summary( $summary ) {
	// Convert bullet points to list
	$lines = explode( "\n", $summary );
	$formatted = '<div class="tldr-pro-summary-content">';
	
	$in_list = false;
	foreach ( $lines as $line ) {
		$line = trim( $line );
		
		if ( empty( $line ) ) {
			continue;
		}
		
		// Check if line starts with bullet point
		if ( preg_match( '/^[-•*]\s+(.+)/', $line, $matches ) ) {
			if ( ! $in_list ) {
				$formatted .= '<ul class="tldr-pro-summary-list">';
				$in_list = true;
			}
			$formatted .= '<li>' . esc_html( $matches[1] ) . '</li>';
		} else {
			if ( $in_list ) {
				$formatted .= '</ul>';
				$in_list = false;
			}
			$formatted .= '<p>' . esc_html( $line ) . '</p>';
		}
	}
	
	if ( $in_list ) {
		$formatted .= '</ul>';
	}
	
	$formatted .= '</div>';
	
	// Add emojis for visual appeal
	$formatted = str_replace( 
		array( '✅', '❌', '⚡', '🎯', '💡', '🚀', '📊', '⭐' ),
		array(
			'<span class="tldr-emoji">✅</span>',
			'<span class="tldr-emoji">❌</span>',
			'<span class="tldr-emoji">⚡</span>',
			'<span class="tldr-emoji">🎯</span>',
			'<span class="tldr-emoji">💡</span>',
			'<span class="tldr-emoji">🚀</span>',
			'<span class="tldr-emoji">📊</span>',
			'<span class="tldr-emoji">⭐</span>'
		),
		$formatted
	);
	
	return $formatted;
}