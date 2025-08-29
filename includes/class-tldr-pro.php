<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    TLDR_Pro
 * @subpackage TLDR_Pro/includes
 * @author     Your Name <your-email@example.com>
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The core plugin class.
 *
 * @since      1.0.0
 * @package    TLDR_Pro
 * @subpackage TLDR_Pro/includes
 */
class TLDR_Pro {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      TLDR_Pro_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * The single instance of the class.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      TLDR_Pro    $instance    The single instance of the class.
	 */
	protected static $instance = null;

	/**
	 * Main TLDR_Pro Instance.
	 *
	 * Ensures only one instance of TLDR_Pro is loaded or can be loaded.
	 *
	 * @since    1.0.0
	 * @static
	 * @return   TLDR_Pro    Main instance.
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->version     = TLDR_PRO_VERSION;
		$this->plugin_name = 'tldr-pro';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		
		// Initialize encryption after WordPress is loaded
		add_action( 'init', array( $this, 'init_encryption' ) );
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - TLDR_Pro_Loader. Orchestrates the hooks of the plugin.
	 * - TLDR_Pro_i18n. Defines internationalization functionality.
	 * - TLDR_Pro_Admin. Defines all hooks for the admin area.
	 * - TLDR_Pro_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once TLDR_PRO_PLUGIN_DIR . 'includes/class-tldr-pro-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once TLDR_PRO_PLUGIN_DIR . 'includes/class-tldr-pro-i18n.php';

		/**
		 * The class responsible for encryption of sensitive data.
		 */
		require_once TLDR_PRO_PLUGIN_DIR . 'includes/class-tldr-pro-encryption.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once TLDR_PRO_PLUGIN_DIR . 'admin/class-tldr-pro-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once TLDR_PRO_PLUGIN_DIR . 'public/class-tldr-pro-public.php';

		/**
		 * The classes responsible for database operations
		 */
		require_once TLDR_PRO_PLUGIN_DIR . 'includes/class-tldr-pro-database.php';
		require_once TLDR_PRO_PLUGIN_DIR . 'includes/class-tldr-pro-logger.php';
		require_once TLDR_PRO_PLUGIN_DIR . 'includes/class-tldr-pro-content-extractor.php';
		require_once TLDR_PRO_PLUGIN_DIR . 'includes/class-tldr-pro-error-handler.php';
		require_once TLDR_PRO_PLUGIN_DIR . 'includes/class-tldr-pro-prompt-templates.php';
		require_once TLDR_PRO_PLUGIN_DIR . 'includes/class-tldr-pro-settings-manager.php';

		/**
		 * AI Provider classes
		 */
		require_once TLDR_PRO_PLUGIN_DIR . 'includes/ai/class-tldr-pro-ai-provider.php';
		require_once TLDR_PRO_PLUGIN_DIR . 'includes/ai/class-tldr-pro-ai-manager.php';
		require_once TLDR_PRO_PLUGIN_DIR . 'includes/ai/class-tldr-pro-ai-models.php';
		require_once TLDR_PRO_PLUGIN_DIR . 'includes/ai/class-tldr-pro-prompt-manager.php';
		require_once TLDR_PRO_PLUGIN_DIR . 'includes/ai/class-tldr-pro-deepseek-api.php';
		require_once TLDR_PRO_PLUGIN_DIR . 'includes/ai/class-tldr-pro-gemini-api.php';
		require_once TLDR_PRO_PLUGIN_DIR . 'includes/ai/class-tldr-pro-claude-api.php';
		require_once TLDR_PRO_PLUGIN_DIR . 'includes/ai/class-tldr-pro-gpt-api.php';
		
		// Performance and optimization classes
		require_once TLDR_PRO_PLUGIN_DIR . 'includes/class-tldr-pro-cache.php';
		require_once TLDR_PRO_PLUGIN_DIR . 'includes/class-tldr-pro-auto-generate.php';
		require_once TLDR_PRO_PLUGIN_DIR . 'includes/class-tldr-pro-performance.php';
		require_once TLDR_PRO_PLUGIN_DIR . 'includes/class-tldr-pro-accessibility.php';

		$this->loader = new TLDR_Pro_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the TLDR_Pro_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {
		$plugin_i18n = new TLDR_Pro_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Initialize encryption system.
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public function init_encryption() {
		// Initialize encryption singleton if it exists
		if ( class_exists( 'TLDR_Pro_Encryption' ) ) {
			TLDR_Pro_Encryption::get_instance();
		}
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		$plugin_admin = new TLDR_Pro_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );
		
		// AJAX hooks for admin
		$this->loader->add_action( 'wp_ajax_tldr_pro_generate_summary', $plugin_admin, 'ajax_generate_summary' );
		$this->loader->add_action( 'wp_ajax_tldr_pro_get_summary', $plugin_admin, 'ajax_get_summary' );
		$this->loader->add_action( 'wp_ajax_tldr_pro_bulk_process', $plugin_admin, 'ajax_bulk_process' );
		$this->loader->add_action( 'wp_ajax_tldr_pro_test_api', $plugin_admin, 'ajax_test_api' );
		$this->loader->add_action( 'wp_ajax_tldr_pro_test_all_providers', $plugin_admin, 'ajax_test_all_providers' );
		$this->loader->add_action( 'wp_ajax_tldr_pro_get_test_progress', $plugin_admin, 'ajax_get_test_progress' );
		$this->loader->add_action( 'wp_ajax_tldr_pro_get_test_history', $plugin_admin, 'ajax_get_test_history' );
		$this->loader->add_action( 'wp_ajax_tldr_pro_get_generation_status', $plugin_admin, 'ajax_get_generation_status' );
		
		// Prompt template AJAX actions
		$this->loader->add_action( 'wp_ajax_tldr_pro_save_prompts', $plugin_admin, 'ajax_save_prompts' );
		$this->loader->add_action( 'wp_ajax_tldr_pro_reset_prompt', $plugin_admin, 'ajax_reset_prompt' );
		$this->loader->add_action( 'wp_ajax_tldr_pro_reset_all_prompts', $plugin_admin, 'ajax_reset_all_prompts' );
		
		// Settings management AJAX actions
		$this->loader->add_action( 'wp_ajax_tldr_pro_init_defaults', $plugin_admin, 'ajax_init_defaults' );
		$this->loader->add_action( 'wp_ajax_tldr_pro_reset_all_settings', $plugin_admin, 'ajax_reset_all_settings' );
		$this->loader->add_action( 'wp_ajax_tldr_pro_reset_button_settings', $plugin_admin, 'ajax_reset_button_settings' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {
		$plugin_public = new TLDR_Pro_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
		
		// Content filters
		$this->loader->add_filter( 'the_content', $plugin_public, 'add_summary_button', 99 );
		
		// AJAX hooks for frontend
		$this->loader->add_action( 'wp_ajax_tldr_pro_get_frontend_summary', $plugin_public, 'ajax_get_summary' );
		$this->loader->add_action( 'wp_ajax_nopriv_tldr_pro_get_frontend_summary', $plugin_public, 'ajax_get_summary' );
		
		// Tracking AJAX hooks
		$this->loader->add_action( 'wp_ajax_tldr_pro_track_click', $plugin_public, 'ajax_track_click' );
		$this->loader->add_action( 'wp_ajax_nopriv_tldr_pro_track_click', $plugin_public, 'ajax_track_click' );
		$this->loader->add_action( 'wp_ajax_tldr_pro_track_view', $plugin_public, 'ajax_track_view' );
		$this->loader->add_action( 'wp_ajax_nopriv_tldr_pro_track_view', $plugin_public, 'ajax_track_view' );
		$this->loader->add_action( 'wp_ajax_tldr_pro_track_page_view', $plugin_public, 'ajax_track_page_view' );
		$this->loader->add_action( 'wp_ajax_nopriv_tldr_pro_track_page_view', $plugin_public, 'ajax_track_page_view' );
		$this->loader->add_action( 'wp_ajax_tldr_pro_track_ab_variant', $plugin_public, 'ajax_track_ab_variant' );
		$this->loader->add_action( 'wp_ajax_nopriv_tldr_pro_track_ab_variant', $plugin_public, 'ajax_track_ab_variant' );
		
		// Rating and sharing AJAX hooks
		$this->loader->add_action( 'wp_ajax_tldr_pro_submit_rating', $plugin_public, 'ajax_submit_rating' );
		$this->loader->add_action( 'wp_ajax_nopriv_tldr_pro_submit_rating', $plugin_public, 'ajax_submit_rating' );
		$this->loader->add_action( 'wp_ajax_tldr_pro_track_share', $plugin_public, 'ajax_track_share' );
		$this->loader->add_action( 'wp_ajax_nopriv_tldr_pro_track_share', $plugin_public, 'ajax_track_share' );
		
		// REST API endpoints
		$this->loader->add_action( 'rest_api_init', $plugin_public, 'register_rest_routes' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    TLDR_Pro_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}