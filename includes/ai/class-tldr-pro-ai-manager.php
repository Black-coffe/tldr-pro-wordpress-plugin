<?php
/**
 * AI Manager class for handling multiple AI providers
 *
 * @package TLDR_Pro
 * @subpackage AI
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI Manager class
 *
 * @since 1.0.0
 */
class TLDR_Pro_AI_Manager {

	/**
	 * Singleton instance
	 *
	 * @var TLDR_Pro_AI_Manager
	 */
	private static $instance = null;

	/**
	 * Available AI providers
	 *
	 * @var array
	 */
	private $providers = array();

	/**
	 * Active provider instance
	 *
	 * @var TLDR_Pro_AI_Provider
	 */
	private $active_provider = null;

	/**
	 * Logger instance
	 *
	 * @var TLDR_Pro_Logger
	 */
	private $logger;

	/**
	 * Provider configuration
	 *
	 * @var array
	 */
	private $provider_config = array(
		'deepseek' => array(
			'name' => 'DeepSeek',
			'class' => 'TLDR_Pro_DeepSeek_API',
			'requires_api_key' => true,
			'supports_streaming' => false,
			'supports_vision' => false,
			'supports_prefix_caching' => true,
			'default_model' => 'deepseek-chat',
		),
		'gemini' => array(
			'name' => 'Google Gemini',
			'class' => 'TLDR_Pro_Gemini_API',
			'requires_api_key' => true,
			'supports_streaming' => true,
			'supports_vision' => true,
			'default_model' => 'gemini-1.5-flash',
		),
		'claude' => array(
			'name' => 'Claude (Anthropic)',
			'class' => 'TLDR_Pro_Claude_API',
			'requires_api_key' => true,
			'supports_streaming' => true,
			'supports_vision' => true,
			'supports_function_calling' => true,
			'default_model' => 'claude-sonnet-4-20250514',
		),
		'gpt' => array(
			'name' => 'OpenAI GPT',
			'class' => 'TLDR_Pro_GPT_API',
			'requires_api_key' => true,
			'supports_streaming' => true,
			'supports_vision' => true,
			'supports_function_calling' => true,
			'default_model' => 'gpt-4-turbo',
		),
	);

	/**
	 * Get singleton instance
	 *
	 * @return TLDR_Pro_AI_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->logger = TLDR_Pro_Logger::get_instance();
		$this->load_providers();
		$this->set_active_provider();
	}

	/**
	 * Load all available providers
	 *
	 * @return void
	 */
	private function load_providers() {
		foreach ( $this->provider_config as $key => $config ) {
			if ( class_exists( $config['class'] ) ) {
				$api_key = $this->get_provider_api_key( $key );
				$this->providers[ $key ] = new $config['class']( $api_key );
			}
		}

		$this->logger->log(
			sprintf( 'Loaded %d AI providers', count( $this->providers ) ),
			'info'
		);
	}

	/**
	 * Set the active provider based on settings
	 *
	 * @param string|null $provider_key Provider key to activate.
	 * @return bool Success status.
	 */
	public function set_active_provider( $provider_key = null ) {
		if ( null === $provider_key ) {
			$provider_key = get_option( 'tldr_pro_active_provider', 'deepseek' );
		}

		if ( ! isset( $this->providers[ $provider_key ] ) ) {
			$this->logger->log(
				sprintf( 'Provider %s not found', $provider_key ),
				'error'
			);
			return false;
		}

		$this->active_provider = $this->providers[ $provider_key ];
		
		// Update model if specified
		$model = get_option( 'tldr_pro_' . $provider_key . '_model' );
		if ( $model ) {
			$this->active_provider->set_model( $model );
		}

		$this->logger->log(
			sprintf( 'Active provider set to: %s', $provider_key ),
			'info'
		);

		return true;
	}

	/**
	 * Get active provider
	 *
	 * @return TLDR_Pro_AI_Provider|null
	 */
	public function get_active_provider() {
		return $this->active_provider;
	}

	/**
	 * Get all available providers
	 *
	 * @return array
	 */
	public function get_providers() {
		return $this->providers;
	}

	/**
	 * Get provider configuration
	 *
	 * @param string|null $provider_key Provider key.
	 * @return array
	 */
	public function get_provider_config( $provider_key = null ) {
		if ( null === $provider_key ) {
			return $this->provider_config;
		}

		return isset( $this->provider_config[ $provider_key ] ) 
			? $this->provider_config[ $provider_key ] 
			: array();
	}

	/**
	 * Generate summary using active provider
	 *
	 * @param string $content Content to summarize.
	 * @param array  $options Options.
	 * @return array|WP_Error
	 */
	public function generate_summary( $content, $options = array() ) {
		$this->logger->log('=== AI Manager Generate Summary Started ===', 'info');
		$this->logger->log('Content length: ' . strlen($content) . ' chars', 'debug');
		$this->logger->log('Options received: ' . json_encode($options), 'debug');
		
		if ( ! $this->active_provider ) {
			$this->logger->log('ERROR: No active provider configured', 'error');
			$this->logger->log('Available providers: ' . json_encode(array_keys($this->providers)), 'debug');
			return new WP_Error(
				'no_active_provider',
				__( 'No active AI provider configured.', 'tldr-pro' )
			);
		}

		$this->logger->log('Active provider: ' . $this->active_provider->get_provider_name(), 'info');
		$this->logger->log('Provider class: ' . get_class($this->active_provider), 'debug');

		// Add global options
		$options = $this->prepare_options( $options );
		$this->logger->log('Prepared options: ' . json_encode($options), 'debug');

		// Try primary provider
		$this->logger->log('Calling provider generate_summary method...', 'info');
		$provider_start = microtime(true);
		
		$result = $this->active_provider->generate_summary( $content, $options );
		
		$provider_time = microtime(true) - $provider_start;
		$this->logger->log('Provider call completed in ' . round($provider_time, 2) . ' seconds', 'info');

		// Fallback mechanism
		if ( is_wp_error( $result ) ) {
			$this->logger->log('Provider returned error: ' . $result->get_error_message(), 'error');
			$this->logger->log('Error code: ' . $result->get_error_code(), 'debug');
			
			if ( $this->should_use_fallback( $result ) ) {
				$this->logger->log('Checking for fallback provider...', 'info');
				$fallback_provider = $this->get_fallback_provider();
				if ( $fallback_provider ) {
					$this->logger->log(
						sprintf( 
							'Primary provider failed, trying fallback: %s', 
							$fallback_provider->get_provider_name() 
						),
						'warning'
					);
					$result = $fallback_provider->generate_summary( $content, $options );
					
					if ( is_wp_error( $result ) ) {
						$this->logger->log('Fallback also failed: ' . $result->get_error_message(), 'error');
					} else {
						$this->logger->log('Fallback succeeded', 'info');
					}
				} else {
					$this->logger->log('No fallback provider available', 'warning');
				}
			} else {
				$this->logger->log('Fallback not enabled or error not eligible for fallback', 'debug');
			}
		} else {
			$this->logger->log('Summary generated successfully', 'info');
			if ( is_array( $result ) ) {
				$this->logger->log('Result keys: ' . json_encode(array_keys($result)), 'debug');
				if ( isset( $result['summary'] ) ) {
					$this->logger->log('Summary length: ' . strlen($result['summary']) . ' chars', 'debug');
				}
			}
		}

		$this->logger->log('=== AI Manager Generate Summary Completed ===', 'info');
		return $result;
	}

	/**
	 * Prepare options for summary generation
	 *
	 * @param array $options Raw options.
	 * @return array Prepared options.
	 */
	private function prepare_options( $options ) {
		$defaults = array(
			'language' => get_locale(),
			'max_length' => get_option( 'tldr_pro_max_summary_length', 150 ),
			'style' => get_option( 'tldr_pro_summary_style', 'professional' ),
			'format' => get_option( 'tldr_pro_summary_format', 'paragraph' ),
			'use_emojis' => get_option( 'tldr_pro_use_emojis', true ),
			'include_keywords' => get_option( 'tldr_pro_include_keywords', true ),
			'bullet_points' => get_option( 'tldr_pro_bullet_points', 3 ),
		);

		return wp_parse_args( $options, $defaults );
	}

	/**
	 * Check if fallback should be used
	 *
	 * @param WP_Error $error Error object.
	 * @return bool
	 */
	private function should_use_fallback( $error ) {
		$fallback_enabled = get_option( 'tldr_pro_enable_fallback', true );
		if ( ! $fallback_enabled ) {
			return false;
		}

		$error_code = $error->get_error_code();
		$fallback_errors = array(
			'rate_limit_exceeded',
			'api_error',
			'timeout',
			'service_unavailable',
		);

		return in_array( $error_code, $fallback_errors, true );
	}

	/**
	 * Get fallback provider
	 *
	 * @return TLDR_Pro_AI_Provider|null
	 */
	private function get_fallback_provider() {
		$fallback_order = get_option( 'tldr_pro_fallback_order', array( 'deepseek', 'gemini' ) );
		$current_provider = $this->active_provider->get_provider_name();

		foreach ( $fallback_order as $provider_key ) {
			if ( $provider_key === $current_provider ) {
				continue;
			}

			if ( isset( $this->providers[ $provider_key ] ) ) {
				$provider = $this->providers[ $provider_key ];
				$validation = $provider->validate_credentials();
				if ( ! is_wp_error( $validation ) ) {
					return $provider;
				}
			}
		}

		return null;
	}

	/**
	 * Get provider API key
	 *
	 * @param string $provider_key Provider key.
	 * @return string
	 */
	private function get_provider_api_key( $provider_key ) {
		// Check if encryption is available
		if ( class_exists( 'TLDR_Pro_Encryption' ) ) {
			$encryption = TLDR_Pro_Encryption::get_instance();
			return $encryption->get_api_key( $provider_key );
		}
		
		// Fallback to plain text for backward compatibility
		return get_option( 'tldr_pro_' . $provider_key . '_api_key', '' );
	}

	/**
	 * Validate provider credentials
	 *
	 * @param string $provider_key Provider key.
	 * @return bool|WP_Error
	 */
	public function validate_provider( $provider_key ) {
		if ( ! isset( $this->providers[ $provider_key ] ) ) {
			return new WP_Error(
				'provider_not_found',
				sprintf( __( 'Provider %s not found.', 'tldr-pro' ), $provider_key )
			);
		}

		return $this->providers[ $provider_key ]->validate_credentials();
	}
	
	/**
	 * Get provider instance by key
	 *
	 * @param string $provider_key Provider key.
	 * @return TLDR_Pro_AI_Provider|null Provider instance or null if not found.
	 */
	public function get_provider_instance( $provider_key ) {
		if ( ! isset( $this->providers[ $provider_key ] ) ) {
			return null;
		}
		
		return $this->providers[ $provider_key ];
	}

	/**
	 * Get all models for a provider
	 *
	 * @param string $provider_key Provider key.
	 * @return array
	 */
	public function get_provider_models( $provider_key ) {
		if ( ! isset( $this->providers[ $provider_key ] ) ) {
			return array();
		}

		$provider = $this->providers[ $provider_key ];
		if ( method_exists( $provider, 'get_available_models' ) ) {
			return $provider->get_available_models();
		}

		return array();
	}

	/**
	 * Get pricing for all providers
	 *
	 * @return array
	 */
	public function get_all_pricing() {
		$pricing = array();

		foreach ( $this->providers as $key => $provider ) {
			if ( method_exists( $provider, 'get_available_models' ) ) {
				$models = $provider->get_available_models();
				$pricing[ $key ] = array(
					'name' => $this->provider_config[ $key ]['name'],
					'models' => array(),
				);

				foreach ( $models as $model_key => $model_info ) {
					$pricing[ $key ]['models'][ $model_key ] = array(
						'name' => $model_info['name'],
						'input' => isset( $model_info['cost_per_1m_input'] ) 
							? $model_info['cost_per_1m_input'] 
							: ( isset( $model_info['cost_per_1k_input'] ) 
								? $model_info['cost_per_1k_input'] * 1000 
								: 0 ),
						'output' => isset( $model_info['cost_per_1m_output'] ) 
							? $model_info['cost_per_1m_output'] 
							: ( isset( $model_info['cost_per_1k_output'] ) 
								? $model_info['cost_per_1k_output'] * 1000 
								: 0 ),
						'recommended' => isset( $model_info['recommended'] ) 
							? $model_info['recommended'] 
							: false,
					);
				}
			}
		}

		return $pricing;
	}

	/**
	 * Get usage statistics for all providers
	 *
	 * @return array
	 */
	public function get_all_statistics() {
		$stats = array();

		foreach ( $this->providers as $key => $provider ) {
			$stats[ $key ] = $provider->get_statistics();
		}

		return $stats;
	}

	/**
	 * Batch generate summaries
	 *
	 * @param array $contents Array of content to summarize.
	 * @param array $options Options.
	 * @return array Results array.
	 */
	public function batch_generate_summaries( $contents, $options = array() ) {
		$results = array();
		$batch_size = get_option( 'tldr_pro_batch_size', 5 );
		$delay = get_option( 'tldr_pro_batch_delay', 1 );

		$chunks = array_chunk( $contents, $batch_size );

		foreach ( $chunks as $chunk_index => $chunk ) {
			foreach ( $chunk as $index => $content ) {
				$results[] = $this->generate_summary( $content, $options );
				
				// Add delay between requests to avoid rate limiting
				if ( $index < count( $chunk ) - 1 ) {
					sleep( $delay );
				}
			}

			// Longer delay between chunks
			if ( $chunk_index < count( $chunks ) - 1 ) {
				sleep( $delay * 2 );
			}
		}

		return $results;
	}

	/**
	 * Test all configured providers
	 *
	 * @return array Test results.
	 */
	public function test_all_providers() {
		$results = array();
		$test_content = 'This is a test content for validating AI provider functionality. Please generate a brief summary.';

		foreach ( $this->providers as $key => $provider ) {
			$start_time = microtime( true );
			$validation = $provider->validate_credentials();
			
			if ( is_wp_error( $validation ) ) {
				$results[ $key ] = array(
					'status' => 'error',
					'message' => $validation->get_error_message(),
					'time' => 0,
				);
				continue;
			}

			$summary = $provider->generate_summary( $test_content, array(
				'max_length' => 30,
			) );

			$duration = microtime( true ) - $start_time;

			if ( is_wp_error( $summary ) ) {
				$results[ $key ] = array(
					'status' => 'error',
					'message' => $summary->get_error_message(),
					'time' => $duration,
				);
			} else {
				$results[ $key ] = array(
					'status' => 'success',
					'message' => __( 'Provider is working correctly', 'tldr-pro' ),
					'summary' => $summary['summary'],
					'time' => $duration,
					'model' => $summary['model'],
				);
			}
		}

		return $results;
	}

	/**
	 * Get recommended provider based on content
	 *
	 * @param string $content Content to analyze.
	 * @return string Provider key.
	 */
	public function get_recommended_provider( $content ) {
		$word_count = str_word_count( $content );
		
		// For very long content, use providers with larger context windows
		if ( $word_count > 10000 ) {
			if ( isset( $this->providers['gemini'] ) ) {
				return 'gemini';
			}
		}

		// For technical content, prefer specialized providers
		if ( $this->is_technical_content( $content ) ) {
			if ( isset( $this->providers['deepseek'] ) ) {
				return 'deepseek';
			}
		}

		// Default to most cost-effective
		return 'deepseek';
	}

	/**
	 * Check if content is technical
	 *
	 * @param string $content Content to check.
	 * @return bool
	 */
	private function is_technical_content( $content ) {
		$technical_keywords = array(
			'code', 'function', 'class', 'method', 'api', 'database',
			'algorithm', 'framework', 'library', 'programming',
		);

		$content_lower = strtolower( $content );
		$keyword_count = 0;

		foreach ( $technical_keywords as $keyword ) {
			$keyword_count += substr_count( $content_lower, $keyword );
		}

		$word_count = str_word_count( $content );
		$keyword_density = $keyword_count / max( $word_count, 1 );

		return $keyword_density > 0.02; // 2% keyword density threshold
	}
}