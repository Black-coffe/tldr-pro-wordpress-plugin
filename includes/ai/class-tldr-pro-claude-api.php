<?php
/**
 * Claude API Provider class
 *
 * @package TLDR_Pro
 * @subpackage AI
 * @since 2.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Claude API implementation using Anthropic SDK
 *
 * @since 2.6.0
 */
class TLDR_Pro_Claude_API extends TLDR_Pro_AI_Provider {

	/**
	 * Available Claude models with their specifications
	 *
	 * @var array
	 */
	private $available_models = array(
		'claude-opus-4-1-20250805' => array(
			'name' => 'Claude Opus 4.1',
			'description' => 'Most powerful model for complex challenges and reasoning',
			'context_window' => 200000,
			'output_tokens' => 4096,
			'cost_per_1m_input' => 15.00,
			'cost_per_1m_output' => 75.00,
			'recommended' => false,
			'supports_vision' => true,
		),
		'claude-sonnet-4-20250514' => array(
			'name' => 'Claude Sonnet 4',
			'description' => 'Balanced model for general tasks with excellent performance',
			'context_window' => 200000,
			'output_tokens' => 4096,
			'cost_per_1m_input' => 3.00,
			'cost_per_1m_output' => 15.00,
			'recommended' => true,
			'supports_vision' => true,
		),
		'claude-3-5-sonnet-20241022' => array(
			'name' => 'Claude 3.5 Sonnet',
			'description' => 'Fast and efficient for most content summarization tasks',
			'context_window' => 200000,
			'output_tokens' => 8192,
			'cost_per_1m_input' => 3.00,
			'cost_per_1m_output' => 15.00,
			'recommended' => false,
			'supports_vision' => true,
		),
		'claude-3-5-haiku-20241022' => array(
			'name' => 'Claude 3.5 Haiku',
			'description' => 'Fastest and most economical for simple tasks',
			'context_window' => 200000,
			'output_tokens' => 8192,
			'cost_per_1m_input' => 0.25,
			'cost_per_1m_output' => 1.25,
			'recommended' => false,
			'supports_vision' => false,
		),
	);

	/**
	 * Claude client instance
	 *
	 * @var \Anthropic\Client
	 */
	private $client = null;

	/**
	 * Initialize Claude-specific settings
	 *
	 * @return void
	 */
	protected function init() {
		$this->provider_name = 'claude';
		$this->api_endpoint = 'https://api.anthropic.com/v1/messages';
		$this->model = get_option( 'tldr_pro_claude_model', 'claude-sonnet-4-20250514' );
		$this->max_tokens = 4096; // Increased for complete summaries
		$this->temperature = 0.3;
		$this->timeout = 60;
		$this->rate_limit = 100;
		
		// Initialize Claude client if API key is available
		if ( ! empty( $this->api_key ) ) {
			$this->init_client();
		}
	}

	/**
	 * Initialize Claude client
	 *
	 * @return void
	 */
	private function init_client() {
		if ( ! class_exists( '\Anthropic\Client' ) ) {
			// Load composer autoloader if not already loaded
			$autoload_path = plugin_dir_path( dirname( __DIR__ ) ) . 'vendor/autoload.php';
			if ( file_exists( $autoload_path ) ) {
				require_once $autoload_path;
			} else {
				$this->logger->log( 'Anthropic SDK autoloader not found', 'error' );
				return;
			}
		}

		try {
			$this->client = new \Anthropic\Client( 
				apiKey: $this->api_key,
				authToken: null,
				baseUrl: null
			);
		} catch ( Exception $e ) {
			$this->logger->log( 'Failed to initialize Claude client: ' . $e->getMessage(), 'error' );
		}
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
		$this->logger->log('=== Claude Generate Summary Started ===', 'info');
		
		// Generate request ID for tracking
		$request_id = isset( $options['request_id'] ) ? $options['request_id'] : uniqid( 'claude_' );
		$this->logger->log('Request ID: ' . $request_id, 'info');
		$this->logger->log('Content length: ' . strlen($content) . ' chars', 'debug');
		$this->logger->log('Options: ' . json_encode($options), 'debug');
		
		// Include status tracker
		if ( file_exists( TLDR_PRO_PLUGIN_DIR . 'includes/class-tldr-pro-status-tracker.php' ) ) {
			require_once TLDR_PRO_PLUGIN_DIR . 'includes/class-tldr-pro-status-tracker.php';
		}
		
		// Update status: initializing
		if ( class_exists( 'TLDR_Pro_Status_Tracker' ) ) {
			TLDR_Pro_Status_Tracker::update_status( $request_id, 'initializing', array( 'provider' => 'claude' ) );
		}
		
		if ( empty( $this->api_key ) ) {
			$this->logger->log('ERROR: Claude API key is missing', 'error');
			if ( class_exists( 'TLDR_Pro_Status_Tracker' ) ) {
				TLDR_Pro_Status_Tracker::update_status( $request_id, 'error', array( 'message' => 'API key missing' ) );
			}
			return new WP_Error( 
				'missing_api_key',
				__( 'Claude API key is not configured.', 'tldr-pro' )
			);
		}

		// Initialize client if not already initialized
		if ( null === $this->client ) {
			$this->init_client();
			if ( null === $this->client ) {
				return new WP_Error(
					'client_init_failed',
					__( 'Failed to initialize Claude client.', 'tldr-pro' )
				);
			}
		}

		$cache_key = 'claude_' . md5( $content . serialize( $options ) );
		$this->logger->log('Cache key: ' . $cache_key, 'debug');
		
		$cached = $this->get_cached_response( $cache_key );
		
		if ( false !== $cached ) {
			$this->logger->log( 'Using cached Claude response', 'info' );
			return $cached;
		}

		$system_prompt = $this->create_system_prompt( $options );
		$user_prompt = $this->create_user_prompt( $content, $options );
		
		// Update status: preparing
		if ( class_exists( 'TLDR_Pro_Status_Tracker' ) ) {
			TLDR_Pro_Status_Tracker::update_status( $request_id, 'preparing', array( 
				'model' => $this->model,
				'content_length' => strlen( $content )
			) );
		}

		try {
			// Use SDK directly if available, otherwise fall back to HTTP API
			if ( $this->client && method_exists( $this->client, 'messages' ) ) {
				$this->logger->log('Using Anthropic SDK for request', 'info');
				
				// Update status: sending
				if ( class_exists( 'TLDR_Pro_Status_Tracker' ) ) {
					TLDR_Pro_Status_Tracker::update_status( $request_id, 'sending', array( 
						'endpoint' => 'Claude SDK',
						'timeout' => $this->timeout
					) );
				}

				$start_time = microtime( true );
				
				// Use the SDK to create a message
				$message = $this->client->messages->create(
					$this->max_tokens, // maxTokens
					[ // messages
						\Anthropic\Messages\MessageParam::with(
							'user',
							$user_prompt
						)
					],
					$this->model, // model
					null, // metadata
					null, // serviceTier
					null, // stopSequences
					$system_prompt, // system
					$this->temperature // temperature
				);
				
				$api_duration = microtime( true ) - $start_time;
				$this->logger->log('Claude API call completed in ' . round($api_duration, 2) . ' seconds', 'info');

				// Extract response
				$summary_text = '';
				if ( isset( $message->content ) && is_array( $message->content ) ) {
					foreach ( $message->content as $content_block ) {
						if ( isset( $content_block->text ) ) {
							$summary_text .= $content_block->text;
						}
					}
				}

				// Get token usage
				$input_tokens = isset( $message->usage->input_tokens ) ? $message->usage->input_tokens : 0;
				$output_tokens = isset( $message->usage->output_tokens ) ? $message->usage->output_tokens : 0;
				$total_tokens = $input_tokens + $output_tokens;

			} else {
				// Fallback to HTTP API
				$this->logger->log('Using HTTP API for request', 'info');
				
				$messages = array(
					array(
						'role' => 'user',
						'content' => $user_prompt,
					),
				);

				$request_body = array(
					'model' => $this->model,
					'messages' => $messages,
					'system' => $system_prompt,
					'temperature' => $this->temperature,
					'max_tokens' => $this->max_tokens,
				);

				$args = array(
					'method' => 'POST',
					'headers' => array(
						'Content-Type' => 'application/json',
						'x-api-key' => $this->api_key,
						'anthropic-version' => '2023-06-01',
					),
					'body' => wp_json_encode( $request_body ),
					'timeout' => $this->timeout,
				);

				// Update status: sending
				if ( class_exists( 'TLDR_Pro_Status_Tracker' ) ) {
					TLDR_Pro_Status_Tracker::update_status( $request_id, 'sending', array( 
						'endpoint' => $this->api_endpoint,
						'timeout' => $this->timeout
					) );
				}

				$start_time = microtime( true );
				$response = $this->make_request( $this->api_endpoint, $args );
				$api_duration = microtime( true ) - $start_time;
				
				if ( is_wp_error( $response ) ) {
					$this->logger->log('Claude API request FAILED', 'error');
					$this->logger->log('Error: ' . $response->get_error_message(), 'error');
					
					if ( class_exists( 'TLDR_Pro_Status_Tracker' ) ) {
						TLDR_Pro_Status_Tracker::update_status( $request_id, 'error', array( 
							'message' => $response->get_error_message()
						) );
					}
					return $response;
				}

				$body = json_decode( $response['body'], true );
				
				if ( ! isset( $body['content'][0]['text'] ) ) {
					$error_message = isset( $body['error']['message'] ) 
						? $body['error']['message'] 
						: __( 'Invalid response from Claude API', 'tldr-pro' );
					
					$this->logger->log('Invalid API response structure', 'error');
					$this->logger->log('Error message: ' . $error_message, 'error');
					
					return new WP_Error(
						'api_error',
						$error_message,
						array( 'response' => $body )
					);
				}

				$summary_text = $body['content'][0]['text'];
				$input_tokens = isset( $body['usage']['input_tokens'] ) ? $body['usage']['input_tokens'] : 0;
				$output_tokens = isset( $body['usage']['output_tokens'] ) ? $body['usage']['output_tokens'] : 0;
				$total_tokens = $input_tokens + $output_tokens;
			}

			// Clean up markdown code blocks if present
			$summary_text = $this->clean_markdown_code_blocks( $summary_text );
			
			$this->add_tokens_used( $total_tokens );
			$this->increment_request_counter();
			$this->record_response_time( $api_duration );

			$result = array(
				'summary' => trim( $summary_text ),
				'provider' => 'claude',
				'model' => $this->model,
				'tokens_used' => $total_tokens,
				'input_tokens' => $input_tokens,
				'output_tokens' => $output_tokens,
				'generation_time' => $api_duration,
				'cached' => false,
			);

			// Update status: completed
			if ( class_exists( 'TLDR_Pro_Status_Tracker' ) ) {
				TLDR_Pro_Status_Tracker::update_status( $request_id, 'completed', array( 
					'tokens' => $total_tokens,
					'time' => $api_duration
				) );
			}
			
			$this->cache_response( $cache_key, $result, 2 * HOUR_IN_SECONDS );
			
			$this->logger->log(
				sprintf(
					'Claude summary generated: %d tokens in %.2fs',
					$total_tokens,
					$api_duration
				),
				'info'
			);

			return $result;

		} catch ( Exception $e ) {
			$this->logger->log('Claude API exception: ' . $e->getMessage(), 'error');
			
			if ( class_exists( 'TLDR_Pro_Status_Tracker' ) ) {
				TLDR_Pro_Status_Tracker::update_status( $request_id, 'error', array( 
					'message' => $e->getMessage()
				) );
			}
			
			return new WP_Error(
				'api_exception',
				sprintf( __( 'Claude API error: %s', 'tldr-pro' ), $e->getMessage() )
			);
		}
	}

	/**
	 * Create system prompt for Claude
	 *
	 * @param array $options Options.
	 * @return string System prompt.
	 */
	private function create_system_prompt( $options = array() ) {
		$template = get_option( 'tldr_pro_claude_system_prompt', '' );
		
		if ( empty( $template ) ) {
			$template = "You are an expert content summarizer specializing in creating clear, concise TL;DR summaries.

CRITICAL REQUIREMENTS:
1. OUTPUT MUST BE VALID HTML - NO MARKDOWN!
2. Use proper HTML tags: <h2>, <h3>, <p>, <ul>, <li>, <strong>, <em>
3. DO NOT use Markdown syntax like ##, **, *, or -
4. Include inline CSS styles in HTML tags
5. Focus on key insights, main arguments, and actionable takeaways
6. Maintain professional tone while being engaging

Your response must be ready to display directly in a web browser as HTML.";
		}

		return $template;
	}

	/**
	 * Create user prompt for Claude
	 *
	 * @param string $content Content to summarize.
	 * @param array  $options Additional options.
	 * @return string User prompt.
	 */
	private function create_user_prompt( $content, $options = array() ) {
		// Get prompt template from Prompt Manager
		$template = TLDR_Pro_Prompt_Manager::get_prompt( 'claude' );
		
		if ( empty( $template ) ) {
			$template = $this->get_default_user_prompt_template();
		}

		// Process the prompt with actual settings
		$processed_prompt = TLDR_Pro_Prompt_Manager::process_prompt( $template, array(
			'content' => $content,
		) );
		
		// Log the prompt for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '=== TLDR PRO CLAUDE API REQUEST ===' );
			error_log( 'Language Setting: ' . TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_summary_language', 'en' ) );
			error_log( 'Max Length: ' . TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_max_summary_length', 150 ) );
			error_log( 'Model: ' . $this->model );
			error_log( '=== END CLAUDE REQUEST ===' );
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

Requirements:
- Language: {language}
- Maximum length: {max_length} words
- Style: {style}
- Format: {format}
- Include 3-5 key bullet points
- Use relevant emojis for better readability
- Focus on actionable insights and main takeaways

Content to summarize:
{content}

Generate a concise, engaging summary that captures the core message.";
	}

	/**
	 * Clean markdown code blocks from response and convert to HTML if needed
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
		
		// Check if response contains Markdown formatting
		if ( strpos( $text, '##' ) !== false || strpos( $text, '**' ) !== false || strpos( $text, '•' ) !== false ) {
			$text = $this->convert_markdown_to_html( $text );
		}
		
		return trim( $text );
	}
	
	/**
	 * Convert Markdown to HTML
	 *
	 * @param string $markdown Markdown text.
	 * @return string HTML text.
	 */
	private function convert_markdown_to_html( $markdown ) {
		// Convert headers
		$html = preg_replace( '/^### (.+)$/m', '<h3>$1</h3>', $markdown );
		$html = preg_replace( '/^## (.+)$/m', '<h2>$1</h2>', $html );
		$html = preg_replace( '/^# (.+)$/m', '<h1>$1</h1>', $html );
		
		// Convert bold text
		$html = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html );
		
		// Convert italic text
		$html = preg_replace( '/\*(.+?)\*/s', '<em>$1</em>', $html );
		
		// Convert bullet points
		$html = preg_replace( '/^[•\*\-] (.+)$/m', '<li>$1</li>', $html );
		
		// Wrap consecutive <li> tags in <ul>
		$html = preg_replace( '/(<li>.*<\/li>\s*)+/s', '<ul>$0</ul>', $html );
		
		// Convert line breaks to paragraphs
		$paragraphs = explode( "\n\n", $html );
		$html_output = '';
		
		foreach ( $paragraphs as $paragraph ) {
			$paragraph = trim( $paragraph );
			if ( ! empty( $paragraph ) ) {
				// Don't wrap headers, lists in paragraphs
				if ( ! preg_match( '/^<(h[1-6]|ul|ol|li)/i', $paragraph ) ) {
					$html_output .= '<p>' . $paragraph . '</p>';
				} else {
					$html_output .= $paragraph;
				}
			}
		}
		
		// Apply our standard HTML template
		$formatted_html = '<div class="tldr-summary-container" style="font-family: \'Inter\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif; background: linear-gradient(145deg, #06b6d4 0%, #3b82f6 50%, #8b5cf6 100%); border-radius: 20px; padding: 4px; box-shadow: 0 25px 50px -12px rgba(59,130,246,0.25);">';
		$formatted_html .= '<div style="background: #ffffff; border-radius: 16px; padding: 28px;">';
		$formatted_html .= '<h2 style="margin: 0 0 20px 0; color: #3b82f6; font-size: 1.75em; font-weight: 700; display: flex; align-items: center; gap: 14px; border-bottom: 3px solid transparent; border-image: linear-gradient(90deg, #06b6d4, #3b82f6, #8b5cf6) 1; padding-bottom: 16px;">';
		$formatted_html .= '<span style="font-size: 1.1em;">🎯</span>';
		$formatted_html .= '<span>TL;DR Summary</span>';
		$formatted_html .= '</h2>';
		$formatted_html .= '<div class="tldr-content" style="line-height: 1.85; color: #1e293b; font-size: 1.06em;">';
		$formatted_html .= $html_output;
		$formatted_html .= '</div></div></div>';
		
		return $formatted_html;
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
				__( 'Claude API key is missing.', 'tldr-pro' )
			);
		}

		// Initialize client if not already initialized
		if ( null === $this->client ) {
			$this->init_client();
			if ( null === $this->client ) {
				// Try HTTP validation
				return $this->validate_via_http();
			}
		}

		try {
			// Test with a simple message
			$message = $this->client->messages->create(
				10, // maxTokens
				[ // messages
					\Anthropic\Messages\MessageParam::with(
						'user',
						'Say "API key is valid" if you can read this.'
					)
				],
				$this->model, // model
				null, // metadata
				null, // serviceTier
				null, // stopSequences
				null, // system
				0 // temperature
			);

			if ( isset( $message->content ) ) {
				return true;
			}

		} catch ( Exception $e ) {
			if ( strpos( $e->getMessage(), 'authentication' ) !== false || 
			     strpos( $e->getMessage(), '401' ) !== false ) {
				return new WP_Error(
					'invalid_api_key',
					__( 'Invalid Claude API key.', 'tldr-pro' )
				);
			}
			
			return new WP_Error(
				'api_error',
				sprintf( __( 'API validation failed: %s', 'tldr-pro' ), $e->getMessage() )
			);
		}

		return true;
	}

	/**
	 * Validate credentials via HTTP API
	 *
	 * @return bool|WP_Error
	 */
	private function validate_via_http() {
		$test_body = array(
			'model' => $this->model,
			'messages' => array(
				array(
					'role' => 'user',
					'content' => 'Test message for API validation.',
				),
			),
			'max_tokens' => 10,
			'temperature' => 0,
		);

		$args = array(
			'method' => 'POST',
			'headers' => array(
				'Content-Type' => 'application/json',
				'x-api-key' => $this->api_key,
				'anthropic-version' => '2023-06-01',
			),
			'body' => wp_json_encode( $test_body ),
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
				__( 'Invalid Claude API key.', 'tldr-pro' )
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
		$results = array(
			'status' => 'testing',
			'provider' => 'Claude (Anthropic)',
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
			sprintf( 'Starting Claude API test for model: %s', $this->model ),
			'info'
		);
		
		// Test 1: API Key Format
		if ( empty( $this->api_key ) ) {
			$results['tests']['api_key'] = array(
				'status' => 'error',
				'message' => __( 'API key is not configured', 'tldr-pro' ),
			);
			$results['status'] = 'error';
			$results['recommendations'][] = __( 'Please enter your Claude API key', 'tldr-pro' );
			$results['recommendations'][] = __( 'Get your API key at https://console.anthropic.com/settings/keys', 'tldr-pro' );
			return $results;
		}
		
		if ( ! preg_match( '/^sk-ant-api03-[a-zA-Z0-9\-_]{90,}$/', $this->api_key ) ) {
			$results['tests']['api_key'] = array(
				'status' => 'warning',
				'message' => __( 'API key format may be incorrect', 'tldr-pro' ),
			);
			$results['recommendations'][] = __( 'Claude API keys typically start with "sk-ant-api03-"', 'tldr-pro' );
		} else {
			$results['tests']['api_key'] = array(
				'status' => 'success',
				'message' => __( 'API key format is valid', 'tldr-pro' ),
			);
		}
		
		// Test 2: Network Connectivity
		$connectivity_start = microtime( true );
		$connectivity_test = wp_remote_get( 'https://api.anthropic.com', array( 'timeout' => 5 ) );
		$connectivity_time = microtime( true ) - $connectivity_start;
		
		if ( is_wp_error( $connectivity_test ) ) {
			$results['tests']['connectivity'] = array(
				'status' => 'error',
				'message' => sprintf( __( 'Cannot reach Claude API: %s', 'tldr-pro' ), $connectivity_test->get_error_message() ),
			);
			$results['status'] = 'error';
			return $results;
		}
		
		$results['tests']['connectivity'] = array(
			'status' => 'success',
			'message' => sprintf( __( 'API endpoint reachable (%.2fs)', 'tldr-pro' ), $connectivity_time ),
		);
		
		// Test 3: Authentication & Generation
		$auth_start = microtime( true );
		
		// Initialize client if needed
		if ( null === $this->client ) {
			$this->init_client();
		}
		
		$auth_success = false;
		$auth_error_msg = '';
		
		// Try SDK first
		if ( $this->client ) {
			try {
				$message = $this->client->messages->create(
					10, // maxTokens
					[ // messages
						\Anthropic\Messages\MessageParam::with(
							'user',
							'Test: respond with "OK"'
						)
					],
					$this->model, // model
					null, // metadata
					null, // serviceTier
					null, // stopSequences
					null, // system
					0 // temperature
				);
				
				$auth_success = true;
				$auth_time = microtime( true ) - $auth_start;
				
				// Get usage info
				if ( isset( $message->usage ) ) {
					$input_tokens = $message->usage->input_tokens ?? 0;
					$output_tokens = $message->usage->output_tokens ?? 0;
					$cost = $this->calculate_cost( $input_tokens, $output_tokens, $this->model );
					
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
							'estimated_cost' => $cost,
						),
					);
				}
				
			} catch ( Exception $e ) {
				$auth_error_msg = $e->getMessage();
			}
		}
		
		// Fallback to HTTP if SDK failed
		if ( ! $auth_success ) {
			$test_response = $this->validate_via_http();
			if ( is_wp_error( $test_response ) ) {
				$auth_error_msg = $test_response->get_error_message();
			} else {
				$auth_success = true;
				$auth_time = microtime( true ) - $auth_start;
			}
		}
		
		if ( $auth_success ) {
			$results['tests']['authentication'] = array(
				'status' => 'success',
				'message' => sprintf( __( 'Authentication successful (%.2fs)', 'tldr-pro' ), $auth_time ),
			);
			$results['tests']['generation'] = array(
				'status' => 'success',
				'message' => __( 'API is generating responses correctly', 'tldr-pro' ),
			);
		} else {
			$results['tests']['authentication'] = array(
				'status' => 'error',
				'message' => sprintf( __( 'Authentication failed: %s', 'tldr-pro' ), $auth_error_msg ),
			);
			$results['status'] = 'error';
			
			if ( strpos( $auth_error_msg, '401' ) !== false || strpos( $auth_error_msg, 'authentication' ) !== false ) {
				$results['recommendations'][] = __( 'Please check your API key at https://console.anthropic.com/settings/keys', 'tldr-pro' );
			}
		}
		
		// Calculate total time
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
				__( 'Claude API is ready to use. Response time: %.2fs', 'tldr-pro' ),
				$auth_time ?? 0
			);
		}
		
		// Add model info
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
				'vision_support' => $model_info['supports_vision'] ? 
					__( '✅ Vision/image analysis supported', 'tldr-pro' ) : 
					__( '❌ No vision support', 'tldr-pro' ),
			);
		}
		
		// Log final result
		$this->logger->log(
			sprintf( 'Claude API test completed: %s (%.2fs)', $results['status'], $total_time ),
			$results['status'] === 'error' ? 'error' : 'info'
		);
		
		return $results;
	}

	/**
	 * Calculate cost for a specific generation
	 *
	 * @param int $input_tokens Input token count.
	 * @param int $output_tokens Output token count.
	 * @param string $model Model used.
	 * @return float Cost in USD.
	 */
	public function calculate_cost( $input_tokens, $output_tokens, $model = null ) {
		if ( null === $model ) {
			$model = $this->model;
		}
		
		if ( ! isset( $this->available_models[ $model ] ) ) {
			return 0;
		}
		
		$model_info = $this->available_models[ $model ];
		
		$input_cost = ( $input_tokens / 1000000 ) * $model_info['cost_per_1m_input'];
		$output_cost = ( $output_tokens / 1000000 ) * $model_info['cost_per_1m_output'];
		
		return $input_cost + $output_cost;
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
				'per_tokens' => 1000000,
			);
		}

		$model_info = $this->available_models[ $model ];

		return array(
			'input' => $model_info['cost_per_1m_input'],
			'output' => $model_info['cost_per_1m_output'],
			'currency' => 'USD',
			'per_tokens' => 1000000,
		);
	}
}