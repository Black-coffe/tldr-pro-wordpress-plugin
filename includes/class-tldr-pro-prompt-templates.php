<?php
/**
 * Prompt Templates class
 *
 * @package TLDR_Pro
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages prompt templates for AI summarization
 *
 * @since 1.0.0
 */
class TLDR_Pro_Prompt_Templates {

	/**
	 * Instance of this class
	 *
	 * @var TLDR_Pro_Prompt_Templates
	 */
	private static $instance = null;

	/**
	 * Available templates
	 *
	 * @var array
	 */
	private $templates = array();

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_templates();
	}

	/**
	 * Get instance
	 *
	 * @return TLDR_Pro_Prompt_Templates
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize default templates
	 *
	 * @return void
	 */
	private function init_templates() {
		$this->templates = array(
			'default' => array(
				'name' => __( 'Default', 'tldr-pro' ),
				'description' => __( 'General purpose summary for any content', 'tldr-pro' ),
				'prompt' => 'Provide a concise TL;DR summary of the following content in {language}. Include {bullets} key points. {emoji_instruction}

Title: {title}
Author: {author}
Date: {date}
Category: {category}

Content:
{content}',
				'variables' => array( 'language', 'bullets', 'emoji_instruction', 'content', 'title', 'author', 'date', 'category' ),
				'version' => '1.0',
			),
			'business' => array(
				'name' => __( 'Business', 'tldr-pro' ),
				'description' => __( 'Executive summaries for business content', 'tldr-pro' ),
				'prompt' => 'Create an executive summary of this business content in {language}. Focus on key takeaways, metrics, and actionable insights. Provide {bullets} main points. {emoji_instruction}

Article Title: {title}
Published: {date}

Content:
{content}

Format the summary with:
- Key business insights
- Important metrics and numbers
- Action items
- Strategic implications',
				'variables' => array( 'language', 'bullets', 'emoji_instruction', 'content', 'title', 'date' ),
				'version' => '1.0',
			),
			'academic' => array(
				'name' => __( 'Academic', 'tldr-pro' ),
				'description' => __( 'Scholarly abstracts for research and academic content', 'tldr-pro' ),
				'prompt' => 'Generate an academic abstract for this content in {language}. Include main thesis, methodology, findings, and conclusions in {bullets} points. Use formal language. {emoji_instruction}

Title: {title}
Author: {author}

Content:
{content}

Structure the abstract with:
1. Research objective
2. Methodology
3. Key findings
4. Conclusions
5. Implications',
				'variables' => array( 'language', 'bullets', 'emoji_instruction', 'content', 'title', 'author' ),
				'version' => '1.0',
			),
			'news' => array(
				'name' => __( 'News Article', 'tldr-pro' ),
				'description' => __( 'News summary with 5W1H format', 'tldr-pro' ),
				'prompt' => 'Summarize this news article in {language}. Include the who, what, when, where, why, and how. Provide {bullets} key facts. {emoji_instruction}

Headline: {title}
Date: {date}
Category: {category}

Content:
{content}

Focus on:
- Who is involved
- What happened
- When it occurred
- Where it took place
- Why it matters
- How it happened',
				'variables' => array( 'language', 'bullets', 'emoji_instruction', 'content', 'title', 'date', 'category' ),
				'version' => '1.0',
			),
			'blog' => array(
				'name' => __( 'Blog Post', 'tldr-pro' ),
				'description' => __( 'Engaging summaries for blog content', 'tldr-pro' ),
				'prompt' => 'Create an engaging summary of this blog post in {language}. Capture the main ideas and any actionable tips in {bullets} points. Keep it conversational. {emoji_instruction}

Blog Title: {title}
Author: {author}
Published: {date}

Content:
{content}

Include:
- Main topic and purpose
- Key insights or tips
- Practical takeaways
- Call to action if present',
				'variables' => array( 'language', 'bullets', 'emoji_instruction', 'content', 'title', 'author', 'date' ),
				'version' => '1.0',
			),
			'technical' => array(
				'name' => __( 'Technical', 'tldr-pro' ),
				'prompt' => 'Provide a technical summary of this content in {language}. Focus on specifications, features, implementation details, and technical benefits. List {bullets} key technical points. {emoji_instruction}

Content:
{content}',
				'variables' => array( 'language', 'bullets', 'emoji_instruction', 'content' ),
			),
			'product' => array(
				'name' => __( 'Product Description', 'tldr-pro' ),
				'prompt' => 'Summarize this product information in {language}. Highlight key features, benefits, and unique selling points in {bullets} points. {emoji_instruction}

Content:
{content}',
				'variables' => array( 'language', 'bullets', 'emoji_instruction', 'content' ),
			),
			'tutorial' => array(
				'name' => __( 'Tutorial', 'tldr-pro' ),
				'prompt' => 'Create a step-by-step summary of this tutorial in {language}. List the main steps and expected outcome in {bullets} points. {emoji_instruction}

Content:
{content}',
				'variables' => array( 'language', 'bullets', 'emoji_instruction', 'content' ),
			),
		);

		// Allow themes/plugins to add custom templates
		$this->templates = apply_filters( 'tldr_pro_prompt_templates', $this->templates );
	}

	/**
	 * Get all templates
	 *
	 * @return array Templates array.
	 */
	public function get_templates() {
		return $this->templates;
	}

	/**
	 * Get template by ID
	 *
	 * @param string $template_id Template ID.
	 * @return array|false Template array or false if not found.
	 */
	public function get_template( $template_id ) {
		return isset( $this->templates[ $template_id ] ) ? $this->templates[ $template_id ] : false;
	}

	/**
	 * Add custom template
	 *
	 * @param string $id       Template ID.
	 * @param string $name     Template name.
	 * @param string $prompt   Prompt template.
	 * @param array  $variables Variables used in template.
	 * @return bool Success status.
	 */
	public function add_template( $id, $name, $prompt, $variables = array() ) {
		if ( empty( $id ) || empty( $name ) || empty( $prompt ) ) {
			return false;
		}

		$this->templates[ $id ] = array(
			'name' => $name,
			'prompt' => $prompt,
			'variables' => $variables,
			'custom' => true,
		);

		return true;
	}

	/**
	 * Remove template
	 *
	 * @param string $template_id Template ID.
	 * @return bool Success status.
	 */
	public function remove_template( $template_id ) {
		if ( isset( $this->templates[ $template_id ] ) && ! empty( $this->templates[ $template_id ]['custom'] ) ) {
			unset( $this->templates[ $template_id ] );
			return true;
		}
		return false;
	}

	/**
	 * Generate prompt from template
	 *
	 * @param string $template_id Template ID.
	 * @param array  $variables   Variables to replace.
	 * @return string|WP_Error Generated prompt or error.
	 */
	public function generate_prompt( $template_id, $variables = array() ) {
		$template = $this->get_template( $template_id );
		
		if ( ! $template ) {
			return new WP_Error(
				'invalid_template',
				sprintf(
					/* translators: %s: template ID */
					__( 'Template "%s" not found.', 'tldr-pro' ),
					$template_id
				)
			);
		}

		// Set default values
		$defaults = array(
			'language' => get_locale() === 'uk' ? 'Ukrainian' : 'English',
			'bullets' => 3,
			'emoji_instruction' => 'Use relevant emojis to make it engaging.',
			'content' => '',
		);

		$variables = wp_parse_args( $variables, $defaults );

		// Handle emoji preference
		if ( isset( $variables['use_emoji'] ) && ! $variables['use_emoji'] ) {
			$variables['emoji_instruction'] = 'Do not use emojis.';
		}

		// Replace variables in template
		$prompt = $template['prompt'];
		foreach ( $variables as $key => $value ) {
			$prompt = str_replace( '{' . $key . '}', $value, $prompt );
		}

		return $prompt;
	}

	/**
	 * Get template for post type
	 *
	 * @param string $post_type Post type.
	 * @return string Template ID.
	 */
	public function get_template_for_post_type( $post_type ) {
		$mapping = array(
			'post' => 'blog',
			'page' => 'default',
			'product' => 'product',
		);

		$mapping = apply_filters( 'tldr_pro_post_type_template_mapping', $mapping );

		return isset( $mapping[ $post_type ] ) ? $mapping[ $post_type ] : 'default';
	}

	/**
	 * Detect best template based on content
	 *
	 * @param string $content Content to analyze.
	 * @return string Template ID.
	 */
	public function detect_template( $content ) {
		$content_lower = strtolower( $content );

		// Check for technical keywords
		$technical_keywords = array( 'api', 'code', 'function', 'database', 'algorithm', 'software', 'programming' );
		$technical_count = 0;
		foreach ( $technical_keywords as $keyword ) {
			$technical_count += substr_count( $content_lower, $keyword );
		}
		if ( $technical_count > 5 ) {
			return 'technical';
		}

		// Check for business keywords
		$business_keywords = array( 'revenue', 'profit', 'market', 'strategy', 'investment', 'growth', 'roi' );
		$business_count = 0;
		foreach ( $business_keywords as $keyword ) {
			$business_count += substr_count( $content_lower, $keyword );
		}
		if ( $business_count > 5 ) {
			return 'business';
		}

		// Check for tutorial patterns
		if ( preg_match( '/step\s+\d+|first[,\s]+|then[,\s]+|finally[,\s]+/i', $content ) ) {
			return 'tutorial';
		}

		// Check for news patterns
		if ( preg_match( '/reported|announced|according to|sources say/i', $content ) ) {
			return 'news';
		}

		// Check for academic patterns
		if ( preg_match( '/abstract|methodology|hypothesis|conclusion|research/i', $content ) ) {
			return 'academic';
		}

		return 'default';
	}

	/**
	 * Save custom templates to database
	 *
	 * @return bool Success status.
	 */
	public function save_custom_templates() {
		$custom_templates = array_filter( $this->templates, function( $template ) {
			return ! empty( $template['custom'] );
		});

		return update_option( 'tldr_pro_custom_templates', $custom_templates );
	}

	/**
	 * Load custom templates from database
	 *
	 * @return void
	 */
	public function load_custom_templates() {
		$custom_templates = get_option( 'tldr_pro_custom_templates', array() );
		
		if ( ! empty( $custom_templates ) && is_array( $custom_templates ) ) {
			$this->templates = array_merge( $this->templates, $custom_templates );
		}
	}

	/**
	 * Validate prompt template
	 *
	 * @param string $prompt Prompt template.
	 * @return bool|WP_Error True if valid, error otherwise.
	 */
	public function validate_template( $prompt ) {
		if ( empty( $prompt ) ) {
			return new WP_Error(
				'empty_prompt',
				__( 'Prompt template cannot be empty.', 'tldr-pro' )
			);
		}

		if ( strlen( $prompt ) > 5000 ) {
			return new WP_Error(
				'prompt_too_long',
				__( 'Prompt template is too long (max 5000 characters).', 'tldr-pro' )
			);
		}

		if ( strpos( $prompt, '{content}' ) === false ) {
			return new WP_Error(
				'missing_content_variable',
				__( 'Prompt template must contain {content} variable.', 'tldr-pro' )
			);
		}

		return true;
	}

	/**
	 * Get template variables
	 *
	 * @param string $prompt Prompt template.
	 * @return array Variables found in template.
	 */
	public function extract_variables( $prompt ) {
		preg_match_all( '/\{([^}]+)\}/', $prompt, $matches );
		return array_unique( $matches[1] );
	}

	/**
	 * Clone template with versioning
	 *
	 * @param string $template_id Original template ID.
	 * @param string $new_id New template ID.
	 * @param string $new_name New template name.
	 * @return bool Success status.
	 */
	public function clone_template( $template_id, $new_id, $new_name ) {
		$template = $this->get_template( $template_id );
		
		if ( ! $template ) {
			return false;
		}

		$this->templates[ $new_id ] = array(
			'name' => $new_name,
			'description' => $template['description'] ?? '',
			'prompt' => $template['prompt'],
			'variables' => $template['variables'],
			'version' => '1.0',
			'parent' => $template_id,
			'custom' => true,
		);

		return true;
	}

	/**
	 * Get template history
	 *
	 * @param string $template_id Template ID.
	 * @return array History of template versions.
	 */
	public function get_template_history( $template_id ) {
		$history = get_option( 'tldr_pro_template_history_' . $template_id, array() );
		return is_array( $history ) ? $history : array();
	}

	/**
	 * Save template version
	 *
	 * @param string $template_id Template ID.
	 * @param array $template Template data.
	 * @return bool Success status.
	 */
	public function save_template_version( $template_id, $template ) {
		$history = $this->get_template_history( $template_id );
		
		$history[] = array(
			'version' => $template['version'] ?? '1.0',
			'prompt' => $template['prompt'],
			'variables' => $template['variables'],
			'saved_at' => current_time( 'mysql' ),
			'saved_by' => get_current_user_id(),
		);

		// Keep only last 10 versions
		if ( count( $history ) > 10 ) {
			$history = array_slice( $history, -10 );
		}

		return update_option( 'tldr_pro_template_history_' . $template_id, $history );
	}

	/**
	 * Test prompt with sample content
	 *
	 * @param string $template_id Template ID.
	 * @param string $sample_content Sample content for testing.
	 * @return array Test results.
	 */
	public function test_prompt( $template_id, $sample_content = null ) {
		if ( null === $sample_content ) {
			$sample_content = 'This is a sample article about artificial intelligence and its impact on modern society. AI has revolutionized various industries including healthcare, finance, and transportation. Machine learning algorithms can now process vast amounts of data to make predictions and decisions. However, there are also concerns about privacy, job displacement, and ethical considerations. The future of AI depends on how we address these challenges while harnessing its potential benefits.';
		}

		$post_data = array(
			'title' => __( 'Sample Article Title', 'tldr-pro' ),
			'author' => __( 'John Doe', 'tldr-pro' ),
			'date' => current_time( 'mysql' ),
			'category' => __( 'Technology', 'tldr-pro' ),
		);

		$variables = array(
			'content' => $sample_content,
			'language' => 'English',
			'bullets' => 3,
			'use_emoji' => true,
			'title' => $post_data['title'],
			'author' => $post_data['author'],
			'date' => $post_data['date'],
			'category' => $post_data['category'],
		);

		$prompt = $this->generate_prompt( $template_id, $variables );

		if ( is_wp_error( $prompt ) ) {
			return array(
				'success' => false,
				'error' => $prompt->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'prompt' => $prompt,
			'word_count' => str_word_count( $prompt ),
			'char_count' => strlen( $prompt ),
			'variables_used' => array_keys( $variables ),
		);
	}

	/**
	 * Import templates from JSON
	 *
	 * @param string $json JSON string with templates.
	 * @return array Import results.
	 */
	public function import_templates( $json ) {
		$imported = json_decode( $json, true );
		
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array(
				'success' => false,
				'error' => __( 'Invalid JSON format', 'tldr-pro' ),
			);
		}

		$results = array(
			'imported' => 0,
			'skipped' => 0,
			'errors' => array(),
		);

		foreach ( $imported as $id => $template ) {
			if ( isset( $this->templates[ $id ] ) && empty( $template['force'] ) ) {
				$results['skipped']++;
				continue;
			}

			$validation = $this->validate_template( $template['prompt'] ?? '' );
			
			if ( is_wp_error( $validation ) ) {
				$results['errors'][] = sprintf(
					__( 'Template %s: %s', 'tldr-pro' ),
					$id,
					$validation->get_error_message()
				);
				continue;
			}

			$this->templates[ $id ] = array(
				'name' => $template['name'] ?? $id,
				'description' => $template['description'] ?? '',
				'prompt' => $template['prompt'],
				'variables' => $template['variables'] ?? $this->extract_variables( $template['prompt'] ),
				'version' => $template['version'] ?? '1.0',
				'custom' => true,
			);

			$results['imported']++;
		}

		$this->save_custom_templates();

		return $results;
	}

	/**
	 * Export templates to JSON
	 *
	 * @param array $template_ids Template IDs to export (empty for all).
	 * @return string JSON string.
	 */
	public function export_templates( $template_ids = array() ) {
		$export = array();

		if ( empty( $template_ids ) ) {
			$export = $this->templates;
		} else {
			foreach ( $template_ids as $id ) {
				if ( isset( $this->templates[ $id ] ) ) {
					$export[ $id ] = $this->templates[ $id ];
				}
			}
		}

		return wp_json_encode( $export, JSON_PRETTY_PRINT );
	}

	/**
	 * Get prompt library
	 *
	 * @return array Library of ready-to-use prompts.
	 */
	public function get_prompt_library() {
		return array(
			'ecommerce' => array(
				'name' => __( 'E-commerce Product', 'tldr-pro' ),
				'description' => __( 'Product descriptions and reviews', 'tldr-pro' ),
				'prompt' => 'Summarize this product information in {language}. Include key features, benefits, price point, and target audience. Use {bullets} main selling points. {emoji_instruction}

Product: {title}
Category: {category}

{content}

Focus on:
- Key features and specifications
- Main benefits for customers
- Value proposition
- Target audience
- Unique selling points',
			),
			'recipe' => array(
				'name' => __( 'Recipe', 'tldr-pro' ),
				'description' => __( 'Quick recipe summaries', 'tldr-pro' ),
				'prompt' => 'Create a quick summary of this recipe in {language}. Include prep time, main ingredients, and {bullets} key steps. {emoji_instruction}

Recipe: {title}

{content}

Include:
- Preparation and cooking time
- Servings
- Main ingredients
- Key cooking steps
- Difficulty level',
			),
			'tutorial' => array(
				'name' => __( 'Tutorial', 'tldr-pro' ),
				'description' => __( 'How-to guides and tutorials', 'tldr-pro' ),
				'prompt' => 'Summarize this tutorial in {language}. List the goal, requirements, and {bullets} main steps. {emoji_instruction}

Tutorial: {title}
Author: {author}

{content}

Structure:
- What you will learn
- Prerequisites
- Main steps
- Expected outcome
- Time required',
			),
			'review' => array(
				'name' => __( 'Review', 'tldr-pro' ),
				'description' => __( 'Product or service reviews', 'tldr-pro' ),
				'prompt' => 'Summarize this review in {language}. Include pros, cons, rating, and {bullets} key points. {emoji_instruction}

Review of: {title}
Date: {date}

{content}

Highlight:
- Overall rating/score
- Main pros
- Main cons
- Best for whom
- Value for money
- Final verdict',
			),
			'case_study' => array(
				'name' => __( 'Case Study', 'tldr-pro' ),
				'description' => __( 'Business case studies', 'tldr-pro' ),
				'prompt' => 'Summarize this case study in {language}. Include the challenge, solution, and results in {bullets} points. {emoji_instruction}

Case Study: {title}

{content}

Structure:
- Initial challenge/problem
- Solution implemented
- Key results and metrics
- Lessons learned
- Applicability',
			),
		);
	}

	/**
	 * Get recommended template for content
	 *
	 * @param string $content Content to analyze.
	 * @param array $metadata Post metadata.
	 * @return string Recommended template ID.
	 */
	public function get_recommended_template( $content, $metadata = array() ) {
		// Check post format
		if ( ! empty( $metadata['post_format'] ) ) {
			$format_mapping = array(
				'aside' => 'default',
				'gallery' => 'default',
				'link' => 'news',
				'image' => 'default',
				'quote' => 'default',
				'status' => 'default',
				'video' => 'tutorial',
				'audio' => 'default',
				'chat' => 'default',
			);

			if ( isset( $format_mapping[ $metadata['post_format'] ] ) ) {
				return $format_mapping[ $metadata['post_format'] ];
			}
		}

		// Check categories
		if ( ! empty( $metadata['categories'] ) ) {
			$category_keywords = array(
				'news' => array( 'news', 'press', 'announcement' ),
				'business' => array( 'business', 'finance', 'economy', 'marketing' ),
				'technical' => array( 'technology', 'programming', 'development', 'coding' ),
				'academic' => array( 'research', 'science', 'study', 'education' ),
			);

			foreach ( $category_keywords as $template => $keywords ) {
				foreach ( $metadata['categories'] as $category ) {
					$cat_lower = strtolower( $category );
					foreach ( $keywords as $keyword ) {
						if ( strpos( $cat_lower, $keyword ) !== false ) {
							return $template;
						}
					}
				}
			}
		}

		// Fall back to content detection
		return $this->detect_template( $content );
	}
}