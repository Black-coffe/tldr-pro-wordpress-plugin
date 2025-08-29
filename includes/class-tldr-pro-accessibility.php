<?php
/**
 * Accessibility features for TL;DR Pro plugin.
 *
 * @package    TLDR_Pro
 * @subpackage TLDR_Pro/includes
 * @since      2.3.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Accessibility class implementing WCAG 2.1 standards.
 */
class TLDR_Pro_Accessibility {

	/**
	 * Initialize accessibility features.
	 */
	public static function init() {
		// Add ARIA attributes
		add_filter( 'tldr_pro_button_attributes', array( __CLASS__, 'add_button_aria' ) );
		add_filter( 'tldr_pro_modal_attributes', array( __CLASS__, 'add_modal_aria' ) );
		
		// Add keyboard navigation
		add_action( 'wp_footer', array( __CLASS__, 'add_keyboard_navigation' ) );
		
		// Add skip links
		add_action( 'tldr_pro_before_content', array( __CLASS__, 'add_skip_links' ) );
		
		// Add focus indicators
		add_action( 'wp_head', array( __CLASS__, 'add_focus_styles' ) );
		
		// Add RTL support
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'add_rtl_styles' ) );
		
		// Add screen reader support
		add_filter( 'tldr_pro_summary_output', array( __CLASS__, 'add_screen_reader_text' ) );
		
		// Add high contrast mode
		add_action( 'wp_head', array( __CLASS__, 'detect_high_contrast' ) );
		
		// Add reduced motion support
		add_action( 'wp_head', array( __CLASS__, 'detect_reduced_motion' ) );
	}

	/**
	 * Add ARIA attributes to TL;DR button.
	 *
	 * @param array $attributes Button attributes.
	 * @return array Modified attributes.
	 */
	public static function add_button_aria( $attributes ) {
		$attributes['role'] = 'button';
		$attributes['aria-label'] = __( 'Show TL;DR Summary', 'tldr-pro' );
		$attributes['aria-expanded'] = 'false';
		$attributes['aria-controls'] = 'tldr-pro-modal';
		$attributes['tabindex'] = '0';
		
		return $attributes;
	}

	/**
	 * Add ARIA attributes to modal.
	 *
	 * @param array $attributes Modal attributes.
	 * @return array Modified attributes.
	 */
	public static function add_modal_aria( $attributes ) {
		$attributes['role'] = 'dialog';
		$attributes['aria-modal'] = 'true';
		$attributes['aria-labelledby'] = 'tldr-pro-modal-title';
		$attributes['aria-describedby'] = 'tldr-pro-modal-content';
		
		return $attributes;
	}

	/**
	 * Add keyboard navigation support.
	 */
	public static function add_keyboard_navigation() {
		?>
		<script>
		// TL;DR Pro Keyboard Navigation
		(function() {
			document.addEventListener('DOMContentLoaded', function() {
				// Trap focus in modal when open
				let focusableElements = null;
				let firstFocusable = null;
				let lastFocusable = null;
				
				// Handle keyboard events
				document.addEventListener('keydown', function(e) {
					const modal = document.getElementById('tldr-pro-modal');
					
					if (!modal || modal.style.display === 'none') {
						// Handle button activation with Enter/Space
						if ((e.key === 'Enter' || e.key === ' ') && e.target.classList.contains('tldr-pro-button')) {
							e.preventDefault();
							e.target.click();
						}
						return;
					}
					
					// Modal is open
					if (e.key === 'Escape') {
						// Close modal on ESC
						const closeBtn = modal.querySelector('.tldr-pro-close');
						if (closeBtn) closeBtn.click();
					} else if (e.key === 'Tab') {
						// Trap focus in modal
						if (!focusableElements) {
							focusableElements = modal.querySelectorAll(
								'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
							);
							firstFocusable = focusableElements[0];
							lastFocusable = focusableElements[focusableElements.length - 1];
						}
						
						if (e.shiftKey) {
							// Shift + Tab
							if (document.activeElement === firstFocusable) {
								e.preventDefault();
								lastFocusable.focus();
							}
						} else {
							// Tab
							if (document.activeElement === lastFocusable) {
								e.preventDefault();
								firstFocusable.focus();
							}
						}
					}
				});
				
				// Announce modal state changes to screen readers
				const announcer = document.createElement('div');
				announcer.setAttribute('role', 'status');
				announcer.setAttribute('aria-live', 'polite');
				announcer.setAttribute('aria-atomic', 'true');
				announcer.className = 'screen-reader-text tldr-pro-announcer';
				document.body.appendChild(announcer);
				
				// Monitor modal state
				const modalObserver = new MutationObserver(function(mutations) {
					mutations.forEach(function(mutation) {
						if (mutation.attributeName === 'style') {
							const modal = mutation.target;
							if (modal.style.display === 'block') {
								announcer.textContent = '<?php esc_html_e( 'TL;DR Summary modal opened', 'tldr-pro' ); ?>';
								// Focus first focusable element
								setTimeout(() => {
									const firstElement = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
									if (firstElement) firstElement.focus();
								}, 100);
							} else {
								announcer.textContent = '<?php esc_html_e( 'TL;DR Summary modal closed', 'tldr-pro' ); ?>';
								// Return focus to trigger button
								const triggerButton = document.querySelector('.tldr-pro-button[aria-expanded="true"]');
								if (triggerButton) {
									triggerButton.setAttribute('aria-expanded', 'false');
									triggerButton.focus();
								}
							}
						}
					});
				});
				
				const modal = document.getElementById('tldr-pro-modal');
				if (modal) {
					modalObserver.observe(modal, { attributes: true });
				}
			});
		})();
		</script>
		<?php
	}

	/**
	 * Add skip links for keyboard navigation.
	 */
	public static function add_skip_links() {
		?>
		<a href="#tldr-pro-summary" class="screen-reader-text tldr-pro-skip-link">
			<?php esc_html_e( 'Skip to TL;DR Summary', 'tldr-pro' ); ?>
		</a>
		<?php
	}

	/**
	 * Add focus indicator styles.
	 */
	public static function add_focus_styles() {
		?>
		<style>
		/* TL;DR Pro Accessibility Styles */
		.tldr-pro-button:focus-visible,
		.tldr-pro-floating-button:focus-visible,
		.tldr-pro-close:focus-visible,
		.tldr-pro-modal a:focus-visible,
		.tldr-pro-modal button:focus-visible {
			outline: 3px solid #005fcc !important;
			outline-offset: 2px !important;
		}
		
		/* Skip links */
		.tldr-pro-skip-link {
			position: absolute;
			left: -9999px;
			z-index: 999999;
		}
		
		.tldr-pro-skip-link:focus {
			position: fixed;
			top: 5px;
			left: 5px;
			z-index: 999999;
			padding: 10px 15px;
			background: #005fcc;
			color: white;
			text-decoration: none;
			border-radius: 3px;
		}
		
		/* Screen reader text */
		.screen-reader-text {
			clip: rect(1px, 1px, 1px, 1px);
			clip-path: inset(50%);
			height: 1px;
			margin: -1px;
			overflow: hidden;
			padding: 0;
			position: absolute;
			width: 1px;
			word-wrap: normal !important;
		}
		
		/* High contrast mode */
		@media (prefers-contrast: high) {
			.tldr-pro-button,
			.tldr-pro-modal {
				border: 2px solid currentColor !important;
			}
			
			.tldr-pro-button:hover {
				background: transparent !important;
				color: currentColor !important;
			}
		}
		
		/* Reduced motion */
		@media (prefers-reduced-motion: reduce) {
			.tldr-pro-button,
			.tldr-pro-modal,
			.tldr-pro-floating-button {
				animation: none !important;
				transition: none !important;
			}
		}
		
		/* Forced colors mode (Windows High Contrast) */
		@media (forced-colors: active) {
			.tldr-pro-button {
				border: 1px solid;
			}
			
			.tldr-pro-modal {
				border: 1px solid;
				background: Canvas;
				color: CanvasText;
			}
		}
		</style>
		<?php
	}

	/**
	 * Add RTL styles.
	 */
	public static function add_rtl_styles() {
		if ( ! is_rtl() ) {
			return;
		}
		
		wp_add_inline_style( 'tldr-pro-button', '
			/* RTL Support */
			.rtl .tldr-pro-button {
				direction: rtl;
				text-align: right;
			}
			
			.rtl .tldr-pro-button .tldr-pro-icon {
				margin-left: 8px;
				margin-right: 0;
			}
			
			.rtl .tldr-pro-modal-header {
				direction: rtl;
			}
			
			.rtl .tldr-pro-close {
				left: 10px;
				right: auto;
			}
			
			.rtl .tldr-pro-modal-content {
				direction: rtl;
				text-align: right;
			}
			
			.rtl .tldr-pro-modal-content ul,
			.rtl .tldr-pro-modal-content ol {
				padding-right: 2em;
				padding-left: 0;
			}
			
			.rtl .tldr-pro-floating-button.bottom-right {
				right: auto;
				left: 30px;
			}
			
			.rtl .tldr-pro-floating-button.top-right {
				right: auto;
				left: 30px;
			}
		' );
	}

	/**
	 * Add screen reader text to summary.
	 *
	 * @param string $content Summary content.
	 * @return string Modified content.
	 */
	public static function add_screen_reader_text( $content ) {
		$screen_reader_text = '<span class="screen-reader-text">' . 
		                     __( 'Beginning of TL;DR Summary', 'tldr-pro' ) . 
		                     '</span>';
		
		$screen_reader_end = '<span class="screen-reader-text">' . 
		                    __( 'End of TL;DR Summary', 'tldr-pro' ) . 
		                    '</span>';
		
		return $screen_reader_text . $content . $screen_reader_end;
	}

	/**
	 * Detect high contrast mode preference.
	 */
	public static function detect_high_contrast() {
		?>
		<script>
		// Detect high contrast mode
		if (window.matchMedia && window.matchMedia('(prefers-contrast: high)').matches) {
			document.documentElement.classList.add('tldr-pro-high-contrast');
		}
		</script>
		<?php
	}

	/**
	 * Detect reduced motion preference.
	 */
	public static function detect_reduced_motion() {
		?>
		<script>
		// Detect reduced motion preference
		if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
			document.documentElement.classList.add('tldr-pro-reduced-motion');
		}
		</script>
		<?php
	}

	/**
	 * Get accessibility settings.
	 *
	 * @return array Accessibility settings.
	 */
	public static function get_settings() {
		return array(
			'enable_keyboard_nav'   => get_option( 'tldr_pro_enable_keyboard_nav', true ),
			'enable_screen_reader'  => get_option( 'tldr_pro_enable_screen_reader', true ),
			'enable_focus_indicators' => get_option( 'tldr_pro_enable_focus_indicators', true ),
			'enable_skip_links'     => get_option( 'tldr_pro_enable_skip_links', true ),
			'enable_high_contrast'  => get_option( 'tldr_pro_enable_high_contrast', true ),
			'enable_reduced_motion' => get_option( 'tldr_pro_enable_reduced_motion', true ),
			'wcag_compliance_level' => get_option( 'tldr_pro_wcag_compliance', 'AA' )
		);
	}

	/**
	 * Run accessibility audit.
	 *
	 * @return array Audit results.
	 */
	public static function audit() {
		$results = array(
			'passed' => array(),
			'failed' => array(),
			'warnings' => array()
		);
		
		// Check for ARIA labels
		if ( get_option( 'tldr_pro_button_text' ) ) {
			$results['passed'][] = 'Button has accessible text';
		} else {
			$results['failed'][] = 'Button missing accessible text';
		}
		
		// Check color contrast
		$button_bg = get_option( 'tldr_pro_button_bg_color', '#667eea' );
		$button_text = get_option( 'tldr_pro_button_text_color', '#ffffff' );
		
		if ( self::check_color_contrast( $button_bg, $button_text ) >= 4.5 ) {
			$results['passed'][] = 'Button color contrast meets WCAG AA standards';
		} else {
			$results['warnings'][] = 'Button color contrast may not meet WCAG AA standards';
		}
		
		// Check for keyboard navigation
		if ( get_option( 'tldr_pro_enable_keyboard_nav', true ) ) {
			$results['passed'][] = 'Keyboard navigation enabled';
		} else {
			$results['warnings'][] = 'Keyboard navigation disabled';
		}
		
		return $results;
	}

	/**
	 * Calculate color contrast ratio.
	 *
	 * @param string $color1 First color (hex).
	 * @param string $color2 Second color (hex).
	 * @return float Contrast ratio.
	 */
	private static function check_color_contrast( $color1, $color2 ) {
		// Convert hex to RGB
		$rgb1 = self::hex_to_rgb( $color1 );
		$rgb2 = self::hex_to_rgb( $color2 );
		
		// Calculate relative luminance
		$l1 = self::get_luminance( $rgb1 );
		$l2 = self::get_luminance( $rgb2 );
		
		// Calculate contrast ratio
		$lighter = max( $l1, $l2 );
		$darker = min( $l1, $l2 );
		
		return ( $lighter + 0.05 ) / ( $darker + 0.05 );
	}

	/**
	 * Convert hex to RGB.
	 *
	 * @param string $hex Hex color.
	 * @return array RGB values.
	 */
	private static function hex_to_rgb( $hex ) {
		$hex = ltrim( $hex, '#' );
		
		return array(
			'r' => hexdec( substr( $hex, 0, 2 ) ),
			'g' => hexdec( substr( $hex, 2, 2 ) ),
			'b' => hexdec( substr( $hex, 4, 2 ) )
		);
	}

	/**
	 * Get relative luminance.
	 *
	 * @param array $rgb RGB values.
	 * @return float Luminance.
	 */
	private static function get_luminance( $rgb ) {
		$rgb = array_map( function( $val ) {
			$val = $val / 255;
			return $val <= 0.03928 ? $val / 12.92 : pow( ( $val + 0.055 ) / 1.055, 2.4 );
		}, $rgb );
		
		return 0.2126 * $rgb['r'] + 0.7152 * $rgb['g'] + 0.0722 * $rgb['b'];
	}
}

// Initialize accessibility features
add_action( 'init', array( 'TLDR_Pro_Accessibility', 'init' ) );