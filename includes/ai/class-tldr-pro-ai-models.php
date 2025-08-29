<?php
/**
 * AI Models Configuration and Management
 *
 * @package TLDR_Pro
 * @subpackage AI
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI Models Configuration class
 *
 * @since 1.0.0
 */
class TLDR_Pro_AI_Models {

	/**
	 * Singleton instance
	 *
	 * @var TLDR_Pro_AI_Models
	 */
	private static $instance = null;

	/**
	 * Complete model configurations - ONLY DeepSeek and Gemini
	 *
	 * @var array
	 */
	private $models_config = array(
		'deepseek' => array(
			'provider_name' => 'DeepSeek',
			'models' => array(
				'deepseek-chat' => array(
					'name' => 'DeepSeek Chat',
					'description' => 'Best for general content summarization',
					'context_window' => 32768,
					'max_output' => 4096,
					'cost_per_1m_input' => 0.14,
					'cost_per_1m_output' => 0.28,
					'features' => array( 'prefix_caching', 'json_mode' ),
					'recommended_for' => array( 'articles', 'blogs', 'news' ),
					'quality_score' => 85,
					'speed_score' => 90,
					'cost_score' => 95,
				),
				'deepseek-coder' => array(
					'name' => 'DeepSeek Coder',
					'description' => 'Optimized for technical content',
					'context_window' => 32768,
					'max_output' => 4096,
					'cost_per_1m_input' => 0.14,
					'cost_per_1m_output' => 0.28,
					'features' => array( 'prefix_caching', 'code_understanding' ),
					'recommended_for' => array( 'documentation', 'tutorials', 'technical' ),
					'quality_score' => 88,
					'speed_score' => 88,
					'cost_score' => 95,
				),
				'deepseek-reasoner' => array(
					'name' => 'DeepSeek Reasoner',
					'description' => 'Advanced reasoning for complex content',
					'context_window' => 32768,
					'max_output' => 4096,
					'cost_per_1m_input' => 0.55,
					'cost_per_1m_output' => 2.19,
					'features' => array( 'advanced_reasoning', 'multi_step_analysis' ),
					'recommended_for' => array( 'research', 'academic', 'analysis' ),
					'quality_score' => 92,
					'speed_score' => 75,
					'cost_score' => 70,
				),
			),
		),
		'gemini' => array(
			'provider_name' => 'Google Gemini',
			'models' => array(
				'gemini-1.5-flash' => array(
					'name' => 'Gemini 1.5 Flash',
					'description' => 'Fast and efficient for most tasks',
					'context_window' => 1048576,
					'max_output' => 8192,
					'cost_per_1m_input' => 0.075,
					'cost_per_1m_output' => 0.30,
					'features' => array( 'multimodal', 'long_context', 'caching' ),
					'recommended_for' => array( 'long_articles', 'books', 'reports' ),
					'quality_score' => 87,
					'speed_score' => 95,
					'cost_score' => 90,
				),
				'gemini-1.5-flash-8b' => array(
					'name' => 'Gemini 1.5 Flash 8B',
					'description' => 'Ultra-fast for simple summaries',
					'context_window' => 1048576,
					'max_output' => 8192,
					'cost_per_1m_input' => 0.0375,
					'cost_per_1m_output' => 0.15,
					'features' => array( 'multimodal', 'long_context' ),
					'recommended_for' => array( 'short_content', 'quick_summaries' ),
					'quality_score' => 80,
					'speed_score' => 98,
					'cost_score' => 98,
				),
				'gemini-1.5-pro' => array(
					'name' => 'Gemini 1.5 Pro',
					'description' => 'Most capable for complex content',
					'context_window' => 2097152,
					'max_output' => 8192,
					'cost_per_1m_input' => 1.25,
					'cost_per_1m_output' => 5.00,
					'features' => array( 'multimodal', 'super_long_context', 'advanced_reasoning' ),
					'recommended_for' => array( 'books', 'research_papers', 'complex_analysis' ),
					'quality_score' => 93,
					'speed_score' => 85,
					'cost_score' => 60,
				),
			),
		),
	);

	/**
	 * Get singleton instance
	 *
	 * @return TLDR_Pro_AI_Models
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
		// Private constructor for singleton
	}

	/**
	 * Get all models configuration
	 *
	 * @return array
	 */
	public function get_all_models() {
		return $this->models_config;
	}

	/**
	 * Get models for a specific provider
	 *
	 * @param string $provider Provider key.
	 * @return array
	 */
	public function get_provider_models( $provider ) {
		return isset( $this->models_config[ $provider ] ) 
			? $this->models_config[ $provider ]['models'] 
			: array();
	}

	/**
	 * Get specific model configuration
	 *
	 * @param string $provider Provider key.
	 * @param string $model Model key.
	 * @return array|null
	 */
	public function get_model_config( $provider, $model ) {
		if ( isset( $this->models_config[ $provider ]['models'][ $model ] ) ) {
			return $this->models_config[ $provider ]['models'][ $model ];
		}
		return null;
	}

	/**
	 * Get recommended model based on content and requirements
	 *
	 * @param array $requirements Requirements array.
	 * @return array Recommended model info.
	 */
	public function get_recommended_model( $requirements = array() ) {
		$defaults = array(
			'content_length' => 'medium',
			'content_type' => 'general',
			'priority' => 'balanced',
			'budget' => 'medium',
			'max_cost_per_summary' => 0.01,
		);

		$requirements = wp_parse_args( $requirements, $defaults );
		
		$recommendations = array();

		foreach ( $this->models_config as $provider_key => $provider ) {
			foreach ( $provider['models'] as $model_key => $model ) {
				$score = $this->calculate_model_score( $model, $requirements );
				
				$recommendations[] = array(
					'provider' => $provider_key,
					'model' => $model_key,
					'name' => $model['name'],
					'score' => $score,
					'cost_estimate' => $this->estimate_cost( $model, $requirements ),
				);
			}
		}

		// Sort by score
		usort( $recommendations, function( $a, $b ) {
			return $b['score'] - $a['score'];
		} );

		return $recommendations[0] ?? null;
	}

	/**
	 * Calculate model score based on requirements
	 *
	 * @param array $model Model configuration.
	 * @param array $requirements Requirements.
	 * @return int Score.
	 */
	private function calculate_model_score( $model, $requirements ) {
		$score = 0;

		// Priority weights
		$weights = array(
			'speed' => array( 'speed' => 3, 'quality' => 1, 'cost' => 2 ),
			'quality' => array( 'speed' => 1, 'quality' => 3, 'cost' => 1 ),
			'cost' => array( 'speed' => 1, 'quality' => 1, 'cost' => 3 ),
			'balanced' => array( 'speed' => 2, 'quality' => 2, 'cost' => 2 ),
		);

		$priority_weights = $weights[ $requirements['priority'] ] ?? $weights['balanced'];

		// Calculate weighted score
		$score += $model['speed_score'] * $priority_weights['speed'];
		$score += $model['quality_score'] * $priority_weights['quality'];
		$score += $model['cost_score'] * $priority_weights['cost'];

		// Content type bonus
		if ( in_array( $requirements['content_type'], $model['recommended_for'], true ) ) {
			$score += 20;
		}

		// Context window check
		$required_context = $this->get_required_context( $requirements['content_length'] );
		if ( $model['context_window'] < $required_context ) {
			$score -= 50;
		}

		// Cost constraint
		$estimated_cost = $this->estimate_cost( $model, $requirements );
		if ( $estimated_cost > $requirements['max_cost_per_summary'] ) {
			$score -= 100;
		}

		return $score;
	}

	/**
	 * Estimate cost for a summary
	 *
	 * @param array $model Model configuration.
	 * @param array $requirements Requirements.
	 * @return float Estimated cost.
	 */
	private function estimate_cost( $model, $requirements ) {
		$input_tokens = $this->estimate_tokens( $requirements['content_length'], 'input' );
		$output_tokens = $this->estimate_tokens( $requirements['content_length'], 'output' );

		$input_cost = ( $input_tokens / 1000000 ) * $model['cost_per_1m_input'];
		$output_cost = ( $output_tokens / 1000000 ) * $model['cost_per_1m_output'];

		return $input_cost + $output_cost;
	}

	/**
	 * Estimate token count
	 *
	 * @param string $length Content length category.
	 * @param string $type Token type (input/output).
	 * @return int Token estimate.
	 */
	private function estimate_tokens( $length, $type ) {
		$estimates = array(
			'short' => array( 'input' => 500, 'output' => 150 ),
			'medium' => array( 'input' => 2000, 'output' => 300 ),
			'long' => array( 'input' => 5000, 'output' => 500 ),
			'very_long' => array( 'input' => 10000, 'output' => 750 ),
		);

		return $estimates[ $length ][ $type ] ?? $estimates['medium'][ $type ];
	}

	/**
	 * Get required context window
	 *
	 * @param string $length Content length category.
	 * @return int Required context window.
	 */
	private function get_required_context( $length ) {
		$requirements = array(
			'short' => 4000,
			'medium' => 8000,
			'long' => 16000,
			'very_long' => 32000,
		);

		return $requirements[ $length ] ?? 8000;
	}

	/**
	 * Get models comparison table
	 *
	 * @return array Comparison data.
	 */
	public function get_comparison_table() {
		$comparison = array();

		foreach ( $this->models_config as $provider_key => $provider ) {
			foreach ( $provider['models'] as $model_key => $model ) {
				$comparison[] = array(
					'provider' => $provider['provider_name'],
					'model' => $model['name'],
					'context_window' => number_format( $model['context_window'] ),
					'max_output' => number_format( $model['max_output'] ),
					'cost_1k_input' => '$' . number_format( $model['cost_per_1m_input'] / 1000, 4 ),
					'cost_1k_output' => '$' . number_format( $model['cost_per_1m_output'] / 1000, 4 ),
					'quality_score' => $model['quality_score'],
					'speed_score' => $model['speed_score'],
					'cost_score' => $model['cost_score'],
					'features' => implode( ', ', $model['features'] ),
				);
			}
		}

		return $comparison;
	}

	/**
	 * Get cost-optimized model selection
	 *
	 * @param float $max_monthly_budget Maximum monthly budget.
	 * @param int $expected_summaries Expected summaries per month.
	 * @return array Recommended models.
	 */
	public function get_cost_optimized_selection( $max_monthly_budget, $expected_summaries ) {
		$cost_per_summary = $max_monthly_budget / max( $expected_summaries, 1 );
		$recommendations = array();

		foreach ( $this->models_config as $provider_key => $provider ) {
			foreach ( $provider['models'] as $model_key => $model ) {
				$estimated_cost = $this->estimate_cost( 
					$model, 
					array( 'content_length' => 'medium' ) 
				);

				if ( $estimated_cost <= $cost_per_summary ) {
					$recommendations[] = array(
						'provider' => $provider_key,
						'model' => $model_key,
						'name' => $provider['provider_name'] . ' - ' . $model['name'],
						'estimated_cost' => $estimated_cost,
						'monthly_cost' => $estimated_cost * $expected_summaries,
						'quality_score' => $model['quality_score'],
						'speed_score' => $model['speed_score'],
					);
				}
			}
		}

		// Sort by quality score
		usort( $recommendations, function( $a, $b ) {
			return $b['quality_score'] - $a['quality_score'];
		} );

		return array_slice( $recommendations, 0, 5 );
	}
}