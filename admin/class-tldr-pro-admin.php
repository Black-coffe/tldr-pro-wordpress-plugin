<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    TLDR_Pro
 * @subpackage TLDR_Pro/admin
 * @author     Your Name <your-email@example.com>
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The admin-specific functionality of the plugin.
 */
class TLDR_Pro_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    string    $plugin_name       The name of this plugin.
	 * @param    string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		$screen = get_current_screen();
		
		// Load styles only on our plugin pages
		if ( strpos( $screen->id, 'tldr-pro' ) !== false ) {
			// Enqueue Select2 CSS
			wp_enqueue_style(
				'select2',
				'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
				array(),
				'4.1.0',
				'all'
			);
			
			wp_enqueue_style(
				$this->plugin_name . '-admin',
				plugin_dir_url( __FILE__ ) . 'css/tldr-pro-admin.css',
				array( 'select2' ),
				$this->version,
				'all'
			);
		}
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();
		
		// Load scripts only on our plugin pages
		if ( strpos( $screen->id, 'tldr-pro' ) !== false ) {
			// Enqueue jQuery UI sortable for fallback order
			wp_enqueue_script( 'jquery-ui-sortable' );
			
			// Enqueue Select2 for searchable dropdowns
			wp_enqueue_script( 
				'select2', 
				'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', 
				array( 'jquery' ), 
				'4.1.0', 
				true 
			);
			
			wp_enqueue_script(
				$this->plugin_name . '-admin',
				plugin_dir_url( __FILE__ ) . 'js/tldr-pro-admin.js',
				array( 'jquery', 'jquery-ui-sortable', 'select2' ),
				$this->version,
				true
			);
			
			// Get current settings for the prompt preview
			$language_code = TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_summary_language', 'en' );
			$language_names = TLDR_Pro_Prompt_Manager::get_language_names();
			$current_language = isset( $language_names[ $language_code ] ) ? $language_names[ $language_code ] : 'English';
			
			// Localize script for AJAX
			wp_localize_script(
				$this->plugin_name . '-admin',
				'tldr_pro_admin',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'ajaxurl' => admin_url( 'admin-ajax.php' ), // Also provide ajaxurl
					'nonce' => wp_create_nonce( 'tldr_pro_admin_nonce' ),
					'nonce_test' => wp_create_nonce( 'tldr_pro_test_api' ),
					'nonce_general' => wp_create_nonce( 'tldr_pro_admin_nonce' ),
					'nonce_prompts' => wp_create_nonce( 'tldr_pro_save_prompts' ),
					'nonce_reset_prompt' => wp_create_nonce( 'tldr_pro_reset_prompt' ),
					'nonce_reset_all' => wp_create_nonce( 'tldr_pro_reset_all_prompts' ),
					'strings' => array(
						'saving' => __( 'Saving...', 'tldr-pro' ),
						'saved' => __( 'Settings saved successfully!', 'tldr-pro' ),
						'error' => __( 'An error occurred. Please try again.', 'tldr-pro' ),
						'testing' => __( 'Testing connection...', 'tldr-pro' ),
						'test_success' => __( 'API connection successful!', 'tldr-pro' ),
						'test_failed' => __( 'API connection failed. Please check your credentials.', 'tldr-pro' ),
					),
					'i18n' => array(
						'generating' => __( 'Generating...', 'tldr-pro' ),
						'regenerate' => __( 'Regenerate', 'tldr-pro' ),
						'generated' => __( 'Generated', 'tldr-pro' ),
						'preview' => __( 'Preview', 'tldr-pro' ),
						'cancel' => __( 'Cancel', 'tldr-pro' ),
						'cancelling' => __( 'Cancelling...', 'tldr-pro' ),
						'no_posts_selected' => __( 'Please select at least one post.', 'tldr-pro' ),
						'confirm_bulk' => __( 'Generate summaries for selected posts?', 'tldr-pro' ),
						'processing' => __( 'Processing...', 'tldr-pro' ),
						'complete' => __( 'Complete', 'tldr-pro' ),
						'error' => __( 'Error', 'tldr-pro' ),
						'success' => __( 'Success', 'tldr-pro' ),
					),
					'current_settings' => array(
						'language' => $current_language,
						'max_length' => TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_max_summary_length', 150 ),
						'style' => TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_summary_style', 'professional' ),
						'format' => TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_summary_format', 'paragraph' ),
						'bullet_points' => TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_bullet_points', 3 ),
						'use_emojis' => TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_use_emojis', false ),
					),
				)
			);
			
			// Load bulk operations script on bulk page
			if ( strpos( $screen->id, 'tldr-pro-bulk' ) !== false ) {
				// Ensure WordPress's ajaxurl is available
				wp_enqueue_script( 'wp-util' );
				
				wp_enqueue_script(
					'tldr-pro-bulk',
					plugin_dir_url( __FILE__ ) . 'js/modules/bulk.min.js',
					array( 'jquery' ),
					$this->version,
					true
				);
				
				// Ensure localization for bulk script - merge with existing if present
				wp_localize_script(
					'tldr-pro-bulk',
					'tldr_pro_bulk',
					array(
						'ajaxurl' => admin_url( 'admin-ajax.php' ),
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'nonce' => wp_create_nonce( 'tldr_pro_admin_nonce' ),
						'nonce_test' => wp_create_nonce( 'tldr_pro_test_api' ),
						'i18n' => array(
							'generating' => __( 'Generating...', 'tldr-pro' ),
							'regenerate' => __( 'Regenerate', 'tldr-pro' ),
							'generated' => __( 'Generated', 'tldr-pro' ),
							'preview' => __( 'Preview', 'tldr-pro' ),
							'cancel' => __( 'Cancel', 'tldr-pro' ),
							'cancelling' => __( 'Cancelling...', 'tldr-pro' ),
							'no_posts_selected' => __( 'Please select at least one post.', 'tldr-pro' ),
							'confirm_bulk' => __( 'Generate summaries for selected posts?', 'tldr-pro' ),
							'processing' => __( 'Processing...', 'tldr-pro' ),
							'complete' => __( 'Complete', 'tldr-pro' ),
							'error' => __( 'Error', 'tldr-pro' ),
							'success' => __( 'Success', 'tldr-pro' ),
						),
					)
				);
			}
		}
	}

	/**
	 * Add admin menu items.
	 *
	 * @since    1.0.0
	 */
	public function add_admin_menu() {
		// Main menu
		add_menu_page(
			__( 'TL;DR Pro', 'tldr-pro' ),
			__( 'TL;DR Pro', 'tldr-pro' ),
			'manage_options',
			'tldr-pro',
			array( $this, 'display_settings_page' ),
			'dashicons-media-text',
			30
		);
		
		// Settings submenu
		add_submenu_page(
			'tldr-pro',
			__( 'Settings', 'tldr-pro' ),
			__( 'Settings', 'tldr-pro' ),
			'manage_options',
			'tldr-pro',
			array( $this, 'display_settings_page' )
		);
		
		// Bulk Operations submenu
		add_submenu_page(
			'tldr-pro',
			__( 'Bulk Operations', 'tldr-pro' ),
			__( 'Bulk Operations', 'tldr-pro' ),
			'manage_options',
			'tldr-pro-bulk',
			array( $this, 'display_bulk_operations_page' )
		);
		
		// Statistics submenu
		add_submenu_page(
			'tldr-pro',
			__( 'Statistics', 'tldr-pro' ),
			__( 'Statistics', 'tldr-pro' ),
			'manage_options',
			'tldr-pro-stats',
			array( $this, 'display_statistics_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @since    1.0.0
	 */
	public function register_settings() {
		// Initialize default settings ONLY on plugin activation or first run
		// Do NOT run this on every admin_init as it may override user settings
		static $initialized = false;
		if ( ! $initialized && ! get_option( 'tldr_pro_defaults_initialized' ) ) {
			if ( ! class_exists( 'TLDR_Pro_Settings_Manager' ) ) {
				require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-tldr-pro-settings-manager.php';
			}
			TLDR_Pro_Settings_Manager::init_defaults();
			$initialized = true;
		}
		
		// Register AI provider settings with sanitization callbacks
		register_setting( 'tldr_pro_settings', 'tldr_pro_active_provider', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default' => 'deepseek'
		) );
		
		register_setting( 'tldr_pro_settings', 'tldr_pro_enable_fallback', array(
			'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			'default' => 1  // Changed to 1 for true default
		) );
		
		register_setting( 'tldr_pro_settings', 'tldr_pro_fallback_order', array(
			'sanitize_callback' => array( $this, 'sanitize_fallback_order' ),
			'default' => 'deepseek,gemini,claude'
		) );
		
		// Register settings for each provider
		$providers = array( 'deepseek', 'gemini', 'claude', 'gpt' );
		foreach ( $providers as $provider ) {
			register_setting( 'tldr_pro_settings', 'tldr_pro_' . $provider . '_api_key', array(
				'sanitize_callback' => array( $this, 'sanitize_and_encrypt_api_key' ),
				'default' => ''
			) );
			
			register_setting( 'tldr_pro_settings', 'tldr_pro_' . $provider . '_model', array(
				'sanitize_callback' => 'sanitize_text_field',
				'default' => ''
			) );
			
			register_setting( 'tldr_pro_settings', 'tldr_pro_' . $provider . '_system_prompt', array(
				'sanitize_callback' => 'sanitize_textarea_field',
				'default' => ''
			) );
			
			register_setting( 'tldr_pro_settings', 'tldr_pro_' . $provider . '_user_prompt', array(
				'sanitize_callback' => 'sanitize_textarea_field',
				'default' => ''
			) );
		}
		
		// Register general settings
		register_setting( 'tldr_pro_settings', 'tldr_pro_max_summary_length', array(
			'sanitize_callback' => 'absint',
			'default' => 150  // Changed to match Settings Manager default
		) );
		
		register_setting( 'tldr_pro_settings', 'tldr_pro_summary_style', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default' => 'professional'  // Changed to match Settings Manager default
		) );
		
		register_setting( 'tldr_pro_settings', 'tldr_pro_summary_language', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default' => 'en'
		) );
		
		register_setting( 'tldr_pro_settings', 'tldr_pro_use_emojis', array(
			'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			'default' => 1  // Changed to 1 for true default
		) );
		
		// Register advanced settings
		register_setting( 'tldr_pro_settings', 'tldr_pro_batch_size', array(
			'sanitize_callback' => 'absint',
			'default' => 5  // Changed to match Settings Manager default
		) );
		
		register_setting( 'tldr_pro_settings', 'tldr_pro_batch_delay', array(
			'sanitize_callback' => 'floatval',  // Changed to floatval for decimal values
			'default' => 1  // Changed to 1 second default
		) );
		
		register_setting( 'tldr_pro_settings', 'tldr_pro_debug_mode', array(
			'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			'default' => 0
		) );
		
		register_setting( 'tldr_pro_settings', 'tldr_pro_deepseek_use_prefix_caching', array(
			'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			'default' => 1
		) );
		
		// Display Settings
		register_setting( 'tldr_pro_settings', 'tldr_pro_button_position', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default' => 'before_content'
		) );
		
		register_setting( 'tldr_pro_settings', 'tldr_pro_button_text', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default' => 'Show TL;DR'
		) );
		
		register_setting( 'tldr_pro_settings', 'tldr_pro_button_style', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default' => 'default'
		) );
		
		register_setting( 'tldr_pro_settings', 'tldr_pro_button_bg_color', array(
			'sanitize_callback' => 'sanitize_hex_color',
			'default' => '#0073aa'
		) );
		
		register_setting( 'tldr_pro_settings', 'tldr_pro_button_text_color', array(
			'sanitize_callback' => 'sanitize_hex_color',
			'default' => '#ffffff'
		) );
		
		// Position settings for posts and pages
		register_setting( 'tldr_pro_settings', 'tldr_pro_button_position_post', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default' => 'after_title'
		) );
		
		register_setting( 'tldr_pro_settings', 'tldr_pro_button_position_page', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default' => 'after_title_elegant'
		) );
		
		// Legacy position setting for backward compatibility
		register_setting( 'tldr_pro_settings', 'tldr_pro_button_position', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default' => 'before_content'
		) );
		
		register_setting( 'tldr_pro_settings', 'tldr_pro_auto_display', array(
			'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			'default' => 1
		) );
		
		register_setting( 'tldr_pro_settings', 'tldr_pro_auto_generate', array(
			'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			'default' => 0
		) );
		
		register_setting( 'tldr_pro_settings', 'tldr_pro_show_reading_time', array(
			'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			'default' => 1
		) );
		
		register_setting( 'tldr_pro_settings', 'tldr_pro_show_word_count', array(
			'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			'default' => 1
		) );
		
		// Post Type Settings
		register_setting( 'tldr_pro_settings', 'tldr_pro_enabled_post_types', array(
			'sanitize_callback' => array( $this, 'sanitize_post_types' ),
			'default' => array( 'post', 'page' )
		) );
		
		register_setting( 'tldr_pro_settings', 'tldr_pro_min_word_count', array(
			'sanitize_callback' => 'absint',
			'default' => 300
		) );
		
		// Summary Format Settings
		register_setting( 'tldr_pro_settings', 'tldr_pro_summary_format', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default' => 'paragraph'
		) );
		
		register_setting( 'tldr_pro_settings', 'tldr_pro_bullet_points_count', array(
			'sanitize_callback' => 'absint',
			'default' => 5
		) );
		
		register_setting( 'tldr_pro_settings', 'tldr_pro_summary_language', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default' => 'auto'
		) );
		
		// Advanced Settings - Delete data on uninstall
		register_setting( 'tldr_pro_settings', 'tldr_pro_delete_data_on_uninstall', array(
			'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			'default' => '0'
		) );
	}

	/**
	 * Display settings page.
	 *
	 * @since    1.0.0
	 */
	public function display_settings_page() {
		include plugin_dir_path( __FILE__ ) . 'partials/tldr-pro-admin-settings.php';
	}
	
	/**
	 * Display bulk operations page.
	 *
	 * @since    1.0.0
	 */
	public function display_bulk_operations_page() {
		// Ensure we're in admin context
		if ( ! is_admin() ) {
			wp_die( __( 'This page can only be accessed from the admin area.', 'tldr-pro' ) );
		}
		
		// Double-check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'tldr-pro' ) );
		}
		
		$file_path = plugin_dir_path( __FILE__ ) . 'partials/tldr-pro-admin-bulk.php';
		if ( ! file_exists( $file_path ) ) {
			wp_die( 'Bulk operations template file not found: ' . $file_path );
		}
		
		// Include the template file
		require_once $file_path;
	}
	
	/**
	 * Display statistics page.
	 *
	 * @since    1.0.0
	 */
	public function display_statistics_page() {
		include plugin_dir_path( __FILE__ ) . 'partials/tldr-pro-admin-stats.php';
	}
	
	/**
	 * Sanitize settings.
	 *
	 * @param array $input Settings input.
	 * @return array Sanitized settings.
	 */
	/**
	 * Sanitize checkbox input
	 */
	public function sanitize_checkbox( $input ) {
		// Return string values to match Settings Manager expectations
		return ( isset( $input ) && $input ) ? '1' : '0';
	}
	
	/**
	 * Sanitize post types array
	 */
	public function sanitize_post_types( $input ) {
		if ( ! is_array( $input ) ) {
			return array( 'post', 'page' );
		}
		return array_map( 'sanitize_text_field', $input );
	}
	
	/**
	 * Sanitize fallback order input
	 */
	public function sanitize_fallback_order( $input ) {
		if ( empty( $input ) ) {
			return 'deepseek,gemini';
		}
		
		$providers = explode( ',', $input );
		$valid_providers = array( 'deepseek', 'gemini' );
		$sanitized_providers = array();
		
		foreach ( $providers as $provider ) {
			$provider = trim( sanitize_text_field( $provider ) );
			if ( in_array( $provider, $valid_providers ) ) {
				$sanitized_providers[] = $provider;
			}
		}
		
		if ( empty( $sanitized_providers ) ) {
			return 'deepseek,gemini';
		}
		
		return implode( ',', array_unique( $sanitized_providers ) );
	}
	
	/**
	 * Sanitize and encrypt API key
	 *
	 * @param string $value API key value.
	 * @return string Empty string (actual storage handled by encryption class).
	 */
	public function sanitize_and_encrypt_api_key( $value ) {
		// Get the option name from current filter
		$current_filter = current_filter();
		preg_match( '/sanitize_option_tldr_pro_(.+)_api_key/', $current_filter, $matches );
		
		if ( ! empty( $matches[1] ) ) {
			$provider = $matches[1];
			$encryption = TLDR_Pro_Encryption::get_instance();
			
			// Don't update if the value is empty and we already have a key
			if ( empty( $value ) && $encryption->get_api_key( $provider ) ) {
				return '';
			}
			
			// Store encrypted key
			if ( ! empty( $value ) ) {
				$encryption->store_api_key( $provider, sanitize_text_field( $value ) );
				// Validation status will be reset only if key changed (handled in store_api_key)
			} else {
				// Clear all data if empty
				$encryption->clear_provider_data( $provider );
			}
		}
		
		// Return empty string - we don't store plain text
		return '';
	}
	
	/**
	 * Render API section description.
	 */
	public function render_api_section() {
		echo '<p>' . __( 'Configure your AI provider settings below.', 'tldr-pro' ) . '</p>';
	}
	
	/**
	 * Render Display section description.
	 */
	public function render_display_section() {
		echo '<p>' . __( 'Customize how the summary button appears on your site.', 'tldr-pro' ) . '</p>';
	}
	
	/**
	 * Render Summary section description.
	 */
	public function render_summary_section() {
		echo '<p>' . __( 'Configure summary generation settings.', 'tldr-pro' ) . '</p>';
	}
	
	/**
	 * AJAX handler for generating summary.
	 *
	 * @since    1.0.0
	 */
	public function ajax_generate_summary() {
		// Initialize logger
		$logger = TLDR_Pro_Logger::get_instance();
		$request_time = microtime(true);
		
		$logger->log('=== AJAX Generate Summary Started ===', 'info');
		$logger->log('POST data: ' . json_encode($_POST), 'debug');
		$logger->log('User ID: ' . get_current_user_id(), 'debug');
		$logger->log('Session valid: ' . (is_user_logged_in() ? 'Yes' : 'No'), 'debug');
		
		// Check nonce - try multiple nonce names for backward compatibility
		$nonce_valid = false;
		if ( isset( $_POST['nonce'] ) ) {
			// Try primary nonce
			if ( wp_verify_nonce( $_POST['nonce'], 'tldr_pro_test_api' ) ) {
				$nonce_valid = true;
				$logger->log('Nonce verified with tldr_pro_test_api', 'debug');
			} 
			// Try fallback nonce
			else if ( wp_verify_nonce( $_POST['nonce'], 'tldr_pro_admin_nonce' ) ) {
				$nonce_valid = true;
				$logger->log('Nonce verified with tldr_pro_admin_nonce (fallback)', 'debug');
			}
		}
		
		if ( ! $nonce_valid ) {
			$logger->log('AJAX Security check failed - nonce invalid', 'error');
			$logger->log('Expected nonces: tldr_pro_test_api or tldr_pro_admin_nonce', 'debug');
			$logger->log('Received nonce: ' . (isset($_POST['nonce']) ? $_POST['nonce'] : 'none'), 'debug');
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'tldr-pro' ) ) );
		}
		
		$logger->log('Security check passed', 'debug');
		
		// Check capabilities
		if ( ! current_user_can( 'edit_posts' ) ) {
			$logger->log('User lacks edit_posts capability', 'error');
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'tldr-pro' ) ) );
		}
		
		$logger->log('User capability check passed', 'debug');
		
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$logger->log('Processing post ID: ' . $post_id, 'info');
		
		if ( ! $post_id ) {
			$logger->log('Invalid post ID provided: ' . $_POST['post_id'], 'error');
			wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'tldr-pro' ) ) );
		}
		
		// Get the post
		$post = get_post( $post_id );
		if ( ! $post ) {
			$logger->log('Post not found for ID: ' . $post_id, 'error');
			wp_send_json_error( array( 'message' => __( 'Post not found.', 'tldr-pro' ) ) );
		}
		
		$logger->log('Post found: ' . $post->post_title, 'info');
		$logger->log('Post type: ' . $post->post_type, 'debug');
		$logger->log('Post status: ' . $post->post_status, 'debug');
		
		// Check if post type is enabled
		$enabled_post_types = get_option( 'tldr_pro_enabled_post_types', array( 'post', 'page' ) );
		$logger->log('Enabled post types: ' . json_encode($enabled_post_types), 'debug');
		
		if ( ! in_array( $post->post_type, $enabled_post_types, true ) ) {
			$logger->log('Post type not enabled: ' . $post->post_type, 'error');
			wp_send_json_error( array( 
				'message' => sprintf( 
					__( 'Post type "%s" is not enabled for summaries.', 'tldr-pro' ), 
					$post->post_type 
				) 
			) );
		}
		
		// Check minimum word count
		$content = wp_strip_all_tags( $post->post_content );
		$word_count = str_word_count( $content );
		$min_word_count = get_option( 'tldr_pro_min_word_count', 100 );
		
		$logger->log('Content word count: ' . $word_count, 'debug');
		$logger->log('Minimum word count required: ' . $min_word_count, 'debug');
		
		if ( $word_count < $min_word_count ) {
			$logger->log('Post too short - word count: ' . $word_count . ', minimum: ' . $min_word_count, 'error');
			wp_send_json_error( array( 
				'message' => sprintf( 
					__( 'Post is too short. Minimum word count is %d, post has %d words.', 'tldr-pro' ), 
					$min_word_count, 
					$word_count 
				) 
			) );
		}
		
		$logger->log('Word count check passed', 'debug');
		
		// Get AI Manager
		$logger->log('Getting AI Manager instance', 'debug');
		$ai_manager = TLDR_Pro_AI_Manager::get_instance();
		
		// Check if we have an active provider
		$active_provider = get_option( 'tldr_pro_active_provider', 'deepseek' );
		$logger->log('Active AI provider: ' . $active_provider, 'info');
		
		if ( ! $active_provider ) {
			$logger->log('No AI provider configured', 'error');
			wp_send_json_error( array( 
				'message' => __( 'No AI provider is configured. Please configure an API provider in settings.', 'tldr-pro' ) 
			) );
		}
		
		// Get the language setting
		$language_code = TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_summary_language', 'en' );
		$logger->log('Summary language: ' . $language_code, 'debug');
		
		// Generate unique request ID for tracking
		$request_id = 'gen_' . $post_id . '_' . time();
		$logger->log('Request ID: ' . $request_id, 'info');
		
		// Prepare options for generation
		$options = array(
			'post_id' => $post_id,
			'post_title' => $post->post_title,
			'post_type' => $post->post_type,
			'language' => $language_code,
			'max_length' => get_option( 'tldr_pro_max_summary_length', 150 ),
			'style' => get_option( 'tldr_pro_summary_style', 'professional' ),
			'format' => get_option( 'tldr_pro_summary_format', 'paragraph' ),
			'request_id' => $request_id,  // Add request ID for tracking
		);
		
		$logger->log('Generation options: ' . json_encode($options), 'debug');
		$logger->log('Content length (chars): ' . strlen($post->post_content), 'debug');
		
		// Generate the summary
		$logger->log('Calling AI Manager to generate summary...', 'info');
		$generation_start = microtime(true);
		
		$summary_result = $ai_manager->generate_summary( $post->post_content, $options );
		
		$generation_time = microtime(true) - $generation_start;
		$logger->log('Summary generation completed in ' . round($generation_time, 2) . ' seconds', 'info');
		
		if ( is_wp_error( $summary_result ) ) {
			// Log the error
			$logger->log('Summary generation FAILED', 'error');
			$logger->log('Error message: ' . $summary_result->get_error_message(), 'error');
			$logger->log('Error code: ' . $summary_result->get_error_code(), 'error');
			$logger->log('Error data: ' . json_encode($summary_result->get_error_data()), 'debug');
			error_log( 'TL;DR Pro Summary Generation Error: ' . $summary_result->get_error_message() );
			
			wp_send_json_error( array( 
				'message' => sprintf(
					__( 'Failed to generate summary: %s', 'tldr-pro' ),
					$summary_result->get_error_message()
				),
				'error_code' => $summary_result->get_error_code(),
				'post_id' => $post_id
			) );
		}
		
		$logger->log('Summary generation SUCCESS', 'info');
		$logger->log('Result type: ' . gettype($summary_result), 'debug');
		if (is_array($summary_result)) {
			$logger->log('Result keys: ' . json_encode(array_keys($summary_result)), 'debug');
		}
		
		// Extract summary text from result array
		$summary_text = is_array( $summary_result ) && isset( $summary_result['summary'] ) 
			? $summary_result['summary'] 
			: $summary_result;
		
		// Extract other metadata if available
		$tokens_used = is_array( $summary_result ) && isset( $summary_result['tokens_used'] ) 
			? $summary_result['tokens_used'] 
			: 0;
		$generation_time = is_array( $summary_result ) && isset( $summary_result['generation_time'] ) 
			? $summary_result['generation_time'] 
			: 0;
		$model_used = is_array( $summary_result ) && isset( $summary_result['model'] ) 
			? $summary_result['model'] 
			: get_option( 'tldr_pro_' . $active_provider . '_model' );
		
		// Save the summary to database
		$database = TLDR_Pro_Database::get_instance();
		$saved = $database->insert_summary( array(
			'post_id' => $post_id,
			'summary_text' => $summary_text,
			'api_provider' => $active_provider,
			'tokens_used' => $tokens_used,
			'generation_time' => $generation_time,
			'summary_meta' => array(
				'model' => $model_used,
				'word_count' => str_word_count( $summary_text ),
				'original_word_count' => $word_count,
				'input_tokens' => is_array( $summary_result ) && isset( $summary_result['input_tokens'] ) ? $summary_result['input_tokens'] : 0,
				'output_tokens' => is_array( $summary_result ) && isset( $summary_result['output_tokens'] ) ? $summary_result['output_tokens'] : 0,
				'cached_tokens' => is_array( $summary_result ) && isset( $summary_result['cached_tokens'] ) ? $summary_result['cached_tokens'] : 0,
			),
			'status' => 'active',
			'language' => $language_code,
		) );
		
		if ( ! $saved ) {
			wp_send_json_error( array( 
				'message' => __( 'Summary generated but failed to save to database.', 'tldr-pro' ),
				'summary' => $summary_text,
				'post_id' => $post_id
			) );
		}
		
		// Return success
		wp_send_json_success( array(
			'message' => __( 'Summary generated successfully!', 'tldr-pro' ),
			'summary' => $summary_text,
			'post_id' => $post_id,
			'word_count' => str_word_count( $summary_text ),
			'tokens_used' => $tokens_used,
			'generation_time' => round( $generation_time, 2 ),
			'provider' => $active_provider,
			'model' => $model_used,
		) );
	}

	/**
	 * AJAX handler for getting summary data.
	 *
	 * @since    1.0.0
	 */
	public function ajax_get_summary() {
		// Check nonce - try multiple for compatibility
		$nonce_valid = false;
		if ( isset( $_POST['nonce'] ) ) {
			if ( wp_verify_nonce( $_POST['nonce'], 'tldr_pro_test_api' ) ) {
				$nonce_valid = true;
			} else if ( wp_verify_nonce( $_POST['nonce'], 'tldr_pro_admin_nonce' ) ) {
				$nonce_valid = true;
			}
		}
		
		if ( ! $nonce_valid ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'tldr-pro' ) ) );
		}
		
		// Check capabilities
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'tldr-pro' ) ) );
		}
		
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'tldr-pro' ) ) );
		}
		
		// Get summary from database
		$database = TLDR_Pro_Database::get_instance();
		$summary = $database->get_summary( $post_id );
		
		if ( ! $summary ) {
			wp_send_json_error( array( 'message' => __( 'No summary found for this post.', 'tldr-pro' ) ) );
		}
		
		// Parse summary meta - it might be JSON string or already an array
		$summary_meta = array();
		if ( ! empty( $summary->summary_meta ) ) {
			if ( is_string( $summary->summary_meta ) ) {
				$summary_meta = json_decode( $summary->summary_meta, true );
			} elseif ( is_array( $summary->summary_meta ) ) {
				$summary_meta = $summary->summary_meta;
			}
		}
		
		// Format summary text (convert line breaks to <br> for display)
		$summary_html = wpautop( $summary->summary_text );
		
		// Return summary data
		wp_send_json_success( array(
			'summary_text' => $summary->summary_text,
			'summary_html' => $summary_html,
			'provider' => ucfirst( $summary->api_provider ),
			'tokens_used' => $summary->tokens_used,
			'generation_time' => round( $summary->generation_time, 2 ),
			'generated_at' => isset( $summary->generated_at ) ? $summary->generated_at : '',
			'model' => isset( $summary_meta['model'] ) ? $summary_meta['model'] : '',
			'word_count' => isset( $summary_meta['word_count'] ) ? $summary_meta['word_count'] : str_word_count( $summary->summary_text ),
		) );
	}
	
	/**
	 * AJAX handler for bulk processing.
	 *
	 * @since    1.0.0
	 */
	public function ajax_bulk_process() {
		// TODO: Implement bulk processing
		wp_die();
	}

	/**
	 * AJAX handler for testing API connection.
	 *
	 * @since    1.0.0
	 */
	public function ajax_test_api() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'tldr_pro_test_api' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Security check failed.', 'tldr-pro' ),
				'code' => 'invalid_nonce',
			));
		}
		
		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Insufficient permissions.', 'tldr-pro' ),
				'code' => 'insufficient_permissions',
			));
		}
		
		$provider = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : '';
		$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( $_POST['api_key'] ) : '';
		
		if ( empty( $provider ) ) {
			wp_send_json_error( array(
				'message' => __( 'Provider is required.', 'tldr-pro' ),
				'code' => 'missing_provider',
			));
		}
		
		if ( empty( $api_key ) ) {
			wp_send_json_error( array(
				'message' => __( 'API key is required.', 'tldr-pro' ),
				'code' => 'missing_api_key',
			));
		}
		
		// Use encryption for API key
		$encryption = TLDR_Pro_Encryption::get_instance();
		$original_key = $encryption->get_api_key( $provider );
		
		// Temporarily store the API key
		$encryption->store_api_key( $provider, $api_key );
		
		// Get the provider instance and run comprehensive test
		$ai_manager = TLDR_Pro_AI_Manager::get_instance();
		$provider_instance = $ai_manager->get_provider_instance( $provider );
		
		if ( ! $provider_instance ) {
			// Restore original key
			if ( ! empty( $original_key ) ) {
				$encryption->store_api_key( $provider, $original_key );
			} else {
				$encryption->clear_provider_data( $provider );
			}
			
			wp_send_json_error( array(
				'message' => sprintf( __( 'Provider "%s" not found.', 'tldr-pro' ), $provider ),
				'code' => 'provider_not_found',
			));
		}
		
		// Run comprehensive test if available, otherwise use basic validation
		$test_results = null;
		if ( method_exists( $provider_instance, 'test_api_connection' ) ) {
			// Use comprehensive test for providers that support it
			$test_results = $provider_instance->test_api_connection();
			
			// If test is successful, save the new key and validation status
			if ( $test_results['status'] === 'success' ) {
				// Keep the new valid key
				$encryption->store_api_key( $provider, $api_key );
				$encryption->store_validation_status( $provider, true, $test_results );
				
				wp_send_json_success( $test_results );
			} else {
				// Restore original key on failure
				if ( ! empty( $original_key ) ) {
					$encryption->store_api_key( $provider, $original_key );
				} else {
					$encryption->clear_provider_data( $provider );
				}
				
				// Mark as not validated
				$encryption->store_validation_status( $provider, false, $test_results );
				
				wp_send_json_error( $test_results );
			}
		} else {
			// Fall back to basic validation for providers without comprehensive test
			$result = $ai_manager->validate_provider( $provider );
			
			if ( is_wp_error( $result ) ) {
				// Restore original key on failure
				if ( ! empty( $original_key ) ) {
					$encryption->store_api_key( $provider, $original_key );
				} else {
					$encryption->clear_provider_data( $provider );
				}
				
				wp_send_json_error( array(
					'message' => $result->get_error_message(),
					'code' => $result->get_error_code(),
					'status' => 'error',
				));
			} else {
				// Keep the new valid key on success
				$encryption->store_api_key( $provider, $api_key );
				$encryption->store_validation_status( $provider, true );
				
				wp_send_json_success( array(
					'message' => __( 'API connection successful!', 'tldr-pro' ),
					'status' => 'success',
					'provider' => $provider,
				));
			}
		}
	}
	
	/**
	 * AJAX handler for testing all providers.
	 *
	 * @since    1.0.0
	 */
	public function ajax_test_all_providers() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'tldr_pro_test_all' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'tldr-pro' ) );
		}
		
		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'tldr-pro' ) );
		}
		
		$ai_manager = TLDR_Pro_AI_Manager::get_instance();
		$results = array();
		$provider_config = $ai_manager->get_provider_config();
		
		foreach ( $provider_config as $key => $config ) {
			$api_key = get_option( 'tldr_pro_' . $key . '_api_key' );
			
			if ( empty( $api_key ) ) {
				$results[ $config['name'] ] = array(
					'status' => 'error',
					'message' => 'No API key configured',
				);
			} else {
				$validation = $ai_manager->validate_provider( $key );
				
				if ( is_wp_error( $validation ) ) {
					$results[ $config['name'] ] = array(
						'status' => 'error',
						'message' => $validation->get_error_message(),
					);
				} else {
					$results[ $config['name'] ] = array(
						'status' => 'success',
						'message' => 'Connected',
					);
				}
			}
		}
		
		wp_send_json_success( $results );
	}
	
	/**
	 * AJAX handler for getting test progress.
	 *
	 * @since    1.0.0
	 */
	public function ajax_get_test_progress() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'tldr_pro_test_api' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'tldr-pro' ) );
		}
		
		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'tldr-pro' ) );
		}
		
		$progress = get_transient( 'tldr_pro_test_progress_' . get_current_user_id() );
		
		if ( false === $progress ) {
			wp_send_json_success( array(
				'status' => 'idle',
				'progress' => 0,
			));
		}
		
		wp_send_json_success( $progress );
	}
	
	/**
	 * AJAX handler for getting test history.
	 *
	 * @since    1.0.0
	 */
	public function ajax_get_test_history() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'tldr_pro_test_api' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'tldr-pro' ) );
		}
		
		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'tldr-pro' ) );
		}
		
		global $wpdb;
		$table_name = $wpdb->prefix . 'tldr_pro_test_logs';
		
		// Check if table exists
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
			wp_send_json_success( array() );
			return;
		}
		
		$provider = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : '';
		$limit = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 10;
		
		$where = '';
		if ( ! empty( $provider ) ) {
			$where = $wpdb->prepare( ' WHERE provider = %s', $provider );
		}
		
		$query = "SELECT * FROM $table_name $where ORDER BY tested_at DESC LIMIT %d";
		$results = $wpdb->get_results( $wpdb->prepare( $query, $limit ) );
		
		$history = array();
		foreach ( $results as $row ) {
			$test_data = json_decode( $row->test_results, true );
			$history[] = array(
				'id' => $row->id,
				'provider' => $row->provider,
				'model' => $row->model,
				'status' => $row->status,
				'summary' => isset( $test_data['summary'] ) ? $test_data['summary'] : '',
				'timing' => isset( $test_data['timing']['total'] ) ? $test_data['timing']['total'] : 0,
				'tested_at' => $row->tested_at,
				'user_id' => $row->user_id,
			);
		}
		
		wp_send_json_success( $history );
	}
	
	/**
	 * AJAX handler for saving prompt templates
	 *
	 * @since    1.0.0
	 */
	public function ajax_save_prompts() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'tldr_pro_save_prompts' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'tldr-pro' ) );
		}
		
		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'tldr-pro' ) );
		}
		
		// Get prompts from request
		$prompts = isset( $_POST['prompts'] ) ? $_POST['prompts'] : array();
		
		if ( empty( $prompts ) || ! is_array( $prompts ) ) {
			wp_send_json_error( __( 'No prompts provided.', 'tldr-pro' ) );
		}
		
		// Load prompt manager
		if ( ! class_exists( 'TLDR_Pro_Prompt_Manager' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/ai/class-tldr-pro-prompt-manager.php';
		}
		
		// Save prompts
		$result = TLDR_Pro_Prompt_Manager::save_prompts( $prompts );
		
		if ( $result ) {
			wp_send_json_success( array(
				'message' => __( 'Prompts saved successfully!', 'tldr-pro' )
			) );
		} else {
			wp_send_json_error( __( 'Failed to save prompts.', 'tldr-pro' ) );
		}
	}
	
	/**
	 * AJAX handler for resetting a single prompt to default
	 *
	 * @since    1.0.0
	 */
	public function ajax_reset_prompt() {
		// Check nonce - try multiple for compatibility
		$nonce_valid = false;
		if ( isset( $_POST['nonce'] ) ) {
			if ( wp_verify_nonce( $_POST['nonce'], 'tldr_pro_reset_prompt' ) ) {
				$nonce_valid = true;
			} else if ( wp_verify_nonce( $_POST['nonce'], 'tldr_pro_admin_nonce' ) ) {
				$nonce_valid = true;
			}
		}
		
		if ( ! $nonce_valid ) {
			wp_send_json_error( __( 'Security check failed.', 'tldr-pro' ) );
		}
		
		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'tldr-pro' ) );
		}
		
		// Get provider
		$provider = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : '';
		
		if ( empty( $provider ) ) {
			wp_send_json_error( __( 'Provider not specified.', 'tldr-pro' ) );
		}
		
		// Load prompt manager
		if ( ! class_exists( 'TLDR_Pro_Prompt_Manager' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/ai/class-tldr-pro-prompt-manager.php';
		}
		
		// Get default prompt
		$default_prompt = TLDR_Pro_Prompt_Manager::get_default_prompt( $provider );
		
		if ( ! $default_prompt ) {
			wp_send_json_error( __( 'Default prompt not found for this provider.', 'tldr-pro' ) );
		}
		
		// Reset to default
		$result = TLDR_Pro_Prompt_Manager::reset_to_default( $provider );
		
		if ( $result ) {
			wp_send_json_success( array(
				'prompt' => $default_prompt,
				'message' => __( 'Prompt reset to default successfully!', 'tldr-pro' )
			) );
		} else {
			wp_send_json_error( __( 'Failed to reset prompt.', 'tldr-pro' ) );
		}
	}
	
	/**
	 * AJAX handler for resetting all prompts to defaults
	 *
	 * @since    1.0.0
	 */
	public function ajax_reset_all_prompts() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'tldr_pro_admin_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'tldr-pro' ) );
		}
		
		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'tldr-pro' ) );
		}
		
		// Load prompt manager
		if ( ! class_exists( 'TLDR_Pro_Prompt_Manager' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/ai/class-tldr-pro-prompt-manager.php';
		}
		
		// Reset all prompts
		$result = TLDR_Pro_Prompt_Manager::reset_all_to_defaults();
		
		if ( $result ) {
			// Get all default prompts
			$default_prompts = array();
			$providers = array( 'deepseek', 'gemini' );
			
			foreach ( $providers as $provider ) {
				$default_prompts[ $provider ] = TLDR_Pro_Prompt_Manager::get_default_prompt( $provider );
			}
			
			wp_send_json_success( array(
				'prompts' => $default_prompts,
				'message' => __( 'All prompts reset to defaults successfully!', 'tldr-pro' )
			) );
		} else {
			wp_send_json_error( __( 'Failed to reset prompts.', 'tldr-pro' ) );
		}
	}
	
	/**
	 * AJAX handler for initializing default settings
	 *
	 * @since    1.0.0
	 */
	public function ajax_init_defaults() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'tldr_pro_admin_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'tldr-pro' ) );
		}
		
		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'tldr-pro' ) );
		}
		
		// Load Settings Manager
		if ( ! class_exists( 'TLDR_Pro_Settings_Manager' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-tldr-pro-settings-manager.php';
		}
		
		// Initialize defaults
		TLDR_Pro_Settings_Manager::init_defaults();
		
		wp_send_json_success( array(
			'message' => __( 'Default settings initialized successfully!', 'tldr-pro' )
		) );
	}
	
	/**
	 * AJAX handler for resetting all settings to defaults
	 *
	 * @since    1.0.0
	 */
	public function ajax_reset_all_settings() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'tldr_pro_admin_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'tldr-pro' ) );
		}
		
		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'tldr-pro' ) );
		}
		
		// Load Settings Manager
		if ( ! class_exists( 'TLDR_Pro_Settings_Manager' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-tldr-pro-settings-manager.php';
		}
		
		// Reset all settings to defaults
		$result = TLDR_Pro_Settings_Manager::reset_to_defaults();
		
		if ( $result ) {
			wp_send_json_success( array(
				'message' => __( 'All settings reset to defaults successfully!', 'tldr-pro' )
			) );
		} else {
			wp_send_json_error( __( 'Failed to reset settings.', 'tldr-pro' ) );
		}
	}
	
	/**
	 * AJAX handler for resetting button display settings
	 *
	 * @since    1.0.0
	 */
	public function ajax_reset_button_settings() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'tldr_pro_admin_nonce' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Security check failed.', 'tldr-pro' )
			) );
		}
		
		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Insufficient permissions.', 'tldr-pro' )
			) );
		}
		
		// Reset button settings to defaults
		update_option( 'tldr_pro_button_text', 'Show TL;DR' );
		update_option( 'tldr_pro_button_style', 'default' );
		update_option( 'tldr_pro_button_bg_color', '#0073aa' );
		update_option( 'tldr_pro_button_text_color', '#ffffff' );
		update_option( 'tldr_pro_button_position_post', 'after_title' );
		update_option( 'tldr_pro_button_position_page', 'after_title_elegant' );
		update_option( 'tldr_pro_button_position', 'before_content' );
		
		wp_send_json_success( array(
			'message' => __( 'Button settings reset to defaults successfully!', 'tldr-pro' )
		) );
	}
	
	/**
	 * AJAX handler for getting generation status
	 *
	 * @since    2.4.8
	 */
	public function ajax_get_generation_status() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'tldr_pro_admin_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'tldr-pro' ) );
		}
		
		// Get request ID
		$request_id = isset( $_POST['request_id'] ) ? sanitize_text_field( $_POST['request_id'] ) : '';
		
		if ( empty( $request_id ) ) {
			wp_send_json_error( __( 'Invalid request ID.', 'tldr-pro' ) );
		}
		
		// Include status tracker
		if ( ! class_exists( 'TLDR_Pro_Status_Tracker' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-tldr-pro-status-tracker.php';
		}
		
		// Get status
		$status = TLDR_Pro_Status_Tracker::get_status( $request_id );
		
		if ( false === $status ) {
			wp_send_json_error( array(
				'message' => __( 'Status not found.', 'tldr-pro' ),
				'status' => 'unknown'
			) );
		}
		
		wp_send_json_success( $status );
	}
}