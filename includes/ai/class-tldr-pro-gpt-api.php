<?php
/**
 * OpenAI GPT API Integration
 *
 * @package    TLDR_Pro
 * @subpackage TLDR_Pro/includes/ai
 * @since      2.7.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenAI GPT API provider class
 */
class TLDR_Pro_GPT_API extends TLDR_Pro_AI_Provider {

	/**
	 * Provider name
	 *
	 * @var string
	 */
	protected $provider_name = 'gpt';

	/**
	 * API client instance
	 *
	 * @var \OpenAI\Client
	 */
	protected $client;

	/**
	 * Default model
	 *
	 * @var string
	 */
	protected $default_model = 'gpt-4-turbo';

	/**
	 * Available models with their configurations
	 *
	 * @var array
	 */
	protected $models = array(
		'gpt-4-turbo' => array(
			'name' => 'GPT-4 Turbo',
			'max_tokens' => 128000,
			'supports_vision' => true,
			'supports_functions' => true,
			'cost_per_1k_input' => 0.01,
			'cost_per_1k_output' => 0.03,
		),
		'gpt-4o' => array(
			'name' => 'GPT-4o',
			'max_tokens' => 128000,
			'supports_vision' => true,
			'supports_functions' => true,
			'cost_per_1k_input' => 0.005,
			'cost_per_1k_output' => 0.015,
		),
		'gpt-4o-mini' => array(
			'name' => 'GPT-4o Mini',
			'max_tokens' => 128000,
			'supports_vision' => true,
			'supports_functions' => true,
			'cost_per_1k_input' => 0.00015,
			'cost_per_1k_output' => 0.0006,
		),
		'gpt-3.5-turbo' => array(
			'name' => 'GPT-3.5 Turbo',
			'max_tokens' => 16385,
			'supports_vision' => false,
			'supports_functions' => true,
			'cost_per_1k_input' => 0.0005,
			'cost_per_1k_output' => 0.0015,
		),
		'o1' => array(
			'name' => 'O1 (Reasoning)',
			'max_tokens' => 128000,
			'supports_vision' => false,
			'supports_functions' => false,
			'cost_per_1k_input' => 0.015,
			'cost_per_1k_output' => 0.06,
			'description' => 'Advanced reasoning model for complex tasks',
		),
		'o1-mini' => array(
			'name' => 'O1 Mini (Reasoning)',
			'max_tokens' => 128000,
			'supports_vision' => false,
			'supports_functions' => false,
			'cost_per_1k_input' => 0.003,
			'cost_per_1k_output' => 0.012,
			'description' => 'Faster reasoning model for coding and analysis',
		),
	);

	/**
	 * Initialize the provider
	 */
	protected function init() {
		if ( ! empty( $this->api_key ) ) {
			$this->init_client();
		}
	}

	/**
	 * Initialize OpenAI client
	 */
	protected function init_client() {
		try {
			if ( ! class_exists( '\OpenAI' ) ) {
				$this->logger->log( 'OpenAI PHP client not found. Please install openai-php/client via Composer.', 'error' );
				return;
			}

			// Initialize OpenAI client with API key
			$this->client = \OpenAI::client( $this->api_key );
			
			$this->logger->log( 'OpenAI GPT client initialized successfully', 'debug' );
		} catch ( Exception $e ) {
			$this->logger->log( 'Failed to initialize OpenAI client: ' . $e->getMessage(), 'error' );
		}
	}

	/**
	 * Generate summary using OpenAI GPT
	 *
	 * @param string $content Content to summarize
	 * @param array  $options Additional options
	 * @return array|WP_Error
	 */
	public function generate_summary( $content, $options = array() ) {
		// Check if client is initialized
		if ( ! $this->client ) {
			$this->init_client();
			if ( ! $this->client ) {
				return new WP_Error( 'no_client', 'OpenAI client not initialized. Please check your API key.' );
			}
		}

		// Get model from options or use default
		$model = isset( $options['model'] ) ? $options['model'] : $this->get_model();
		
		// Validate model
		if ( ! isset( $this->models[ $model ] ) ) {
			$model = $this->default_model;
		}

		// Get prompt
		$prompt = $this->create_prompt_structure( $content, $options );

		$start_time = microtime( true );

		try {
			// Special handling for O1 models (reasoning models)
			if ( strpos( $model, 'o1' ) === 0 ) {
				$response = $this->client->chat()->create( array(
					'model' => $model,
					'messages' => array(
						array(
							'role' => 'user',
							'content' => $prompt['user'],
						),
					),
					'max_completion_tokens' => $this->max_tokens,
					'temperature' => 1, // O1 models use fixed temperature of 1
				) );
			} else {
				// Standard GPT models
				$messages = array(
					array(
						'role' => 'system',
						'content' => $prompt['system'],
					),
					array(
						'role' => 'user',
						'content' => $prompt['user'],
					),
				);

				$params = array(
					'model' => $model,
					'messages' => $messages,
					'max_tokens' => $this->max_tokens,
					'temperature' => $this->temperature,
				);

				// Add optional parameters
				if ( isset( $options['top_p'] ) ) {
					$params['top_p'] = floatval( $options['top_p'] );
				}
				if ( isset( $options['presence_penalty'] ) ) {
					$params['presence_penalty'] = floatval( $options['presence_penalty'] );
				}
				if ( isset( $options['frequency_penalty'] ) ) {
					$params['frequency_penalty'] = floatval( $options['frequency_penalty'] );
				}

				$response = $this->client->chat()->create( $params );
			}

			$end_time = microtime( true );

			// Extract summary from response
			$summary = $response->choices[0]->message->content;

			// Convert Markdown to HTML if needed
			$summary = $this->ensure_html_output( $summary );

			// Prepare result
			$result = array(
				'summary' => $summary,
				'summary_text' => wp_strip_all_tags( $summary ),
				'summary_html' => $summary,
				'api_provider' => 'gpt',
				'model' => $model,
				'tokens_used' => $response->usage->totalTokens ?? 0,
				'prompt_tokens' => $response->usage->promptTokens ?? 0,
				'completion_tokens' => $response->usage->completionTokens ?? 0,
				'processing_time' => round( $end_time - $start_time, 2 ),
				'raw_response' => $this->debug_mode ? $response->toArray() : null,
			);

			// Calculate cost if available
			if ( isset( $this->models[ $model ] ) && isset( $result['prompt_tokens'] ) ) {
				$model_config = $this->models[ $model ];
				$input_cost = ( $result['prompt_tokens'] / 1000 ) * $model_config['cost_per_1k_input'];
				$output_cost = ( $result['completion_tokens'] / 1000 ) * $model_config['cost_per_1k_output'];
				$result['estimated_cost'] = round( $input_cost + $output_cost, 6 );
			}

			$this->logger->log( sprintf(
				'GPT summary generated: Model=%s, Tokens=%d, Time=%.2fs',
				$model,
				$result['tokens_used'],
				$result['processing_time']
			), 'info' );

			return $result;

		} catch ( Exception $e ) {
			$this->logger->log( 'GPT API error: ' . $e->getMessage(), 'error' );
			
			return new WP_Error(
				'api_error',
				sprintf( 'OpenAI GPT API error: %s', $e->getMessage() ),
				array( 'provider' => 'gpt', 'model' => $model )
			);
		}
	}

	/**
	 * Create prompt structure for GPT API
	 *
	 * @param string $content Content to summarize
	 * @param array  $options Options for prompt generation
	 * @return array Prompt structure with system and user messages
	 */
	protected function create_prompt_structure( $content, $options = array() ) {
		// Get prompt template from Prompt Manager
		$template = TLDR_Pro_Prompt_Manager::get_prompt( 'gpt' );
		
		if ( empty( $template ) ) {
			// Fallback to default template if none configured
			$template = 'Generate a professional TL;DR summary of the following content. Output must be in HTML format. Maximum {max_length} words. Language: {language}. Style: {style}. Content: {content}';
		}
		
		// Process the prompt with actual values
		$processed_prompt = TLDR_Pro_Prompt_Manager::process_prompt( $template, array(
			'content' => $content,
		) );
		
		// Split into system and user prompts
		// For GPT, we'll use a system prompt for instructions and user prompt for content
		$system_prompt = 'You are a professional content summarizer. Generate HTML-formatted TL;DR summaries. 
CRITICAL RULES:
1. Output MUST be valid, properly closed HTML
2. NEVER output incomplete tags like <p>&lt;/div or &lt;/div
3. All opening tags must have matching closing tags
4. Do NOT escape HTML entities in your output
5. End your response with proper closing tags, NOT with partial tags
6. Follow all instructions precisely.';
		
		return array(
			'system' => $system_prompt,
			'user' => $processed_prompt,
		);
	}

	/**
	 * Ensure output is HTML formatted
	 *
	 * @param string $text Text to check/convert
	 * @return string HTML formatted text
	 */
	protected function ensure_html_output( $text ) {
		// Clean up any escaped HTML entities that might break the output
		$text = str_replace( '&lt;/div', '</div', $text );
		$text = str_replace( '&lt;/p', '</p', $text );
		$text = str_replace( '&lt;/ul', '</ul', $text );
		$text = str_replace( '&lt;/li', '</li', $text );
		$text = str_replace( '&lt;/h', '</h', $text );
		$text = str_replace( '&lt;li', '<li', $text );
		$text = str_replace( '&lt;p', '<p', $text );
		
		// Remove any incomplete HTML tags (both opening and closing)
		// Remove incomplete opening tags at the end
		$text = preg_replace( '/<[^>]*$/s', '', $text );
		// Remove incomplete closing tags at the end
		$text = preg_replace( '/<p>&lt;\/[^>]+$/s', '', $text );
		$text = preg_replace( '/&lt;\/[^>]+$/s', '', $text );
		
		// Remove any <p> tags that contain incomplete <li> tags
		$text = preg_replace( '/<p>\s*&lt;li[^<]*<\/p>/s', '', $text );
		$text = preg_replace( '/<p>\s*<li[^>]*$/s', '', $text );
		
		// Fix unclosed HTML tags
		// Count opening and closing tags
		$tags_to_check = array( 'div', 'ul', 'ol', 'li', 'p' );
		foreach ( $tags_to_check as $tag ) {
			$open_count = substr_count( $text, '<' . $tag );
			$close_count = substr_count( $text, '</' . $tag . '>' );
			if ( $open_count > $close_count ) {
				$text .= str_repeat( '</' . $tag . '>', $open_count - $close_count );
			}
		}
		
		// Check if it's already valid HTML
		if ( preg_match( '/<(p|div|h[1-6]|ul|ol|blockquote)[^>]*>/i', $text ) ) {
			return $text;
		}

		// Convert Markdown to HTML if needed
		return $this->convert_markdown_to_html( $text );
	}

	/**
	 * Convert Markdown to HTML
	 *
	 * @param string $markdown Markdown text
	 * @return string HTML text
	 */
	protected function convert_markdown_to_html( $markdown ) {
		$html = $markdown;
		
		// Convert headers
		$html = preg_replace( '/^### (.+)$/m', '<h3>$1</h3>', $html );
		$html = preg_replace( '/^## (.+)$/m', '<h2>$1</h2>', $html );
		$html = preg_replace( '/^# (.+)$/m', '<h1>$1</h1>', $html );
		
		// Convert bold
		$html = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html );
		$html = preg_replace( '/__(.+?)__/', '<strong>$1</strong>', $html );
		
		// Convert italic
		$html = preg_replace( '/\*([^\*]+)\*/', '<em>$1</em>', $html );
		$html = preg_replace( '/_([^_]+)_/', '<em>$1</em>', $html );
		
		// Convert bullet lists
		$html = preg_replace_callback( '/^(\* .+(\n\* .+)*)/m', function( $matches ) {
			$items = explode( "\n", $matches[0] );
			$list = '<ul>';
			foreach ( $items as $item ) {
				$item = preg_replace( '/^\* /', '', $item );
				if ( ! empty( trim( $item ) ) ) {
					$list .= '<li>' . trim( $item ) . '</li>';
				}
			}
			$list .= '</ul>';
			return $list;
		}, $html );
		
		// Convert numbered lists
		$html = preg_replace_callback( '/^(\d+\. .+(\n\d+\. .+)*)/m', function( $matches ) {
			$items = explode( "\n", $matches[0] );
			$list = '<ol>';
			foreach ( $items as $item ) {
				$item = preg_replace( '/^\d+\. /', '', $item );
				if ( ! empty( trim( $item ) ) ) {
					$list .= '<li>' . trim( $item ) . '</li>';
				}
			}
			$list .= '</ol>';
			return $list;
		}, $html );
		
		// Convert blockquotes
		$html = preg_replace( '/^> (.+)$/m', '<blockquote>$1</blockquote>', $html );
		
		// Convert paragraphs
		$paragraphs = explode( "\n\n", $html );
		$html = '';
		foreach ( $paragraphs as $paragraph ) {
			$paragraph = trim( $paragraph );
			if ( ! empty( $paragraph ) ) {
				// Don't wrap if already has block-level tags
				if ( ! preg_match( '/^<(h[1-6]|ul|ol|blockquote|p|div)/i', $paragraph ) ) {
					$html .= '<p>' . $paragraph . '</p>';
				} else {
					$html .= $paragraph;
				}
			}
		}
		
		return $html;
	}

	/**
	 * Test API connection
	 *
	 * @return array|WP_Error
	 */
	public function test_connection() {
		// Initialize client if needed
		if ( ! $this->client ) {
			$this->init_client();
			if ( ! $this->client ) {
				return new WP_Error( 'no_client', 'Failed to initialize OpenAI client' );
			}
		}

		try {
			$start_time = microtime( true );
			
			// Simple test with minimal tokens
			$response = $this->client->chat()->create( array(
				'model' => 'gpt-3.5-turbo',
				'messages' => array(
					array(
						'role' => 'user',
						'content' => 'Say "API connection successful" in exactly 3 words.',
					),
				),
				'max_tokens' => 10,
				'temperature' => 0.5,
			) );
			
			$end_time = microtime( true );
			
			return array(
				'success' => true,
				'message' => 'OpenAI GPT connection successful',
				'response' => $response->choices[0]->message->content,
				'model' => 'gpt-3.5-turbo',
				'response_time' => round( $end_time - $start_time, 2 ) . 's',
				'tokens_used' => $response->usage->totalTokens ?? 0,
			);
			
		} catch ( Exception $e ) {
			$this->logger->log( 'Connection test failed: ' . $e->getMessage(), 'error' );
			return new WP_Error( 'test_failed', 'GPT connection test failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Validate API credentials
	 *
	 * @param string $api_key API key to validate
	 * @return bool|WP_Error
	 */
	public function validate_credentials( $api_key = null ) {
		if ( $api_key ) {
			$this->api_key = $api_key;
			$this->init_client();
		}
		
		$result = $this->test_connection();
		
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		
		return true;
	}

	/**
	 * Get available models
	 *
	 * @return array
	 */
	public function get_available_models() {
		$models = array();
		
		foreach ( $this->models as $model_id => $config ) {
			$models[ $model_id ] = array(
				'id' => $model_id,
				'name' => $config['name'],
				'description' => isset( $config['description'] ) ? $config['description'] : '',
				'max_tokens' => $config['max_tokens'],
				'supports_vision' => $config['supports_vision'],
				'supports_functions' => $config['supports_functions'],
				'cost_input' => '$' . number_format( $config['cost_per_1k_input'], 5 ) . '/1K tokens',
				'cost_output' => '$' . number_format( $config['cost_per_1k_output'], 5 ) . '/1K tokens',
			);
		}
		
		return $models;
	}

	/**
	 * Estimate cost for summary generation
	 *
	 * @param string $content Content to estimate
	 * @param string $model Model to use
	 * @return array
	 */
	public function estimate_cost( $content, $model = null ) {
		if ( ! $model ) {
			$model = $this->get_model();
		}
		
		if ( ! isset( $this->models[ $model ] ) ) {
			return array(
				'error' => 'Invalid model',
			);
		}
		
		// Rough token estimation (1 token ≈ 4 characters for English)
		$content_tokens = ceil( strlen( $content ) / 4 );
		$prompt_tokens = 200; // System + user prompt overhead
		$expected_output_tokens = min( 500, ceil( $content_tokens * 0.2 ) ); // 20% of input
		
		$model_config = $this->models[ $model ];
		
		$input_cost = ( ( $content_tokens + $prompt_tokens ) / 1000 ) * $model_config['cost_per_1k_input'];
		$output_cost = ( $expected_output_tokens / 1000 ) * $model_config['cost_per_1k_output'];
		
		return array(
			'model' => $model,
			'model_name' => $model_config['name'],
			'estimated_input_tokens' => $content_tokens + $prompt_tokens,
			'estimated_output_tokens' => $expected_output_tokens,
			'estimated_total_tokens' => $content_tokens + $prompt_tokens + $expected_output_tokens,
			'estimated_cost' => '$' . number_format( $input_cost + $output_cost, 6 ),
			'breakdown' => array(
				'input_cost' => '$' . number_format( $input_cost, 6 ),
				'output_cost' => '$' . number_format( $output_cost, 6 ),
			),
		);
	}
}