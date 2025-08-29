<?php
/**
 * Content Extractor class
 *
 * @package TLDR_Pro
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles content extraction and cleaning from WordPress posts
 *
 * @since 1.0.0
 */
class TLDR_Pro_Content_Extractor {

	/**
	 * Instance of this class
	 *
	 * @var TLDR_Pro_Content_Extractor
	 */
	private static $instance = null;

	/**
	 * Supported page builders
	 *
	 * @var array
	 */
	private $page_builders = array(
		'elementor',
		'divi',
		'beaver-builder',
		'wpbakery',
		'gutenberg',
	);

	/**
	 * Logger instance
	 *
	 * @var TLDR_Pro_Logger
	 */
	private $logger;

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->logger = TLDR_Pro_Logger::get_instance();
	}

	/**
	 * Get instance
	 *
	 * @return TLDR_Pro_Content_Extractor
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Extract content from post
	 *
	 * @param int|WP_Post $post Post ID or object.
	 * @return string|WP_Error Extracted content or error.
	 */
	public function extract_from_post( $post ) {
		if ( is_numeric( $post ) ) {
			$post = get_post( $post );
		}
		
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error(
				'invalid_post',
				__( 'Invalid post provided for content extraction.', 'tldr-pro' )
			);
		}
		
		$this->logger->log( sprintf( 'Extracting content from post ID: %d', $post->ID ), 'debug' );
		
		$content = $post->post_content;
		
		if ( empty( $content ) ) {
			return new WP_Error(
				'empty_content',
				__( 'Post content is empty.', 'tldr-pro' )
			);
		}
		
		$builder = $this->detect_page_builder( $post );
		if ( $builder ) {
			$this->logger->log( sprintf( 'Detected page builder: %s', $builder ), 'debug' );
			$content = $this->extract_from_page_builder( $content, $builder, $post );
		}
		
		$content = $this->process_gutenberg_blocks( $content );
		
		$content = $this->remove_shortcodes( $content );
		
		$content = $this->strip_html_tags( $content );
		
		$content = $this->clean_content( $content );
		
		$content = apply_filters( 'tldr_pro_extracted_content', $content, $post );
		
		$word_count = str_word_count( $content );
		$this->logger->log( sprintf( 'Extracted %d words from post ID: %d', $word_count, $post->ID ), 'info' );
		
		return $content;
	}

	/**
	 * Detect which page builder is being used
	 *
	 * @param WP_Post $post Post object.
	 * @return string|false Page builder name or false.
	 */
	private function detect_page_builder( $post ) {
		if ( defined( 'ELEMENTOR_VERSION' ) && get_post_meta( $post->ID, '_elementor_edit_mode', true ) ) {
			return 'elementor';
		}
		
		if ( function_exists( 'et_pb_is_pagebuilder_used' ) && et_pb_is_pagebuilder_used( $post->ID ) ) {
			return 'divi';
		}
		
		if ( class_exists( 'FLBuilderModel' ) && FLBuilderModel::is_builder_enabled( $post->ID ) ) {
			return 'beaver-builder';
		}
		
		if ( defined( 'WPB_VC_VERSION' ) && get_post_meta( $post->ID, '_wpb_vc_js_status', true ) === 'true' ) {
			return 'wpbakery';
		}
		
		if ( has_blocks( $post->post_content ) ) {
			return 'gutenberg';
		}
		
		return false;
	}

	/**
	 * Extract content from page builder
	 *
	 * @param string  $content Original content.
	 * @param string  $builder Page builder name.
	 * @param WP_Post $post Post object.
	 * @return string Extracted content.
	 */
	private function extract_from_page_builder( $content, $builder, $post ) {
		switch ( $builder ) {
			case 'elementor':
				return $this->extract_from_elementor( $post );
				
			case 'divi':
				return $this->extract_from_divi( $content );
				
			case 'beaver-builder':
				return $this->extract_from_beaver_builder( $post );
				
			case 'wpbakery':
				return $this->extract_from_wpbakery( $content );
				
			case 'gutenberg':
				return $this->process_gutenberg_blocks( $content );
				
			default:
				return $content;
		}
	}

	/**
	 * Extract content from Elementor
	 *
	 * @param WP_Post $post Post object.
	 * @return string Extracted content.
	 */
	private function extract_from_elementor( $post ) {
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			return $post->post_content;
		}
		
		$document = \Elementor\Plugin::$instance->documents->get( $post->ID );
		
		if ( ! $document ) {
			return $post->post_content;
		}
		
		$data = $document->get_elements_data();
		
		if ( empty( $data ) ) {
			return $post->post_content;
		}
		
		return $this->extract_elementor_text( $data );
	}

	/**
	 * Recursively extract text from Elementor data
	 *
	 * @param array $elements Elementor elements data.
	 * @return string Extracted text.
	 */
	private function extract_elementor_text( $elements ) {
		$text = '';
		
		foreach ( $elements as $element ) {
			if ( ! empty( $element['elements'] ) ) {
				$text .= $this->extract_elementor_text( $element['elements'] );
			}
			
			if ( isset( $element['settings'] ) ) {
				$widget_type = $element['widgetType'] ?? '';
				
				switch ( $widget_type ) {
					case 'text-editor':
					case 'theme-post-content':
						$text .= $element['settings']['editor'] ?? '';
						break;
						
					case 'heading':
						$text .= $element['settings']['title'] ?? '';
						break;
						
					case 'button':
						$text .= $element['settings']['text'] ?? '';
						break;
				}
			}
		}
		
		return $text . ' ';
	}

	/**
	 * Extract content from Divi Builder
	 *
	 * @param string $content Original content.
	 * @return string Extracted content.
	 */
	private function extract_from_divi( $content ) {
		$pattern = '/\[et_pb_text[^\]]*\](.*?)\[\/et_pb_text\]/s';
		preg_match_all( $pattern, $content, $matches );
		
		$text = '';
		if ( ! empty( $matches[1] ) ) {
			$text = implode( ' ', $matches[1] );
		}
		
		$heading_pattern = '/\[et_pb_post_title[^\]]*title="([^"]+)"/';
		preg_match_all( $heading_pattern, $content, $heading_matches );
		
		if ( ! empty( $heading_matches[1] ) ) {
			$text = implode( ' ', $heading_matches[1] ) . ' ' . $text;
		}
		
		return ! empty( $text ) ? $text : $content;
	}

	/**
	 * Extract content from Beaver Builder
	 *
	 * @param WP_Post $post Post object.
	 * @return string Extracted content.
	 */
	private function extract_from_beaver_builder( $post ) {
		if ( ! class_exists( 'FLBuilderModel' ) ) {
			return $post->post_content;
		}
		
		$data = FLBuilderModel::get_layout_data( 'published', $post->ID );
		
		if ( empty( $data ) ) {
			return $post->post_content;
		}
		
		$text = '';
		
		foreach ( $data as $node ) {
			if ( $node->type === 'module' ) {
				$settings = $node->settings;
				
				switch ( $node->slug ) {
					case 'rich-text':
					case 'text':
						$text .= $settings->text ?? '';
						break;
						
					case 'heading':
						$text .= $settings->heading ?? '';
						break;
				}
			}
		}
		
		return ! empty( $text ) ? $text : $post->post_content;
	}

	/**
	 * Extract content from WPBakery Page Builder
	 *
	 * @param string $content Original content.
	 * @return string Extracted content.
	 */
	private function extract_from_wpbakery( $content ) {
		$pattern = '/\[vc_column_text\](.*?)\[\/vc_column_text\]/s';
		preg_match_all( $pattern, $content, $matches );
		
		$text = '';
		if ( ! empty( $matches[1] ) ) {
			$text = implode( ' ', $matches[1] );
		}
		
		$heading_pattern = '/\[vc_custom_heading[^\]]*text="([^"]+)"/';
		preg_match_all( $heading_pattern, $content, $heading_matches );
		
		if ( ! empty( $heading_matches[1] ) ) {
			$text = implode( ' ', $heading_matches[1] ) . ' ' . $text;
		}
		
		return ! empty( $text ) ? $text : $content;
	}

	/**
	 * Process Gutenberg blocks
	 *
	 * @param string $content Content with blocks.
	 * @return string Processed content.
	 */
	private function process_gutenberg_blocks( $content ) {
		if ( ! has_blocks( $content ) ) {
			return $content;
		}
		
		$blocks = parse_blocks( $content );
		$text = '';
		
		foreach ( $blocks as $block ) {
			$text .= $this->extract_from_block( $block );
		}
		
		return ! empty( $text ) ? $text : $content;
	}

	/**
	 * Extract text from a single Gutenberg block
	 *
	 * @param array $block Block data.
	 * @return string Extracted text.
	 */
	private function extract_from_block( $block ) {
		$text = '';
		
		$text_blocks = array(
			'core/paragraph',
			'core/heading',
			'core/list',
			'core/quote',
			'core/verse',
			'core/preformatted',
			'core/pullquote',
			'core/table',
		);
		
		if ( in_array( $block['blockName'], $text_blocks, true ) ) {
			$text .= $block['innerHTML'] ?? '';
		}
		
		if ( ! empty( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $inner_block ) {
				$text .= $this->extract_from_block( $inner_block );
			}
		}
		
		return $text . ' ';
	}

	/**
	 * Remove shortcodes from content
	 *
	 * @param string $content Content with shortcodes.
	 * @return string Content without shortcodes.
	 */
	private function remove_shortcodes( $content ) {
		$content = preg_replace( '/\[[^\]]*\]/', '', $content );
		
		$content = preg_replace( '/\[\/[^\]]*\]/', '', $content );
		
		return $content;
	}

	/**
	 * Strip HTML tags
	 *
	 * @param string $content HTML content.
	 * @return string Plain text content.
	 */
	private function strip_html_tags( $content ) {
		$content = wp_strip_all_tags( $content, true );
		
		$content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, get_option( 'blog_charset' ) );
		
		return $content;
	}

	/**
	 * Clean content
	 *
	 * @param string $content Raw content.
	 * @return string Cleaned content.
	 */
	private function clean_content( $content ) {
		$content = preg_replace( '/\s+/', ' ', $content );
		
		$content = preg_replace( '/\r|\n/', ' ', $content );
		
		$content = preg_replace( '/\s{2,}/', ' ', $content );
		
		$content = trim( $content );
		
		return $content;
	}

	/**
	 * Split content into chunks
	 *
	 * @param string $content Content to split.
	 * @param int    $max_words Maximum words per chunk.
	 * @return array Array of content chunks.
	 */
	public function split_into_chunks( $content, $max_words = 500 ) {
		$words = explode( ' ', $content );
		$chunks = array();
		
		if ( count( $words ) <= $max_words ) {
			return array( $content );
		}
		
		$current_chunk = array();
		$current_count = 0;
		
		foreach ( $words as $word ) {
			$current_chunk[] = $word;
			$current_count++;
			
			if ( $current_count >= $max_words ) {
				$chunks[] = implode( ' ', $current_chunk );
				$current_chunk = array();
				$current_count = 0;
			}
		}
		
		if ( ! empty( $current_chunk ) ) {
			$chunks[] = implode( ' ', $current_chunk );
		}
		
		$this->logger->log( sprintf( 'Split content into %d chunks', count( $chunks ) ), 'debug' );
		
		return $chunks;
	}

	/**
	 * Count words in content
	 *
	 * @param string $content Content to count.
	 * @return int Word count.
	 */
	public function count_words( $content ) {
		return str_word_count( $content );
	}

	/**
	 * Count characters in content
	 *
	 * @param string $content Content to count.
	 * @return int Character count.
	 */
	public function count_characters( $content ) {
		return strlen( $content );
	}

	/**
	 * Estimate reading time
	 *
	 * @param string $content Content to estimate.
	 * @param int    $wpm Words per minute (default 200).
	 * @return int Reading time in minutes.
	 */
	public function estimate_reading_time( $content, $wpm = 200 ) {
		$word_count = $this->count_words( $content );
		$minutes = ceil( $word_count / $wpm );
		
		return (int) $minutes;
	}

	/**
	 * Extract key sentences
	 *
	 * @param string $content Content to analyze.
	 * @param int    $num_sentences Number of sentences to extract.
	 * @return array Key sentences.
	 */
	public function extract_key_sentences( $content, $num_sentences = 3 ) {
		$sentences = preg_split( '/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY );
		
		if ( count( $sentences ) <= $num_sentences ) {
			return $sentences;
		}
		
		$word_frequency = array();
		$words = str_word_count( strtolower( $content ), 1 );
		
		foreach ( $words as $word ) {
			if ( strlen( $word ) > 3 ) {
				$word_frequency[ $word ] = ( $word_frequency[ $word ] ?? 0 ) + 1;
			}
		}
		
		$sentence_scores = array();
		
		foreach ( $sentences as $index => $sentence ) {
			$score = 0;
			$sentence_words = str_word_count( strtolower( $sentence ), 1 );
			
			foreach ( $sentence_words as $word ) {
				if ( isset( $word_frequency[ $word ] ) ) {
					$score += $word_frequency[ $word ];
				}
			}
			
			$sentence_scores[ $index ] = $score;
		}
		
		arsort( $sentence_scores );
		$top_indices = array_slice( array_keys( $sentence_scores ), 0, $num_sentences );
		sort( $top_indices );
		
		$key_sentences = array();
		foreach ( $top_indices as $index ) {
			$key_sentences[] = trim( $sentences[ $index ] );
		}
		
		return $key_sentences;
	}
}