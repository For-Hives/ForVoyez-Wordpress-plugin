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

if (!function_exists('custom_error_log')) {
    function custom_error_log($message) {
        error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, '/tmp/forvoyez-debug.log');
    }
}

class Forvoyez_API {
    const ERROR_PERMISSION_DENIED = 'Permission denied';
    const ERROR_API_KEY_NOT_SET = 'API key is not set';
    const ERROR_API_KEY_INVALID = 'API key is invalid';
    const SUCCESS_API_KEY_VALID = 'API key is valid';

    /**
     * Initialize the API hooks.
     *
     * @return void
     */
    public function init(): void {
        custom_error_log("Initializing Forvoyez_API");
        add_action('wp_ajax_forvoyez_verify_api_key', [$this, 'verify_api_key']);
    }

    /**
     * Verify the API key.
     *
     * @return array Response data
     */
    public function verify_api_key(): array {
        custom_error_log("Starting verify_api_key method");

        try {
            custom_error_log("Checking nonce");
            check_ajax_referer('forvoyez_nonce', 'nonce');
            custom_error_log("Nonce check passed");
        } catch (Exception $e) {
            custom_error_log("Nonce check failed: " . $e->getMessage());
            return $this->error_response('Invalid nonce', 403);
        }

        custom_error_log("Checking user capabilities");
        if (!current_user_can('manage_options')) {
            custom_error_log("User does not have manage_options capability");
            return $this->error_response(self::ERROR_PERMISSION_DENIED, 403);
        }
        custom_error_log("User has required capabilities");

        custom_error_log("Getting API key");
        $api_key = forvoyez_get_api_key();
        custom_error_log("API key retrieved: " . (empty($api_key) ? 'Empty' : 'Not empty'));

        if (empty($api_key)) {
            custom_error_log("API key is empty");
            return $this->error_response(self::ERROR_API_KEY_NOT_SET, 400);
        }

        custom_error_log("Performing API key verification");
        $verification_result = $this->perform_api_key_verification($api_key);
        custom_error_log("Verification result: " . ($verification_result ? 'Success' : 'Failure'));

        if ($verification_result) {
            custom_error_log("Returning success response");
            return $this->success_response(self::SUCCESS_API_KEY_VALID);
        } else {
            custom_error_log("Returning error response for invalid API key");
            return $this->error_response(self::ERROR_API_KEY_INVALID, 400);
        }
    }

    /**
     * Perform the actual API key verification.
     *
     * @param string $api_key The API key to verify.
     * @return bool True if the API key is valid, false otherwise.
     */
    protected function perform_api_key_verification(string $api_key): bool {
        custom_error_log("Verifying API key: " . $api_key);
        // TODO: Implement actual API key verification logic here
        return true;
    }

    /**
     * Format an error response.
     *
     * @param string $message Error message.
     * @param int $status HTTP status code.
     * @return array Formatted error response.
     */
    private function error_response(string $message, int $status = 400): array {
        custom_error_log("Creating error response: $message (Status: $status)");
        return [
            'success' => false,
            'data' => $message,
            'status' => $status,
        ];
    }

    /**
     * Format a success response.
     *
     * @param string $message Success message.
     * @return array Formatted success response.
     */
    private function success_response(string $message): array {
        custom_error_log("Creating success response: $message");
        return [
            'success' => true,
            'data' => $message,
        ];
    }
}