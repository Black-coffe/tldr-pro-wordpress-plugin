<?php
/**
 * Encryption handler for sensitive data
 *
 * @package TLDR_Pro
 * @subpackage TLDR_Pro/includes
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encryption class for API keys and sensitive data
 *
 * @since 1.0.0
 */
class TLDR_Pro_Encryption {

	/**
	 * Singleton instance
	 *
	 * @var TLDR_Pro_Encryption
	 */
	private static $instance = null;

	/**
	 * Encryption method
	 *
	 * @var string
	 */
	private $cipher = 'AES-256-CBC';

	/**
	 * Get singleton instance
	 *
	 * @return TLDR_Pro_Encryption
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Initialize encryption key if not exists
		if ( ! get_option( 'tldr_pro_encryption_key' ) ) {
			$this->generate_encryption_key();
		}
	}

	/**
	 * Generate and store encryption key
	 *
	 * @return void
	 */
	private function generate_encryption_key() {
		// Generate random key using available methods
		if ( function_exists( 'wp_generate_password' ) ) {
			$key = wp_generate_password( 64, true, true );
		} elseif ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
			$key = base64_encode( openssl_random_pseudo_bytes( 48 ) );
		} else {
			// Fallback to less secure method
			$key = hash( 'sha256', uniqid( mt_rand(), true ) . microtime( true ) . SECURE_AUTH_KEY );
		}
		
		// Store in database (this should ideally be in wp-config.php)
		update_option( 'tldr_pro_encryption_key', $key, false );
		
		// Also try to write to wp-config.php if possible
		$this->add_key_to_wp_config( $key );
	}

	/**
	 * Try to add encryption key to wp-config.php
	 *
	 * @param string $key Encryption key.
	 * @return bool Success status.
	 */
	private function add_key_to_wp_config( $key ) {
		$config_path = ABSPATH . 'wp-config.php';
		
		// Check if we can write to wp-config.php
		if ( ! is_writable( $config_path ) ) {
			return false;
		}
		
		// Check if constant already defined
		if ( defined( 'TLDR_PRO_ENCRYPTION_KEY' ) ) {
			return true;
		}
		
		$config_content = file_get_contents( $config_path );
		
		// Find the right place to insert (before "That's all, stop editing!")
		$insert_before = "/* That's all, stop editing!";
		if ( strpos( $config_content, $insert_before ) === false ) {
			$insert_before = "require_once ABSPATH . 'wp-settings.php';";
		}
		
		$new_line = "\n/* TL;DR Pro Encryption Key */\ndefine( 'TLDR_PRO_ENCRYPTION_KEY', '" . $key . "' );\n\n";
		
		$config_content = str_replace( $insert_before, $new_line . $insert_before, $config_content );
		
		return file_put_contents( $config_path, $config_content ) !== false;
	}

	/**
	 * Get encryption key
	 *
	 * @return string
	 */
	private function get_encryption_key() {
		// First try to get from wp-config.php constant
		if ( defined( 'TLDR_PRO_ENCRYPTION_KEY' ) ) {
			return TLDR_PRO_ENCRYPTION_KEY;
		}
		
		// Fallback to database
		$key = get_option( 'tldr_pro_encryption_key' );
		
		if ( ! $key ) {
			$this->generate_encryption_key();
			$key = get_option( 'tldr_pro_encryption_key' );
		}
		
		return $key;
	}

	/**
	 * Encrypt data
	 *
	 * @param string $data Data to encrypt.
	 * @return string|false Encrypted data or false on failure.
	 */
	public function encrypt( $data ) {
		if ( empty( $data ) ) {
			return '';
		}
		
		$key = $this->get_encryption_key();
		
		// Generate IV
		$iv_length = openssl_cipher_iv_length( $this->cipher );
		$iv = openssl_random_pseudo_bytes( $iv_length );
		
		// Encrypt
		$encrypted = openssl_encrypt(
			$data,
			$this->cipher,
			$key,
			OPENSSL_RAW_DATA,
			$iv
		);
		
		if ( false === $encrypted ) {
			return false;
		}
		
		// Combine IV and encrypted data
		$combined = base64_encode( $iv . $encrypted );
		
		// Add HMAC for authentication
		$hmac = hash_hmac( 'sha256', $combined, $key );
		
		return $hmac . ':' . $combined;
	}

	/**
	 * Decrypt data
	 *
	 * @param string $encrypted_data Encrypted data.
	 * @return string|false Decrypted data or false on failure.
	 */
	public function decrypt( $encrypted_data ) {
		if ( empty( $encrypted_data ) ) {
			return '';
		}
		
		$key = $this->get_encryption_key();
		
		// Split HMAC and data
		$parts = explode( ':', $encrypted_data, 2 );
		if ( count( $parts ) !== 2 ) {
			return false;
		}
		
		list( $hmac, $combined ) = $parts;
		
		// Verify HMAC
		$calculated_hmac = hash_hmac( 'sha256', $combined, $key );
		if ( ! hash_equals( $calculated_hmac, $hmac ) ) {
			return false;
		}
		
		// Decode
		$decoded = base64_decode( $combined );
		if ( false === $decoded ) {
			return false;
		}
		
		// Extract IV
		$iv_length = openssl_cipher_iv_length( $this->cipher );
		$iv = substr( $decoded, 0, $iv_length );
		$encrypted = substr( $decoded, $iv_length );
		
		// Decrypt
		$decrypted = openssl_decrypt(
			$encrypted,
			$this->cipher,
			$key,
			OPENSSL_RAW_DATA,
			$iv
		);
		
		return $decrypted;
	}

	/**
	 * Secure API key storage
	 *
	 * @param string $provider Provider name.
	 * @param string $api_key API key to store.
	 * @return bool Success status.
	 */
	public function store_api_key( $provider, $api_key ) {
		if ( empty( $api_key ) ) {
			// Clear validation status when key is removed
			update_option( 'tldr_pro_' . $provider . '_validated', false );
			update_option( 'tldr_pro_' . $provider . '_validated_at', '' );
			return delete_option( 'tldr_pro_' . $provider . '_api_key_encrypted' );
		}
		
		// Check if key has actually changed
		$current_key = $this->get_api_key( $provider );
		$key_changed = ( $current_key !== $api_key );
		
		$encrypted = $this->encrypt( $api_key );
		
		if ( false === $encrypted ) {
			return false;
		}
		
		// Store encrypted version
		update_option( 'tldr_pro_' . $provider . '_api_key_encrypted', $encrypted, false );
		
		// Remove plain text version if exists
		delete_option( 'tldr_pro_' . $provider . '_api_key' );
		
		// Only reset validation status if the key actually changed
		if ( $key_changed ) {
			update_option( 'tldr_pro_' . $provider . '_validated', false );
			update_option( 'tldr_pro_' . $provider . '_validated_at', '' );
		}
		
		return true;
	}

	/**
	 * Retrieve decrypted API key
	 *
	 * @param string $provider Provider name.
	 * @return string|false Decrypted API key or false on failure.
	 */
	public function get_api_key( $provider ) {
		// First check for encrypted version
		$encrypted = get_option( 'tldr_pro_' . $provider . '_api_key_encrypted' );
		
		if ( $encrypted ) {
			return $this->decrypt( $encrypted );
		}
		
		// Check for legacy plain text version
		$plain = get_option( 'tldr_pro_' . $provider . '_api_key' );
		
		if ( $plain ) {
			// Migrate to encrypted storage
			$this->store_api_key( $provider, $plain );
			return $plain;
		}
		
		return '';
	}

	/**
	 * Get masked API key for display
	 *
	 * @param string $provider Provider name.
	 * @return string Masked API key.
	 */
	public function get_masked_api_key( $provider ) {
		$key = $this->get_api_key( $provider );
		
		if ( empty( $key ) ) {
			return '';
		}
		
		$length = strlen( $key );
		
		if ( $length <= 8 ) {
			return str_repeat( '•', $length );
		}
		
		// Show first 3 and last 4 characters
		$visible_start = substr( $key, 0, 3 );
		$visible_end = substr( $key, -4 );
		$masked_middle = str_repeat( '•', $length - 7 );
		
		return $visible_start . $masked_middle . $visible_end;
	}

	/**
	 * Check if API key is validated
	 *
	 * @param string $provider Provider name.
	 * @return array Validation status info.
	 */
	public function get_validation_status( $provider ) {
		$validated = get_option( 'tldr_pro_' . $provider . '_validated', false );
		$validated_at = get_option( 'tldr_pro_' . $provider . '_validated_at', '' );
		$last_test = get_option( 'tldr_pro_' . $provider . '_last_test_result', array() );
		
		// Check if validation is expired (older than 30 days)
		$is_expired = false;
		if ( $validated && $validated_at ) {
			$validated_timestamp = strtotime( $validated_at );
			$thirty_days_ago = strtotime( '-30 days' );
			$is_expired = $validated_timestamp < $thirty_days_ago;
		}
		
		return array(
			'is_validated' => $validated && ! $is_expired,
			'validated_at' => $validated_at,
			'is_expired' => $is_expired,
			'last_test' => $last_test,
			'has_key' => ! empty( $this->get_api_key( $provider ) ),
		);
	}

	/**
	 * Store validation status
	 *
	 * @param string $provider Provider name.
	 * @param bool   $is_valid Validation status.
	 * @param array  $test_result Test result details.
	 * @return void
	 */
	public function store_validation_status( $provider, $is_valid, $test_result = array() ) {
		update_option( 'tldr_pro_' . $provider . '_validated', $is_valid );
		update_option( 'tldr_pro_' . $provider . '_validated_at', current_time( 'mysql' ) );
		update_option( 'tldr_pro_' . $provider . '_last_test_result', $test_result );
	}

	/**
	 * Clear all sensitive data for a provider
	 *
	 * @param string $provider Provider name.
	 * @return void
	 */
	public function clear_provider_data( $provider ) {
		// Only delete encrypted key, not plain text key (which shouldn't exist)
		delete_option( 'tldr_pro_' . $provider . '_api_key_encrypted' );
		delete_option( 'tldr_pro_' . $provider . '_validated' );
		delete_option( 'tldr_pro_' . $provider . '_validated_at' );
		delete_option( 'tldr_pro_' . $provider . '_last_test_result' );
	}

	/**
	 * Export encryption status (for debugging)
	 *
	 * @return array Status information.
	 */
	public function get_encryption_status() {
		return array(
			'cipher' => $this->cipher,
			'key_in_config' => defined( 'TLDR_PRO_ENCRYPTION_KEY' ),
			'key_in_db' => ! empty( get_option( 'tldr_pro_encryption_key' ) ),
			'openssl_available' => function_exists( 'openssl_encrypt' ),
			'providers_encrypted' => array(
				'deepseek' => ! empty( get_option( 'tldr_pro_deepseek_api_key_encrypted' ) ),
				'gemini' => ! empty( get_option( 'tldr_pro_gemini_api_key_encrypted' ) ),
			),
		);
	}
}