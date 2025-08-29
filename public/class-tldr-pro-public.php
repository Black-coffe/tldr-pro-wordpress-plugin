<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @package    TLDR_Pro
 * @subpackage TLDR_Pro/public
 * @author     Your Name <your-email@example.com>
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The public-facing functionality of the plugin.
 */
class TLDR_Pro_Public {

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
	 * @param    string    $plugin_name       The name of the plugin.
	 * @param    string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		// Only load on singular posts/pages
		if ( ! is_singular() ) {
			return;
		}

		// Check if summary exists for this post
		$post_id = get_the_ID();
		if ( ! $this->has_summary( $post_id ) ) {
			return;
		}

		// Enqueue button styles
		wp_enqueue_style(
			$this->plugin_name . '-button',
			plugin_dir_url( __FILE__ ) . 'css/tldr-pro-button.css',
			array(),
			$this->version,
			'all'
		);

		// Enqueue modal styles
		wp_enqueue_style(
			$this->plugin_name . '-modal',
			plugin_dir_url( __FILE__ ) . 'css/tldr-pro-modal.css',
			array(),
			$this->version,
			'all'
		);

		// Enqueue compatibility styles to prevent conflicts
		wp_enqueue_style(
			$this->plugin_name . '-compatibility',
			plugin_dir_url( __FILE__ ) . 'css/tldr-pro-compatibility.css',
			array(),
			$this->version,
			'all'
		);

		// Add inline styles for customization
		$custom_css = $this->get_custom_button_styles();
		if ( ! empty( $custom_css ) ) {
			wp_add_inline_style( $this->plugin_name . '-button', $custom_css );
		}
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		// Only load on singular posts/pages
		if ( ! is_singular() ) {
			return;
		}

		// Check if summary exists for this post
		$post_id = get_the_ID();
		if ( ! $this->has_summary( $post_id ) ) {
			return;
		}

		// Enqueue button script
		wp_enqueue_script(
			$this->plugin_name . '-button',
			plugin_dir_url( __FILE__ ) . 'js/tldr-pro-button.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		// Enqueue enhanced modal script
		wp_enqueue_script(
			$this->plugin_name . '-modal',
			plugin_dir_url( __FILE__ ) . 'js/tldr-pro-modal.js',
			array( 'jquery', $this->plugin_name . '-button' ),
			$this->version,
			true
		);

		// Localize script with data
		wp_localize_script(
			$this->plugin_name . '-button',
			'tldr_pro_frontend',
			$this->get_localized_data( $post_id )
		);
	}

	/**
	 * Add summary button to content.
	 *
	 * @since    1.0.0
	 * @param    string    $content    The post content.
	 * @return   string                The modified content.
	 */
	public function add_summary_button( $content ) {
		// Add comprehensive logging
		$logger = TLDR_Pro_Logger::get_instance();
		$post_id = get_the_ID();
		$logger->log( 'DEBUG', sprintf( '[Frontend Button] Checking button display for post ID: %d', $post_id ) );
		
		// Check if auto display is enabled
		$auto_display = TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_auto_display' );
		$logger->log( 'DEBUG', sprintf( '[Frontend Button] Auto display setting: %s', $auto_display ? 'enabled' : 'disabled' ) );
		if ( ! $auto_display ) {
			$logger->log( 'DEBUG', '[Frontend Button] Auto display disabled, not adding button' );
			return $content;
		}

		// Only add to main content in the loop
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			$logger->log( 'DEBUG', sprintf( '[Frontend Button] Context check failed - singular: %s, in_loop: %s, main_query: %s',
				is_singular() ? 'yes' : 'no',
				in_the_loop() ? 'yes' : 'no',
				is_main_query() ? 'yes' : 'no'
			) );
			return $content;
		}

		// Check if this post type is enabled
		$post_type = get_post_type();
		$enabled_post_types = TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_enabled_post_types', array( 'post', 'page' ) );
		$logger->log( 'DEBUG', sprintf( '[Frontend Button] Post type: %s, Enabled types: %s', 
			$post_type, 
			implode(', ', $enabled_post_types) 
		) );
		if ( ! in_array( $post_type, $enabled_post_types ) ) {
			$logger->log( 'DEBUG', '[Frontend Button] Post type not enabled for summaries' );
			return $content;
		}

		// Check if summary exists
		$has_summary = $this->has_summary( $post_id );
		$logger->log( 'DEBUG', sprintf( '[Frontend Button] Has summary: %s', $has_summary ? 'yes' : 'no' ) );
		if ( ! $has_summary ) {
			$logger->log( 'DEBUG', '[Frontend Button] No summary found for post' );
			return $content;
		}

		// Check minimum word count
		$min_words = TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_min_word_count', 300 );
		$word_count = str_word_count( strip_tags( $content ) );
		$logger->log( 'DEBUG', sprintf( '[Frontend Button] Word count: %d, Minimum required: %d', $word_count, $min_words ) );
		if ( $word_count < $min_words ) {
			$logger->log( 'DEBUG', '[Frontend Button] Content below minimum word count' );
			return $content;
		}

		// Get button position setting based on post type
		$post_type = get_post_type( $post_id );
		$position = '';
		
		if ( $post_type === 'post' ) {
			$position = get_option( 'tldr_pro_button_position_post', 'after_title' );
		} elseif ( $post_type === 'page' ) {
			$position = get_option( 'tldr_pro_button_position_page', 'after_title_elegant' );
		} else {
			// Fallback to legacy setting for custom post types
			$position = get_option( 'tldr_pro_button_position', 'before_content' );
		}
		
		$button_html = $this->get_button_html( $post_id, $position );
		$logger->log( 'DEBUG', sprintf( '[Frontend Button] Button position for %s: %s', $post_type, $position ) );
		$logger->log( 'DEBUG', '[Frontend Button] Button HTML generated successfully' );

		// Handle inline positions
		switch ( $position ) {
			case 'after_title':
			case 'after_title_elegant':
				// These need to be handled via filter on the_title or via JavaScript
				$logger->log( 'INFO', sprintf( '[Frontend Button] After title position will be handled via JS for post ID: %d', $post_id ) );
				add_action( 'wp_footer', function() use ( $post_id, $position, $button_html ) {
					$this->add_after_title_script( $post_id, $position, $button_html );
				});
				return $content;
				
			case 'before_content':
				$logger->log( 'INFO', sprintf( '[Frontend Button] Added TL;DR button before content for post ID: %d', $post_id ) );
				return $button_html . $content;
				
			case 'after_content':
				$logger->log( 'INFO', sprintf( '[Frontend Button] Added TL;DR button after content for post ID: %d', $post_id ) );
				return $content . $button_html;
				
			case 'fixed_bottom_left':
			case 'fixed_bottom_right':
			case 'fixed_bottom_center':
			case 'floating_bar':
			case 'sidebar_sticky':
			case 'hero_section':
				// Fixed and floating positions are handled via JavaScript
				$logger->log( 'INFO', sprintf( '[Frontend Button] Fixed/floating position will be added via JS for post ID: %d', $post_id ) );
				add_action( 'wp_footer', function() use ( $post_id, $position, $button_html ) {
					$this->add_fixed_button_script( $post_id, $position, $button_html );
				});
				return $content;
				
			default:
				$logger->log( 'WARNING', sprintf( '[Frontend Button] Unknown button position: %s', $position ) );
				return $content;
		}
	}

	/**
	 * AJAX handler for getting summary.
	 *
	 * @since    1.0.0
	 */
	public function ajax_get_summary() {
		// Check nonce
		if ( ! check_ajax_referer( 'tldr_pro_frontend', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'tldr-pro' ) ) );
		}

		// Get post ID
		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID', 'tldr-pro' ) ) );
		}

		// Get summary from database
		$database = TLDR_Pro_Database::get_instance();
		$summary = $database->get_summary( $post_id );

		if ( ! $summary ) {
			wp_send_json_error( array( 'message' => __( 'Summary not found', 'tldr-pro' ) ) );
		}

		// Prepare response data (handle both object and array format)
		if ( is_object( $summary ) ) {
			$response = array(
				'post_id' => $post_id,
				'post_title' => get_the_title( $post_id ),
				'summary_text' => $summary->summary_text,
				'summary_html' => $summary->summary_text, // Already contains HTML
				'provider' => isset( $summary->api_provider ) ? $summary->api_provider : 'unknown',
				'language' => isset( $summary->language ) ? $summary->language : 'en',
				'reading_time' => $this->calculate_reading_time( $summary->summary_text ),
				'word_count' => str_word_count( strip_tags( $summary->summary_text ) )
			);
		} else {
			$response = array(
				'post_id' => $post_id,
				'post_title' => get_the_title( $post_id ),
				'summary_text' => $summary['summary_text'],
				'summary_html' => $summary['summary_text'], // Already contains HTML
				'provider' => isset( $summary['api_provider'] ) ? $summary['api_provider'] : 'unknown',
				'language' => isset( $summary['language'] ) ? $summary['language'] : 'en',
				'reading_time' => $this->calculate_reading_time( $summary['summary_text'] ),
				'word_count' => str_word_count( strip_tags( $summary['summary_text'] ) )
			);
		}

		wp_send_json_success( $response );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since    1.0.0
	 */
	public function register_rest_routes() {
		// TODO: Implement REST routes if needed
	}

	/**
	 * Check if post has summary.
	 *
	 * @since    1.0.0
	 * @param    int    $post_id    The post ID.
	 * @return   bool               True if summary exists.
	 */
	private function has_summary( $post_id ) {
		$database = TLDR_Pro_Database::get_instance();
		$summary = $database->get_summary( $post_id );
		return ! empty( $summary );
	}

	/**
	 * Get button HTML.
	 *
	 * @since    1.0.0
	 * @param    int    $post_id    The post ID.
	 * @return   string             The button HTML.
	 */
	private function get_button_html( $post_id, $position = '' ) {
		$button_text = get_option( 'tldr_pro_button_text', 'Show TL;DR' );
		$button_style = get_option( 'tldr_pro_button_style', 'default' );
		
		// Add position-specific classes
		$position_class = $position ? 'tldr-position-' . esc_attr( $position ) : '';
		
		// For fixed center position, add special class for icon-only on mobile
		$mobile_class = ( $position === 'fixed_bottom_center' ) ? 'tldr-mobile-icon-only' : '';

		$html = '<div class="tldr-pro-button-container ' . $position_class . ' ' . $mobile_class . '">';
		$html .= '<button class="tldr-pro-button tldr-style-' . esc_attr( $button_style ) . '" data-post-id="' . esc_attr( $post_id ) . '" data-position="' . esc_attr( $position ) . '">';
		$html .= '<span class="tldr-pro-icon">';
		$html .= $this->get_button_icon();
		$html .= '</span>';
		$html .= '<span class="tldr-pro-text">' . esc_html( $button_text ) . '</span>';
		$html .= '</button>';
		$html .= '</div>';

		return $html;
	}
	
	/**
	 * Add script for after title positioning.
	 *
	 * @since    2.7.5
	 * @param    int    $post_id    The post ID.
	 * @param    string $position   The position type.
	 * @param    string $button_html The button HTML.
	 */
	private function add_after_title_script( $post_id, $position, $button_html ) {
		?>
		<script>
		jQuery(document).ready(function($) {
			var buttonHtml = <?php echo json_encode( $button_html ); ?>;
			var $title = $('.entry-title, .post-title, h1.title').first();
			
			if ($title.length) {
				<?php if ( $position === 'after_title_elegant' ) : ?>
				// Add elegant spacing for pages
				var $wrapper = $('<div class="tldr-after-title-wrapper" style="text-align: center; margin: 20px 0; padding: 20px; background: linear-gradient(135deg, #f5f5f5 0%, #ffffff 100%); border-radius: 10px;"></div>');
				$wrapper.html(buttonHtml);
				$title.after($wrapper);
				<?php else : ?>
				// Simple after title for posts
				$title.after(buttonHtml);
				<?php endif; ?>
			}
		});
		</script>
		<?php
	}
	
	/**
	 * Add script for fixed/floating positioning.
	 *
	 * @since    2.7.5
	 * @param    int    $post_id    The post ID.
	 * @param    string $position   The position type.
	 * @param    string $button_html The button HTML.
	 */
	private function add_fixed_button_script( $post_id, $position, $button_html ) {
		?>
		<script>
		jQuery(document).ready(function($) {
			var buttonHtml = <?php echo json_encode( $button_html ); ?>;
			var position = '<?php echo esc_js( $position ); ?>';
			
			// Add button to body for fixed positioning
			$('body').append(buttonHtml);
			
			// Apply position-specific styles
			var $container = $('.tldr-pro-button-container').last();
			
			switch(position) {
				case 'fixed_bottom_left':
					$container.css({
						'position': 'fixed',
						'bottom': '30px',
						'left': '30px',
						'z-index': '9999'
					});
					break;
					
				case 'fixed_bottom_right':
					$container.css({
						'position': 'fixed',
						'bottom': '30px',
						'right': '30px',
						'z-index': '9999'
					});
					break;
					
				case 'fixed_bottom_center':
					$container.css({
						'position': 'fixed',
						'bottom': '30px',
						'left': '50%',
						'transform': 'translateX(-50%)',
						'z-index': '9999'
					});
					// Hide text on mobile
					if (window.innerWidth < 768) {
						$container.find('.tldr-pro-text').hide();
						$container.find('.tldr-pro-button').css({
							'border-radius': '50%',
							'width': '60px',
							'height': '60px',
							'padding': '0'
						});
					}
					break;
					
				case 'floating_bar':
					$container.css({
						'position': 'fixed',
						'top': '0',
						'left': '0',
						'right': '0',
						'background': 'rgba(255, 255, 255, 0.95)',
						'backdrop-filter': 'blur(10px)',
						'padding': '10px',
						'text-align': 'center',
						'box-shadow': '0 2px 10px rgba(0,0,0,0.1)',
						'z-index': '9999'
					});
					break;
					
				case 'sidebar_sticky':
					// This would need to find the sidebar and append there
					var $sidebar = $('.sidebar, .widget-area, aside').first();
					if ($sidebar.length) {
						$sidebar.prepend(buttonHtml);
						$sidebar.find('.tldr-pro-button-container').first().css({
							'position': 'sticky',
							'top': '100px'
						});
					}
					break;
					
				case 'hero_section':
					// Find hero section or create one after header
					var $hero = $('.hero, .page-header, .entry-header').first();
					if ($hero.length) {
						$hero.append(buttonHtml);
						$hero.find('.tldr-pro-button-container').css({
							'text-align': 'center',
							'margin': '20px 0'
						});
					}
					break;
			}
		});
		</script>
		<?php
	}

	/**
	 * Get button icon SVG.
	 *
	 * @since    1.0.0
	 * @return   string    The icon SVG.
	 */
	private function get_button_icon() {
		return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
			<path d="M9 2H15C16.1046 2 17 2.89543 17 4V20C17 21.1046 16.1046 22 15 22H9C7.89543 22 7 21.1046 7 20V4C7 2.89543 7.89543 2 9 2Z" stroke="currentColor" stroke-width="2"/>
			<path d="M10 6H14M10 10H14M10 14H12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
		</svg>';
	}

	/**
	 * Get localized data for JavaScript.
	 *
	 * @since    1.0.0
	 * @param    int    $post_id    The post ID.
	 * @return   array              The localized data.
	 */
	private function get_localized_data( $post_id ) {
		return array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'tldr_pro_frontend' ),
			'post_id' => $post_id,
			'is_singular' => is_singular() ? '1' : '0',
			'has_summary' => $this->has_summary( $post_id ) ? '1' : '0',
			'min_word_count' => TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_min_word_count', 300 ),
			'button_position' => TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_button_position', 'before_content' ),
			'floating_button' => TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_button_position', 'before_content' ) === 'floating' ? '1' : '0',
			'floating_position' => TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_floating_position', 'bottom-right' ),
			'hide_if_viewed' => TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_hide_if_viewed', false ) ? '1' : '0',
			'ab_testing' => TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_ab_testing', false ) ? '1' : '0',
			'strings' => array(
				'loading' => __( 'Loading...', 'tldr-pro' ),
				'error' => __( 'Error loading summary', 'tldr-pro' ),
				'close' => __( 'Close', 'tldr-pro' )
			)
		);
	}

	/**
	 * Get custom button styles.
	 *
	 * @since    1.0.0
	 * @return   string    The custom CSS.
	 */
	private function get_custom_button_styles() {
		$custom_css = '';
		
		// Get button style setting
		$button_style = get_option( 'tldr_pro_button_style', 'default' );
		
		// Define styles for each preset
		$style_definitions = array(
			'default' => '.tldr-pro-button.tldr-style-default { background: #0073aa !important; color: #ffffff !important; border: none !important; }',
			
			'gradient-blue' => '.tldr-pro-button.tldr-style-gradient-blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important; color: #ffffff !important; border: none !important; }',
			
			'gradient-purple' => '.tldr-pro-button.tldr-style-gradient-purple { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%) !important; color: #ffffff !important; border: none !important; }',
			
			'gradient-sunset' => '.tldr-pro-button.tldr-style-gradient-sunset { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%) !important; color: #ffffff !important; text-shadow: 0 1px 2px rgba(0,0,0,0.1) !important; border: none !important; }',
			
			'gradient-ocean' => '.tldr-pro-button.tldr-style-gradient-ocean { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%) !important; color: #ffffff !important; border: none !important; }',
			
			'dark' => '.tldr-pro-button.tldr-style-dark { background: #2c3e50 !important; color: #ffffff !important; border: none !important; }
				.tldr-pro-button.tldr-style-dark:hover { background: #34495e !important; }',
			
			'light' => '.tldr-pro-button.tldr-style-light { background: #ffffff !important; color: #333333 !important; border: 2px solid #dddddd !important; }
				.tldr-pro-button.tldr-style-light:hover { background: #f5f5f5 !important; }',
			
			'minimal-dark' => '.tldr-pro-button.tldr-style-minimal-dark { background: transparent !important; color: #333333 !important; border: 2px solid #333333 !important; }
				.tldr-pro-button.tldr-style-minimal-dark:hover { background: #333333 !important; color: #ffffff !important; }',
			
			'minimal-light' => '.tldr-pro-button.tldr-style-minimal-light { background: transparent !important; color: #666666 !important; border: 2px solid #999999 !important; }
				.tldr-pro-button.tldr-style-minimal-light:hover { background: #999999 !important; color: #ffffff !important; }',
			
			'glassmorphism' => '.tldr-pro-button.tldr-style-glassmorphism { 
				background: rgba(255, 255, 255, 0.2) !important; 
				backdrop-filter: blur(10px) !important;
				-webkit-backdrop-filter: blur(10px) !important;
				color: #333333 !important; 
				border: 1px solid rgba(255, 255, 255, 0.3) !important;
				box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
			}
			.tldr-pro-button.tldr-style-glassmorphism:hover { 
				background: rgba(255, 255, 255, 0.3) !important;
				box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15) !important;
			}'
		);
		
		// Apply the selected style
		if ( isset( $style_definitions[$button_style] ) ) {
			$custom_css .= $style_definitions[$button_style];
		}
		
		// If custom style is selected, use custom colors
		if ( $button_style === 'custom' ) {
			$bg_color = get_option( 'tldr_pro_button_bg_color', '#0073aa' );
			$text_color = get_option( 'tldr_pro_button_text_color', '#ffffff' );
			
			$custom_css .= '.tldr-pro-button.tldr-style-custom { 
				background: ' . esc_attr( $bg_color ) . ' !important; 
				color: ' . esc_attr( $text_color ) . ' !important; 
				border: none !important;
			}';
			
			// Add hover effect for custom colors
			$custom_css .= '.tldr-pro-button.tldr-style-custom:hover { 
				opacity: 0.9 !important;
			}';
		}
		
		// Add common button transitions
		$custom_css .= '
		.tldr-pro-button {
			transition: all 0.3s ease !important;
		}
		.tldr-pro-button svg {
			fill: none !important;
			transition: transform 0.3s ease !important;
		}
		.tldr-pro-button:hover svg {
			transform: scale(1.1);
		}';
		
		return $custom_css;
	}

	/**
	 * Calculate reading time.
	 *
	 * @since    1.0.0
	 * @param    string    $text    The text.
	 * @return   string             The reading time.
	 */
	private function calculate_reading_time( $text ) {
		$word_count = str_word_count( strip_tags( $text ) );
		$minutes = ceil( $word_count / 200 ); // Average reading speed
		return sprintf( _n( '%d min read', '%d min read', $minutes, 'tldr-pro' ), $minutes );
	}

	/**
	 * AJAX handler for tracking clicks.
	 *
	 * @since    1.0.0
	 */
	public function ajax_track_click() {
		check_ajax_referer( 'tldr_pro_frontend', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$button_type = isset( $_POST['button_type'] ) ? sanitize_text_field( $_POST['button_type'] ) : 'inline';

		if ( $post_id ) {
			// Update click count in post meta
			$current_count = get_post_meta( $post_id, 'tldr_pro_click_count', true );
			$current_count = $current_count ? intval( $current_count ) : 0;
			update_post_meta( $post_id, 'tldr_pro_click_count', $current_count + 1 );

			// Track button type
			$type_key = 'tldr_pro_click_' . $button_type;
			$type_count = get_post_meta( $post_id, $type_key, true );
			$type_count = $type_count ? intval( $type_count ) : 0;
			update_post_meta( $post_id, $type_key, $type_count + 1 );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX handler for tracking views.
	 *
	 * @since    1.0.0
	 */
	public function ajax_track_view() {
		check_ajax_referer( 'tldr_pro_frontend', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( $post_id ) {
			// Update view count in post meta
			$current_count = get_post_meta( $post_id, 'tldr_pro_view_count', true );
			$current_count = $current_count ? intval( $current_count ) : 0;
			update_post_meta( $post_id, 'tldr_pro_view_count', $current_count + 1 );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX handler for tracking page views.
	 *
	 * @since    1.0.0
	 */
	public function ajax_track_page_view() {
		check_ajax_referer( 'tldr_pro_frontend', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( $post_id ) {
			// Update page view count for posts with summaries
			$current_count = get_post_meta( $post_id, 'tldr_pro_page_views', true );
			$current_count = $current_count ? intval( $current_count ) : 0;
			update_post_meta( $post_id, 'tldr_pro_page_views', $current_count + 1 );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX handler for tracking A/B test variants.
	 *
	 * @since    1.0.0
	 */
	public function ajax_track_ab_variant() {
		check_ajax_referer( 'tldr_pro_frontend', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$variant = isset( $_POST['variant'] ) ? sanitize_text_field( $_POST['variant'] ) : 'a';

		if ( $post_id ) {
			// Track variant impressions
			$meta_key = 'tldr_pro_ab_variant_' . $variant;
			$current_count = get_post_meta( $post_id, $meta_key, true );
			$current_count = $current_count ? intval( $current_count ) : 0;
			update_post_meta( $post_id, $meta_key, $current_count + 1 );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX handler for submitting rating.
	 *
	 * @since    2.3.0
	 */
	public function ajax_submit_rating() {
		check_ajax_referer( 'tldr_pro_frontend', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$rating = isset( $_POST['rating'] ) ? intval( $_POST['rating'] ) : 0;

		if ( $post_id && $rating >= 1 && $rating <= 5 ) {
			// Get current ratings
			$ratings = get_post_meta( $post_id, 'tldr_pro_ratings', true );
			if ( ! is_array( $ratings ) ) {
				$ratings = array();
			}

			// Add new rating
			$ratings[] = $rating;

			// Update ratings
			update_post_meta( $post_id, 'tldr_pro_ratings', $ratings );

			// Calculate average rating
			$average = array_sum( $ratings ) / count( $ratings );
			update_post_meta( $post_id, 'tldr_pro_rating_average', $average );
			update_post_meta( $post_id, 'tldr_pro_rating_count', count( $ratings ) );

			wp_send_json_success( array(
				'average' => round( $average, 1 ),
				'count' => count( $ratings )
			) );
		}

		wp_send_json_error( 'Invalid rating' );
	}

	/**
	 * AJAX handler for tracking social shares.
	 *
	 * @since    2.3.0
	 */
	public function ajax_track_share() {
		check_ajax_referer( 'tldr_pro_frontend', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$network = isset( $_POST['network'] ) ? sanitize_text_field( $_POST['network'] ) : '';

		if ( $post_id && $network ) {
			// Track share for specific network
			$meta_key = 'tldr_pro_share_' . $network;
			$current_count = get_post_meta( $post_id, $meta_key, true );
			$current_count = $current_count ? intval( $current_count ) : 0;
			update_post_meta( $post_id, $meta_key, $current_count + 1 );

			// Track total shares
			$total_shares = get_post_meta( $post_id, 'tldr_pro_total_shares', true );
			$total_shares = $total_shares ? intval( $total_shares ) : 0;
			update_post_meta( $post_id, 'tldr_pro_total_shares', $total_shares + 1 );
		}

		wp_send_json_success();
	}
}