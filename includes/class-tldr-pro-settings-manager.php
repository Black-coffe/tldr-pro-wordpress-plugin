<?php
/**
 * Settings Manager for TL;DR Pro
 *
 * @package    TLDR_Pro
 * @subpackage TLDR_Pro/includes
 */

class TLDR_Pro_Settings_Manager {

    /**
     * Default settings values
     *
     * @var array
     */
    private static $defaults = [
        // General Settings
        'tldr_pro_active_provider' => 'deepseek',
        'tldr_pro_enable_fallback' => true,
        'tldr_pro_max_summary_length' => 150,
        'tldr_pro_summary_style' => 'professional',
        'tldr_pro_use_emojis' => true,
        'tldr_pro_summary_language' => 'en', // Language for summaries
        
        // Advanced Settings
        'tldr_pro_batch_size' => 5,
        'tldr_pro_batch_delay' => 1,
        'tldr_pro_fallback_order' => 'deepseek,gemini,claude,gpt',
        'tldr_pro_debug_mode' => false,
        
        // Provider-specific settings
        'tldr_pro_deepseek_model' => 'deepseek-chat',
        'tldr_pro_deepseek_use_prefix_caching' => true,
        'tldr_pro_gemini_model' => 'gemini-1.5-flash',
        
        // Display Settings
        'tldr_pro_button_position' => 'before_content',
        'tldr_pro_button_text' => 'Show TL;DR',
        'tldr_pro_auto_generate' => false,
        'tldr_pro_show_reading_time' => true,
        'tldr_pro_show_word_count' => true,
        
        // Post Type Settings
        'tldr_pro_enabled_post_types' => ['post', 'page'],
        'tldr_pro_min_word_count' => 300,
        
        // Summary Format Settings
        'tldr_pro_summary_format' => 'paragraph', // paragraph, bullet_points, numbered_list
        'tldr_pro_bullet_points_count' => 5,
        'tldr_pro_summary_language' => 'auto', // auto-detect from content
        
        // Uninstall Settings
        'tldr_pro_delete_data_on_uninstall' => '0', // Don't delete data by default
    ];

    /**
     * Initialize default settings
     *
     * @return void
     */
    public static function init_defaults() {
        foreach ( self::$defaults as $option_name => $default_value ) {
            // Check if option truly doesn't exist
            // Use strict false check because get_option returns false when not exists
            if ( get_option( $option_name, 'TLDR_PRO_NOT_SET' ) === 'TLDR_PRO_NOT_SET' ) {
                add_option( $option_name, $default_value, '', 'yes' );
            }
        }
        
        // Mark that defaults have been initialized
        update_option( 'tldr_pro_defaults_initialized', true );
    }

    /**
     * Get a setting value with fallback to default
     *
     * @param string $option_name Option name
     * @param mixed $default Optional custom default value
     * @return mixed
     */
    public static function get_setting( $option_name, $default = null ) {
        // Get the actual value from database
        $value = get_option( $option_name );
        
        // If value exists (even if it's 0 or false), return it
        if ( $value !== false ) {
            return $value;
        }
        
        // Otherwise use default
        if ( $default === null && isset( self::$defaults[ $option_name ] ) ) {
            $default = self::$defaults[ $option_name ];
        }
        
        return $default;
    }

    /**
     * Update a setting value
     *
     * @param string $option_name Option name
     * @param mixed $value New value
     * @return bool
     */
    public static function update_setting( $option_name, $value ) {
        // Sanitize based on option type
        $sanitized_value = self::sanitize_setting( $option_name, $value );
        
        return update_option( $option_name, $sanitized_value );
    }

    /**
     * Sanitize setting value based on type
     *
     * @param string $option_name Option name
     * @param mixed $value Value to sanitize
     * @return mixed
     */
    private static function sanitize_setting( $option_name, $value ) {
        // Boolean settings
        $boolean_settings = [
            'tldr_pro_enable_fallback',
            'tldr_pro_use_emojis',
            'tldr_pro_debug_mode',
            'tldr_pro_deepseek_use_prefix_caching',
            'tldr_pro_auto_generate',
            'tldr_pro_show_reading_time',
            'tldr_pro_show_word_count'
        ];
        
        if ( in_array( $option_name, $boolean_settings ) ) {
            return (bool) $value;
        }
        
        // Integer settings
        $integer_settings = [
            'tldr_pro_max_summary_length',
            'tldr_pro_batch_size',
            'tldr_pro_batch_delay',
            'tldr_pro_bullet_points_count',
            'tldr_pro_min_word_count'
        ];
        
        if ( in_array( $option_name, $integer_settings ) ) {
            return absint( $value );
        }
        
        // Array settings
        if ( $option_name === 'tldr_pro_enabled_post_types' ) {
            if ( ! is_array( $value ) ) {
                $value = [];
            }
            return array_map( 'sanitize_text_field', $value );
        }
        
        // Text settings
        return sanitize_text_field( $value );
    }

    /**
     * Get all settings as array
     *
     * @return array
     */
    public static function get_all_settings() {
        $settings = [];
        
        foreach ( self::$defaults as $option_name => $default_value ) {
            $settings[ $option_name ] = self::get_setting( $option_name );
        }
        
        return $settings;
    }

    /**
     * Reset settings to defaults
     *
     * @param array $settings Optional specific settings to reset
     * @return bool
     */
    public static function reset_to_defaults( $settings = [] ) {
        // If no specific settings provided, reset all
        if ( empty( $settings ) ) {
            $settings = array_keys( self::$defaults );
        }
        
        foreach ( $settings as $option_name ) {
            if ( isset( self::$defaults[ $option_name ] ) ) {
                // First delete the option to ensure it's reset
                delete_option( $option_name );
                
                // Then set to default value
                // Use add_option to ensure it's created if it doesn't exist
                add_option( $option_name, self::$defaults[ $option_name ] );
            }
        }
        
        // Also reset encrypted API keys
        $encryption_keys = array(
            'tldr_pro_deepseek_api_key_encrypted',
            'tldr_pro_gemini_api_key_encrypted',
            'tldr_pro_openai_api_key_encrypted',
            'tldr_pro_anthropic_api_key_encrypted',
            'tldr_pro_groq_api_key_encrypted',
            'tldr_pro_cohere_api_key_encrypted'
        );
        
        foreach ( $encryption_keys as $key ) {
            delete_option( $key );
        }
        
        // Reset prompt templates
        delete_option( 'tldr_pro_prompt_templates' );
        
        // Always return true - we've done the reset operations
        return true;
    }

    /**
     * Get default value for a setting
     *
     * @param string $option_name Option name
     * @return mixed|null
     */
    public static function get_default( $option_name ) {
        return isset( self::$defaults[ $option_name ] ) ? self::$defaults[ $option_name ] : null;
    }

    /**
     * Check if settings need migration
     *
     * @return bool
     */
    public static function needs_migration() {
        $version = get_option( 'tldr_pro_settings_version', '0' );
        return version_compare( $version, '2.0.0', '<' );
    }

    /**
     * Migrate settings from old structure
     *
     * @return void
     */
    public static function migrate_settings() {
        // Migration logic for older versions
        // This can be expanded as needed
        
        // Mark migration as complete
        update_option( 'tldr_pro_settings_version', '2.0.0' );
    }
}