<?php
/**
 * Auto-generation handler for TL;DR Pro plugin.
 *
 * Automatically generates summaries when posts are saved or updated.
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
 * Auto-generation class.
 */
class TLDR_Pro_Auto_Generate {

	/**
	 * Initialize auto-generation functionality.
	 */
	public static function init() {
		// Hook into post save action
		add_action( 'save_post', array( __CLASS__, 'maybe_generate_summary' ), 10, 3 );
		add_action( 'wp_after_insert_post', array( __CLASS__, 'handle_post_insert' ), 10, 4 );
		
		// Background processing for bulk operations
		add_action( 'tldr_pro_generate_summary_async', array( __CLASS__, 'generate_summary_background' ), 10, 1 );
	}

	/**
	 * Check if auto-generation should run for a post.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an update.
	 */
	public static function maybe_generate_summary( $post_id, $post, $update ) {
		// Skip if autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Skip if doing AJAX
		if ( wp_doing_ajax() ) {
			return;
		}

		// Check if auto-generation is enabled
		if ( ! TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_auto_generate' ) ) {
			return;
		}

		// Check post type
		$allowed_types = TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_enabled_post_types', array( 'post' ) );
		if ( ! in_array( $post->post_type, $allowed_types ) ) {
			return;
		}

		// Check post status
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		// Check minimum word count
		$min_words = intval( TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_min_word_count', 300 ) );
		$word_count = str_word_count( strip_tags( $post->post_content ) );
		if ( $word_count < $min_words ) {
			return;
		}

		// Check if summary already exists (only for updates)
		if ( $update ) {
			$database = TLDR_Pro_Database::get_instance();
			$existing = $database->get_summary( $post_id );
			
			// Check if we should regenerate on update
			$regenerate_on_update = TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_regenerate_on_update', false );
			if ( $existing && ! $regenerate_on_update ) {
				return;
			}
			
			// Check if content changed significantly
			if ( $existing && ! self::has_content_changed_significantly( $post_id, $post ) ) {
				return;
			}
		}

		// Schedule background generation
		if ( TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_async_generation', true ) ) {
			wp_schedule_single_event( time(), 'tldr_pro_generate_summary_async', array( $post_id ) );
		} else {
			// Generate immediately
			self::generate_summary( $post_id );
		}
	}

	/**
	 * Handle new post insertion.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an update.
	 * @param WP_Post $post_before Previous post object.
	 */
	public static function handle_post_insert( $post_id, $post, $update, $post_before ) {
		if ( ! $update ) {
			self::maybe_generate_summary( $post_id, $post, false );
		}
	}

	/**
	 * Check if content has changed significantly.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Current post object.
	 * @return bool True if content changed significantly.
	 */
	private static function has_content_changed_significantly( $post_id, $post ) {
		// Get previous content hash
		$previous_hash = get_post_meta( $post_id, '_tldr_pro_content_hash', true );
		
		// Calculate current content hash
		$current_hash = md5( $post->post_content . $post->post_title );
		
		// Update hash
		update_post_meta( $post_id, '_tldr_pro_content_hash', $current_hash );
		
		// If no previous hash, consider it changed
		if ( empty( $previous_hash ) ) {
			return true;
		}
		
		// Compare hashes
		return $previous_hash !== $current_hash;
	}

	/**
	 * Generate summary in background.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function generate_summary_background( $post_id ) {
		self::generate_summary( $post_id );
	}

	/**
	 * Generate summary for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True on success, false on failure.
	 */
	public static function generate_summary( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		// Get AI manager
		$ai_manager = new TLDR_Pro_AI_Manager();
		
		// Prepare content
		$content = apply_filters( 'the_content', $post->post_content );
		$content = wp_strip_all_tags( $content );
		
		// Generate summary
		$result = $ai_manager->generate_summary( $content, $post_id );
		
		if ( is_wp_error( $result ) ) {
			// Log error
			tldr_pro_log( 
				sprintf( 'Auto-generation failed for post %d: %s', $post_id, $result->get_error_message() ),
				'error'
			);
			
			// Mark as failed
			update_post_meta( $post_id, '_tldr_pro_auto_generate_failed', current_time( 'mysql' ) );
			
			return false;
		}

		// Save to database
		$database = TLDR_Pro_Database::get_instance();
		$saved = $database->insert_summary( array(
			'post_id'         => $post_id,
			'summary_text'    => $result['summary'],
			'api_provider'    => $result['provider'],
			'tokens_used'     => $result['tokens_used'] ?? 0,
			'generation_time' => $result['generation_time'] ?? 0,
			'status'          => 'active',
			'language'        => get_option( 'tldr_pro_summary_language', 'en' ),
			'version'         => TLDR_PRO_VERSION
		) );

		if ( $saved ) {
			// Mark as having summary
			update_post_meta( $post_id, '_tldr_pro_has_summary', '1' );
			delete_post_meta( $post_id, '_tldr_pro_auto_generate_failed' );
			
			// Clear cache
			if ( class_exists( 'TLDR_Pro_Cache' ) ) {
				TLDR_Pro_Cache::delete_summary( $post_id );
			}
			
			// Trigger action
			do_action( 'tldr_pro_summary_auto_generated', $post_id, $result );
			
			// Log success
			tldr_pro_log( sprintf( 'Summary auto-generated for post %d', $post_id ) );
			
			return true;
		}

		return false;
	}

	/**
	 * Bulk generate summaries for existing posts.
	 *
	 * @param array $args Query arguments.
	 * @return array Results array.
	 */
	public static function bulk_generate( $args = array() ) {
		$defaults = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'meta_query'     => array(
				array(
					'key'     => '_tldr_pro_has_summary',
					'compare' => 'NOT EXISTS'
				)
			)
		);
		
		$args = wp_parse_args( $args, $defaults );
		$posts = get_posts( $args );
		
		$results = array(
			'total'     => count( $posts ),
			'success'   => 0,
			'failed'    => 0,
			'skipped'   => 0,
			'post_ids'  => array()
		);
		
		foreach ( $posts as $post ) {
			// Check word count
			$word_count = str_word_count( strip_tags( $post->post_content ) );
			$min_words = intval( get_option( 'tldr_pro_min_word_count', 300 ) );
			
			if ( $word_count < $min_words ) {
				$results['skipped']++;
				continue;
			}
			
			// Generate summary
			if ( self::generate_summary( $post->ID ) ) {
				$results['success']++;
				$results['post_ids'][] = $post->ID;
			} else {
				$results['failed']++;
			}
			
			// Add delay to avoid API rate limits
			if ( $results['success'] % 5 === 0 ) {
				sleep( 2 );
			}
		}
		
		return $results;
	}
}

// Initialize auto-generation
add_action( 'init', array( 'TLDR_Pro_Auto_Generate', 'init' ) );