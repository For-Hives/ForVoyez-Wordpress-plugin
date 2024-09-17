<?php
/**
 * Class Forvoyez_Settings
 *
 * Handles the plugin settings, including API key encryption and decryption.
 *
 * @package ForVoyez
 * @since 1.0.0
 */

defined('ABSPATH') || exit('Direct access to this file is not allowed.');

class Forvoyez_Settings
{
	/**
	 * @var string The encryption key used for API key encryption/decryption.
	 */
	private $encryption_key;

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->encryption_key = $this->generate_site_specific_key();
	}

	/**
	 * Initialize the settings.
	 */
	public function init()
	{
		add_action('admin_init', [ $this, 'register_settings' ]);
		add_action(
			'wp_ajax_forvoyez_save_api_key',
			[
				$this,
				'ajax_save_api_key',
			]
		);
	}

	/**
	 * Register the plugin settings.
	 */
	public function register_settings()
	{
		if (!current_user_can('manage_options')) {
			return;
		}
		register_setting(
			'forvoyez_settings',
			'forvoyez_encrypted_api_key',
			[
				'type' => 'string',
				'sanitize_callback' => [ $this, 'sanitize_api_key' ],
				'default' => '',
			]
		);
	}

	/**
	 * AJAX callback to save the API key.
	 */
	public function ajax_save_api_key()
	{
		check_ajax_referer('forvoyez_save_api_key_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(
				esc_html__('Permission denied', 'auto-alt-text-for-images'),
				403,
			);
		}

		$api_key = isset($_POST['api_key'])
			? sanitize_text_field(wp_unslash($_POST['api_key']))
			: '';

		if (empty($api_key)) {
			wp_send_json_error(
				esc_html__(
					'API key cannot be empty',
					'auto-alt-text-for-images',
				),
				400,
			);
		}

		$encrypted_api_key = $this->encrypt($api_key);
		update_option('forvoyez_encrypted_api_key', $encrypted_api_key);

		wp_send_json_success(
			esc_html__(
				'API key saved successfully',
				'auto-alt-text-for-images',
			),
		);
	}

	/**
	 * Get the decrypted API key.
	 *
	 * @return string The decrypted API key or an empty string if not set.
	 */
	public function get_api_key()
	{
		$encrypted_api_key = get_option('forvoyez_encrypted_api_key');
		if (empty($encrypted_api_key)) {
			return '';
		}

		return $this->decrypt($encrypted_api_key);
	}

	/**
	 * Encrypt the given data.
	 *
	 * @param string $data The data to encrypt.
	 * @return string The encrypted data.
	 */
	private function encrypt($data)
	{
		$iv = openssl_random_pseudo_bytes(
			openssl_cipher_iv_length('aes-256-cbc'),
		);
		$encrypted = openssl_encrypt(
			$data,
			'aes-256-cbc',
			$this->encryption_key,
			0,
			$iv,
		);

		return base64_encode($encrypted . '::' . $iv);
	}

	/**
	 * Decrypt the given data.
	 *
	 * @param string $data The data to decrypt.
	 * @return string The decrypted data or an empty string if decryption fails.
	 */
	private function decrypt($data)
	{
		if (empty($data)) {
			return '';
		}

		$decoded = base64_decode($data);
		if ($decoded === false) {
			return '';
		}

		[$encrypted_data, $iv] = array_pad(explode('::', $decoded, 2), 2, null);
		if ($iv === null) {
			return '';
		}

		$decrypted = openssl_decrypt(
			$encrypted_data,
			'aes-256-cbc',
			$this->encryption_key,
			0,
			$iv,
		);

		return $decrypted !== false ? $decrypted : '';
	}

	/**
	 * Generate a site-specific encryption key.
	 *
	 * @return string The generated encryption key.
	 */
	private function generate_site_specific_key()
	{
		$site_url = get_site_url();
		$auth_key = defined('AUTH_KEY') ? AUTH_KEY : '';
		$secure_auth_key = defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : '';

		$raw_key = $site_url . $auth_key . $secure_auth_key;

		return hash('sha256', $raw_key, true);
	}

	/**
	 * Sanitize the API key before saving.
	 *
	 * @param string $api_key The API key to sanitize.
	 * @return string The sanitized API key.
	 */
	public function sanitize_api_key($api_key)
	{
		return sanitize_text_field($api_key);
	}
}
