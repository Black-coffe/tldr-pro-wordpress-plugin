<?php
/**
 * Abstract AI Provider class
 *
 * @package TLDR_Pro
 * @subpackage AI
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract class for AI providers
 *
 * @since 1.0.0
 */
abstract class TLDR_Pro_AI_Provider {

	/**
	 * Provider name
	 *
	 * @var string
	 */
	protected $provider_name = '';

	/**
	 * API key
	 *
	 * @var string
	 */
	protected $api_key = '';

	/**
	 * API endpoint
	 *
	 * @var string
	 */
	protected $api_endpoint = '';

	/**
	 * Default model
	 *
	 * @var string
	 */
	protected $model = '';

	/**
	 * Maximum tokens
	 *
	 * @var int
	 */
	protected $max_tokens = 4096; // Increased from 500 to prevent truncation

	/**
	 * Temperature setting
	 *
	 * @var float
	 */
	protected $temperature = 0.7;

	/**
	 * Request timeout in seconds
	 *
	 * @var int
	 */
	protected $timeout = 30;

	/**
	 * Rate limit - requests per minute
	 *
	 * @var int
	 */
	protected $rate_limit = 60;

	/**
	 * Retry attempts
	 *
	 * @var int
	 */
	protected $retry_attempts = 3;

	/**
	 * Logger instance
	 *
	 * @var TLDR_Pro_Logger
	 */
	protected $logger;

	/**
	 * Constructor
	 *
	 * @param string $api_key API key for the provider.
	 */
	public function __construct( $api_key = '' ) {
		$this->api_key = $api_key;
		$this->logger = TLDR_Pro_Logger::get_instance();
		$this->init();
	}

	/**
	 * Initialize provider-specific settings
	 *
	 * @return void
	 */
	abstract protected function init();

	/**
	 * Generate summary from content
	 *
	 * @param string $content The content to summarize.
	 * @param array  $options Additional options.
	 * @return array|WP_Error Response array or error.
	 */
	abstract public function generate_summary( $content, $options = array() );

	/**
	 * Validate API credentials
	 *
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	abstract public function validate_credentials();

	/**
	 * Get provider name
	 *
	 * @return string
	 */
	public function get_provider_name() {
		return $this->provider_name;
	}

	/**
	 * Set API key
	 *
	 * @param string $api_key API key.
	 * @return void
	 */
	public function set_api_key( $api_key ) {
		$this->api_key = sanitize_text_field( $api_key );
	}

	/**
	 * Get API key
	 *
	 * @return string
	 */
	public function get_api_key() {
		return $this->api_key;
	}

	/**
	 * Set model
	 *
	 * @param string $model Model name.
	 * @return void
	 */
	public function set_model( $model ) {
		$this->model = sanitize_text_field( $model );
	}

	/**
	 * Get model
	 *
	 * @return string
	 */
	public function get_model() {
		return $this->model;
	}

	/**
	 * Set max tokens
	 *
	 * @param int $max_tokens Maximum tokens.
	 * @return void
	 */
	public function set_max_tokens( $max_tokens ) {
		$this->max_tokens = absint( $max_tokens );
	}

	/**
	 * Set temperature
	 *
	 * @param float $temperature Temperature setting.
	 * @return void
	 */
	public function set_temperature( $temperature ) {
		$this->temperature = floatval( $temperature );
	}

	/**
	 * Check rate limit
	 *
	 * @return bool True if within rate limit.
	 */
	protected function check_rate_limit() {
		$transient_key = 'tldr_pro_rate_limit_' . $this->provider_name;
		$requests = get_transient( $transient_key );
		
		if ( false === $requests ) {
			set_transient( $transient_key, 1, MINUTE_IN_SECONDS );
			return true;
		}
		
		if ( $requests >= $this->rate_limit ) {
			$this->logger->log( 
				sprintf( 'Rate limit exceeded for %s', $this->provider_name ),
				'warning'
			);
			return false;
		}
		
		set_transient( $transient_key, $requests + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Make API request with retry logic
	 *
	 * @param string $url     API endpoint URL.
	 * @param array  $args    Request arguments.
	 * @param int    $attempt Current attempt number.
	 * @return array|WP_Error Response or error.
	 */
	protected function make_request( $url, $args, $attempt = 1 ) {
		$this->logger->log('=== AI Provider Make Request Started ===', 'info');
		$this->logger->log('URL: ' . $url, 'info');
		$this->logger->log('Attempt: ' . $attempt . '/' . $this->retry_attempts, 'debug');
		
		if ( ! $this->check_rate_limit() ) {
			$this->logger->log('Rate limit exceeded for provider: ' . $this->provider_name, 'error');
			return new WP_Error( 
				'rate_limit_exceeded',
				__( 'API rate limit exceeded. Please try again later.', 'tldr-pro' )
			);
		}

		$start_time = microtime( true );
		
		$args = wp_parse_args( $args, array(
			'timeout' => $this->timeout,
			'headers' => array(
				'Content-Type' => 'application/json',
			),
		) );

		$this->logger->log('Request timeout: ' . $args['timeout'] . ' seconds', 'debug');
		$this->logger->log('Request headers: ' . json_encode($args['headers']), 'debug');
		if ( isset($args['body']) ) {
			$body_log = $args['body'];
			// Truncate body if too long for logging
			if ( strlen($body_log) > 1000 ) {
				$body_log = substr($body_log, 0, 1000) . '...[truncated]';
			}
			$this->logger->log('Request body: ' . $body_log, 'debug');
		}

		$this->logger->log_api_request( $url, $args, null, 0 );
		
		// Use the appropriate method based on args
		$method = isset( $args['method'] ) ? strtoupper( $args['method'] ) : 'GET';
		$this->logger->log('HTTP Method: ' . $method, 'info');
		
		$this->logger->log('Sending ' . $method . ' request to API...', 'info');
		
		if ( $method === 'POST' ) {
			$response = wp_remote_post( $url, $args );
		} else if ( $method === 'GET' ) {
			$response = wp_remote_get( $url, $args );
		} else {
			$response = wp_remote_request( $url, $args );
		}
		
		$duration = microtime( true ) - $start_time;
		$this->logger->log('Request completed in ' . round($duration, 2) . ' seconds', 'info');
		
		if ( is_wp_error( $response ) ) {
			$this->logger->log('Request FAILED with WP_Error', 'error');
			$this->logger->log('Error message: ' . $response->get_error_message(), 'error');
			$this->logger->log('Error code: ' . $response->get_error_code(), 'error');
			$this->logger->log_api_request( $url, $args, $response, $duration );
			
			if ( $attempt < $this->retry_attempts ) {
				$wait_time = pow( 2, $attempt );
				$this->logger->log( 
					sprintf( 'Retrying request in %d seconds (attempt %d/%d)', $wait_time, $attempt + 1, $this->retry_attempts ),
					'info'
				);
				sleep( $wait_time );
				return $this->make_request( $url, $args, $attempt + 1 );
			}
			
			$this->logger->log('Max retry attempts reached, returning error', 'error');
			return $response;
		}
		
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		
		$this->logger->log('Response code: ' . $response_code, 'info');
		$this->logger->log('Response body length: ' . strlen($response_body) . ' chars', 'debug');
		
		// Log truncated response for debugging
		$body_log = $response_body;
		if ( strlen($body_log) > 500 ) {
			$body_log = substr($body_log, 0, 500) . '...[truncated]';
		}
		$this->logger->log('Response body: ' . $body_log, 'debug');
		
		$this->logger->log_api_request( $url, $args, array(
			'code' => $response_code,
			'body' => $response_body,
		), $duration );
		
		if ( $response_code >= 500 && $attempt < $this->retry_attempts ) {
			$wait_time = pow( 2, $attempt );
			$this->logger->log( 
				sprintf( 'Server error %d. Retrying in %d seconds (attempt %d/%d)', $response_code, $wait_time, $attempt + 1, $this->retry_attempts ),
				'warning'
			);
			sleep( $wait_time );
			return $this->make_request( $url, $args, $attempt + 1 );
		}
		
		if ( $response_code >= 400 ) {
			return new WP_Error(
				'api_error',
				sprintf( __( 'API error: %s', 'tldr-pro' ), $response_body ),
				array( 'status' => $response_code )
			);
		}
		
		return array(
			'code' => $response_code,
			'body' => $response_body,
			'duration' => $duration,
		);
	}

	/**
	 * Count tokens in text (approximate)
	 *
	 * @param string $text Text to count tokens.
	 * @return int Approximate token count.
	 */
	protected function count_tokens( $text ) {
		$words = str_word_count( $text );
		return (int) ( $words * 1.3 );
	}

	/**
	 * Detect content language
	 *
	 * @param string $content Content to analyze.
	 * @return string Detected language name.
	 */
	protected function detect_content_language( $content ) {
		// Remove HTML tags for cleaner detection
		$clean_content = wp_strip_all_tags( $content );
		
		// Get first 500 characters for language detection
		$sample = mb_substr( $clean_content, 0, 500 );
		
		// Common language patterns
		$language_patterns = array(
			'russian' => array(
				'chars' => '/[а-яА-ЯёЁ]{3,}/u',
				'words' => array( 'и', 'в', 'на', 'с', 'по', 'для', 'это', 'что', 'как', 'из' ),
				'name' => 'Russian'
			),
			'chinese' => array(
				'chars' => '/[\x{4e00}-\x{9fa5}]{2,}/u',
				'words' => array(),
				'name' => 'Chinese'
			),
			'japanese' => array(
				'chars' => '/[\x{3040}-\x{309f}\x{30a0}-\x{30ff}]{2,}/u',
				'words' => array(),
				'name' => 'Japanese'
			),
			'korean' => array(
				'chars' => '/[\x{ac00}-\x{d7af}\x{1100}-\x{11ff}\x{3130}-\x{318f}]{2,}/u',
				'words' => array(),
				'name' => 'Korean'
			),
			'arabic' => array(
				'chars' => '/[\x{0600}-\x{06ff}]{3,}/u',
				'words' => array(),
				'name' => 'Arabic'
			),
			'hebrew' => array(
				'chars' => '/[\x{0590}-\x{05ff}]{3,}/u',
				'words' => array(),
				'name' => 'Hebrew'
			),
			'spanish' => array(
				'chars' => '/[áéíóúñÁÉÍÓÚÑ]/u',
				'words' => array( 'el', 'la', 'de', 'que', 'y', 'en', 'un', 'por', 'con', 'para' ),
				'name' => 'Spanish'
			),
			'french' => array(
				'chars' => '/[àâçéèêëîïôûùüÿæœÀÂÇÉÈÊËÎÏÔÛÙÜŸÆŒ]/u',
				'words' => array( 'le', 'de', 'un', 'être', 'et', 'à', 'il', 'avoir', 'ne', 'je' ),
				'name' => 'French'
			),
			'german' => array(
				'chars' => '/[äöüßÄÖÜ]/u',
				'words' => array( 'der', 'die', 'das', 'und', 'ist', 'ich', 'nicht', 'ein', 'sie', 'mit' ),
				'name' => 'German'
			),
			'polish' => array(
				'chars' => '/[ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]/u',
				'words' => array( 'i', 'w', 'na', 'z', 'do', 'nie', 'że', 'się', 'jest', 'to' ),
				'name' => 'Polish'
			),
			'ukrainian' => array(
				'chars' => '/[а-щьюяїієґА-ЩЬЮЯЇІЄҐ]{3,}/u',
				'words' => array( 'і', 'в', 'на', 'з', 'до', 'не', 'що', 'це', 'як', 'для' ),
				'name' => 'Ukrainian'
			)
		);
		
		$scores = array();
		
		// Check each language pattern
		foreach ( $language_patterns as $lang => $patterns ) {
			$score = 0;
			
			// Check character patterns
			if ( preg_match_all( $patterns['chars'], $sample, $matches ) ) {
				$score += count( $matches[0] ) * 10;
			}
			
			// Check common words
			if ( ! empty( $patterns['words'] ) ) {
				$sample_lower = mb_strtolower( $sample );
				foreach ( $patterns['words'] as $word ) {
					if ( mb_strpos( $sample_lower, ' ' . $word . ' ' ) !== false ||
					     mb_strpos( $sample_lower, $word . ' ' ) === 0 ||
					     mb_strpos( $sample_lower, ' ' . $word ) === mb_strlen( $sample_lower ) - mb_strlen( $word ) - 1 ) {
						$score += 5;
					}
				}
			}
			
			if ( $score > 0 ) {
				$scores[ $lang ] = $score;
			}
		}
		
		// Return language with highest score
		if ( ! empty( $scores ) ) {
			arsort( $scores );
			$detected_lang = array_key_first( $scores );
			return $language_patterns[ $detected_lang ]['name'];
		}
		
		// Default to English if no specific language detected
		return 'English';
	}

	/**
	 * Create summary prompt
	 *
	 * @param string $content Content to summarize.
	 * @param array  $options Additional options.
	 * @return string Generated prompt.
	 */
	protected function create_prompt( $content, $options = array() ) {
		$defaults = array(
			'language' => 'English',
			'style' => 'concise',
			'bullets' => 3,
			'emoji' => true,
		);
		
		$options = wp_parse_args( $options, $defaults );
		
		$prompt = "Please provide a TL;DR summary of the following content in {$options['language']}. ";
		$prompt .= "The summary should be {$options['style']} with {$options['bullets']} key points. ";
		
		if ( $options['emoji'] ) {
			$prompt .= "Use relevant emojis to make it engaging. ";
		}
		
		$prompt .= "\n\nContent:\n" . $content;
		
		return $prompt;
	}

	/**
	 * Cache response
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $data  Data to cache.
	 * @param int    $ttl   Time to live in seconds.
	 * @return bool Success status.
	 */
	protected function cache_response( $key, $data, $ttl = HOUR_IN_SECONDS ) {
		$cache_key = 'tldr_pro_cache_' . md5( $key );
		return set_transient( $cache_key, $data, $ttl );
	}

	/**
	 * Get cached response
	 *
	 * @param string $key Cache key.
	 * @return mixed|false Cached data or false.
	 */
	protected function get_cached_response( $key ) {
		$cache_key = 'tldr_pro_cache_' . md5( $key );
		return get_transient( $cache_key );
	}

	/**
	 * Clear cache for a specific key
	 *
	 * @param string $key Cache key.
	 * @return bool Success status.
	 */
	protected function clear_cache( $key ) {
		$cache_key = 'tldr_pro_cache_' . md5( $key );
		return delete_transient( $cache_key );
	}

	/**
	 * Get usage statistics
	 *
	 * @return array Statistics array.
	 */
	public function get_statistics() {
		return array(
			'provider' => $this->provider_name,
			'model' => $this->model,
			'requests_today' => $this->get_requests_today(),
			'tokens_used' => $this->get_tokens_used(),
			'average_response_time' => $this->get_average_response_time(),
		);
	}

	/**
	 * Get requests made today
	 *
	 * @return int Number of requests.
	 */
	protected function get_requests_today() {
		$option_key = 'tldr_pro_requests_' . $this->provider_name . '_' . date( 'Y-m-d' );
		return absint( get_option( $option_key, 0 ) );
	}

	/**
	 * Increment request counter
	 *
	 * @return void
	 */
	protected function increment_request_counter() {
		$option_key = 'tldr_pro_requests_' . $this->provider_name . '_' . date( 'Y-m-d' );
		$current = $this->get_requests_today();
		update_option( $option_key, $current + 1 );
	}

	/**
	 * Get total tokens used
	 *
	 * @return int Token count.
	 */
	protected function get_tokens_used() {
		$option_key = 'tldr_pro_tokens_' . $this->provider_name;
		return absint( get_option( $option_key, 0 ) );
	}

	/**
	 * Add to token counter
	 *
	 * @param int $tokens Number of tokens to add.
	 * @return void
	 */
	protected function add_tokens_used( $tokens ) {
		$option_key = 'tldr_pro_tokens_' . $this->provider_name;
		$current = $this->get_tokens_used();
		update_option( $option_key, $current + $tokens );
	}

	/**
	 * Get average response time
	 *
	 * @return float Average time in seconds.
	 */
	protected function get_average_response_time() {
		$option_key = 'tldr_pro_response_times_' . $this->provider_name;
		$times = get_option( $option_key, array() );
		
		if ( empty( $times ) ) {
			return 0;
		}
		
		return array_sum( $times ) / count( $times );
	}

	/**
	 * Record response time
	 *
	 * @param float $time Response time in seconds.
	 * @return void
	 */
	protected function record_response_time( $time ) {
		$option_key = 'tldr_pro_response_times_' . $this->provider_name;
		$times = get_option( $option_key, array() );
		
		$times[] = $time;
		
		if ( count( $times ) > 100 ) {
			array_shift( $times );
		}
		
		update_option( $option_key, $times );
	}
}