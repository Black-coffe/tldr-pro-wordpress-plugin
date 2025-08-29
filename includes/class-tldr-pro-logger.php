<?php
/**
 * Logger class for the plugin.
 *
 * Handles all logging operations including debug, info, warning, and error logs.
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
 * Logger class.
 *
 * @since      1.0.0
 * @package    TLDR_Pro
 * @subpackage TLDR_Pro/includes
 */
class TLDR_Pro_Logger {

	/**
	 * The single instance of the class.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      TLDR_Pro_Logger    $instance    The single instance of the class.
	 */
	protected static $instance = null;

	/**
	 * Log file path.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $log_file    Path to the log file.
	 */
	protected $log_file;

	/**
	 * Debug mode status.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      bool    $debug_mode    Whether debug mode is enabled.
	 */
	protected $debug_mode;

	/**
	 * Log levels.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      array    $log_levels    Available log levels.
	 */
	protected $log_levels = array(
		'debug'   => 0,
		'info'    => 1,
		'warning' => 2,
		'error'   => 3,
		'fatal'   => 4,
	);

	/**
	 * Current log level.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      int    $current_log_level    Current minimum log level.
	 */
	protected $current_log_level = 1; // Default to 'info'

	/**
	 * Main TLDR_Pro_Logger Instance.
	 *
	 * @since    1.0.0
	 * @static
	 * @return   TLDR_Pro_Logger    Main instance.
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 */
	protected function __construct() {
		$this->setup_log_file();
		$this->setup_debug_mode();
		$this->setup_log_level();
		
		// Add WordPress debug logging if WP_DEBUG is enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			add_action( 'init', array( $this, 'log_system_info' ) );
		}
	}

	/**
	 * Setup log file path.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function setup_log_file() {
		$upload_dir = wp_upload_dir();
		$log_dir = $upload_dir['basedir'] . '/tldr-pro/logs';
		
		// Create log directory if it doesn't exist
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
			
			// Add .htaccess to prevent direct access
			$htaccess_file = $log_dir . '/.htaccess';
			if ( ! file_exists( $htaccess_file ) ) {
				file_put_contents( $htaccess_file, 'Deny from all' );
			}
			
			// Add index.php for extra security
			$index_file = $log_dir . '/index.php';
			if ( ! file_exists( $index_file ) ) {
				file_put_contents( $index_file, '<?php // Silence is golden' );
			}
		}
		
		// Set log file with date rotation
		$date = current_time( 'Y-m-d' );
		$this->log_file = $log_dir . '/tldr-pro-' . $date . '.log';
		
		// Rotate old logs (keep last 7 days)
		$this->rotate_logs( $log_dir );
	}

	/**
	 * Setup debug mode.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function setup_debug_mode() {
		$this->debug_mode = get_option( 'tldr_pro_enable_debug', false );
		
		// Override with WP_DEBUG constant if set
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->debug_mode = true;
		}
		
		// Allow override via constant
		if ( defined( 'TLDR_PRO_DEBUG' ) ) {
			$this->debug_mode = TLDR_PRO_DEBUG;
		}
	}

	/**
	 * Setup log level.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function setup_log_level() {
		$level = get_option( 'tldr_pro_log_level', 'info' );
		
		if ( isset( $this->log_levels[ $level ] ) ) {
			$this->current_log_level = $this->log_levels[ $level ];
		}
		
		// In debug mode, log everything
		if ( $this->debug_mode ) {
			$this->current_log_level = 0;
		}
	}

	/**
	 * Log a message.
	 *
	 * @since    1.0.0
	 * @param    string    $message    The message to log.
	 * @param    string    $level      The log level.
	 * @param    array     $context    Additional context data.
	 */
	public function log( $message, $level = 'info', $context = array() ) {
		// Check if we should log this level
		if ( ! $this->should_log( $level ) ) {
			return;
		}
		
		// Format the log entry
		$entry = $this->format_log_entry( $message, $level, $context );
		
		// Write to log file
		$this->write_to_file( $entry );
		
		// Also log to WordPress debug.log if in debug mode
		if ( $this->debug_mode && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[TL;DR Pro] ' . $entry );
		}
		
		// For fatal errors, also trigger WordPress error
		if ( 'fatal' === $level ) {
			wp_die( 
				esc_html( $message ), 
				esc_html__( 'TL;DR Pro Fatal Error', 'tldr-pro' ),
				array( 'response' => 500 )
			);
		}
	}

	/**
	 * Log debug message.
	 *
	 * @since    1.0.0
	 * @param    string    $message    The message to log.
	 * @param    array     $context    Additional context data.
	 */
	public function debug( $message, $context = array() ) {
		$this->log( $message, 'debug', $context );
	}

	/**
	 * Log info message.
	 *
	 * @since    1.0.0
	 * @param    string    $message    The message to log.
	 * @param    array     $context    Additional context data.
	 */
	public function info( $message, $context = array() ) {
		$this->log( $message, 'info', $context );
	}

	/**
	 * Log warning message.
	 *
	 * @since    1.0.0
	 * @param    string    $message    The message to log.
	 * @param    array     $context    Additional context data.
	 */
	public function warning( $message, $context = array() ) {
		$this->log( $message, 'warning', $context );
	}

	/**
	 * Log error message.
	 *
	 * @since    1.0.0
	 * @param    string    $message    The message to log.
	 * @param    array     $context    Additional context data.
	 */
	public function error( $message, $context = array() ) {
		$this->log( $message, 'error', $context );
	}

	/**
	 * Log fatal error message.
	 *
	 * @since    1.0.0
	 * @param    string    $message    The message to log.
	 * @param    array     $context    Additional context data.
	 */
	public function fatal( $message, $context = array() ) {
		$this->log( $message, 'fatal', $context );
	}

	/**
	 * Log API request.
	 *
	 * @since    1.0.0
	 * @param    string    $endpoint    The API endpoint.
	 * @param    array     $request     Request data.
	 * @param    array     $response    Response data.
	 * @param    float     $duration    Request duration in seconds.
	 */
	public function log_api_request( $endpoint, $request, $response, $duration = 0 ) {
		$context = array(
			'endpoint' => $endpoint,
			'request'  => $request,
			'response' => $response,
			'duration' => $duration,
		);
		
		$message = sprintf(
			'API Request to %s completed in %.2f seconds',
			$endpoint,
			$duration
		);
		
		$this->info( $message, $context );
	}

	/**
	 * Log database query.
	 *
	 * @since    1.0.0
	 * @param    string    $query      The SQL query.
	 * @param    float     $duration   Query duration in seconds.
	 * @param    bool      $success    Whether the query was successful.
	 */
	public function log_query( $query, $duration = 0, $success = true ) {
		if ( ! $this->debug_mode ) {
			return;
		}
		
		$context = array(
			'query'    => $query,
			'duration' => $duration,
			'success'  => $success,
		);
		
		$level = $success ? 'debug' : 'error';
		$message = sprintf(
			'Database query %s in %.4f seconds',
			$success ? 'executed' : 'failed',
			$duration
		);
		
		$this->log( $message, $level, $context );
	}

	/**
	 * Log exception.
	 *
	 * @since    1.0.0
	 * @param    Exception    $exception    The exception to log.
	 */
	public function log_exception( $exception ) {
		$context = array(
			'file'  => $exception->getFile(),
			'line'  => $exception->getLine(),
			'trace' => $exception->getTraceAsString(),
		);
		
		$this->error( $exception->getMessage(), $context );
	}

	/**
	 * Check if should log this level.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $level    The log level.
	 * @return   bool                Whether to log this level.
	 */
	private function should_log( $level ) {
		if ( ! isset( $this->log_levels[ $level ] ) ) {
			return false;
		}
		
		return $this->log_levels[ $level ] >= $this->current_log_level;
	}

	/**
	 * Format log entry.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $message    The message to log.
	 * @param    string    $level      The log level.
	 * @param    array     $context    Additional context data.
	 * @return   string                 Formatted log entry.
	 */
	private function format_log_entry( $message, $level, $context ) {
		$timestamp = current_time( 'Y-m-d H:i:s' );
		$level_upper = strtoupper( $level );
		
		// Basic log entry
		$entry = sprintf( '[%s] [%s] %s', $timestamp, $level_upper, $message );
		
		// Add context if available
		if ( ! empty( $context ) ) {
			$entry .= ' | Context: ' . wp_json_encode( $context, JSON_PRETTY_PRINT );
		}
		
		// Add memory usage in debug mode
		if ( $this->debug_mode ) {
			$memory = size_format( memory_get_usage( true ), 2 );
			$peak_memory = size_format( memory_get_peak_usage( true ), 2 );
			$entry .= sprintf( ' | Memory: %s (Peak: %s)', $memory, $peak_memory );
		}
		
		// Add user info for certain actions
		if ( in_array( $level, array( 'warning', 'error', 'fatal' ), true ) ) {
			$current_user = wp_get_current_user();
			if ( $current_user->ID ) {
				$entry .= sprintf( ' | User: %s (ID: %d)', $current_user->user_login, $current_user->ID );
			}
		}
		
		return $entry;
	}

	/**
	 * Write to log file.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $entry    The log entry to write.
	 */
	private function write_to_file( $entry ) {
		if ( ! is_writable( dirname( $this->log_file ) ) ) {
			return;
		}
		
		// Add line break
		$entry .= PHP_EOL;
		
		// Write to file with lock
		file_put_contents( $this->log_file, $entry, FILE_APPEND | LOCK_EX );
		
		// Check file size and rotate if needed
		if ( file_exists( $this->log_file ) && filesize( $this->log_file ) > 10485760 ) { // 10MB
			$this->rotate_current_log();
		}
	}

	/**
	 * Rotate current log file.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function rotate_current_log() {
		$timestamp = current_time( 'Y-m-d-His' );
		$new_name = str_replace( '.log', '-' . $timestamp . '.log', $this->log_file );
		
		rename( $this->log_file, $new_name );
		
		// Create new log file
		touch( $this->log_file );
	}

	/**
	 * Rotate old logs.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $log_dir    The log directory.
	 */
	private function rotate_logs( $log_dir ) {
		$files = glob( $log_dir . '/tldr-pro-*.log' );
		
		if ( ! $files ) {
			return;
		}
		
		// Sort by modification time
		usort( $files, function( $a, $b ) {
			return filemtime( $a ) - filemtime( $b );
		});
		
		// Keep only last 7 days of logs
		$keep_days = apply_filters( 'tldr_pro_log_retention_days', 7 );
		$cutoff_time = strtotime( '-' . $keep_days . ' days' );
		
		foreach ( $files as $file ) {
			if ( filemtime( $file ) < $cutoff_time ) {
				unlink( $file );
			}
		}
	}

	/**
	 * Log system info.
	 *
	 * @since    1.0.0
	 */
	public function log_system_info() {
		if ( ! $this->debug_mode ) {
			return;
		}
		
		$system_info = array(
			'php_version'       => PHP_VERSION,
			'wp_version'        => get_bloginfo( 'version' ),
			'plugin_version'    => TLDR_PRO_VERSION,
			'memory_limit'      => ini_get( 'memory_limit' ),
			'max_execution_time' => ini_get( 'max_execution_time' ),
			'active_theme'      => get_option( 'stylesheet' ),
			'active_plugins'    => get_option( 'active_plugins' ),
			'site_url'          => get_site_url(),
			'home_url'          => get_home_url(),
		);
		
		$this->debug( 'System Information', $system_info );
	}

	/**
	 * Clear logs.
	 *
	 * @since    1.0.0
	 * @param    bool    $all    Whether to clear all logs or just current.
	 */
	public function clear_logs( $all = false ) {
		if ( $all ) {
			$upload_dir = wp_upload_dir();
			$log_dir = $upload_dir['basedir'] . '/tldr-pro/logs';
			$files = glob( $log_dir . '/tldr-pro-*.log' );
			
			if ( $files ) {
				foreach ( $files as $file ) {
					unlink( $file );
				}
			}
		} else {
			if ( file_exists( $this->log_file ) ) {
				unlink( $this->log_file );
				touch( $this->log_file );
			}
		}
		
		$this->info( 'Logs cleared' );
	}

	/**
	 * Get log file path.
	 *
	 * @since    1.0.0
	 * @return   string    The log file path.
	 */
	public function get_log_file() {
		return $this->log_file;
	}

	/**
	 * Get log file URL.
	 *
	 * @since    1.0.0
	 * @return   string    The log file URL.
	 */
	public function get_log_url() {
		$upload_dir = wp_upload_dir();
		$log_url = str_replace( 
			$upload_dir['basedir'], 
			$upload_dir['baseurl'], 
			$this->log_file 
		);
		
		return $log_url;
	}

	/**
	 * Read log file.
	 *
	 * @since    1.0.0
	 * @param    int      $lines    Number of lines to read (0 for all).
	 * @param    string   $level    Filter by log level.
	 * @return   array              Array of log entries.
	 */
	public function read_log( $lines = 100, $level = '' ) {
		if ( ! file_exists( $this->log_file ) ) {
			return array();
		}
		
		$content = file_get_contents( $this->log_file );
		$log_lines = explode( PHP_EOL, $content );
		
		// Filter by level if specified
		if ( ! empty( $level ) ) {
			$level_upper = '[' . strtoupper( $level ) . ']';
			$log_lines = array_filter( $log_lines, function( $line ) use ( $level_upper ) {
				return strpos( $line, $level_upper ) !== false;
			});
		}
		
		// Get last N lines
		if ( $lines > 0 ) {
			$log_lines = array_slice( $log_lines, -$lines );
		}
		
		return array_filter( $log_lines ); // Remove empty lines
	}
}