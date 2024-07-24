<?php
defined('ABSPATH') || exit();

class Forvoyez_API {
	public function init() {
		add_action('wp_ajax_forvoyez_verify_api_key', [
			$this,
			'verify_api_key',
		]);
	}

	public function verify_api_key() {
		$api_key = forvoyez_get_api_key();
		if (empty($api_key)) {
			wp_send_json_error('API key is not set');
		}

		// Implement your API key verification logic here
		// Make a call to ForVoyez API to verify the key

		// For now, we'll just simulate a successful verification
		wp_send_json_success('API key is valid');
	}
}
