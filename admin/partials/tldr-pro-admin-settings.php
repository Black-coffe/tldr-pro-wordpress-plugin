<?php
/**
 * Settings page template with AI provider configuration
 *
 * @package TLDR_Pro
 * @subpackage TLDR_Pro/admin/partials
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get AI Manager instance
$ai_manager = TLDR_Pro_AI_Manager::get_instance();
$provider_config = $ai_manager->get_provider_config();

// Use Settings Manager for getting values with defaults
if ( ! class_exists( 'TLDR_Pro_Settings_Manager' ) ) {
	require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/class-tldr-pro-settings-manager.php';
}

$active_provider = TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_active_provider' );
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<?php settings_errors(); ?>
	
	<div class="tldr-pro-admin-wrapper">
		<div class="tldr-pro-admin-content">
			<div class="nav-tab-wrapper">
				<a href="#general" class="nav-tab nav-tab-active" data-tab="general">
					<?php _e( 'General Settings', 'tldr-pro' ); ?>
				</a>
				<a href="#ai-providers" class="nav-tab" data-tab="ai-providers">
					<?php _e( 'AI Providers', 'tldr-pro' ); ?>
				</a>
				<a href="#display" class="nav-tab" data-tab="display">
					<?php _e( 'Display Settings', 'tldr-pro' ); ?>
				</a>
				<a href="#prompts" class="nav-tab" data-tab="prompts">
					<?php _e( 'Prompt Templates', 'tldr-pro' ); ?>
				</a>
				<a href="#advanced" class="nav-tab" data-tab="advanced">
					<?php _e( 'Advanced', 'tldr-pro' ); ?>
				</a>
			</div>

			<form method="post" action="options.php" id="tldr-pro-settings-form">
				<?php 
				settings_fields( 'tldr_pro_settings' );
				// do_settings_sections is needed for WordPress settings API to work properly
				// but we'll handle sections manually for tabbed interface
				?>
				
				<!-- General Settings Tab -->
				<div id="general" class="tab-content active">
					<h2><?php _e( 'General Settings', 'tldr-pro' ); ?></h2>
					
					<!-- AI Provider Experimentation Tip -->
					<div class="notice notice-info inline" style="margin: 20px 0;">
						<p>
							<strong><?php _e( '💡 Pro Tip:', 'tldr-pro' ); ?></strong>
							<?php _e( 'We highly recommend testing and experimenting with different AI providers and their models. Each provider (DeepSeek, Gemini, Claude, GPT) will generate unique summaries even with the same prompt. Try different combinations to find the best results for your content style and audience preferences. Some models excel at technical content, while others are better at creative or conversational summaries.', 'tldr-pro' ); ?>
						</p>
					</div>
					
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="tldr_pro_active_provider">
									<?php _e( 'Active AI Provider', 'tldr-pro' ); ?>
								</label>
							</th>
							<td>
								<select name="tldr_pro_active_provider" id="tldr_pro_active_provider">
									<?php foreach ( $provider_config as $key => $config ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $active_provider, $key ); ?>>
											<?php echo esc_html( $config['name'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php _e( 'Select the AI provider to use for generating summaries.', 'tldr-pro' ); ?>
								</p>
							</td>
						</tr>
						
						<tr>
							<th scope="row">
								<label for="tldr_pro_enable_fallback">
									<?php _e( 'Enable Fallback', 'tldr-pro' ); ?>
								</label>
							</th>
							<td>
								<label>
									<input type="checkbox" name="tldr_pro_enable_fallback" id="tldr_pro_enable_fallback" 
										   value="1" <?php checked( TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_enable_fallback' ) ); ?> />
									<?php _e( 'Use fallback providers if primary fails', 'tldr-pro' ); ?>
								</label>
							</td>
						</tr>
						
						<tr>
							<th scope="row">
								<label for="tldr_pro_max_summary_length">
									<?php _e( 'Max Summary Length', 'tldr-pro' ); ?>
								</label>
							</th>
							<td>
								<input type="number" name="tldr_pro_max_summary_length" id="tldr_pro_max_summary_length"
									   value="<?php echo esc_attr( TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_max_summary_length' ) ); ?>"
									   min="50" max="500" />
								<span><?php _e( 'words', 'tldr-pro' ); ?></span>
							</td>
						</tr>
						
						<tr>
							<th scope="row">
								<label for="tldr_pro_summary_style">
									<?php _e( 'Summary Style', 'tldr-pro' ); ?>
								</label>
							</th>
							<td>
								<select name="tldr_pro_summary_style" id="tldr_pro_summary_style">
									<option value="professional" <?php selected( TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_summary_style' ), 'professional' ); ?>>
										<?php _e( 'Professional', 'tldr-pro' ); ?>
									</option>
									<option value="casual" <?php selected( TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_summary_style' ), 'casual' ); ?>>
										<?php _e( 'Casual', 'tldr-pro' ); ?>
									</option>
									<option value="academic" <?php selected( TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_summary_style' ), 'academic' ); ?>>
										<?php _e( 'Academic', 'tldr-pro' ); ?>
									</option>
									<option value="creative" <?php selected( TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_summary_style' ), 'creative' ); ?>>
										<?php _e( 'Creative', 'tldr-pro' ); ?>
									</option>
								</select>
							</td>
						</tr>
						
						<tr>
							<th scope="row">
								<label for="tldr_pro_summary_language">
									<?php _e( 'Summary Language', 'tldr-pro' ); ?>
									<span class="required">*</span>
								</label>
							</th>
							<td>
								<select name="tldr_pro_summary_language" id="tldr_pro_summary_language" class="tldr-pro-select2">
									<?php
									// Top 50 languages with flags - alphabetically sorted
									$languages = array(
										'ar' => array('name' => 'Arabic (العربية)', 'flag' => '🇸🇦'),
										'bn' => array('name' => 'Bengali (বাংলা)', 'flag' => '🇧🇩'),
										'bg' => array('name' => 'Bulgarian (Български)', 'flag' => '🇧🇬'),
										'my' => array('name' => 'Burmese (မြန်မာ)', 'flag' => '🇲🇲'),
										'zh' => array('name' => 'Chinese (中文)', 'flag' => '🇨🇳'),
										'hr' => array('name' => 'Croatian (Hrvatski)', 'flag' => '🇭🇷'),
										'cs' => array('name' => 'Czech (Čeština)', 'flag' => '🇨🇿'),
										'da' => array('name' => 'Danish (Dansk)', 'flag' => '🇩🇰'),
										'nl' => array('name' => 'Dutch (Nederlands)', 'flag' => '🇳🇱'),
										'en' => array('name' => 'English', 'flag' => '🇬🇧'),
										'et' => array('name' => 'Estonian (Eesti)', 'flag' => '🇪🇪'),
										'tl' => array('name' => 'Filipino (Tagalog)', 'flag' => '🇵🇭'),
										'fi' => array('name' => 'Finnish (Suomi)', 'flag' => '🇫🇮'),
										'fr' => array('name' => 'French (Français)', 'flag' => '🇫🇷'),
										'de' => array('name' => 'German (Deutsch)', 'flag' => '🇩🇪'),
										'el' => array('name' => 'Greek (Ελληνικά)', 'flag' => '🇬🇷'),
										'gu' => array('name' => 'Gujarati (ગુજરાતી)', 'flag' => '🇮🇳'),
										'he' => array('name' => 'Hebrew (עברית)', 'flag' => '🇮🇱'),
										'hi' => array('name' => 'Hindi (हिन्दी)', 'flag' => '🇮🇳'),
										'hu' => array('name' => 'Hungarian (Magyar)', 'flag' => '🇭🇺'),
										'id' => array('name' => 'Indonesian (Bahasa Indonesia)', 'flag' => '🇮🇩'),
										'it' => array('name' => 'Italian (Italiano)', 'flag' => '🇮🇹'),
										'ja' => array('name' => 'Japanese (日本語)', 'flag' => '🇯🇵'),
										'jv' => array('name' => 'Javanese', 'flag' => '🇮🇩'),
										'kn' => array('name' => 'Kannada (ಕನ್ನಡ)', 'flag' => '🇮🇳'),
										'ko' => array('name' => 'Korean (한국어)', 'flag' => '🇰🇷'),
										'ms' => array('name' => 'Malay (Bahasa Melayu)', 'flag' => '🇲🇾'),
										'ml' => array('name' => 'Malayalam (മലയാളം)', 'flag' => '🇮🇳'),
										'mr' => array('name' => 'Marathi (मराठी)', 'flag' => '🇮🇳'),
										'ne' => array('name' => 'Nepali (नेपाली)', 'flag' => '🇳🇵'),
										'no' => array('name' => 'Norwegian (Norsk)', 'flag' => '🇳🇴'),
										'or' => array('name' => 'Oriya (ଓଡ଼ିଆ)', 'flag' => '🇮🇳'),
										'fa' => array('name' => 'Persian (فارسی)', 'flag' => '🇮🇷'),
										'pl' => array('name' => 'Polish (Polski)', 'flag' => '🇵🇱'),
										'pt' => array('name' => 'Portuguese (Português)', 'flag' => '🇵🇹'),
										'pa' => array('name' => 'Punjabi (ਪੰਜਾਬੀ)', 'flag' => '🇮🇳'),
										'ro' => array('name' => 'Romanian (Română)', 'flag' => '🇷🇴'),
										'ru' => array('name' => 'Russian (Русский)', 'flag' => '🇷🇺'),
										'sr' => array('name' => 'Serbian (Српски)', 'flag' => '🇷🇸'),
										'si' => array('name' => 'Sinhala (සිංහල)', 'flag' => '🇱🇰'),
										'sk' => array('name' => 'Slovak (Slovenčina)', 'flag' => '🇸🇰'),
										'es' => array('name' => 'Spanish (Español)', 'flag' => '🇪🇸'),
										'sv' => array('name' => 'Swedish (Svenska)', 'flag' => '🇸🇪'),
										'ta' => array('name' => 'Tamil (தமிழ்)', 'flag' => '🇮🇳'),
										'te' => array('name' => 'Telugu (తెలుగు)', 'flag' => '🇮🇳'),
										'th' => array('name' => 'Thai (ไทย)', 'flag' => '🇹🇭'),
										'tr' => array('name' => 'Turkish (Türkçe)', 'flag' => '🇹🇷'),
										'uk' => array('name' => 'Ukrainian (Українська)', 'flag' => '🇺🇦'),
										'ur' => array('name' => 'Urdu (اردو)', 'flag' => '🇵🇰'),
										'vi' => array('name' => 'Vietnamese (Tiếng Việt)', 'flag' => '🇻🇳'),
									);
									
									$current_language = TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_summary_language' );
									foreach ( $languages as $code => $lang ) :
									?>
										<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $current_language, $code ); ?>>
											<?php echo esc_html( $lang['flag'] . ' ' . $lang['name'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php _e( 'Language for all generated summaries', 'tldr-pro' ); ?>
								</p>
							</td>
						</tr>
						
						<tr>
							<th scope="row">
								<label for="tldr_pro_use_emojis">
									<?php _e( 'Use Emojis', 'tldr-pro' ); ?>
								</label>
							</th>
							<td>
								<label>
									<input type="checkbox" name="tldr_pro_use_emojis" id="tldr_pro_use_emojis"
										   value="1" <?php checked( TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_use_emojis' ) ); ?> />
									<?php _e( 'Include emojis in summaries', 'tldr-pro' ); ?>
								</label>
							</td>
						</tr>
					</table>
				</div>
				
				<!-- AI Providers Tab -->
				<div id="ai-providers" class="tab-content">
					<h2><?php _e( 'AI Provider Configuration', 'tldr-pro' ); ?></h2>
					
					<?php 
					$encryption = null;
					$has_encryption = class_exists( 'TLDR_Pro_Encryption' );
					if ( $has_encryption ) {
						$encryption = TLDR_Pro_Encryption::get_instance();
					}
					
					foreach ( $provider_config as $provider_key => $config ) : 
						$validation_status = $has_encryption ? $encryption->get_validation_status( $provider_key ) : array(
							'is_validated' => false,
							'validated_at' => '',
							'is_expired' => false,
							'has_key' => ! empty( get_option( 'tldr_pro_' . $provider_key . '_api_key_encrypted' ) ) || ! empty( get_option( 'tldr_pro_' . $provider_key . '_api_key' ) ),
							'last_test' => array(),
						);
					?>
						<div class="provider-settings tldr-pro-provider-section" data-provider="<?php echo esc_attr( $provider_key ); ?>">
							<div class="provider-header">
								<h3><?php echo esc_html( $config['name'] ); ?></h3>
								<?php if ( $validation_status['is_validated'] ) : ?>
									<span class="tldr-pro-validation-badge validated">
										✅ <?php _e( 'Validated', 'tldr-pro' ); ?>
										<span class="validation-date">
											<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $validation_status['validated_at'] ) ) ); ?>
										</span>
									</span>
								<?php elseif ( $validation_status['has_key'] && $validation_status['is_expired'] ) : ?>
									<span class="tldr-pro-validation-badge expired">
										⚠️ <?php _e( 'Validation Expired', 'tldr-pro' ); ?>
									</span>
								<?php elseif ( $validation_status['has_key'] ) : ?>
									<span class="tldr-pro-validation-badge not-validated">
										❌ <?php _e( 'Not Validated', 'tldr-pro' ); ?>
									</span>
								<?php else : ?>
									<span class="tldr-pro-validation-badge no-key">
										🔑 <?php _e( 'No API Key', 'tldr-pro' ); ?>
									</span>
								<?php endif; ?>
							</div>
							
							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="tldr_pro_<?php echo $provider_key; ?>_api_key">
											<?php _e( 'API Key', 'tldr-pro' ); ?>
										</label>
									</th>
									<td>
										<?php 
										$masked_key = $has_encryption ? $encryption->get_masked_api_key( $provider_key ) : '';
										if ( ! $has_encryption ) {
											// Check encrypted first, then plain
											$encrypted_key = get_option( 'tldr_pro_' . $provider_key . '_api_key_encrypted' );
											$plain_key = get_option( 'tldr_pro_' . $provider_key . '_api_key' );
											$key_to_mask = $encrypted_key ? $encrypted_key : $plain_key;
											if ( ! empty( $key_to_mask ) ) {
												$masked_key = substr( $key_to_mask, 0, 3 ) . str_repeat( '•', min( strlen( $key_to_mask ) - 7, 20 ) ) . substr( $key_to_mask, -4 );
											}
										}
										?>
										<input type="password" 
											   name="tldr_pro_<?php echo $provider_key; ?>_api_key" 
											   id="tldr_pro_<?php echo $provider_key; ?>_api_key"
											   placeholder="<?php echo ! empty( $masked_key ) ? esc_attr( $masked_key ) : 'Enter API key'; ?>"
											   class="regular-text tldr-pro-api-key" />
										<button type="button" class="button tldr-pro-toggle-key" data-provider="<?php echo esc_attr( $provider_key ); ?>">
											👁️
										</button>
										<button type="button" class="button tldr-pro-test-api" 
												data-provider="<?php echo esc_attr( $provider_key ); ?>">
											<?php _e( 'Test Connection', 'tldr-pro' ); ?>
										</button>
										<div class="tldr-pro-test-results" style="margin-top: 15px;"></div>
									</td>
								</tr>
								
								<tr>
									<th scope="row">
										<label for="tldr_pro_<?php echo $provider_key; ?>_model">
											<?php _e( 'Model', 'tldr-pro' ); ?>
										</label>
									</th>
									<td>
										<select name="tldr_pro_<?php echo $provider_key; ?>_model" 
												id="tldr_pro_<?php echo $provider_key; ?>_model">
											<?php 
											$models = $ai_manager->get_provider_models( $provider_key );
											$selected_model = get_option( 'tldr_pro_' . $provider_key . '_model', $config['default_model'] );
											
											if ( ! empty( $models ) && is_array( $models ) ) :
												foreach ( $models as $model_key => $model_info ) :
													if ( ! is_array( $model_info ) ) continue;
											?>
												<option value="<?php echo esc_attr( $model_key ); ?>" 
														<?php selected( $selected_model, $model_key ); ?>
														data-cost-input="<?php echo esc_attr( ( $model_info['cost_per_1k_input'] ?? 0 ) * 1000 ); ?>"
														data-cost-output="<?php echo esc_attr( ( $model_info['cost_per_1k_output'] ?? 0 ) * 1000 ); ?>">
													<?php echo esc_html( $model_info['name'] ); ?>
													<?php if ( ! empty( $model_info['recommended'] ) ) : ?>
														(<?php _e( 'Recommended', 'tldr-pro' ); ?>)
													<?php endif; ?>
												</option>
											<?php 
												endforeach;
											else :
											?>
												<option value="<?php echo esc_attr( $config['default_model'] ); ?>">
													<?php echo esc_html( $config['default_model'] ); ?>
												</option>
											<?php endif; ?>
										</select>
										<p class="description model-pricing"></p>
									</td>
								</tr>
							</table>
						</div>
					<?php endforeach; ?>
				</div>
				
				<!-- Display Settings Tab -->
				<div id="display" class="tab-content">
					<h2><?php _e( 'Display Settings', 'tldr-pro' ); ?></h2>
					
					<div class="notice notice-info inline" style="margin: 20px 0;">
						<p>
							<?php _e( 'Customize how the TL;DR summary button appears on your frontend pages. These settings control the button text, styling, positioning and behavior.', 'tldr-pro' ); ?>
						</p>
					</div>
					
					<!-- Hidden fields to preserve other display settings when saving this tab -->
					<input type="hidden" name="tldr_pro_auto_display" value="<?php echo esc_attr( get_option( 'tldr_pro_auto_display', '1' ) ); ?>" />
					<input type="hidden" name="tldr_pro_enabled_post_types[]" value="post" />
					<input type="hidden" name="tldr_pro_enabled_post_types[]" value="page" />
					
					<!-- Accordion Sections -->
					<div class="tldr-accordion-container">
						
						<!-- Button Text Section -->
						<div class="tldr-accordion-section">
							<div class="tldr-accordion-header active" data-section="button-text">
								<span class="dashicons dashicons-text"></span>
								<h3><?php _e( 'Button Text', 'tldr-pro' ); ?></h3>
								<span class="tldr-accordion-toggle dashicons dashicons-arrow-down"></span>
							</div>
							<div class="tldr-accordion-content" id="section-button-text" style="display: block;">
								<table class="form-table">
									<tr>
										<th scope="row">
											<label for="tldr_pro_button_text">
												<?php _e( 'Button Text', 'tldr-pro' ); ?>
											</label>
										</th>
										<td>
											<input type="text" 
												   name="tldr_pro_button_text" 
												   id="tldr_pro_button_text" 
												   value="<?php echo esc_attr( get_option( 'tldr_pro_button_text', 'Show TL;DR' ) ); ?>"
												   class="regular-text" 
												   placeholder="Show TL;DR" />
											<p class="description">
												<?php _e( 'The text that appears on the summary button after the icon. Default: "Show TL;DR"', 'tldr-pro' ); ?>
											</p>
											<div id="button-preview" style="margin-top: 15px;">
												<strong><?php _e( 'Preview:', 'tldr-pro' ); ?></strong>
												<div style="margin-top: 10px;">
													<button type="button" class="button" style="display: inline-flex; align-items: center; gap: 8px;" disabled>
														<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
															<path d="M9 2H15C16.1046 2 17 2.89543 17 4V20C17 21.1046 16.1046 22 15 22H9C7.89543 22 7 21.1046 7 20V4C7 2.89543 7.89543 2 9 2Z" stroke="currentColor" stroke-width="2"/>
															<path d="M10 6H14M10 10H14M10 14H12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
														</svg>
														<span id="button-text-preview"><?php echo esc_html( get_option( 'tldr_pro_button_text', 'Show TL;DR' ) ); ?></span>
													</button>
												</div>
											</div>
										</td>
									</tr>
								</table>
							</div>
						</div>
						
						<!-- Button Color Style Section -->
						<div class="tldr-accordion-section">
							<div class="tldr-accordion-header" data-section="button-color">
								<span class="dashicons dashicons-art"></span>
								<h3><?php _e( 'Button Color Style', 'tldr-pro' ); ?></h3>
								<span class="tldr-accordion-toggle dashicons dashicons-arrow-down"></span>
							</div>
							<div class="tldr-accordion-content" id="section-button-color" style="display: none;">
								<table class="form-table">
									<tr>
										<th scope="row">
											<label for="tldr_pro_button_style">
												<?php _e( 'Color Style', 'tldr-pro' ); ?>
											</label>
										</th>
										<td>
								<div class="tldr-color-palette-wrapper">
									<!-- Preset Color Styles -->
									<div class="tldr-color-presets">
										<label class="tldr-color-preset">
											<input type="radio" name="tldr_pro_button_style" value="default" 
												   <?php checked( get_option( 'tldr_pro_button_style', 'default' ), 'default' ); ?> />
											<span class="tldr-preset-preview" data-style="default">
												<span class="preset-button">Default Blue</span>
											</span>
										</label>
										
										<label class="tldr-color-preset">
											<input type="radio" name="tldr_pro_button_style" value="gradient-blue" 
												   <?php checked( get_option( 'tldr_pro_button_style' ), 'gradient-blue' ); ?> />
											<span class="tldr-preset-preview" data-style="gradient-blue">
												<span class="preset-button">Blue Gradient</span>
											</span>
										</label>
										
										<label class="tldr-color-preset">
											<input type="radio" name="tldr_pro_button_style" value="gradient-purple" 
												   <?php checked( get_option( 'tldr_pro_button_style' ), 'gradient-purple' ); ?> />
											<span class="tldr-preset-preview" data-style="gradient-purple">
												<span class="preset-button">Purple Gradient</span>
											</span>
										</label>
										
										<label class="tldr-color-preset">
											<input type="radio" name="tldr_pro_button_style" value="gradient-sunset" 
												   <?php checked( get_option( 'tldr_pro_button_style' ), 'gradient-sunset' ); ?> />
											<span class="tldr-preset-preview" data-style="gradient-sunset">
												<span class="preset-button">Sunset Gradient</span>
											</span>
										</label>
										
										<label class="tldr-color-preset">
											<input type="radio" name="tldr_pro_button_style" value="gradient-ocean" 
												   <?php checked( get_option( 'tldr_pro_button_style' ), 'gradient-ocean' ); ?> />
											<span class="tldr-preset-preview" data-style="gradient-ocean">
												<span class="preset-button">Ocean Gradient</span>
											</span>
										</label>
										
										<label class="tldr-color-preset">
											<input type="radio" name="tldr_pro_button_style" value="dark" 
												   <?php checked( get_option( 'tldr_pro_button_style' ), 'dark' ); ?> />
											<span class="tldr-preset-preview" data-style="dark">
												<span class="preset-button">Dark Mode</span>
											</span>
										</label>
										
										<label class="tldr-color-preset">
											<input type="radio" name="tldr_pro_button_style" value="light" 
												   <?php checked( get_option( 'tldr_pro_button_style' ), 'light' ); ?> />
											<span class="tldr-preset-preview" data-style="light">
												<span class="preset-button">Light Mode</span>
											</span>
										</label>
										
										<label class="tldr-color-preset">
											<input type="radio" name="tldr_pro_button_style" value="minimal-dark" 
												   <?php checked( get_option( 'tldr_pro_button_style' ), 'minimal-dark' ); ?> />
											<span class="tldr-preset-preview" data-style="minimal-dark">
												<span class="preset-button">Minimal Dark</span>
											</span>
										</label>
										
										<label class="tldr-color-preset">
											<input type="radio" name="tldr_pro_button_style" value="minimal-light" 
												   <?php checked( get_option( 'tldr_pro_button_style' ), 'minimal-light' ); ?> />
											<span class="tldr-preset-preview" data-style="minimal-light">
												<span class="preset-button">Minimal Light</span>
											</span>
										</label>
										
										<label class="tldr-color-preset">
											<input type="radio" name="tldr_pro_button_style" value="glassmorphism" 
												   <?php checked( get_option( 'tldr_pro_button_style' ), 'glassmorphism' ); ?> />
											<span class="tldr-preset-preview" data-style="glassmorphism">
												<span class="preset-button">Glassmorphism</span>
											</span>
										</label>
										
										<label class="tldr-color-preset">
											<input type="radio" name="tldr_pro_button_style" value="custom" 
												   <?php checked( get_option( 'tldr_pro_button_style' ), 'custom' ); ?> />
											<span class="tldr-preset-preview" data-style="custom">
												<span class="preset-button">Custom Colors</span>
											</span>
										</label>
									</div>
									
									<!-- Custom Color Settings -->
									<div id="custom-color-settings" style="<?php echo get_option( 'tldr_pro_button_style' ) === 'custom' ? '' : 'display: none;'; ?>margin-top: 20px;">
										<table>
											<tr>
												<td style="padding-right: 20px;">
													<label for="tldr_pro_button_bg_color">
														<?php _e( 'Background Color:', 'tldr-pro' ); ?>
													</label>
													<input type="color" 
														   name="tldr_pro_button_bg_color" 
														   id="tldr_pro_button_bg_color" 
														   value="<?php echo esc_attr( get_option( 'tldr_pro_button_bg_color', '#0073aa' ) ); ?>" />
												</td>
												<td>
													<label for="tldr_pro_button_text_color">
														<?php _e( 'Text Color:', 'tldr-pro' ); ?>
													</label>
													<input type="color" 
														   name="tldr_pro_button_text_color" 
														   id="tldr_pro_button_text_color" 
														   value="<?php echo esc_attr( get_option( 'tldr_pro_button_text_color', '#ffffff' ) ); ?>" />
												</td>
											</tr>
										</table>
									</div>
									
									<p class="description" style="margin-top: 15px;">
										<?php _e( 'Choose a preset color style or create your own custom colors for the TL;DR button.', 'tldr-pro' ); ?>
									</p>
								</div>
								
								<!-- Enhanced Preview -->
								<div id="button-preview-enhanced" style="margin-top: 20px; padding: 20px; background: #f5f5f5; border-radius: 5px;">
									<strong><?php _e( 'Live Preview:', 'tldr-pro' ); ?></strong>
									<div style="margin-top: 15px; padding: 20px; background: white; border-radius: 3px;">
										<button type="button" id="tldr-preview-button" class="tldr-pro-button-preview" disabled>
											<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
												<path d="M9 2H15C16.1046 2 17 2.89543 17 4V20C17 21.1046 16.1046 22 15 22H9C7.89543 22 7 21.1046 7 20V4C7 2.89543 7.89543 2 9 2Z" stroke="currentColor" stroke-width="2"/>
												<path d="M10 6H14M10 10H14M10 14H12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
											</svg>
											<span id="button-text-preview-enhanced"><?php echo esc_html( get_option( 'tldr_pro_button_text', 'Show TL;DR' ) ); ?></span>
										</button>
									</div>
									<div style="margin-top: 15px; padding: 20px; background: #2c3e50; border-radius: 3px;">
										<button type="button" id="tldr-preview-button-dark" class="tldr-pro-button-preview" disabled>
											<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
												<path d="M9 2H15C16.1046 2 17 2.89543 17 4V20C17 21.1046 16.1046 22 15 22H9C7.89543 22 7 21.1046 7 20V4C7 2.89543 7.89543 2 9 2Z" stroke="currentColor" stroke-width="2"/>
												<path d="M10 6H14M10 10H14M10 14H12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
											</svg>
											<span id="button-text-preview-dark"><?php echo esc_html( get_option( 'tldr_pro_button_text', 'Show TL;DR' ) ); ?></span>
										</button>
									</div>
								</div>
							</td>
						</tr>
					</table>
				</div>
			</div>
			
			<!-- Button Position Section -->
			<div class="tldr-accordion-section">
				<div class="tldr-accordion-header" data-section="button-position">
					<span class="dashicons dashicons-move"></span>
					<h3><?php _e( 'Button Position', 'tldr-pro' ); ?></h3>
					<span class="tldr-accordion-toggle dashicons dashicons-arrow-down"></span>
				</div>
				<div class="tldr-accordion-content" id="section-button-position" style="display: none;">
					<table class="form-table">
						<!-- Position for Posts -->
						<tr>
							<th scope="row">
								<label for="tldr_pro_button_position_post">
									<?php _e( 'Position for Posts', 'tldr-pro' ); ?>
								</label>
							</th>
							<td>
								<select name="tldr_pro_button_position_post" id="tldr_pro_button_position_post" class="position-selector">
									<option value="after_title" <?php selected( get_option( 'tldr_pro_button_position_post', 'after_title' ), 'after_title' ); ?>>
										<?php _e( 'After Post Title', 'tldr-pro' ); ?>
									</option>
									<option value="before_content" <?php selected( get_option( 'tldr_pro_button_position_post' ), 'before_content' ); ?>>
										<?php _e( 'Before Content', 'tldr-pro' ); ?>
									</option>
									<option value="after_content" <?php selected( get_option( 'tldr_pro_button_position_post' ), 'after_content' ); ?>>
										<?php _e( 'After Content', 'tldr-pro' ); ?>
									</option>
									<option value="fixed_bottom_left" <?php selected( get_option( 'tldr_pro_button_position_post' ), 'fixed_bottom_left' ); ?>>
										<?php _e( 'Fixed Bottom Left', 'tldr-pro' ); ?>
									</option>
									<option value="fixed_bottom_right" <?php selected( get_option( 'tldr_pro_button_position_post' ), 'fixed_bottom_right' ); ?>>
										<?php _e( 'Fixed Bottom Right', 'tldr-pro' ); ?>
									</option>
									<option value="fixed_bottom_center" <?php selected( get_option( 'tldr_pro_button_position_post' ), 'fixed_bottom_center' ); ?>>
										<?php _e( 'Fixed Bottom Center (Icon)', 'tldr-pro' ); ?>
									</option>
									<option value="floating_bar" <?php selected( get_option( 'tldr_pro_button_position_post' ), 'floating_bar' ); ?>>
										<?php _e( 'Floating Bar (Top)', 'tldr-pro' ); ?>
									</option>
									<option value="sidebar_sticky" <?php selected( get_option( 'tldr_pro_button_position_post' ), 'sidebar_sticky' ); ?>>
										<?php _e( 'Sticky in Sidebar', 'tldr-pro' ); ?>
									</option>
								</select>
								<p class="description">
									<?php _e( 'Choose where to display the TL;DR button on blog posts.', 'tldr-pro' ); ?>
								</p>
							</td>
						</tr>
						
						<!-- Position for Pages -->
						<tr>
							<th scope="row">
								<label for="tldr_pro_button_position_page">
									<?php _e( 'Position for Pages', 'tldr-pro' ); ?>
								</label>
							</th>
							<td>
								<select name="tldr_pro_button_position_page" id="tldr_pro_button_position_page" class="position-selector">
									<option value="after_title_elegant" <?php selected( get_option( 'tldr_pro_button_position_page', 'after_title_elegant' ), 'after_title_elegant' ); ?>>
										<?php _e( 'After Title (Elegant)', 'tldr-pro' ); ?>
									</option>
									<option value="before_content" <?php selected( get_option( 'tldr_pro_button_position_page' ), 'before_content' ); ?>>
										<?php _e( 'Before Content', 'tldr-pro' ); ?>
									</option>
									<option value="after_content" <?php selected( get_option( 'tldr_pro_button_position_page' ), 'after_content' ); ?>>
										<?php _e( 'After Content', 'tldr-pro' ); ?>
									</option>
									<option value="fixed_bottom_left" <?php selected( get_option( 'tldr_pro_button_position_page' ), 'fixed_bottom_left' ); ?>>
										<?php _e( 'Fixed Bottom Left', 'tldr-pro' ); ?>
									</option>
									<option value="fixed_bottom_right" <?php selected( get_option( 'tldr_pro_button_position_page' ), 'fixed_bottom_right' ); ?>>
										<?php _e( 'Fixed Bottom Right', 'tldr-pro' ); ?>
									</option>
									<option value="fixed_bottom_center" <?php selected( get_option( 'tldr_pro_button_position_page' ), 'fixed_bottom_center' ); ?>>
										<?php _e( 'Fixed Bottom Center (Icon)', 'tldr-pro' ); ?>
									</option>
									<option value="floating_bar" <?php selected( get_option( 'tldr_pro_button_position_page' ), 'floating_bar' ); ?>>
										<?php _e( 'Floating Bar (Top)', 'tldr-pro' ); ?>
									</option>
									<option value="hero_section" <?php selected( get_option( 'tldr_pro_button_position_page' ), 'hero_section' ); ?>>
										<?php _e( 'Hero Section Integration', 'tldr-pro' ); ?>
									</option>
								</select>
								<p class="description">
									<?php _e( 'Choose where to display the TL;DR button on static pages.', 'tldr-pro' ); ?>
								</p>
							</td>
						</tr>
						
						<!-- Position Preview -->
						<tr>
							<th scope="row">
								<?php _e( 'Position Preview', 'tldr-pro' ); ?>
							</th>
							<td>
								<div class="position-preview-container" style="position: relative; height: 300px; background: #f0f0f0; border: 2px solid #ddd; border-radius: 5px; overflow: hidden;">
									<div class="position-preview-header" style="background: #fff; padding: 15px; border-bottom: 1px solid #ddd;">
										<div style="font-size: 18px; font-weight: bold; margin-bottom: 5px;"><?php _e( 'Sample Post Title', 'tldr-pro' ); ?></div>
										<div style="font-size: 12px; color: #666;"><?php _e( 'Published on January 1, 2025', 'tldr-pro' ); ?></div>
									</div>
									<div class="position-preview-content" style="padding: 15px; background: #fff; margin: 10px; height: 150px; overflow: hidden;">
										<p style="color: #666; line-height: 1.6;"><?php _e( 'This is sample content to demonstrate button positioning...', 'tldr-pro' ); ?></p>
									</div>
									<div id="position-preview-button" class="tldr-pro-button-preview" style="position: absolute;">
										<button type="button" disabled style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; background: #0073aa; color: white; border: none; border-radius: 4px;">
											<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
												<path d="M9 2H15C16.1046 2 17 2.89543 17 4V20C17 21.1046 16.1046 22 15 22H9C7.89543 22 7 21.1046 7 20V4C7 2.89543 7.89543 2 9 2Z" stroke="currentColor" stroke-width="2"/>
												<path d="M10 6H14M10 10H14M10 14H12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
											</svg>
											<span><?php echo esc_html( get_option( 'tldr_pro_button_text', 'Show TL;DR' ) ); ?></span>
										</button>
									</div>
								</div>
							</td>
						</tr>
					</table>
				</div>
			</div>
			
			<!-- Reset Settings -->
			<div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px;">
				<h3><?php _e( 'Reset Settings', 'tldr-pro' ); ?></h3>
				<p><?php _e( 'Reset all display settings to their default values.', 'tldr-pro' ); ?></p>
				<button type="button" id="reset-button-settings" class="button button-secondary">
					<?php _e( 'Reset to Defaults', 'tldr-pro' ); ?>
				</button>
			</div>
			
		</div> <!-- End accordion container -->
					
					<style>
					/* Accordion Styles */
					.tldr-accordion-container {
						margin-top: 20px;
					}
					
					.tldr-accordion-section {
						margin-bottom: 15px;
						background: #fff;
						border: 1px solid #ccd0d4;
						border-radius: 4px;
						overflow: hidden;
					}
					
					.tldr-accordion-header {
						display: flex;
						align-items: center;
						padding: 15px;
						background: #f7f7f7;
						cursor: pointer;
						user-select: none;
						transition: background 0.3s ease;
					}
					
					.tldr-accordion-header:hover {
						background: #f0f0f0;
					}
					
					.tldr-accordion-header.active {
						background: #e8e8e8;
						border-bottom: 1px solid #ccd0d4;
					}
					
					.tldr-accordion-header .dashicons:first-child {
						margin-right: 10px;
						color: #555;
					}
					
					.tldr-accordion-header h3 {
						margin: 0;
						flex-grow: 1;
						font-size: 14px;
						font-weight: 600;
					}
					
					.tldr-accordion-toggle {
						transition: transform 0.3s ease;
					}
					
					.tldr-accordion-header.active .tldr-accordion-toggle {
						transform: rotate(180deg);
					}
					
					.tldr-accordion-content {
						padding: 20px;
						background: #fff;
					}
					
					/* Position Preview Styles */
					.position-preview-container {
						transition: all 0.3s ease;
					}
					
					.position-preview-button {
						transition: all 0.3s ease;
					}
					
					/* Color Palette Styles */
					.tldr-color-presets {
						display: grid;
						grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
						gap: 15px;
						margin: 15px 0;
					}
					
					.tldr-color-preset {
						display: block;
						cursor: pointer;
					}
					
					.tldr-color-preset input[type="radio"] {
						position: absolute;
						opacity: 0;
					}
					
					.tldr-preset-preview {
						display: block;
						padding: 12px;
						border: 2px solid #ddd;
						border-radius: 5px;
						text-align: center;
						transition: all 0.3s ease;
					}
					
					.tldr-color-preset input[type="radio"]:checked + .tldr-preset-preview {
						border-color: #0073aa;
						box-shadow: 0 0 0 1px #0073aa;
					}
					
					.preset-button {
						display: inline-block;
						padding: 8px 16px;
						border-radius: 4px;
						font-size: 12px;
						font-weight: 500;
					}
					
					/* Preset Styles */
					[data-style="default"] .preset-button {
						background: #0073aa;
						color: white;
					}
					
					[data-style="gradient-blue"] .preset-button {
						background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
						color: white;
					}
					
					[data-style="gradient-purple"] .preset-button {
						background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
						color: white;
					}
					
					[data-style="gradient-sunset"] .preset-button {
						background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
						color: white;
					}
					
					[data-style="gradient-ocean"] .preset-button {
						background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
						color: white;
					}
					
					[data-style="dark"] .preset-button {
						background: #2c3e50;
						color: white;
					}
					
					[data-style="light"] .preset-button {
						background: white;
						color: #333;
						border: 2px solid #ddd;
					}
					
					[data-style="minimal-dark"] .preset-button {
						background: transparent;
						color: #333;
						border: 2px solid #333;
					}
					
					[data-style="minimal-light"] .preset-button {
						background: transparent;
						color: #666;
						border: 2px solid #999;
					}
					
					[data-style="glassmorphism"] .preset-button {
						background: rgba(255, 255, 255, 0.2);
						backdrop-filter: blur(10px);
						color: #333;
						border: 1px solid rgba(255, 255, 255, 0.3);
						box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
					}
					
					[data-style="custom"] .preset-button {
						background: linear-gradient(45deg, #ddd 25%, transparent 25%, transparent 75%, #ddd 75%, #ddd),
								    linear-gradient(45deg, #ddd 25%, transparent 25%, transparent 75%, #ddd 75%, #ddd);
						background-size: 10px 10px;
						background-position: 0 0, 5px 5px;
						color: #666;
					}
					
					/* Button Preview Styles */
					.tldr-pro-button-preview {
						display: inline-flex;
						align-items: center;
						gap: 8px;
						padding: 10px 20px;
						border: none;
						border-radius: 4px;
						font-size: 14px;
						font-weight: 500;
						cursor: not-allowed;
						transition: all 0.3s ease;
					}
					</style>
					
					<script>
					jQuery(document).ready(function($) {
						// Accordion functionality
						$('.tldr-accordion-header').on('click', function() {
							var $header = $(this);
							var $section = $header.parent();
							var $content = $section.find('.tldr-accordion-content');
							var sectionId = $header.data('section');
							
							// Toggle current section
							$header.toggleClass('active');
							$content.slideToggle(300);
							
							// Update arrow
							$header.find('.tldr-accordion-toggle').toggleClass('dashicons-arrow-down dashicons-arrow-up');
						});
						
						// Position preview functionality
						function updatePositionPreview(position, type) {
							var $button = $('#position-preview-button');
							var positions = {
								'after_title': { top: '55px', left: '10px', right: 'auto', bottom: 'auto' },
								'after_title_elegant': { top: '55px', left: '50%', transform: 'translateX(-50%)', right: 'auto', bottom: 'auto' },
								'before_content': { top: '85px', left: '10px', right: 'auto', bottom: 'auto' },
								'after_content': { top: 'auto', left: '10px', right: 'auto', bottom: '50px' },
								'fixed_bottom_left': { position: 'fixed', bottom: '20px', left: '20px', right: 'auto', top: 'auto' },
								'fixed_bottom_right': { position: 'fixed', bottom: '20px', right: '20px', left: 'auto', top: 'auto' },
								'fixed_bottom_center': { 
									position: 'fixed', 
									bottom: '20px', 
									left: '50%', 
									transform: 'translateX(-50%)',
									right: 'auto', 
									top: 'auto'
								},
								'floating_bar': { position: 'absolute', top: '0', left: '0', right: '0', bottom: 'auto', width: '100%', textAlign: 'center', padding: '10px', background: 'rgba(0,0,0,0.1)' },
								'sidebar_sticky': { position: 'absolute', top: '85px', right: '10px', left: 'auto', bottom: 'auto' },
								'hero_section': { top: '35px', left: '50%', transform: 'translateX(-50%)', right: 'auto', bottom: 'auto', fontSize: '18px' }
							};
							
							// Reset styles
							$button.removeAttr('style').css({
								position: 'absolute',
								display: 'block'
							});
							
							// Apply new position
							if (positions[position]) {
								$button.css(positions[position]);
								
								// Special handling for fixed center (icon only on mobile)
								if (position === 'fixed_bottom_center') {
									$button.find('span').css('display', window.innerWidth < 768 ? 'none' : 'inline');
									$button.find('button').css('borderRadius', window.innerWidth < 768 ? '50%' : '4px');
								} else {
									$button.find('span').css('display', 'inline');
									$button.find('button').css('borderRadius', '4px');
								}
							}
						}
						
						// Position selector change handlers
						$('#tldr_pro_button_position_post').on('change', function() {
							updatePositionPreview($(this).val(), 'post');
						});
						
						$('#tldr_pro_button_position_page').on('change', function() {
							updatePositionPreview($(this).val(), 'page');
						});
						
						// Initialize position preview
						var currentPostPosition = $('#tldr_pro_button_position_post').val() || 'after_title';
						var currentPagePosition = $('#tldr_pro_button_position_page').val() || 'after_title_elegant';
						updatePositionPreview(currentPostPosition, 'post');
						
						// Button style definitions
						var buttonStyles = {
							'default': {
								background: '#0073aa',
								color: '#ffffff',
								border: 'none'
							},
							'gradient-blue': {
								background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
								color: '#ffffff',
								border: 'none'
							},
							'gradient-purple': {
								background: 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
								color: '#ffffff',
								border: 'none'
							},
							'gradient-sunset': {
								background: 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
								color: '#ffffff',
								border: 'none'
							},
							'gradient-ocean': {
								background: 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
								color: '#ffffff',
								border: 'none'
							},
							'dark': {
								background: '#2c3e50',
								color: '#ffffff',
								border: 'none'
							},
							'light': {
								background: '#ffffff',
								color: '#333333',
								border: '2px solid #dddddd'
							},
							'minimal-dark': {
								background: 'transparent',
								color: '#333333',
								border: '2px solid #333333'
							},
							'minimal-light': {
								background: 'transparent',
								color: '#666666',
								border: '2px solid #999999'
							},
							'glassmorphism': {
								background: 'rgba(255, 255, 255, 0.9)',
								color: '#333333',
								border: '1px solid rgba(255, 255, 255, 0.3)',
								boxShadow: '0 4px 6px rgba(0, 0, 0, 0.1)'
							}
						};
						
						// Apply button style to preview
						function applyButtonStyle(style) {
							var $previewButtons = $('#tldr-preview-button, #tldr-preview-button-dark');
							
							if (style === 'custom') {
								var bgColor = $('#tldr_pro_button_bg_color').val();
								var textColor = $('#tldr_pro_button_text_color').val();
								$previewButtons.css({
									'background': bgColor,
									'color': textColor,
									'border': 'none'
								});
							} else if (buttonStyles[style]) {
								$previewButtons.css(buttonStyles[style]);
							}
						}
						
						// Live preview of button text
						$('#tldr_pro_button_text').on('input', function() {
							var text = $(this).val() || 'Show TL;DR';
							$('#button-text-preview').text(text);
							$('#button-text-preview-enhanced').text(text);
							$('#button-text-preview-dark').text(text);
						});
						
						// Handle color style selection
						$('input[name="tldr_pro_button_style"]').on('change', function() {
							var style = $(this).val();
							
							// Show/hide custom color settings
							if (style === 'custom') {
								$('#custom-color-settings').slideDown();
							} else {
								$('#custom-color-settings').slideUp();
							}
							
							// Apply style to preview
							applyButtonStyle(style);
						});
						
						// Handle custom color changes
						$('#tldr_pro_button_bg_color, #tldr_pro_button_text_color').on('input', function() {
							if ($('input[name="tldr_pro_button_style"]:checked').val() === 'custom') {
								applyButtonStyle('custom');
							}
						});
						
						// Initialize preview on load
						var currentStyle = $('input[name="tldr_pro_button_style"]:checked').val() || 'default';
						applyButtonStyle(currentStyle);
						
						// Reset button settings
						$('#reset-button-settings').on('click', function() {
							if (confirm('<?php _e( 'Are you sure you want to reset button settings to defaults?', 'tldr-pro' ); ?>')) {
								$('#tldr_pro_button_text').val('Show TL;DR').trigger('input');
								$('input[name="tldr_pro_button_style"][value="default"]').prop('checked', true).trigger('change');
								$('#tldr_pro_button_bg_color').val('#0073aa');
								$('#tldr_pro_button_text_color').val('#ffffff');
								
								// Also reset in database via AJAX
								$.ajax({
									url: ajaxurl,
									type: 'POST',
									data: {
										action: 'tldr_pro_reset_button_settings',
										nonce: tldr_pro_admin.nonce
									},
									success: function(response) {
										if (response.success) {
											// Show success message
											var $notice = $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
											$('#display .form-table').before($notice);
											
											// Auto-dismiss after 3 seconds
											setTimeout(function() {
												$notice.fadeOut(function() {
													$(this).remove();
												});
											}, 3000);
										}
									}
								});
							}
						});
					});
					</script>
				</div>
				
				<!-- Prompt Templates Tab -->
				<div id="prompts" class="tab-content">
					<h2><?php _e( 'Prompt Templates', 'tldr-pro' ); ?></h2>
					
					<div class="tldr-pro-prompt-info">
						<p class="description">
							<?php _e( 'Customize the prompts sent to AI providers. Use variables like {content}, {language}, {max_length}, {style}, {format}, {bullet_points}.', 'tldr-pro' ); ?>
						</p>
						
						<div class="available-variables">
							<h4><?php _e( 'Available Variables:', 'tldr-pro' ); ?></h4>
							<ul>
								<li><code>{content}</code> - <?php _e( 'The full content to summarize', 'tldr-pro' ); ?></li>
								<li><code>{language}</code> - <?php _e( 'Target language for the summary', 'tldr-pro' ); ?></li>
								<li><code>{max_length}</code> - <?php _e( 'Maximum word count for summary', 'tldr-pro' ); ?></li>
								<li><code>{style}</code> - <?php _e( 'Writing style (professional, casual, academic)', 'tldr-pro' ); ?></li>
								<li><code>{format}</code> - <?php _e( 'Output format (paragraph, bullet_points, numbered_list)', 'tldr-pro' ); ?></li>
								<li><code>{bullet_points}</code> - <?php _e( 'Number of bullet points to generate', 'tldr-pro' ); ?></li>
							</ul>
						</div>
					</div>
					
					<?php 
					// Load prompt manager if exists
					if ( class_exists( 'TLDR_Pro_Prompt_Manager' ) ) {
						$prompts = TLDR_Pro_Prompt_Manager::get_prompts();
					} else {
						$prompts = [];
					}
					?>
					
					<div id="tldr-pro-prompts-form">
						<?php wp_nonce_field( 'tldr_pro_save_prompts', 'tldr_pro_prompts_nonce' ); ?>
						
						<?php foreach ( $provider_config as $provider_key => $config ) : ?>
							<div class="prompt-settings">
								<h3><?php echo esc_html( $config['name'] ); ?></h3>
								
								<div class="form-field">
									<label for="tldr_pro_prompt_<?php echo $provider_key; ?>">
										<?php _e( 'User Prompt Template', 'tldr-pro' ); ?>
									</label>
									<textarea name="prompts[<?php echo esc_attr( $provider_key ); ?>]" 
											  id="tldr_pro_prompt_<?php echo $provider_key; ?>"
											  rows="12" cols="50" class="large-text code prompt-template"><?php 
										echo esc_textarea( isset( $prompts[ $provider_key ] ) ? $prompts[ $provider_key ] : '' ); 
									?></textarea>
									
									<div class="prompt-actions">
										<button type="button" class="button reset-prompt" 
												data-provider="<?php echo esc_attr( $provider_key ); ?>">
											<span class="dashicons dashicons-image-rotate"></span>
											<?php _e( 'Reset to Default', 'tldr-pro' ); ?>
										</button>
										
										<button type="button" class="button preview-prompt" 
												data-provider="<?php echo esc_attr( $provider_key ); ?>">
											<span class="dashicons dashicons-visibility"></span>
											<?php _e( 'Preview', 'tldr-pro' ); ?>
										</button>
									</div>
									
									<div class="prompt-validation" id="validation-<?php echo esc_attr( $provider_key ); ?>"></div>
								</div>
							</div>
						<?php endforeach; ?>
						
						<div class="prompt-save-section">
							<button type="button" id="save-all-prompts" class="button button-primary">
								<span class="dashicons dashicons-saved"></span>
								<?php _e( 'Save All Templates', 'tldr-pro' ); ?>
							</button>
							
							<button type="button" id="reset-all-prompts" class="button">
								<span class="dashicons dashicons-image-rotate"></span>
								<?php _e( 'Reset All to Defaults', 'tldr-pro' ); ?>
							</button>
							
							<span class="prompt-save-status"></span>
						</div>
					</div>
					
					<!-- Preview Modal -->
					<div id="prompt-preview-modal" class="tldr-pro-modal" style="display: none;">
						<div class="modal-content">
							<span class="close">&times;</span>
							<h3><?php _e( 'Prompt Preview', 'tldr-pro' ); ?></h3>
							<div id="prompt-preview-content"></div>
						</div>
					</div>
				</div>
				
				<!-- Advanced Tab -->
				<div id="advanced" class="tab-content">
					<h2><?php _e( 'Advanced Settings', 'tldr-pro' ); ?></h2>
					
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="tldr_pro_batch_size">
									<?php _e( 'Batch Size', 'tldr-pro' ); ?>
								</label>
							</th>
							<td>
								<input type="number" name="tldr_pro_batch_size" id="tldr_pro_batch_size"
									   value="<?php echo esc_attr( TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_batch_size' ) ); ?>"
									   min="1" max="20" />
								<p class="description">
									<?php _e( 'Number of posts to process in each batch.', 'tldr-pro' ); ?>
								</p>
							</td>
						</tr>
						
						<tr>
							<th scope="row">
								<label for="tldr_pro_batch_delay">
									<?php _e( 'Batch Delay', 'tldr-pro' ); ?>
								</label>
							</th>
							<td>
								<input type="number" name="tldr_pro_batch_delay" id="tldr_pro_batch_delay"
									   value="<?php echo esc_attr( TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_batch_delay' ) ); ?>"
									   min="0" max="10" step="0.5" />
								<span><?php _e( 'seconds', 'tldr-pro' ); ?></span>
								<p class="description">
									<?php _e( 'Delay between API requests to avoid rate limiting.', 'tldr-pro' ); ?>
								</p>
							</td>
						</tr>
						
						<tr>
							<th scope="row">
								<label for="tldr_pro_delete_data_on_uninstall">
									<?php _e( 'Delete Data on Uninstall', 'tldr-pro' ); ?>
								</label>
							</th>
							<td>
								<input type="checkbox" name="tldr_pro_delete_data_on_uninstall" id="tldr_pro_delete_data_on_uninstall"
									   value="1" <?php checked( TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_delete_data_on_uninstall' ), '1' ); ?> />
								<label for="tldr_pro_delete_data_on_uninstall">
									<?php _e( 'Delete all plugin data when uninstalling', 'tldr-pro' ); ?>
								</label>
								<p class="description">
									<?php _e( 'If enabled, all summaries and settings will be permanently deleted when the plugin is uninstalled. This action cannot be undone.', 'tldr-pro' ); ?>
								</p>
							</td>
						</tr>
						
						<tr>
							<th scope="row">
								<label for="tldr_pro_fallback_order">
									<?php _e( 'Fallback Order', 'tldr-pro' ); ?>
								</label>
							</th>
							<td>
								<div id="fallback-order-sortable">
									<?php 
									$fallback_order = TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_fallback_order' );
									$fallback_order = is_string( $fallback_order ) ? explode( ',', $fallback_order ) : $fallback_order;
									$fallback_order = array_filter( array_map( 'trim', $fallback_order ) ); // Remove empty values
									
									// Get all available providers from AI Manager
									$all_providers = array_keys( $provider_config );
									
									// Create ordered list: first show providers in fallback_order, then add missing ones
									$ordered_providers = $fallback_order;
									foreach ( $all_providers as $provider_key ) {
										if ( ! in_array( $provider_key, $ordered_providers ) ) {
											$ordered_providers[] = $provider_key;
										}
									}
									
									foreach ( $ordered_providers as $provider_key ) :
										if ( isset( $provider_config[ $provider_key ] ) ) :
									?>
										<div class="fallback-item" data-provider="<?php echo esc_attr( $provider_key ); ?>">
											<span class="dashicons dashicons-move"></span>
											<?php echo esc_html( $provider_config[ $provider_key ]['name'] ); ?>
										</div>
									<?php 
										endif;
									endforeach; 
									?>
								</div>
								<input type="hidden" name="tldr_pro_fallback_order" id="tldr_pro_fallback_order" 
									   value="<?php echo esc_attr( implode( ',', $ordered_providers ) ); ?>" />
								<p class="description">
									<?php _e( 'Drag to reorder fallback providers.', 'tldr-pro' ); ?>
								</p>
							</td>
						</tr>
						
						<tr>
							<th scope="row">
								<label for="tldr_pro_debug_mode">
									<?php _e( 'Debug Mode', 'tldr-pro' ); ?>
								</label>
							</th>
							<td>
								<label>
									<input type="checkbox" name="tldr_pro_debug_mode" id="tldr_pro_debug_mode"
										   value="1" <?php checked( TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_debug_mode' ) ); ?> />
									<?php _e( 'Enable debug logging', 'tldr-pro' ); ?>
								</label>
							</td>
						</tr>
						
						<tr>
							<th scope="row">
								<?php _e( 'Settings Management', 'tldr-pro' ); ?>
							</th>
							<td>
								<div class="settings-management-buttons">
									<button type="button" id="tldr-pro-init-defaults" class="button">
										<?php _e( 'Initialize Default Settings', 'tldr-pro' ); ?>
									</button>
									<button type="button" id="tldr-pro-reset-settings" class="button button-secondary">
										<?php _e( 'Reset All Settings to Defaults', 'tldr-pro' ); ?>
									</button>
								</div>
								<p class="description">
									<?php _e( 'Use these tools to manage your plugin settings. Initialize will set missing defaults, Reset will overwrite all settings.', 'tldr-pro' ); ?>
								</p>
								<div id="settings-management-status" style="margin-top: 10px;"></div>
							</td>
						</tr>
					</table>
				</div>
				
				<?php submit_button(); ?>
			</form>
			
			<?php if ( TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_debug_mode' ) ): ?>
				<div class="tldr-pro-stats-section" style="margin-top: 20px;">
					<h2><?php _e( 'Debug: Current Settings Values', 'tldr-pro' ); ?></h2>
					<pre style="background: #f5f5f5; padding: 10px; font-size: 11px; overflow: auto; max-height: 200px;">
<?php
$debug_settings = array(
	'tldr_pro_active_provider' => TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_active_provider' ),
	'tldr_pro_enable_fallback' => TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_enable_fallback' ),
	'tldr_pro_max_summary_length' => TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_max_summary_length' ),
	'tldr_pro_summary_style' => TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_summary_style' ),
	'tldr_pro_use_emojis' => TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_use_emojis' ),
	'tldr_pro_batch_size' => TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_batch_size' ),
	'tldr_pro_batch_delay' => TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_batch_delay' ),
	'tldr_pro_deepseek_api_key' => get_option( 'tldr_pro_deepseek_api_key' ) ? '***set***' : 'not set',
	'tldr_pro_deepseek_model' => TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_deepseek_model' ),
	'tldr_pro_gemini_api_key' => get_option( 'tldr_pro_gemini_api_key' ) ? '***set***' : 'not set',
	'tldr_pro_gemini_model' => TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_gemini_model' ),
	'defaults_initialized' => get_option( 'tldr_pro_defaults_initialized' ) ? 'Yes' : 'No',
);
echo json_encode( $debug_settings, JSON_PRETTY_PRINT );
?>
					</pre>
				</div>
			<?php endif; ?>
		</div>
		
		<div class="tldr-pro-admin-sidebar">
			<div class="tldr-pro-card">
				<h3><?php _e( 'Summary Statistics', 'tldr-pro' ); ?></h3>
				<?php
				$database = TLDR_Pro_Database::get_instance();
				$stats = $database->get_statistics();
				
				// Calculate success rate
				$success_rate = $stats['total_summaries'] > 0 ? 
					round(($stats['total_summaries'] / max(1, $stats['total_summaries'])) * 100, 1) : 0;
				?>
				<ul>
					<li><?php printf( __( 'Total Summaries: %d', 'tldr-pro' ), $stats['total_summaries'] ); ?></li>
					<li><?php printf( __( 'Generated Today: %d', 'tldr-pro' ), $stats['today_summaries'] ); ?></li>
					<li><?php printf( __( 'This Month: %d', 'tldr-pro' ), $stats['month_summaries'] ); ?></li>
					<?php if ( $stats['total_summaries'] > 0 ): ?>
					<li><?php printf( __( 'Average Length: %d words', 'tldr-pro' ), $stats['average_length'] ?? 0 ); ?></li>
					<?php endif; ?>
				</ul>
			</div>
			
			<div class="tldr-pro-card">
				<h3><?php _e( 'AI Provider Status', 'tldr-pro' ); ?></h3>
				<div id="provider-status">
					<?php
					// Get encryption instance for validation status
					$has_encryption = class_exists( 'TLDR_Pro_Encryption' );
					if ( $has_encryption ) {
						$encryption = TLDR_Pro_Encryption::get_instance();
					}
					
					foreach ( $provider_config as $provider_key => $config ) :
						// Check for encrypted API key first, then plain text
						$encrypted_key_option = 'tldr_pro_' . $provider_key . '_api_key_encrypted';
						$plain_key_option = 'tldr_pro_' . $provider_key . '_api_key';
						$has_api_key = ! empty( get_option( $encrypted_key_option ) ) || ! empty( get_option( $plain_key_option ) );
						
						// Get validation status
						if ( $has_encryption && $has_api_key ) {
							$validation_status = $encryption->get_validation_status( $provider_key );
							$is_validated = $validation_status['is_validated'] && ! $validation_status['is_expired'];
						} else {
							$is_validated = false;
						}
						
						// Determine status
						if ( ! $has_api_key ) {
							$status_class = 'status-no-key';
							$status_text = __( 'No API Key', 'tldr-pro' );
							$status_icon = '⚪';
						} elseif ( $is_validated ) {
							$status_class = 'status-validated';
							$status_text = __( 'Validated', 'tldr-pro' );
							$status_icon = '✅';
						} else {
							$status_class = 'status-not-validated';
							$status_text = __( 'Not Validated', 'tldr-pro' );
							$status_icon = '⚠️';
						}
					?>
						<div class="status-item">
							<span><?php echo esc_html( $config['name'] ); ?></span>
							<span class="<?php echo esc_attr( $status_class ); ?>">
								<?php echo $status_icon; ?> <?php echo esc_html( $status_text ); ?>
							</span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			
			<?php if ( get_option( 'tldr_pro_enable_profiling', false ) && defined( 'WP_DEBUG' ) && WP_DEBUG ): ?>
			<div class="tldr-pro-card">
				<h3><?php _e( 'Performance', 'tldr-pro' ); ?></h3>
				<?php
				$cache_stats = TLDR_Pro_Cache::get_stats();
				$profiler_stats = TLDR_Pro_Profiler::get_stats();
				?>
				<ul>
					<li><?php printf( __( 'Cache Hits: %d', 'tldr-pro' ), $cache_stats['hits'] ?? 0 ); ?></li>
					<li><?php printf( __( 'Cache Misses: %d', 'tldr-pro' ), $cache_stats['misses'] ?? 0 ); ?></li>
					<?php if ( $profiler_stats['average_time'] ?? false ): ?>
					<li><?php printf( __( 'Avg. Generation: %.2fs', 'tldr-pro' ), $profiler_stats['average_time'] ); ?></li>
					<?php endif; ?>
				</ul>
			</div>
			<?php endif; ?>
			
			<div class="tldr-pro-card">
				<h3><?php _e( 'System Status', 'tldr-pro' ); ?></h3>
				<ul class="system-status">
					<li>
						<span><?php _e( 'PHP Version:', 'tldr-pro' ); ?></span>
						<span class="status-<?php echo version_compare( PHP_VERSION, '7.4', '>=' ) ? 'good' : 'warning'; ?>">
							<?php echo PHP_VERSION; ?>
						</span>
					</li>
					<li>
						<span><?php _e( 'WordPress Version:', 'tldr-pro' ); ?></span>
						<span class="status-good"><?php echo get_bloginfo( 'version' ); ?></span>
					</li>
					<li>
						<span><?php _e( 'cURL Support:', 'tldr-pro' ); ?></span>
						<span class="status-<?php echo function_exists( 'curl_init' ) ? 'good' : 'error'; ?>">
							<?php echo function_exists( 'curl_init' ) ? __( 'Enabled', 'tldr-pro' ) : __( 'Disabled', 'tldr-pro' ); ?>
						</span>
					</li>
					<li>
						<span><?php _e( 'GD Extension:', 'tldr-pro' ); ?></span>
						<span class="status-<?php echo extension_loaded( 'gd' ) ? 'good' : 'warning'; ?>">
							<?php echo extension_loaded( 'gd' ) ? __( 'Enabled', 'tldr-pro' ) : __( 'Disabled', 'tldr-pro' ); ?>
						</span>
					</li>
					<?php if ( get_option( 'tldr_pro_enable_redis', false ) ): ?>
					<li>
						<span><?php _e( 'Redis:', 'tldr-pro' ); ?></span>
						<span class="status-<?php echo TLDR_Pro_Redis::is_available() ? 'good' : 'warning'; ?>">
							<?php echo TLDR_Pro_Redis::is_available() ? __( 'Connected', 'tldr-pro' ) : __( 'Offline', 'tldr-pro' ); ?>
						</span>
					</li>
					<?php endif; ?>
				</ul>
			</div>
		</div>
	</div>
</div>

<style>
.tldr-pro-admin-wrapper {
	display: flex;
	gap: 20px;
	margin-top: 20px;
}

.tldr-pro-admin-content {
	flex: 1;
	background: #fff;
	padding: 20px;
	border: 1px solid #ccd0d4;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.tldr-pro-admin-sidebar {
	width: 300px;
}

.tldr-pro-card {
	background: #fff;
	border: 1px solid #ccd0d4;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
	padding: 15px;
	margin-bottom: 20px;
}

.tldr-pro-card h3 {
	margin-top: 0;
	border-bottom: 1px solid #eee;
	padding-bottom: 10px;
}

.system-status li {
	display: flex;
	justify-content: space-between;
	padding: 5px 0;
	border-bottom: 1px solid #f0f0f0;
}

.system-status li:last-child {
	border-bottom: none;
}

.status-good {
	color: #46b450;
	font-weight: 500;
}

.status-warning {
	color: #ffba00;
	font-weight: 500;
}

.status-error {
	color: #dc3232;
	font-weight: 500;
}

.status-loading {
	color: #666;
	font-style: italic;
}

#provider-status .status-item {
	display: flex;
	justify-content: space-between;
	padding: 5px 0;
	border-bottom: 1px solid #f0f0f0;
}

#provider-status .status-item:last-child {
	border-bottom: none;
}

.status-online {
	color: #46b450;
	font-weight: 500;
}

.status-offline {
	color: #dc3232;
	font-weight: 500;
}

.tab-content {
	display: none;
	padding-top: 20px;
}

.tab-content.active {
	display: block;
}

/* Show all tabs when submitting to ensure all fields are sent */
.submitting .tab-content {
	display: block !important;
	position: absolute;
	left: -9999px;
}

.submitting .tab-content.active {
	position: relative;
	left: 0;
}

.provider-settings {
	border: 1px solid #ddd;
	padding: 15px;
	margin-bottom: 20px;
	background: #f9f9f9;
}

.prompt-settings {
	margin-bottom: 30px;
	border: 1px solid #ddd;
	padding: 20px;
	background: #f9f9f9;
	border-radius: 4px;
}

.tldr-pro-prompt-info {
	background: #f0f8ff;
	border: 1px solid #c3e0ff;
	padding: 15px;
	border-radius: 4px;
	margin-bottom: 20px;
}

.available-variables {
	margin-top: 15px;
}

.available-variables h4 {
	margin-bottom: 10px;
	font-size: 14px;
}

.available-variables ul {
	list-style: none;
	padding: 0;
	margin: 0;
}

.available-variables li {
	padding: 5px 0;
	font-size: 13px;
}

.available-variables code {
	background: #fff;
	padding: 2px 6px;
	border: 1px solid #ddd;
	border-radius: 3px;
	font-weight: bold;
	color: #2271b1;
}

.prompt-template {
	font-family: 'Monaco', 'Courier New', monospace;
	font-size: 13px;
	line-height: 1.5;
}

.prompt-actions {
	margin-top: 10px;
	display: flex;
	gap: 10px;
}

.prompt-actions .button {
	display: inline-flex;
	align-items: center;
	gap: 5px;
}

.prompt-actions .dashicons {
	font-size: 16px;
	width: 16px;
	height: 16px;
	line-height: 16px;
}

.prompt-validation {
	margin-top: 10px;
	padding: 10px;
	border-radius: 4px;
	display: none;
}

.prompt-validation.error {
	background: #ffebe8;
	border: 1px solid #c00;
	color: #c00;
	display: block;
}

.prompt-validation.success {
	background: #edfaef;
	border: 1px solid #46b450;
	color: #46b450;
	display: block;
}

.prompt-save-section {
	margin-top: 30px;
	padding-top: 20px;
	border-top: 2px solid #ddd;
	display: flex;
	align-items: center;
	gap: 15px;
}

.prompt-save-section .button {
	display: inline-flex;
	align-items: center;
	gap: 5px;
}

.prompt-save-status {
	margin-left: 20px;
	font-style: italic;
	color: #666;
}

.prompt-save-status.success {
	color: #46b450;
}

.prompt-save-status.error {
	color: #dc3232;
}

/* Modal Styles */
.tldr-pro-modal {
	position: fixed;
	z-index: 100000;
	left: 0;
	top: 0;
	width: 100%;
	height: 100%;
	background-color: rgba(0,0,0,0.4);
}

.tldr-pro-modal .modal-content {
	background-color: #fefefe;
	margin: 5% auto;
	padding: 20px;
	border: 1px solid #888;
	width: 80%;
	max-width: 800px;
	border-radius: 4px;
	position: relative;
}

.tldr-pro-modal .close {
	color: #aaa;
	float: right;
	font-size: 28px;
	font-weight: bold;
	cursor: pointer;
	position: absolute;
	right: 15px;
	top: 10px;
}

.tldr-pro-modal .close:hover,
.tldr-pro-modal .close:focus {
	color: #000;
}

#prompt-preview-content {
	background: #f5f5f5;
	padding: 15px;
	border-radius: 4px;
	font-family: monospace;
	white-space: pre-wrap;
	word-wrap: break-word;
	max-height: 400px;
	overflow-y: auto;
}

.form-field {
	margin-bottom: 15px;
}

.form-field label {
	display: block;
	font-weight: 600;
	margin-bottom: 5px;
}

#fallback-order-sortable {
	max-width: 300px;
}

.fallback-item {
	background: #f6f7f7;
	border: 1px solid #dcdcde;
	padding: 8px 12px;
	margin-bottom: 5px;
	cursor: move;
	display: flex;
	align-items: center;
	gap: 10px;
}

.fallback-item:hover {
	background: #fff;
}

.tldr-pro-test-results {
	margin-top: 15px;
}

.tldr-pro-test-report {
	background: #f8f9fa;
	padding: 15px;
	border-radius: 4px;
}

.tldr-pro-test-details {
	margin-top: 15px;
}

.tldr-pro-test-details h4 {
	margin-bottom: 10px;
	font-size: 14px;
}

.tldr-pro-test-list {
	list-style: none;
	padding: 0;
	margin: 0;
}

.tldr-pro-test-list li {
	padding: 8px 10px;
	margin-bottom: 5px;
	background: white;
	border-left: 3px solid #ddd;
	border-radius: 2px;
}

.tldr-pro-test-list li.tldr-test-success {
	border-left-color: #46b450;
}

.tldr-pro-test-list li.tldr-test-warning {
	border-left-color: #ffb900;
}

.tldr-pro-test-list li.tldr-test-error {
	border-left-color: #dc3232;
}

.tldr-pro-test-list li.tldr-test-info {
	border-left-color: #00a0d2;
}

.tldr-test-details {
	margin-left: 25px;
	margin-top: 5px;
	font-size: 12px;
	color: #666;
}

.tldr-pro-model-info,
.tldr-pro-recommendations {
	margin-top: 15px;
	padding: 10px;
	background: white;
	border-radius: 3px;
}

.tldr-pro-model-info h4,
.tldr-pro-recommendations h4 {
	margin-top: 0;
	margin-bottom: 10px;
	font-size: 14px;
}

.tldr-pro-timing {
	margin-top: 10px;
	padding-top: 10px;
	border-top: 1px solid #ddd;
}

.provider-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 15px;
}

.tldr-pro-validation-badge {
	display: inline-flex;
	align-items: center;
	padding: 5px 12px;
	border-radius: 20px;
	font-size: 13px;
	font-weight: 500;
	gap: 5px;
}

.tldr-pro-validation-badge.validated {
	background: #d4f4dd;
	color: #0a6c3d;
	border: 1px solid #46b450;
}

.tldr-pro-validation-badge.expired {
	background: #fff4e5;
	color: #8a6d00;
	border: 1px solid #ffb900;
}

.tldr-pro-validation-badge.not-validated {
	background: #ffeaea;
	color: #8a0000;
	border: 1px solid #dc3232;
}

.tldr-pro-validation-badge.no-key {
	background: #f0f0f1;
	color: #50575e;
	border: 1px solid #c3c4c7;
}

.validation-date {
	font-size: 11px;
	opacity: 0.8;
	margin-left: 5px;
}

.tldr-pro-toggle-key {
	margin-left: 5px;
	padding: 3px 10px;
	min-width: auto;
}

.tldr-pro-api-key-visible {
	font-family: monospace;
	background: #f0f0f1;
}

#provider-status .status-item {
	display: flex;
	justify-content: space-between;
	margin-bottom: 8px;
	padding: 5px;
	background: #f6f7f7;
}

#provider-status .status-online {
	color: #46b450;
}

#provider-status .status-offline {
	color: #dc3232;
}

#provider-status .status-validated {
	color: #46b450;
	font-weight: 500;
}

#provider-status .status-not-validated {
	color: #ffba00;
	font-weight: 500;
}

#provider-status .status-no-key {
	color: #666;
	font-weight: 400;
}

#pricing-result {
	margin-top: 15px;
	padding: 10px;
	background: #f0f0f1;
	border-radius: 3px;
}
</style>

<script>
jQuery(document).ready(function($) {
	// Tab switching
	$('.nav-tab').on('click', function(e) {
		e.preventDefault();
		var tab = $(this).data('tab');
		
		$('.nav-tab').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');
		
		$('.tab-content').removeClass('active');
		$('#' + tab).addClass('active');
	});
	
	// Test API connection
	$('.test-api-key').on('click', function() {
		var button = $(this);
		var provider = button.data('provider');
		var resultSpan = button.siblings('.api-test-result');
		
		button.prop('disabled', true);
		resultSpan.text('Testing...');
		
		$.post(ajaxurl, {
			action: 'tldr_pro_test_api',
			provider: provider,
			api_key: $('#tldr_pro_' + provider + '_api_key').val(),
			nonce: '<?php echo wp_create_nonce( 'tldr_pro_test_api' ); ?>'
		}, function(response) {
			button.prop('disabled', false);
			
			if (response.success) {
				resultSpan.removeClass('error').addClass('success').text('✓ Connected');
			} else {
				resultSpan.removeClass('success').addClass('error').text('✗ ' + response.data);
			}
		});
	});
	
	// Update model pricing display
	$('select[id$="_model"]').on('change', function() {
		var selected = $(this).find(':selected');
		var inputCost = selected.data('cost-input');
		var outputCost = selected.data('cost-output');
		var description = $(this).closest('td').find('.model-pricing');
		
		if (inputCost && outputCost) {
			description.html('Cost: $' + inputCost + '/1M input tokens, $' + outputCost + '/1M output tokens');
		}
	}).trigger('change');
	
	// Fallback order sortable
	if ($('#fallback-order-sortable').length) {
		$('#fallback-order-sortable').sortable({
			update: function(event, ui) {
				var order = [];
				$('#fallback-order-sortable .fallback-item').each(function() {
					order.push($(this).data('provider'));
				});
				$('#tldr_pro_fallback_order').val(order.join(','));
			}
		});
	}
	
	// Reset prompt to default
	$('.reset-prompt').on('click', function() {
		var provider = $(this).data('provider');
		
		if (confirm('Reset prompt to default template?')) {
			var textarea = $(this).siblings('textarea');
			textarea.val(''); // Will use default on next save
		}
	});
	
	// Pricing calculator
	$('#calc-articles').on('input', function() {
		var articles = parseInt($(this).val()) || 0;
		var avgTokens = 1500; // Average tokens per article
		var provider = $('#tldr_pro_active_provider').val();
		var model = $('#tldr_pro_' + provider + '_model :selected');
		
		var inputCost = parseFloat(model.data('cost-input')) || 0;
		var outputCost = parseFloat(model.data('cost-output')) || 0;
		
		var totalCost = (articles * avgTokens * inputCost / 1000000) + 
						(articles * 200 * outputCost / 1000000);
		
		$('#pricing-result').html(
			'<strong>Estimated monthly cost:</strong><br>$' + totalCost.toFixed(2) + ' USD'
		);
	}).trigger('input');
});
</script>