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
        add_action('wp_ajax_forvoyez_verify_api_key', [$this, 'verify_api_key']);
    }

    /**
     * Verify the API key.
     *
     * @return array Response data
     */
    public function verify_api_key(): array {
        error_log('Beginning of verify_api_key');

        try {
            check_ajax_referer('forvoyez_nonce', 'nonce');
        } catch (Exception $e) {
            error_log('Exception during nonce verification: ' . $e->getMessage());
            return $this->error_response('Invalid nonce', 403);
        }

        if (!current_user_can('manage_options')) {
            error_log('User without permission');
            return $this->error_response(self::ERROR_PERMISSION_DENIED, 403);
        }

        $api_key = forvoyez_get_api_key();
        error_log('API Key retrieved: ' . ($api_key ? 'Not empty' : 'Empty'));

        if (empty($api_key)) {
            return $this->error_response(self::ERROR_API_KEY_NOT_SET, 400);
        }

        $verification_result = $this->perform_api_key_verification($api_key);
        error_log('Verification result: ' . ($verification_result ? 'Success' : 'Failure'));

        if ($verification_result) {
            return $this->success_response(self::SUCCESS_API_KEY_VALID);
        } else {
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
        error_log('Verifying API key: ' . $api_key);
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
        return [
            'success' => true,
            'data' => $message,
        ];
    }
}