<?php
/**
 * Prompt Templates Manager
 *
 * @package    TLDR_Pro
 * @subpackage TLDR_Pro/includes/ai
 */

class TLDR_Pro_Prompt_Manager {

    /**
     * Universal prompt template for all providers
     *
     * @var string
     */
    private static $universal_prompt = 'You are an expert content summarization specialist. Your role is to create comprehensive, insightful TL;DR summaries for web content. Focus on extracting the most valuable information while maintaining readability and engagement.

═══════════════════════════════════════════════════════════════════
🚨 ABSOLUTE LANGUAGE REQUIREMENT - MANDATORY:
═══════════════════════════════════════════════════════════════════
YOU MUST GENERATE THE SUMMARY IN {language} LANGUAGE!
THIS IS NON-NEGOTIABLE! ALL OUTPUT MUST BE IN {language}!

IGNORE the article\'s original language completely.
ALWAYS respond in {language}, regardless of input language.

Examples:
- Article in English + Setting={language} → Summary in {language}
- Article in Chinese + Setting={language} → Summary in {language}
- Article in Russian + Setting={language} → Summary in {language}
- Article in ANY language + Setting={language} → Summary in {language}

THIS SETTING OVERRIDES EVERYTHING!
═══════════════════════════════════════════════════════════════════

SUMMARY CONFIGURATION:
- Maximum Length: {max_length} WORDS (NOT characters - count each individual word!)
- Writing Style: {style}
- Format Type: {format}
- Key Points: {bullet_points}
- Emoji Usage: {use_emojis} (if true, include relevant emojis; if false, NO emojis)

OUTPUT FORMATTING GUIDELINES:
- Return PURE HTML code only - NO Markdown syntax
- Use proper HTML tags: <div>, <h2>, <h3>, <p>, <ul>, <li>, <strong>, <em>
- Include inline CSS styles for visual enhancement
- DO NOT use ##, **, *, -, ``` or any Markdown formatting
- Ensure all HTML is properly nested and closed

HTML TEMPLATE STRUCTURE:
<div class="tldr-summary-container" style="font-family: \'Inter\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 16px; padding: 3px; box-shadow: 0 20px 40px rgba(102,126,234,0.3); margin: 20px 0;">
  <div style="background: #ffffff; border-radius: 13px; padding: 28px;">
    <h2 style="margin: 0 0 22px 0; color: #667eea; font-size: 1.65em; font-weight: 700; display: flex; align-items: center; gap: 12px; border-bottom: 2px solid #e8f4f9; padding-bottom: 16px;">
      <span style="font-size: 1.2em;">📋</span>
      <span>TL;DR Summary</span>
    </h2>
    <div class="tldr-content" style="line-height: 1.8; color: #2d3748; font-size: 1.05em;">
      [YOUR COMPREHENSIVE SUMMARY HERE IN {language} LANGUAGE]
    </div>
  </div>
</div>

FOR BULLET POINT FORMAT:
<ul style="list-style: none; padding: 0; margin: 18px 0;">
  <li style="margin: 15px 0; padding: 16px 16px 16px 44px; position: relative; background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); border-radius: 10px; border-left: 4px solid #667eea;">
    <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #667eea; font-size: 1.3em;">▸</span>
    [Key point in {language}]
  </li>
</ul>

FOR NUMBERED LISTS:
<ol style="padding-left: 0; counter-reset: item; list-style: none;">
  <li style="margin: 15px 0; padding: 16px 16px 16px 50px; position: relative; background: #f8fafc; border-radius: 10px; counter-increment: item;">
    <span style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); background: #667eea; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.85em; font-weight: 600;" data-counter></span>
    [Numbered point in {language}]
  </li>
</ol>

HIGHLIGHT BOX (for key insights):
<div style="margin: 20px 0; padding: 20px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 12px; border-left: 4px solid #f59e0b;">
  <strong style="color: #92400e; font-size: 1.1em;">💡 Key Insight:</strong>
  <p style="margin: 8px 0 0 0; color: #78350f; line-height: 1.6;">[Important insight in {language}]</p>
</div>

╔══════════════════════════════════════════════════════════════════╗
║                 ARTICLE CONTENT TO SUMMARIZE                     ║
╠══════════════════════════════════════════════════════════════════╣
║ SOURCE LANGUAGE: [AUTO-DETECT FROM CONTENT]                      ║
║ OUTPUT LANGUAGE: {language} (MANDATORY - OVERRIDE SOURCE)        ║
╚══════════════════════════════════════════════════════════════════╝

{content}

╔══════════════════════════════════════════════════════════════════╗
║                      END OF ARTICLE                              ║
╚══════════════════════════════════════════════════════════════════╝

⚠️ CRITICAL REQUIREMENTS - ALL MUST BE FOLLOWED:
✓ Word Count: Summary MUST be EXACTLY {max_length} words (verify by counting!)
✓ Language: ALL content MUST be in {language} language regardless of source
✓ Style: Apply {style} tone consistently throughout
✓ Format: Use {format} structure as specified
✓ Emojis: Include if {use_emojis}=true, exclude completely if false
✓ HTML: Return ONLY valid HTML code with inline styles
✓ Quality: Focus on actionable insights, key arguments, and main takeaways
✓ Accuracy: Maintain factual accuracy while being concise
✓ Engagement: Make the summary compelling and easy to read
✓ Completeness: Ensure all important points are covered within word limit

RESPOND WITH HTML CODE ONLY - NO EXPLANATIONS OR METADATA!';

    /**
     * Default prompts for each AI provider
     *
     * @var array
     */
    private static $default_prompts = [];

    /**
     * Option name for storing custom prompts
     */
    const OPTION_NAME = 'tldr_pro_prompt_templates';

    /**
     * Get all prompts (custom or default)
     *
     * @return array
     */
    public static function get_prompts() {
        $custom_prompts = get_option( self::OPTION_NAME, [] );
        
        // Define main providers that use universal prompt
        $main_providers = ['deepseek', 'gemini', 'claude', 'gpt'];
        
        $prompts = [];
        foreach ( $main_providers as $provider ) {
            $prompts[ $provider ] = isset( $custom_prompts[ $provider ] ) 
                ? $custom_prompts[ $provider ] 
                : self::$universal_prompt;
        }
        
        return $prompts;
    }

    /**
     * Get prompt for specific provider
     *
     * @param string $provider Provider name
     * @return string|null
     */
    public static function get_prompt( $provider ) {
        $prompts = self::get_prompts();
        return isset( $prompts[ $provider ] ) ? $prompts[ $provider ] : null;
    }

    /**
     * Get default prompt for provider
     *
     * @param string $provider Provider name
     * @return string|null
     */
    public static function get_default_prompt( $provider ) {
        $main_providers = ['deepseek', 'gemini', 'claude', 'gpt'];
        
        if ( in_array( $provider, $main_providers ) ) {
            return self::$universal_prompt;
        }
        
        return null;
    }

    /**
     * Save custom prompts
     *
     * @param array $prompts Array of prompts keyed by provider
     * @return bool
     */
    public static function save_prompts( $prompts ) {
        // Sanitize prompts
        $sanitized = [];
        foreach ( $prompts as $provider => $prompt ) {
            $provider = sanitize_key( $provider );
            $prompt = wp_kses_post( $prompt );
            
            if ( ! empty( $prompt ) ) {
                $sanitized[ $provider ] = $prompt;
            }
        }
        
        return update_option( self::OPTION_NAME, $sanitized );
    }

    /**
     * Reset prompt to default for specific provider
     *
     * @param string $provider Provider name
     * @return bool
     */
    public static function reset_to_default( $provider ) {
        $custom_prompts = get_option( self::OPTION_NAME, [] );
        
        // Remove custom prompt for this provider
        if ( isset( $custom_prompts[ $provider ] ) ) {
            unset( $custom_prompts[ $provider ] );
            return update_option( self::OPTION_NAME, $custom_prompts );
        }
        
        return true;
    }

    /**
     * Reset all prompts to defaults
     *
     * @return bool
     */
    public static function reset_all_to_defaults() {
        return delete_option( self::OPTION_NAME );
    }

    /**
     * Process prompt with variables
     *
     * @param string $prompt Template prompt
     * @param array  $variables Variables to replace
     * @return string
     */
    public static function process_prompt( $prompt, $variables = [] ) {
        // Get language setting and convert to full name
        $language_code = TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_summary_language', 'en' );
        $language_names = self::get_language_names();
        $language_name = isset( $language_names[ $language_code ] ) ? $language_names[ $language_code ] : 'English';
        
        // Get actual settings from General Settings
        $defaults = [
            'content' => '',
            'language' => $language_name,
            'max_length' => TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_max_summary_length', 150 ),
            'style' => TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_summary_style', 'professional' ),
            'format' => TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_summary_format', 'paragraph' ),
            'bullet_points' => TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_bullet_points', 3 ),
            'use_emojis' => TLDR_Pro_Settings_Manager::get_setting( 'tldr_pro_use_emojis', true ) ? 'true' : 'false'
        ];
        
        // Merge with provided variables
        $variables = wp_parse_args( $variables, $defaults );
        
        // OVERRIDE: Always use the language from settings
        $variables['language'] = $language_name;
        
        // Replace variables in prompt
        foreach ( $variables as $key => $value ) {
            $prompt = str_replace( '{' . $key . '}', $value, $prompt );
        }
        
        return $prompt;
    }
    
    /**
     * Get language names mapping
     *
     * @return array
     */
    public static function get_language_names() {
        return array(
            'en' => 'English',
            'zh' => 'Chinese',
            'es' => 'Spanish',
            'hi' => 'Hindi',
            'ar' => 'Arabic',
            'bn' => 'Bengali',
            'pt' => 'Portuguese',
            'ru' => 'Russian',
            'ja' => 'Japanese',
            'pa' => 'Punjabi',
            'de' => 'German',
            'jv' => 'Javanese',
            'ko' => 'Korean',
            'fr' => 'French',
            'te' => 'Telugu',
            'mr' => 'Marathi',
            'tr' => 'Turkish',
            'ta' => 'Tamil',
            'vi' => 'Vietnamese',
            'ur' => 'Urdu',
            'it' => 'Italian',
            'th' => 'Thai',
            'gu' => 'Gujarati',
            'fa' => 'Persian',
            'pl' => 'Polish',
            'uk' => 'Ukrainian',
            'ml' => 'Malayalam',
            'kn' => 'Kannada',
            'or' => 'Oriya',
            'my' => 'Burmese',
            'nl' => 'Dutch',
            'si' => 'Sinhala',
            'ne' => 'Nepali',
            'ro' => 'Romanian',
            'sv' => 'Swedish',
            'el' => 'Greek',
            'hu' => 'Hungarian',
            'cs' => 'Czech',
            'fi' => 'Finnish',
            'no' => 'Norwegian',
            'da' => 'Danish',
            'bg' => 'Bulgarian',
            'sk' => 'Slovak',
            'hr' => 'Croatian',
            'sr' => 'Serbian',
            'he' => 'Hebrew',
            'id' => 'Indonesian',
            'ms' => 'Malay',
            'tl' => 'Filipino',
            'et' => 'Estonian',
        );
    }

    /**
     * Get available variables for prompts
     *
     * @return array
     */
    public static function get_available_variables() {
        return [
            'content' => __( 'The full content to summarize', 'tldr-pro' ),
            'language' => __( 'Target language for the summary', 'tldr-pro' ),
            'max_length' => __( 'Maximum word count for summary', 'tldr-pro' ),
            'style' => __( 'Writing style (professional, casual, academic)', 'tldr-pro' ),
            'format' => __( 'Output format (paragraph, bullet_points, numbered_list)', 'tldr-pro' ),
            'bullet_points' => __( 'Number of bullet points to generate', 'tldr-pro' ),
            'use_emojis' => __( 'Whether to use emojis in summary (true/false)', 'tldr-pro' )
        ];
    }

    /**
     * Validate prompt template
     *
     * @param string $prompt Prompt to validate
     * @return array Validation result
     */
    public static function validate_prompt( $prompt ) {
        $errors = [];
        
        // Check if prompt is not empty
        if ( empty( trim( $prompt ) ) ) {
            $errors[] = __( 'Prompt cannot be empty', 'tldr-pro' );
        }
        
        // Check minimum length
        if ( strlen( $prompt ) < 50 ) {
            $errors[] = __( 'Prompt should be at least 50 characters long', 'tldr-pro' );
        }
        
        // Check if {content} variable is present
        if ( strpos( $prompt, '{content}' ) === false ) {
            $errors[] = __( 'Prompt must include {content} variable', 'tldr-pro' );
        }
        
        return [
            'valid' => empty( $errors ),
            'errors' => $errors
        ];
    }
}