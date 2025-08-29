<?php
/**
 * Google Gemini AI Provider
 *
 * @package TLDR_Pro
 * @subpackage AI
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google Gemini API integration class
 *
 * @since 1.0.0
 */
class TLDR_Pro_Gemini_API extends TLDR_Pro_AI_Provider {

	/**
	 * Available Gemini models with their specifications
	 *
	 * @var array
	 */
	private $available_models = array(
		'gemini-2.5-flash' => array(
			'name' => 'Gemini 2.5 Flash',
			'description' => 'Latest fast model with thinking mode and caching',
			'context_window' => 1048576, // 1M tokens
			'output_tokens' => 8192,
			'cost_per_1m_input' => 0.30, // Per million tokens
			'cost_per_1m_output' => 2.50,
			'cost_per_1m_cached' => 0.075, // 75% discount
			'supports_caching' => true,
			'recommended' => true,
		),
		'gemini-2.5-flash-lite' => array(
			'name' => 'Gemini 2.5 Flash Lite',
			'description' => 'Most economical model for simple tasks',
			'context_window' => 1048576,
			'output_tokens' => 8192,
			'cost_per_1m_input' => 0.10,
			'cost_per_1m_output' => 0.40,
			'cost_per_1m_cached' => 0.025,
			'supports_caching' => true,
			'recommended' => false,
		),
		'gemini-2.5-pro' => array(
			'name' => 'Gemini 2.5 Pro',
			'description' => 'Most powerful model for complex reasoning',
			'context_window' => 200000, // 200K tokens
			'output_tokens' => 8192,
			'cost_per_1m_input' => 1.25,
			'cost_per_1m_output' => 10.00,
			'cost_per_1m_cached' => 0.31,
			'supports_caching' => true,
			'recommended' => false,
		),
		'gemini-1.5-pro' => array(
			'name' => 'Gemini 1.5 Pro (2M context)',
			'description' => 'Largest context window for huge documents',
			'context_window' => 2097152, // 2M tokens
			'output_tokens' => 8192,
			'cost_per_1m_input' => 1.25,
			'cost_per_1m_output' => 5.00,
			'cost_per_1m_cached' => 0.31,
			'supports_caching' => true,
			'recommended' => false,
		),
		'gemini-1.5-flash' => array(
			'name' => 'Gemini 1.5 Flash (Legacy)',
			'description' => 'Previous generation fast model',
			'context_window' => 1048576,
			'output_tokens' => 8192,
			'cost_per_1m_input' => 0.07,
			'cost_per_1m_output' => 0.30,
			'cost_per_1m_cached' => 0.018,
			'supports_caching' => false,
			'recommended' => false,
		),
	);

	/**
	 * Safety settings for content generation
	 *
	 * @var array
	 */
	private $safety_settings = array(
		array(
			'category' => 'HARM_CATEGORY_HATE_SPEECH',
			'threshold' => 'BLOCK_ONLY_HIGH',
		),
		array(
			'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
			'threshold' => 'BLOCK_ONLY_HIGH',
		),
		array(
			'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
			'threshold' => 'BLOCK_ONLY_HIGH',
		),
		array(
			'category' => 'HARM_CATEGORY_HARASSMENT',
			'threshold' => 'BLOCK_ONLY_HIGH',
		),
	);

	/**
	 * Initialize Gemini-specific settings
	 *
	 * @return void
	 */
	protected function init() {
		$this->provider_name = 'gemini';
		$this->api_endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/';
		$this->model = get_option( 'tldr_pro_gemini_model', 'gemini-2.5-flash' );
		$this->max_tokens = 4096; // Increased for complete summaries
		$this->temperature = 0.4;
		$this->timeout = 30;  // Reduced from 60 to 30 seconds
		$this->rate_limit = 60; // Free tier: 60 requests per minute
	}

	/**
	 * Get available models
	 *
	 * @return array
	 */
	public function get_available_models() {
		return $this->available_models;
	}

	/**
	 * Generate summary from content using Gemini API
	 *
	 * @param string $content The content to summarize.
	 * @param array  $options Additional options.
	 * @return array|WP_Error Response array or error.
	 */
	public function generate_summary( $content, $options = array() ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error(
				'missing_api_key',
				__( 'Google Gemini API key is not configured.', 'tldr-pro' )
			);
		}

		$cache_key = 'gemini_' . md5( $content . serialize( $options ) );
		$cached = $this->get_cached_response( $cache_key );
		
		if ( false !== $cached ) {
			$this->logger->log( 'Using cached Gemini response', 'debug' );
			return $cached;
		}

		$prompt = $this->create_gemini_prompt( $content, $options );
		
		$request_body = array(
			'contents' => array(
				array(
					'parts' => array(
						array(
							'text' => $prompt,
						),
					),
				),
			),
			'generationConfig' => array(
				'temperature' => $this->temperature,
				'maxOutputTokens' => $this->max_tokens,
				'topP' => 0.8,
				'topK' => 40,
			),
			'safetySettings' => $this->safety_settings,
		);

		$url = $this->api_endpoint . $this->model . ':generateContent?key=' . $this->api_key;
		
		$args = array(
			'method' => 'POST',
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body' => wp_json_encode( $request_body ),
			'timeout' => $this->timeout,
		);

		$start_time = microtime( true );
		$response = $this->make_request( $url, $args );
		
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( $response['body'], true );
		
		if ( ! isset( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$error_message = isset( $body['error']['message'] ) 
				? $body['error']['message'] 
				: __( 'Invalid response from Gemini API', 'tldr-pro' );
				
			return new WP_Error(
				'api_error',
				$error_message
			);
		}

		$summary_text = $body['candidates'][0]['content']['parts'][0]['text'];
		
		// Clean up markdown code blocks if present
		$summary_text = $this->clean_markdown_code_blocks( $summary_text );
		
		$token_info = $this->estimate_gemini_tokens( $prompt, $summary_text );
		
		// Check for usage metadata from API
		$input_tokens = $token_info['input'];
		$output_tokens = $token_info['output'];
		$cached_tokens = 0;
		
		if ( isset( $body['usageMetadata'] ) ) {
			$usage = $body['usageMetadata'];
			$input_tokens = isset( $usage['promptTokenCount'] ) ? $usage['promptTokenCount'] : $input_tokens;
			$output_tokens = isset( $usage['candidatesTokenCount'] ) ? $usage['candidatesTokenCount'] : $output_tokens;
			$cached_tokens = isset( $usage['cachedContentTokenCount'] ) ? $usage['cachedContentTokenCount'] : 0;
		}
		
		$total_tokens = $input_tokens + $output_tokens;
		
		$this->add_tokens_used( $total_tokens );
		$this->increment_request_counter();
		$this->record_response_time( $response['duration'] );

		$result = array(
			'summary' => trim( $summary_text ),
			'provider' => 'gemini',
			'model' => $this->model,
			'tokens_used' => $total_tokens,
			'input_tokens' => $input_tokens,
			'output_tokens' => $output_tokens,
			'cached_tokens' => $cached_tokens,
			'generation_time' => $response['duration'],
			'cached' => false,
			'cache_hit_rate' => $cached_tokens > 0 ? ( $cached_tokens / $input_tokens * 100 ) : 0,
			'finish_reason' => isset( $body['candidates'][0]['finishReason'] ) 
				? $body['candidates'][0]['finishReason'] 
				: 'STOP',
		);

		$this->cache_response( $cache_key, $result, 2 * HOUR_IN_SECONDS );
		
		$this->logger->log(
			sprintf(
				'Gemini summary generated: %d tokens in %.2fs',
				$total_tokens,
				$response['duration']
			),
			'info'
		);

		return $result;
	}

	/**
	 * Create Gemini-specific prompt
	 *
	 * @param string $content Content to summarize.
	 * @param array  $options Additional options.
	 * @return string Generated prompt.
	 */
	private function create_gemini_prompt( $content, $options = array() ) {
		// Get prompt template from Prompt Manager
		$template = TLDR_Pro_Prompt_Manager::get_prompt( 'gemini' );
		
		if ( empty( $template ) ) {
			$template = $this->get_default_prompt_template();
		}

		// Process the prompt with actual settings - Prompt Manager will handle language override
		$processed_prompt = TLDR_Pro_Prompt_Manager::process_prompt( $template, array(
			'content' => $content,
		) );
		
		// Log the prompt for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '=== TLDR PRO GEMINI API REQUEST ===' );
			error_log( 'Language Setting: ' . TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_summary_language', 'en' ) );
			error_log( 'Max Length: ' . TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_max_summary_length', 150 ) );
			error_log( 'Prompt being sent:' );
			error_log( substr( $processed_prompt, 0, 1000 ) . '...' );
			error_log( '=== END GEMINI REQUEST ===' );
		}

		return $processed_prompt;
	}

	/**
	 * Get default prompt template
	 *
	 * @return string
	 */
	private function get_default_prompt_template() {
		return "You are an expert content summarizer. Create a TL;DR summary of the following content.

Requirements:
- Language: {language}
- Maximum length: {max_length} words
- Style: {style}
- Format: {format}
- Include 3-5 key points
- Use relevant emojis to make it engaging
- Focus on the most important information
- Make it scannable and easy to read

Content to summarize:
{content}

Generate a concise, informative summary that captures the essence of the content.";
	}

	/**
	 * Estimate token usage for Gemini
	 *
	 * @param string $input Input text.
	 * @param string $output Output text.
	 * @return array Token counts.
	 */
	private function estimate_gemini_tokens( $input, $output ) {
		// Gemini uses roughly 1.3 tokens per word
		$input_tokens = (int) ( str_word_count( $input ) * 1.3 );
		$output_tokens = (int) ( str_word_count( $output ) * 1.3 );
		return array(
			'input' => $input_tokens,
			'output' => $output_tokens,
			'total' => $input_tokens + $output_tokens,
		);
	}

	/**
	 * Validate API credentials
	 *
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate_credentials() {
		if ( empty( $this->api_key ) ) {
			return new WP_Error(
				'missing_credentials',
				__( 'Google Gemini API key is missing.', 'tldr-pro' )
			);
		}

		$test_content = 'This is a test message to validate API credentials.';
		
		$result = $this->generate_summary( $test_content, array(
			'max_length' => 20,
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Get language name from locale
	 *
	 * @param string $locale WordPress locale.
	 * @return string Language name.
	 */
	private function get_language_name( $locale ) {
		$languages = array(
			'en_US' => 'English',
			'ru_RU' => 'Russian',
			'es_ES' => 'Spanish',
			'fr_FR' => 'French',
			'de_DE' => 'German',
			'it_IT' => 'Italian',
			'pt_BR' => 'Portuguese',
			'zh_CN' => 'Chinese',
			'ja' => 'Japanese',
			'ko_KR' => 'Korean',
		);

		return isset( $languages[ $locale ] ) ? $languages[ $locale ] : 'English';
	}
	
	/**
	 * Clean markdown code blocks from response
	 *
	 * @param string $text Text to clean.
	 * @return string Cleaned text.
	 */
	private function clean_markdown_code_blocks( $text ) {
		// Remove ```html and ``` markers
		$text = preg_replace( '/^```html\s*\n?/im', '', $text );
		$text = preg_replace( '/^```\s*$/im', '', $text );
		
		// Also remove any remaining backticks at start/end
		$text = trim( $text, '`' );
		
		// Remove any <code> or <pre> tags that might have been added
		$text = preg_replace( '/<\/?code[^>]*>/i', '', $text );
		$text = preg_replace( '/<\/?pre[^>]*>/i', '', $text );
		
		return trim( $text );
	}

	/**
	 * Stream response handling for large content
	 *
	 * @param string $content Content to process.
	 * @param array  $options Options.
	 * @param callable $callback Callback for each chunk.
	 * @return array|WP_Error
	 */
	public function generate_summary_stream( $content, $options = array(), $callback = null ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error(
				'missing_api_key',
				__( 'Google Gemini API key is not configured.', 'tldr-pro' )
			);
		}

		$prompt = $this->create_gemini_prompt( $content, $options );
		
		$request_body = array(
			'contents' => array(
				array(
					'parts' => array(
						array(
							'text' => $prompt,
						),
					),
				),
			),
			'generationConfig' => array(
				'temperature' => $this->temperature,
				'maxOutputTokens' => $this->max_tokens,
			),
			'safetySettings' => $this->safety_settings,
		);

		$url = $this->api_endpoint . $this->model . ':streamGenerateContent?key=' . $this->api_key;
		
		// Note: WordPress doesn't natively support streaming responses
		// This is a placeholder for potential future implementation
		// For now, we fall back to regular generation
		
		return $this->generate_summary( $content, $options );
	}

	/**
	 * Get model pricing information
	 *
	 * @param string $model Model name.
	 * @return array Pricing information.
	 */
	public function get_model_pricing( $model = null ) {
		if ( null === $model ) {
			$model = $this->model;
		}

		if ( ! isset( $this->available_models[ $model ] ) ) {
			return array(
				'input' => 0,
				'output' => 0,
				'cached' => 0,
				'currency' => 'USD',
				'per_tokens' => 1000000,
			);
		}

		$model_info = $this->available_models[ $model ];

		return array(
			'input' => $model_info['cost_per_1m_input'],
			'output' => $model_info['cost_per_1m_output'],
			'cached' => $model_info['cost_per_1m_cached'],
			'currency' => 'USD',
			'per_tokens' => 1000000, // Per million tokens
		);
	}

	/**
	 * Calculate cost for a specific generation
	 *
	 * @param int $input_tokens Input token count.
	 * @param int $output_tokens Output token count.
	 * @param string $model Model used.
	 * @param int $cached_tokens Cached token count.
	 * @return float Cost in USD.
	 */
	public function calculate_cost( $input_tokens, $output_tokens, $model = null, $cached_tokens = 0 ) {
		if ( null === $model ) {
			$model = $this->model;
		}
		
		if ( ! isset( $this->available_models[ $model ] ) ) {
			return 0;
		}
		
		$model_info = $this->available_models[ $model ];
		
		// Calculate input cost with cache consideration
		$cache_miss_tokens = $input_tokens - $cached_tokens;
		$cache_hit_tokens = $cached_tokens;
		
		$input_cost = 0;
		
		// Cost for cache miss tokens
		if ( $cache_miss_tokens > 0 && isset( $model_info['cost_per_1m_input'] ) ) {
			$input_cost += ( $cache_miss_tokens / 1000000 ) * $model_info['cost_per_1m_input'];
		}
		
		// Cost for cache hit tokens (75% discount)
		if ( $cache_hit_tokens > 0 && isset( $model_info['cost_per_1m_cached'] ) ) {
			$input_cost += ( $cache_hit_tokens / 1000000 ) * $model_info['cost_per_1m_cached'];
		}
		
		// Output cost
		$output_cost = 0;
		if ( isset( $model_info['cost_per_1m_output'] ) ) {
			$output_cost = ( $output_tokens / 1000000 ) * $model_info['cost_per_1m_output'];
		}
		
		return $input_cost + $output_cost;
	}
	
	/**
	 * Comprehensive API test with detailed diagnostics
	 *
	 * @return array Test results with detailed information.
	 */
	public function test_api_connection() {
		$results = array(
			'status' => 'testing',
			'provider' => 'Google Gemini',
			'model' => $this->model,
			'tests' => array(),
			'recommendations' => array(),
			'timing' => array(),
			'timestamp' => current_time( 'mysql' ),
			'user_id' => get_current_user_id(),
		);
		
		$start_time = microtime( true );
		
		// Log test start
		$this->logger->log(
			sprintf( 'Starting Gemini API test for model: %s', $this->model ),
			'info'
		);
		
		// Store test progress in transient for real-time updates
		set_transient( 'tldr_pro_test_progress_' . get_current_user_id(), array(
			'status' => 'running',
			'current_test' => 'initialization',
			'progress' => 0,
		), 60 );
		
		// Test 1: API Key Format
		set_transient( 'tldr_pro_test_progress_' . get_current_user_id(), array(
			'status' => 'running',
			'current_test' => 'api_key_validation',
			'progress' => 15,
		), 60 );
		
		if ( empty( $this->api_key ) ) {
			$results['tests']['api_key'] = array(
				'status' => 'error',
				'message' => __( 'API key is not configured', 'tldr-pro' ),
			);
			$results['status'] = 'error';
			$results['recommendations'][] = __( 'Please enter your Google Gemini API key', 'tldr-pro' );
			$results['recommendations'][] = __( 'Get your API key at https://aistudio.google.com/', 'tldr-pro' );
			
			$this->logger->log( 'Gemini API test failed: No API key configured', 'error' );
			$this->save_test_log( $results );
			return $results;
		}
		
		// Gemini API keys typically start with "AIza"
		if ( strpos( $this->api_key, 'AIza' ) !== 0 && strlen( $this->api_key ) !== 39 ) {
			$results['tests']['api_key'] = array(
				'status' => 'warning',
				'message' => __( 'API key format may be incorrect', 'tldr-pro' ),
			);
			$results['recommendations'][] = __( 'Gemini API keys typically start with "AIza" and are 39 characters long', 'tldr-pro' );
		} else {
			$results['tests']['api_key'] = array(
				'status' => 'success',
				'message' => __( 'API key format appears valid', 'tldr-pro' ),
			);
		}
		
		// Test 2: Model List API
		set_transient( 'tldr_pro_test_progress_' . get_current_user_id(), array(
			'status' => 'running',
			'current_test' => 'model_availability',
			'progress' => 30,
		), 60 );
		
		$models_start = microtime( true );
		$models_url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $this->api_key;
		$models_response = wp_remote_get( $models_url, array( 'timeout' => 10 ) );
		$models_time = microtime( true ) - $models_start;
		
		if ( is_wp_error( $models_response ) ) {
			$results['tests']['api_access'] = array(
				'status' => 'error',
				'message' => sprintf( __( 'Cannot connect to Gemini API: %s', 'tldr-pro' ), $models_response->get_error_message() ),
			);
			$results['status'] = 'error';
			$results['recommendations'][] = __( 'Check your internet connection', 'tldr-pro' );
			return $results;
		}
		
		$response_code = wp_remote_retrieve_response_code( $models_response );
		
		if ( $response_code === 403 || $response_code === 401 ) {
			$results['tests']['api_access'] = array(
				'status' => 'error',
				'message' => __( 'Invalid API key - authentication failed', 'tldr-pro' ),
			);
			$results['status'] = 'error';
			$results['recommendations'][] = __( 'Please check your API key at https://aistudio.google.com/', 'tldr-pro' );
			$this->save_test_log( $results );
			return $results;
		}
		
		if ( $response_code === 200 ) {
			$models_body = json_decode( wp_remote_retrieve_body( $models_response ), true );
			$available_models = array();
			
			if ( isset( $models_body['models'] ) ) {
				foreach ( $models_body['models'] as $model ) {
					if ( isset( $model['name'] ) ) {
						$model_name = str_replace( 'models/', '', $model['name'] );
						$available_models[] = $model_name;
					}
				}
			}
			
			$results['tests']['models_list'] = array(
				'status' => 'success',
				'message' => sprintf( __( 'Found %d available models (%.2fs)', 'tldr-pro' ), count( $available_models ), $models_time ),
				'details' => array(
					'models_count' => count( $available_models ),
					'response_time' => $models_time,
				),
			);
			
			// Check if selected model is available
			if ( in_array( $this->model, $available_models ) ) {
				$results['tests']['model_availability'] = array(
					'status' => 'success',
					'message' => sprintf( __( 'Model "%s" is available', 'tldr-pro' ), $this->model ),
				);
			} else {
				$results['tests']['model_availability'] = array(
					'status' => 'warning',
					'message' => sprintf( __( 'Model "%s" not found in available models', 'tldr-pro' ), $this->model ),
				);
				$results['recommendations'][] = sprintf( __( 'Consider using one of: %s', 'tldr-pro' ), implode( ', ', array_slice( $available_models, 0, 3 ) ) );
			}
		}
		
		$results['timing']['models_check'] = $models_time;
		
		// Test 3: Generation Test
		set_transient( 'tldr_pro_test_progress_' . get_current_user_id(), array(
			'status' => 'running',
			'current_test' => 'generation_test',
			'progress' => 50,
		), 60 );
		
		$gen_start = microtime( true );
		
		$test_body = array(
			'contents' => array(
				array(
					'parts' => array(
						array(
							'text' => 'Test: Reply with "OK" if you can read this.',
						),
					),
				),
			),
			'generationConfig' => array(
				'temperature' => 0,
				'maxOutputTokens' => 10,
			),
		);
		
		$gen_url = $this->api_endpoint . $this->model . ':generateContent?key=' . $this->api_key;
		
		$gen_response = wp_remote_post( $gen_url, array(
			'method' => 'POST',
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body' => wp_json_encode( $test_body ),
			'timeout' => 10,  // Reduced from 15 to 10 seconds
		));
		
		$gen_time = microtime( true ) - $gen_start;
		$results['timing']['generation'] = $gen_time;
		
		if ( is_wp_error( $gen_response ) ) {
			$results['tests']['generation'] = array(
				'status' => 'error',
				'message' => sprintf( __( 'Generation failed: %s', 'tldr-pro' ), $gen_response->get_error_message() ),
			);
			$results['status'] = 'error';
			return $results;
		}
		
		$gen_code = wp_remote_retrieve_response_code( $gen_response );
		$gen_body = json_decode( wp_remote_retrieve_body( $gen_response ), true );
		
		// Handle different response codes
		switch ( $gen_code ) {
			case 200:
				$results['tests']['generation'] = array(
					'status' => 'success',
					'message' => sprintf( __( 'Generation successful (%.2fs)', 'tldr-pro' ), $gen_time ),
				);
				
				// Test 4: Token Usage & Cost
				if ( isset( $gen_body['usageMetadata'] ) ) {
					$usage = $gen_body['usageMetadata'];
					$input_tokens = isset( $usage['promptTokenCount'] ) ? $usage['promptTokenCount'] : 0;
					$output_tokens = isset( $usage['candidatesTokenCount'] ) ? $usage['candidatesTokenCount'] : 0;
					$cached_tokens = isset( $usage['cachedContentTokenCount'] ) ? $usage['cachedContentTokenCount'] : 0;
					
					$cost = $this->calculate_cost( $input_tokens, $output_tokens, $this->model, $cached_tokens );
					
					$results['tests']['usage'] = array(
						'status' => 'info',
						'message' => sprintf(
							__( 'Test used %d tokens (cost: $%.6f)', 'tldr-pro' ),
							$input_tokens + $output_tokens,
							$cost
						),
						'details' => array(
							'input_tokens' => $input_tokens,
							'output_tokens' => $output_tokens,
							'cached_tokens' => $cached_tokens,
							'estimated_cost' => $cost,
						),
					);
					
					if ( $cached_tokens > 0 ) {
						$results['tests']['caching'] = array(
							'status' => 'success',
							'message' => sprintf( __( 'Automatic caching is active (%d cached tokens)', 'tldr-pro' ), $cached_tokens ),
						);
						$results['recommendations'][] = __( 'Caching can save up to 75% on repeated requests', 'tldr-pro' );
					}
				}
				
				// Test 5: Response Quality
				if ( isset( $gen_body['candidates'][0]['content']['parts'][0]['text'] ) ) {
					$results['tests']['response'] = array(
						'status' => 'success',
						'message' => __( 'API is generating responses correctly', 'tldr-pro' ),
					);
				}
				
				// Test 6: Safety Ratings
				if ( isset( $gen_body['candidates'][0]['safetyRatings'] ) ) {
					$results['tests']['safety'] = array(
						'status' => 'info',
						'message' => __( 'Content safety filters are active', 'tldr-pro' ),
					);
				}
				break;
				
			case 429:
				$results['tests']['generation'] = array(
					'status' => 'warning',
					'message' => __( 'Rate limit exceeded', 'tldr-pro' ),
				);
				$results['recommendations'][] = __( 'Free tier: 60 requests/minute. Consider upgrading for higher limits', 'tldr-pro' );
				break;
				
			case 403:
				$results['tests']['generation'] = array(
					'status' => 'error',
					'message' => __( 'API key invalid or restricted', 'tldr-pro' ),
				);
				$results['status'] = 'error';
				break;
				
			case 400:
				$error_msg = isset( $gen_body['error']['message'] ) ? $gen_body['error']['message'] : 'Bad request';
				$results['tests']['generation'] = array(
					'status' => 'error',
					'message' => sprintf( __( 'Invalid request: %s', 'tldr-pro' ), $error_msg ),
				);
				$results['status'] = 'error';
				break;
				
			default:
				$error_msg = isset( $gen_body['error']['message'] ) ? $gen_body['error']['message'] : 'Unknown error';
				$results['tests']['generation'] = array(
					'status' => 'error',
					'message' => sprintf( __( 'Unexpected response (Code %d): %s', 'tldr-pro' ), $gen_code, $error_msg ),
				);
				$results['status'] = 'error';
		}
		
		// Calculate total time
		set_transient( 'tldr_pro_test_progress_' . get_current_user_id(), array(
			'status' => 'running',
			'current_test' => 'finalizing',
			'progress' => 90,
		), 60 );
		
		$total_time = microtime( true ) - $start_time;
		$results['timing']['total'] = $total_time;
		
		// Set overall status
		$error_count = 0;
		$warning_count = 0;
		foreach ( $results['tests'] as $test ) {
			if ( $test['status'] === 'error' ) {
				$error_count++;
			} elseif ( $test['status'] === 'warning' ) {
				$warning_count++;
			}
		}
		
		if ( $error_count > 0 ) {
			$results['status'] = 'error';
			$results['summary'] = sprintf( __( 'Failed with %d errors', 'tldr-pro' ), $error_count );
		} elseif ( $warning_count > 0 ) {
			$results['status'] = 'warning';
			$results['summary'] = sprintf( __( 'Working with %d warnings', 'tldr-pro' ), $warning_count );
		} else {
			$results['status'] = 'success';
			$results['summary'] = __( 'All tests passed successfully!', 'tldr-pro' );
			$results['recommendations'][] = sprintf(
				__( 'Gemini API is ready to use. Average response time: %.2fs', 'tldr-pro' ),
				$gen_time
			);
		}
		
		// Log final result
		$this->logger->log(
			sprintf( 'Gemini API test completed: %s (%.2fs)', $results['status'], $total_time ),
			$results['status'] === 'error' ? 'error' : 'info'
		);
		
		// Save test log to database
		$this->save_test_log( $results );
		
		// Clear progress transient
		delete_transient( 'tldr_pro_test_progress_' . get_current_user_id() );
		
		// Add model-specific recommendations
		if ( isset( $this->available_models[ $this->model ] ) ) {
			$model_info = $this->available_models[ $this->model ];
			$results['model_info'] = array(
				'name' => $model_info['name'],
				'description' => $model_info['description'],
				'pricing' => sprintf(
					__( 'Input: $%.2f/1M tokens, Output: $%.2f/1M tokens', 'tldr-pro' ),
					$model_info['cost_per_1m_input'],
					$model_info['cost_per_1m_output']
				),
				'limits' => sprintf(
					__( 'Context: %s tokens, Max output: %d tokens', 'tldr-pro' ),
					number_format( $model_info['context_window'] ),
					$model_info['output_tokens']
				),
				'caching' => $model_info['supports_caching'] ? 
					__( '✅ Automatic caching enabled (75% savings)', 'tldr-pro' ) : 
					__( '❌ No caching support', 'tldr-pro' ),
			);
		}
		
		// Add free tier information
		$results['tier_info'] = array(
			'title' => __( 'Free Tier Limits', 'tldr-pro' ),
			'limits' => array(
				__( '60 requests per minute', 'tldr-pro' ),
				__( '1500 requests per day', 'tldr-pro' ),
				__( '1500 grounding requests free', 'tldr-pro' ),
			),
		);
		
		return $results;
	}
	
	/**
	 * Save test log to database
	 *
	 * @param array $results Test results.
	 * @return void
	 */
	private function save_test_log( $results ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'tldr_pro_test_logs';
		
		// Check if table exists, if not create it
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
			$charset_collate = $wpdb->get_charset_collate();
			
			$sql = "CREATE TABLE $table_name (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				provider varchar(50) NOT NULL,
				model varchar(100) DEFAULT NULL,
				status varchar(20) NOT NULL,
				test_results longtext,
				user_id bigint(20) unsigned DEFAULT NULL,
				tested_at datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY provider (provider),
				KEY status (status),
				KEY tested_at (tested_at)
			) $charset_collate;";
			
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}
		
		// Insert test log
		$wpdb->insert(
			$table_name,
			array(
				'provider' => 'gemini',
				'model' => $this->model,
				'status' => $results['status'],
				'test_results' => wp_json_encode( $results ),
				'user_id' => get_current_user_id(),
				'tested_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}
}