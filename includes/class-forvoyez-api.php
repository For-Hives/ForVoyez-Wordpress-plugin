<?php
/**
 * Class Forvoyez_API
 *
 * Handles API-related functionality for the ForVoyez plugin.
 *
 * @package ForVoyez
 * @since 1.0.0
 */

defined('ABSPATH') || exit('Direct access to this file is not allowed.');

class Forvoyez_API {
    /**
     * Initialize the API hooks.
     *
     * @return void
     */
    public function init() {
        add_action('wp_ajax_forvoyez_verify_api_key', [$this, 'verify_api_key']);
    }

    /**
     * Verify the API key.
     *
     * @return void
     */
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

    /**
     * Sanitize the API key.
     *
     * @param string $key The API key to sanitize.
     * @return string The sanitized API key.
     */
    public function sanitize_api_key($key) {
        return sanitize_text_field($key);
    }

    /**
     * Validate the API key format.
     *
     * @param string $key The API key to validate.
     * @return bool True if the key is valid, false otherwise.
     */
    public function validate_api_key_format($key) {
        // Add your validation logic here
        // For example, check if it's a valid JWT token
        return (bool) preg_match('/^[A-Za-z0-9-_=]+\.[A-Za-z0-9-_=]+\.?[A-Za-z0-9-_.+/=]*$/', $key);
    }
}