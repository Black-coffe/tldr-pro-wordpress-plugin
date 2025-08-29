<?php
/**
 * DeepSeek API Provider class
 *
 * @package TLDR_Pro
 * @subpackage AI
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DeepSeek API implementation
 *
 * @since 1.0.0
 */
class TLDR_Pro_DeepSeek_API extends TLDR_Pro_AI_Provider {

	/**
	 * Available DeepSeek models with their specifications
	 *
	 * @var array
	 */
	private $available_models = array(
		'deepseek-chat' => array(
			'name' => 'DeepSeek Chat (V3)',
			'description' => 'Latest model optimized for conversational and summary tasks',
			'context_window' => 64000,
			'output_tokens' => 8192,
			'cost_per_1m_input' => 0.14,
			'cost_per_1m_output' => 0.28,
			'cost_per_1m_input_cached' => 0.014,
			'recommended' => true,
			'supports_prefix_caching' => true,
		),
		'deepseek-reasoner' => array(
			'name' => 'DeepSeek Reasoner (R1)',
			'description' => 'Advanced reasoning for complex analysis with CoT',
			'context_window' => 64000,
			'output_tokens' => 8192,
			'max_cot_tokens' => 32768,
			'cost_per_1m_input' => 0.55,
			'cost_per_1m_output' => 2.19,
			'cost_per_1m_input_cached' => 0.14,
			'recommended' => false,
			'supports_prefix_caching' => true,
		),
	);

	/**
	 * Initialize DeepSeek-specific settings
	 *
	 * @return void
	 */
	protected function init() {
		$this->provider_name = 'deepseek';
		$this->api_endpoint = 'https://api.deepseek.com/v1/chat/completions';
		$this->model = get_option( 'tldr_pro_deepseek_model', 'deepseek-chat' );
		$this->max_tokens = 4096; // Increased for complete summaries
		$this->temperature = 0.3;
		$this->timeout = 120;  // Increased to 120 seconds for DeepSeek API
		$this->rate_limit = 100;
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
	 * Generate summary from content
	 *
	 * @param string $content The content to summarize.
	 * @param array  $options Additional options.
	 * @return array|WP_Error Response array or error.
	 */
	public function generate_summary( $content, $options = array() ) {
		$this->logger->log('=== DeepSeek Generate Summary Started ===', 'info');
		
		// Generate request ID for tracking
		$request_id = isset( $options['request_id'] ) ? $options['request_id'] : uniqid( 'deepseek_' );
		$this->logger->log('Request ID: ' . $request_id, 'info');
		$this->logger->log('Content length: ' . strlen($content) . ' chars', 'debug');
		$this->logger->log('Options: ' . json_encode($options), 'debug');
		
		// Include status tracker
		if ( file_exists( TLDR_PRO_PLUGIN_DIR . 'includes/class-tldr-pro-status-tracker.php' ) ) {
			require_once TLDR_PRO_PLUGIN_DIR . 'includes/class-tldr-pro-status-tracker.php';
		}
		
		// Update status: initializing
		if ( class_exists( 'TLDR_Pro_Status_Tracker' ) ) {
			TLDR_Pro_Status_Tracker::update_status( $request_id, 'initializing', array( 'provider' => 'deepseek' ) );
		}
		
		$this->logger->log('Checking API key...', 'debug');
		$this->logger->log('API key length: ' . strlen($this->api_key), 'debug');
		
		if ( empty( $this->api_key ) ) {
			$this->logger->log('ERROR: DeepSeek API key is missing', 'error');
			if ( class_exists( 'TLDR_Pro_Status_Tracker' ) ) {
				TLDR_Pro_Status_Tracker::update_status( $request_id, 'error', array( 'message' => 'API key missing' ) );
			}
			return new WP_Error( 
				'missing_api_key',
				__( 'DeepSeek API key is not configured.', 'tldr-pro' )
			);
		}
		
		$this->logger->log('API key present, proceeding...', 'debug');

		$cache_key = 'deepseek_' . md5( $content . serialize( $options ) );
		$this->logger->log('Cache key: ' . $cache_key, 'debug');
		
		$cached = $this->get_cached_response( $cache_key );
		
		if ( false !== $cached ) {
			$this->logger->log( 'Using cached DeepSeek response', 'info' );
			return $cached;
		}
		
		$this->logger->log('No cached response, preparing new API request...', 'debug');

		$system_prompt = $this->create_system_prompt( $options );
		$this->logger->log('System prompt length: ' . strlen($system_prompt) . ' chars', 'debug');
		
		$user_prompt = $this->create_user_prompt( $content, $options );
		$this->logger->log('User prompt length: ' . strlen($user_prompt) . ' chars', 'debug');
		
		$messages = array(
			array(
				'role' => 'system',
				'content' => $system_prompt,
			),
			array(
				'role' => 'user',
				'content' => $user_prompt,
			),
		);

		// Use prefix caching if available
		$use_prefix_caching = $this->should_use_prefix_caching( $options );
		if ( $use_prefix_caching ) {
			$messages[0]['prefix'] = true;
		}

		$request_body = array(
			'model' => $this->model,
			'messages' => $messages,
			'temperature' => $this->temperature,
			'max_tokens' => $this->max_tokens,
			'top_p' => 0.9,
			'frequency_penalty' => 0.0,
			'presence_penalty' => 0.0,
			'stop' => null,
			'stream' => false,
		);

		// Update status: preparing
		if ( class_exists( 'TLDR_Pro_Status_Tracker' ) ) {
			TLDR_Pro_Status_Tracker::update_status( $request_id, 'preparing', array( 
				'model' => $this->model,
				'content_length' => strlen( $content )
			) );
		}
		
		$this->logger->log('Model: ' . $this->model, 'info');
		$this->logger->log('Temperature: ' . $this->temperature, 'debug');
		$this->logger->log('Max tokens: ' . $this->max_tokens, 'debug');
		$this->logger->log('Timeout: ' . $this->timeout . ' seconds', 'debug');
		
		$args = array(
			'method' => 'POST',
			'headers' => array(
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			),
			'body' => wp_json_encode( $request_body ),
			'timeout' => $this->timeout,
		);
		
		$this->logger->log('API Endpoint: ' . $this->api_endpoint, 'info');
		$this->logger->log('Request body size: ' . strlen($args['body']) . ' chars', 'debug');

		// Update status: sending
		if ( class_exists( 'TLDR_Pro_Status_Tracker' ) ) {
			TLDR_Pro_Status_Tracker::update_status( $request_id, 'sending', array( 
				'endpoint' => $this->api_endpoint,
				'timeout' => $this->timeout
			) );
		}
		
		$start_time = microtime( true );
		$this->logger->log('Sending request to DeepSeek API...', 'info');
		
		// Update status: waiting
		if ( class_exists( 'TLDR_Pro_Status_Tracker' ) ) {
			TLDR_Pro_Status_Tracker::update_status( $request_id, 'waiting', array( 
				'started_at' => $start_time
			) );
		}
		
		$response = $this->make_request( $this->api_endpoint, $args );
		
		$api_duration = microtime( true ) - $start_time;
		$this->logger->log('DeepSeek API call completed in ' . round($api_duration, 2) . ' seconds', 'info');
		
		if ( is_wp_error( $response ) ) {
			$this->logger->log('DeepSeek API request FAILED', 'error');
			$this->logger->log('Error: ' . $response->get_error_message(), 'error');
			$this->logger->log('Error code: ' . $response->get_error_code(), 'error');
			
			if ( class_exists( 'TLDR_Pro_Status_Tracker' ) ) {
				$error_type = strpos( $response->get_error_message(), 'timed out' ) !== false ? 'timeout' : 'error';
				TLDR_Pro_Status_Tracker::update_status( $request_id, $error_type, array( 
					'message' => $response->get_error_message()
				) );
			}
			return $response;
		}
		
		$this->logger->log('DeepSeek API request SUCCESS', 'info');
		
		// Update status: processing
		if ( class_exists( 'TLDR_Pro_Status_Tracker' ) ) {
			TLDR_Pro_Status_Tracker::update_status( $request_id, 'processing' );
		}

		$body = json_decode( $response['body'], true );
		$this->logger->log('Response body decoded successfully', 'debug');
		
		if ( ! isset( $body['choices'][0]['message']['content'] ) ) {
			$error_message = isset( $body['error']['message'] ) 
				? $body['error']['message'] 
				: __( 'Invalid response from DeepSeek API', 'tldr-pro' );
			
			$this->logger->log('Invalid API response structure', 'error');
			$this->logger->log('Error message: ' . $error_message, 'error');
			$this->logger->log('Response body: ' . json_encode($body), 'debug');
				
			return new WP_Error(
				'api_error',
				$error_message,
				array( 'response' => $body )
			);
		}
		
		$this->logger->log('Valid response received from DeepSeek', 'info');

		$summary_text = $body['choices'][0]['message']['content'];
		
		// Clean up markdown code blocks if present
		$summary_text = $this->clean_markdown_code_blocks( $summary_text );
		
		$input_tokens = isset( $body['usage']['prompt_tokens'] ) ? $body['usage']['prompt_tokens'] : 0;
		$output_tokens = isset( $body['usage']['completion_tokens'] ) ? $body['usage']['completion_tokens'] : 0;
		$total_tokens = isset( $body['usage']['total_tokens'] ) ? $body['usage']['total_tokens'] : ($input_tokens + $output_tokens);
		
		// Check for cached tokens (prefix caching)
		$cached_tokens = isset( $body['usage']['prompt_cache_hit_tokens'] ) 
			? $body['usage']['prompt_cache_hit_tokens'] 
			: 0;
		
		$this->add_tokens_used( $total_tokens );
		$this->increment_request_counter();
		$this->record_response_time( $response['duration'] );

		$result = array(
			'summary' => trim( $summary_text ),
			'provider' => 'deepseek',
			'model' => $this->model,
			'tokens_used' => $total_tokens,
			'input_tokens' => $input_tokens,
			'output_tokens' => $output_tokens,
			'cached_tokens' => $cached_tokens,
			'generation_time' => $response['duration'],
			'cached' => false,
			'finish_reason' => isset( $body['choices'][0]['finish_reason'] ) 
				? $body['choices'][0]['finish_reason'] 
				: 'stop',
			'prefix_caching_used' => $cached_tokens > 0,
		);

		// Update status: completed
		if ( class_exists( 'TLDR_Pro_Status_Tracker' ) ) {
			TLDR_Pro_Status_Tracker::update_status( $request_id, 'completed', array( 
				'tokens' => $total_tokens,
				'time' => $response['duration'],
				'cached_tokens' => $cached_tokens
			) );
		}
		
		$this->cache_response( $cache_key, $result, 2 * HOUR_IN_SECONDS );
		
		$this->logger->log(
			sprintf(
				'DeepSeek summary generated: %d tokens (cached: %d) in %.2fs',
				$total_tokens,
				$cached_tokens,
				$response['duration']
			),
			'info'
		);

		return $result;
	}

	/**
	 * Create system prompt for DeepSeek
	 *
	 * @param array $options Options.
	 * @return string System prompt.
	 */
	private function create_system_prompt( $options = array() ) {
		$template = get_option( 'tldr_pro_deepseek_system_prompt', '' );
		
		if ( empty( $template ) ) {
			$template = "You are an expert content summarizer. Your task is to create concise, informative TL;DR summaries that capture the essence of long-form content.
Focus on extracting key points, main arguments, and actionable insights.
Maintain accuracy while making the content easily digestible for busy readers.";
		}

		// Add model-specific instructions
		if ( $this->model === 'deepseek-coder' && $this->is_technical_content( $options ) ) {
			$template .= "\n\nPay special attention to technical details, code examples, and implementation specifics.";
		}

		return $template;
	}

	/**
	 * Create user prompt for DeepSeek
	 *
	 * @param string $content Content to summarize.
	 * @param array  $options Additional options.
	 * @return string User prompt.
	 */
	private function create_user_prompt( $content, $options = array() ) {
		// Get prompt template from Prompt Manager
		$template = TLDR_Pro_Prompt_Manager::get_prompt( 'deepseek' );
		
		if ( empty( $template ) ) {
			$template = $this->get_default_user_prompt_template();
		}

		// Process the prompt with actual settings - Prompt Manager will handle language override
		$processed_prompt = TLDR_Pro_Prompt_Manager::process_prompt( $template, array(
			'content' => $content,
		) );
		
		// Log the prompt for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '=== TLDR PRO DEEPSEEK API REQUEST ===' );
			error_log( 'Language Setting: ' . TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_summary_language', 'en' ) );
			error_log( 'Max Length: ' . TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_max_summary_length', 150 ) );
			error_log( 'Prompt being sent:' );
			error_log( substr( $processed_prompt, 0, 1000 ) . '...' );
			error_log( '=== END DEEPSEEK REQUEST ===' );
		}
		
		return $processed_prompt;
	}

	/**
	 * Get default user prompt template
	 *
	 * @return string
	 */
	private function get_default_user_prompt_template() {
		return "Create a TL;DR summary of the following content:

Instructions:
- Language: {language}
- Target length: {max_length} words
- Writing style: {style}
- Output format: {format}
- Include {bullet_points} main points
- Use emojis to enhance readability
- Focus on practical takeaways

Content:
{content}

Generate a summary that is informative, engaging, and easy to scan.";
	}

	/**
	 * Check if content is technical
	 *
	 * @param array $options Options.
	 * @return bool
	 */
	private function is_technical_content( $options ) {
		if ( isset( $options['content_type'] ) ) {
			$technical_types = array( 'code', 'technical', 'api', 'documentation' );
			return in_array( $options['content_type'], $technical_types, true );
		}
		return false;
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
	 * Check if prefix caching should be used
	 *
	 * @param array $options Options.
	 * @return bool
	 */
	private function should_use_prefix_caching( $options ) {
		if ( ! isset( $this->available_models[ $this->model ]['supports_prefix_caching'] ) ) {
			return false;
		}

		if ( ! $this->available_models[ $this->model ]['supports_prefix_caching'] ) {
			return false;
		}

		// Use prefix caching for repeated system prompts
		return get_option( 'tldr_pro_deepseek_use_prefix_caching', true );
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
				__( 'DeepSeek API key is missing.', 'tldr-pro' )
			);
		}

		$test_prompt = array(
			array(
				'role' => 'user',
				'content' => 'Say "API key is valid" if you can read this.',
			),
		);

		$request_body = array(
			'model' => $this->model,
			'messages' => $test_prompt,
			'max_tokens' => 20,
			'temperature' => 0,
		);

		$args = array(
			'method' => 'POST',
			'headers' => array(
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			),
			'body' => wp_json_encode( $request_body ),
			'timeout' => 10,
		);

		$response = wp_remote_post( $this->api_endpoint, $args );
		
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		
		if ( $response_code === 401 ) {
			return new WP_Error(
				'invalid_api_key',
				__( 'Invalid DeepSeek API key.', 'tldr-pro' )
			);
		}

		if ( $response_code !== 200 ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			$error_message = isset( $body['error']['message'] ) 
				? $body['error']['message'] 
				: sprintf( __( 'API validation failed with code %d', 'tldr-pro' ), $response_code );
			
			return new WP_Error(
				'api_error',
				$error_message
			);
		}

		return true;
	}
	
	/**
	 * Comprehensive API test with detailed diagnostics
	 *
	 * @return array Test results with detailed information.
	 */
	public function test_api_connection() {
		// Load debug helper if available
		if ( file_exists( plugin_dir_path( dirname( __DIR__ ) ) . 'includes/class-tldr-pro-api-debug.php' ) ) {
			require_once plugin_dir_path( dirname( __DIR__ ) ) . 'includes/class-tldr-pro-api-debug.php';
		}
		
		$results = array(
			'status' => 'testing',
			'provider' => 'DeepSeek',
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
			sprintf( 'Starting DeepSeek API test for model: %s', $this->model ),
			'info'
		);
		
		if ( class_exists( 'TLDR_Pro_API_Debug' ) ) {
			TLDR_Pro_API_Debug::log_test_progress( 'Test started', 'DeepSeek', 'initialization' );
		}
		
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
			$results['recommendations'][] = __( 'Please enter your DeepSeek API key', 'tldr-pro' );
			
			$this->logger->log( 'DeepSeek API test failed: No API key configured', 'error' );
			$this->save_test_log( $results );
			return $results;
		}
		
		if ( ! preg_match( '/^sk-[a-zA-Z0-9]{32,}$/', $this->api_key ) ) {
			$results['tests']['api_key'] = array(
				'status' => 'warning',
				'message' => __( 'API key format appears incorrect', 'tldr-pro' ),
			);
			$results['recommendations'][] = __( 'DeepSeek API keys usually start with "sk-"', 'tldr-pro' );
		} else {
			$results['tests']['api_key'] = array(
				'status' => 'success',
				'message' => __( 'API key format is valid', 'tldr-pro' ),
			);
		}
		
		// Test 2: Network Connectivity
		set_transient( 'tldr_pro_test_progress_' . get_current_user_id(), array(
			'status' => 'running',
			'current_test' => 'network_connectivity',
			'progress' => 30,
		), 60 );
		
		$connectivity_start = microtime( true );
		$connectivity_test = wp_remote_get( 'https://api.deepseek.com', array( 'timeout' => 5 ) );
		$connectivity_time = microtime( true ) - $connectivity_start;
		
		if ( is_wp_error( $connectivity_test ) ) {
			$results['tests']['connectivity'] = array(
				'status' => 'error',
				'message' => sprintf( __( 'Cannot reach DeepSeek API: %s', 'tldr-pro' ), $connectivity_test->get_error_message() ),
			);
			$results['status'] = 'error';
			$results['recommendations'][] = __( 'Check your internet connection and firewall settings', 'tldr-pro' );
			return $results;
		}
		
		$results['tests']['connectivity'] = array(
			'status' => 'success',
			'message' => sprintf( __( 'API endpoint reachable (%.2fs)', 'tldr-pro' ), $connectivity_time ),
		);
		$results['timing']['connectivity'] = $connectivity_time;
		
		// Test 3: Authentication Test
		set_transient( 'tldr_pro_test_progress_' . get_current_user_id(), array(
			'status' => 'running',
			'current_test' => 'authentication',
			'progress' => 50,
		), 60 );
		
		$auth_start = microtime( true );
		$auth_test_body = array(
			'model' => $this->model,
			'messages' => array(
				array(
					'role' => 'user',
					'content' => 'Test: respond with "OK"',
				),
			),
			'max_tokens' => 10,
			'temperature' => 0,
		);
		
		if ( class_exists( 'TLDR_Pro_API_Debug' ) ) {
			TLDR_Pro_API_Debug::log_test_progress( 'Starting authentication test', 'DeepSeek', 'authentication' );
			TLDR_Pro_API_Debug::log_request( $this->api_endpoint, array( 'timeout' => 10 ), 'DeepSeek' );
		}
		
		$auth_response = wp_remote_post( $this->api_endpoint, array(
			'method' => 'POST',
			'headers' => array(
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			),
			'body' => wp_json_encode( $auth_test_body ),
			'timeout' => 10,  // Reduced from 15 to 10 seconds
		));
		
		if ( class_exists( 'TLDR_Pro_API_Debug' ) ) {
			TLDR_Pro_API_Debug::log_response( $auth_response, 'DeepSeek' );
		}
		
		$auth_time = microtime( true ) - $auth_start;
		$results['timing']['authentication'] = $auth_time;
		
		if ( is_wp_error( $auth_response ) ) {
			$results['tests']['authentication'] = array(
				'status' => 'error',
				'message' => sprintf( __( 'Authentication failed: %s', 'tldr-pro' ), $auth_response->get_error_message() ),
			);
			$results['status'] = 'error';
			return $results;
		}
		
		$response_code = wp_remote_retrieve_response_code( $auth_response );
		$response_body = json_decode( wp_remote_retrieve_body( $auth_response ), true );
		
		// Handle different response codes
		switch ( $response_code ) {
			case 200:
				$results['tests']['authentication'] = array(
					'status' => 'success',
					'message' => sprintf( __( 'Authentication successful (%.2fs)', 'tldr-pro' ), $auth_time ),
				);
				break;
				
			case 401:
				$results['tests']['authentication'] = array(
					'status' => 'error',
					'message' => __( 'Invalid API key - authentication failed', 'tldr-pro' ),
				);
				$results['status'] = 'error';
				$results['recommendations'][] = __( 'Please check your API key at https://platform.deepseek.com/api_keys', 'tldr-pro' );
				return $results;
				
			case 429:
				$results['tests']['authentication'] = array(
					'status' => 'warning',
					'message' => __( 'Rate limit exceeded', 'tldr-pro' ),
				);
				$results['recommendations'][] = __( 'Wait a moment before testing again', 'tldr-pro' );
				break;
				
			case 500:
			case 502:
			case 503:
				$results['tests']['authentication'] = array(
					'status' => 'warning',
					'message' => __( 'DeepSeek API is temporarily unavailable', 'tldr-pro' ),
				);
				$results['recommendations'][] = __( 'Try again in a few minutes', 'tldr-pro' );
				break;
				
			default:
				$error_msg = isset( $response_body['error']['message'] ) ? $response_body['error']['message'] : 'Unknown error';
				$results['tests']['authentication'] = array(
					'status' => 'error',
					'message' => sprintf( __( 'Unexpected response (Code %d): %s', 'tldr-pro' ), $response_code, $error_msg ),
				);
				$results['status'] = 'error';
				return $results;
		}
		
		// Test 4: Model Availability
		set_transient( 'tldr_pro_test_progress_' . get_current_user_id(), array(
			'status' => 'running',
			'current_test' => 'model_verification',
			'progress' => 70,
		), 60 );
		
		if ( $response_code === 200 && isset( $response_body['model'] ) ) {
			$returned_model = $response_body['model'];
			$results['tests']['model'] = array(
				'status' => 'success',
				'message' => sprintf( __( 'Model "%s" is available and working', 'tldr-pro' ), $returned_model ),
			);
			
			// Test 5: Token Usage & Pricing
			if ( isset( $response_body['usage'] ) ) {
				$usage = $response_body['usage'];
				$input_tokens = isset( $usage['prompt_tokens'] ) ? $usage['prompt_tokens'] : 0;
				$output_tokens = isset( $usage['completion_tokens'] ) ? $usage['completion_tokens'] : 0;
				$cached_tokens = isset( $usage['prompt_cache_hit_tokens'] ) ? $usage['prompt_cache_hit_tokens'] : 0;
				
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
						'message' => sprintf( __( 'Prefix caching is active (%d cached tokens)', 'tldr-pro' ), $cached_tokens ),
					);
					$results['recommendations'][] = __( 'Prefix caching can save up to 90% on repeated requests', 'tldr-pro' );
				}
			}
			
			// Test 6: Response Quality
			if ( isset( $response_body['choices'][0]['message']['content'] ) ) {
				$results['tests']['response'] = array(
					'status' => 'success',
					'message' => __( 'API is generating responses correctly', 'tldr-pro' ),
				);
			}
		}
		
		// Test 7: Account Balance (if available in headers)
		$headers = wp_remote_retrieve_headers( $auth_response );
		if ( isset( $headers['x-ratelimit-remaining'] ) ) {
			$results['tests']['rate_limit'] = array(
				'status' => 'info',
				'message' => sprintf( 
					__( 'Rate limit: %s requests remaining', 'tldr-pro' ),
					$headers['x-ratelimit-remaining']
				),
			);
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
				__( 'DeepSeek API is ready to use. Average response time: %.2fs', 'tldr-pro' ),
				$auth_time
			);
		}
		
		// Log final result
		$this->logger->log(
			sprintf( 'DeepSeek API test completed: %s (%.2fs)', $results['status'], $total_time ),
			$results['status'] === 'error' ? 'error' : 'info'
		);
		
		// Save test log to database
		$this->save_test_log( $results );
		
		// Clear progress transient
		delete_transient( 'tldr_pro_test_progress_' . get_current_user_id() );
		
		// Add model-specific recommendations
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
				__( 'Context: %d tokens, Max output: %d tokens', 'tldr-pro' ),
				$model_info['context_window'],
				$model_info['output_tokens']
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
				'provider' => 'deepseek',
				'model' => $this->model,
				'status' => $results['status'],
				'test_results' => wp_json_encode( $results ),
				'user_id' => get_current_user_id(),
				'tested_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s' )
		);
		
		// Keep only last 100 test logs
		$wpdb->query(
			"DELETE FROM $table_name 
			 WHERE id NOT IN (
				 SELECT id FROM (
					 SELECT id FROM $table_name 
					 ORDER BY tested_at DESC 
					 LIMIT 100
				 ) AS keep_logs
			 )"
		);
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
			'ar' => 'Arabic',
			'hi_IN' => 'Hindi',
			'nl_NL' => 'Dutch',
			'pl_PL' => 'Polish',
			'tr_TR' => 'Turkish',
		);

		return isset( $languages[ $locale ] ) ? $languages[ $locale ] : 'English';
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
				'currency' => 'USD',
			);
		}

		return array(
			'input' => $this->available_models[ $model ]['cost_per_1m_input'],
			'output' => $this->available_models[ $model ]['cost_per_1m_output'],
			'currency' => 'USD',
			'per_tokens' => 1000000,
		);
	}

	/**
	 * Calculate cost for a specific generation
	 *
	 * @param int $input_tokens Input token count.
	 * @param int $output_tokens Output token count.
	 * @param string $model Model used.
	 * @param int $cached_tokens Cached tokens (for prefix caching discount).
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
		if ( $cache_miss_tokens > 0 ) {
			$input_cost += ( $cache_miss_tokens / 1000000 ) * $model_info['cost_per_1m_input'];
		}
		
		// Cost for cache hit tokens (10x cheaper)
		if ( $cache_hit_tokens > 0 && isset( $model_info['cost_per_1m_input_cached'] ) ) {
			$input_cost += ( $cache_hit_tokens / 1000000 ) * $model_info['cost_per_1m_input_cached'];
		}
		
		// Output cost
		$output_cost = ( $output_tokens / 1000000 ) * $model_info['cost_per_1m_output'];
		
		return $input_cost + $output_cost;
	}

	/**
	 * Get usage statistics with prefix caching info
	 *
	 * @return array Statistics array.
	 */
	public function get_statistics() {
		$stats = parent::get_statistics();
		
		// Add DeepSeek-specific stats
		$stats['prefix_cache_hits'] = get_option( 'tldr_pro_deepseek_cache_hits', 0 );
		$stats['prefix_cache_savings'] = $this->calculate_cache_savings();
		
		return $stats;
	}

	/**
	 * Calculate savings from prefix caching
	 *
	 * @return float Savings in USD.
	 */
	private function calculate_cache_savings() {
		$cache_hits = get_option( 'tldr_pro_deepseek_cache_hits', 0 );
		$avg_cached_tokens = get_option( 'tldr_pro_deepseek_avg_cached_tokens', 500 );
		
		$pricing = $this->get_model_pricing();
		$savings_per_million = $pricing['input'] * 0.5;
		
		return ( $cache_hits * $avg_cached_tokens / 1000000 ) * $savings_per_million;
	}
}