<?php
/**
 * Performance optimization handler for TL;DR Pro plugin.
 *
 * Handles asset loading, browser caching, and performance optimizations.
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
 * Performance optimization class.
 */
class TLDR_Pro_Performance {

	/**
	 * Initialize performance optimizations.
	 */
	public static function init() {
		// Asset loading optimizations
		add_filter( 'tldr_pro_asset_url', array( __CLASS__, 'get_optimized_asset_url' ), 10, 2 );
		add_filter( 'script_loader_tag', array( __CLASS__, 'add_async_defer_attributes' ), 10, 2 );
		add_filter( 'style_loader_tag', array( __CLASS__, 'add_preload_attributes' ), 10, 2 );
		
		// Browser caching headers
		add_action( 'send_headers', array( __CLASS__, 'set_cache_headers' ) );
		
		// Lazy loading
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'setup_lazy_loading' ), 100 );
		
		// Resource hints
		add_action( 'wp_head', array( __CLASS__, 'add_resource_hints' ), 1 );
	}

	/**
	 * Get optimized asset URL (minified in production).
	 *
	 * @param string $url  Original asset URL.
	 * @param string $type Asset type (css or js).
	 * @return string Optimized asset URL.
	 */
	public static function get_optimized_asset_url( $url, $type = 'js' ) {
		// Use minified version in production
		if ( ! defined( 'SCRIPT_DEBUG' ) || ! SCRIPT_DEBUG ) {
			// Check if minified version exists
			$path = str_replace( plugins_url(), WP_PLUGIN_DIR, $url );
			$min_path = str_replace( '.' . $type, '.min.' . $type, $path );
			
			if ( file_exists( $min_path ) ) {
				$url = str_replace( '.' . $type, '.min.' . $type, $url );
			}
		}
		
		// Add version for cache busting
		$url = add_query_arg( 'ver', TLDR_PRO_VERSION, $url );
		
		return $url;
	}

	/**
	 * Add async/defer attributes to scripts.
	 *
	 * @param string $tag    Script tag.
	 * @param string $handle Script handle.
	 * @return string Modified script tag.
	 */
	public static function add_async_defer_attributes( $tag, $handle ) {
		// Scripts that should load async
		$async_scripts = array(
			'tldr-pro-button',
			'tldr-pro-analytics'
		);
		
		// Scripts that should defer
		$defer_scripts = array(
			'tldr-pro-admin',
			'tldr-pro-modal'
		);
		
		if ( in_array( $handle, $async_scripts ) ) {
			return str_replace( ' src', ' async src', $tag );
		}
		
		if ( in_array( $handle, $defer_scripts ) ) {
			return str_replace( ' src', ' defer src', $tag );
		}
		
		return $tag;
	}

	/**
	 * Add preload attributes to critical styles.
	 *
	 * @param string $tag    Style tag.
	 * @param string $handle Style handle.
	 * @return string Modified style tag.
	 */
	public static function add_preload_attributes( $tag, $handle ) {
		// Critical styles that should preload
		$critical_styles = array(
			'tldr-pro-button'
		);
		
		if ( in_array( $handle, $critical_styles ) ) {
			$tag = str_replace( "rel='stylesheet'", "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"", $tag );
			// Add noscript fallback
			$tag .= "<noscript>" . str_replace( "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"", "rel='stylesheet'", $tag ) . "</noscript>";
		}
		
		return $tag;
	}

	/**
	 * Set browser cache headers for plugin assets.
	 */
	public static function set_cache_headers() {
		if ( ! is_admin() && self::is_plugin_asset() ) {
			// Set cache headers for 1 year
			header( 'Cache-Control: public, max-age=31536000, immutable' );
			header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + 31536000 ) . ' GMT' );
			
			// Set ETag for conditional requests
			$etag = '"' . md5( $_SERVER['REQUEST_URI'] . TLDR_PRO_VERSION ) . '"';
			header( 'ETag: ' . $etag );
			
			// Check if-none-match header
			if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag ) {
				header( 'HTTP/1.1 304 Not Modified' );
				exit;
			}
		}
	}

	/**
	 * Check if current request is for a plugin asset.
	 *
	 * @return bool True if plugin asset.
	 */
	private static function is_plugin_asset() {
		$request_uri = $_SERVER['REQUEST_URI'];
		return strpos( $request_uri, '/wp-content/plugins/tldr-pro/' ) !== false &&
		       ( strpos( $request_uri, '.css' ) !== false || strpos( $request_uri, '.js' ) !== false );
	}

	/**
	 * Setup lazy loading for non-critical scripts.
	 */
	public static function setup_lazy_loading() {
		// Only on frontend
		if ( is_admin() ) {
			return;
		}
		
		// Add intersection observer for lazy loading
		wp_add_inline_script( 'tldr-pro-button', self::get_lazy_load_script(), 'before' );
	}

	/**
	 * Get lazy loading script.
	 *
	 * @return string JavaScript code for lazy loading.
	 */
	private static function get_lazy_load_script() {
		return "
		// TL;DR Pro Lazy Loading
		(function() {
			// Check if IntersectionObserver is supported
			if (!('IntersectionObserver' in window)) {
				return;
			}
			
			// Lazy load TL;DR buttons
			var lazyLoadButtons = function() {
				var buttons = document.querySelectorAll('.tldr-pro-button:not(.tldr-loaded)');
				
				var buttonObserver = new IntersectionObserver(function(entries) {
					entries.forEach(function(entry) {
						if (entry.isIntersecting) {
							var button = entry.target;
							button.classList.add('tldr-loaded');
							buttonObserver.unobserve(button);
							
							// Trigger button initialization
							if (typeof TLDRProButton !== 'undefined') {
								TLDRProButton.initButton(button);
							}
						}
					});
				}, {
					rootMargin: '50px'
				});
				
				buttons.forEach(function(button) {
					buttonObserver.observe(button);
				});
			};
			
			// Run on DOM ready
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', lazyLoadButtons);
			} else {
				lazyLoadButtons();
			}
		})();
		";
	}

	/**
	 * Add resource hints for performance.
	 */
	public static function add_resource_hints() {
		// Preconnect to API endpoints
		$provider = TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_active_provider' );
		
		if ( 'deepseek' === $provider ) {
			echo '<link rel="preconnect" href="https://api.deepseek.com">' . "\n";
			echo '<link rel="dns-prefetch" href="//api.deepseek.com">' . "\n";
		} elseif ( 'gemini' === $provider ) {
			echo '<link rel="preconnect" href="https://generativelanguage.googleapis.com">' . "\n";
			echo '<link rel="dns-prefetch" href="//generativelanguage.googleapis.com">' . "\n";
		}
		
		// Prefetch plugin assets
		$plugin_url = plugin_dir_url( dirname( __FILE__ ) );
		echo '<link rel="prefetch" href="' . esc_url( $plugin_url . 'public/css/tldr-pro-button.min.css' ) . '">' . "\n";
		echo '<link rel="prefetch" href="' . esc_url( $plugin_url . 'public/js/tldr-pro-button.min.js' ) . '">' . "\n";
	}

	/**
	 * Get asset version for cache busting.
	 *
	 * @param string $file File path.
	 * @return string Version string.
	 */
	public static function get_asset_version( $file ) {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			// Use file modification time in development
			return filemtime( $file );
		}
		
		// Use plugin version in production
		return TLDR_PRO_VERSION;
	}

	/**
	 * Optimize database queries.
	 */
	public static function optimize_queries() {
		// Add query optimization filters
		add_filter( 'posts_request', array( __CLASS__, 'optimize_post_queries' ), 10, 2 );
		add_filter( 'tldr_pro_database_query', array( __CLASS__, 'add_query_cache' ), 10, 2 );
	}

	/**
	 * Optimize post queries.
	 *
	 * @param string   $request SQL query.
	 * @param WP_Query $query   Query object.
	 * @return string Modified query.
	 */
	public static function optimize_post_queries( $request, $query ) {
		// Add SQL_CALC_FOUND_ROWS optimization
		if ( ! $query->is_singular() && strpos( $request, 'SQL_CALC_FOUND_ROWS' ) !== false ) {
			$request = str_replace( 'SQL_CALC_FOUND_ROWS', '', $request );
		}
		
		return $request;
	}

	/**
	 * Add query cache support.
	 *
	 * @param string $query     SQL query.
	 * @param string $cache_key Cache key.
	 * @return string Modified query.
	 */
	public static function add_query_cache( $query, $cache_key ) {
		// Add SQL_CACHE hint for MySQL query cache
		if ( strpos( $query, 'SELECT' ) === 0 && strpos( $query, 'SQL_CACHE' ) === false ) {
			$query = str_replace( 'SELECT', 'SELECT SQL_CACHE', $query );
		}
		
		return $query;
	}
}

// Initialize performance optimizations
add_action( 'init', array( 'TLDR_Pro_Performance', 'init' ) );
add_action( 'plugins_loaded', array( 'TLDR_Pro_Performance', 'optimize_queries' ) );