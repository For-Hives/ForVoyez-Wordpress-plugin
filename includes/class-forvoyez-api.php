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
    public const ERROR_PERMISSION_DENIED = 'Permission denied';
    public const ERROR_API_KEY_NOT_SET = 'API key is not set';
    public const ERROR_API_KEY_INVALID = 'API key is invalid';
    public const SUCCESS_API_KEY_VALID = 'API key is valid';

    private $logger;

    /**
     * Forvoyez_API constructor.
     *
     * @param LoggerInterface $logger Logger instance.
     */
    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
    }

    /**
     * Initialize the API hooks.
     *
     * @return void
     */
    public function init(): void {
        $this->logger->info("Initializing Forvoyez_API");
        add_action('wp_ajax_forvoyez_verify_api_key', [$this, 'verify_api_key']);
    }

    /**
     * Verify the API key.
     *
     * @return array Response data
     */
    public function verify_api_key(): array {
        $this->logger->info("Starting verify_api_key method");

        try {
            $this->check_nonce();
            $this->check_user_capability();

            $api_key = $this->get_api_key();

            if (empty($api_key)) {
                return $this->error_response(self::ERROR_API_KEY_NOT_SET, 400);
            }

            $verification_result = $this->perform_api_key_verification($api_key);

            return $verification_result
                ? $this->success_response(self::SUCCESS_API_KEY_VALID)
                : $this->error_response(self::ERROR_API_KEY_INVALID, 400);

        } catch (Exception $e) {
            $this->logger->error("Error in verify_api_key: " . $e->getMessage());
            return $this->error_response($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Check nonce for AJAX requests.
     *
     * @throws Exception If nonce check fails.
     */
    private function check_nonce(): void {
        $this->logger->info("Checking nonce");
        if (!check_ajax_referer('forvoyez_nonce', 'nonce', false)) {
            throw new Exception('Invalid nonce', 403);
        }
    }

    /**
     * Check user capability.
     *
     * @throws Exception If user doesn't have required capability.
     */
    private function check_user_capability(): void {
        $this->logger->info("Checking user capabilities");
        if (!current_user_can('manage_options')) {
            throw new Exception(self::ERROR_PERMISSION_DENIED, 403);
        }
    }

    /**
     * Get the API key.
     *
     * @return string
     */
    private function get_api_key(): string {
        $this->logger->info("Getting API key");
        return forvoyez_get_api_key();
    }

    /**
     * Perform the actual API key verification.
     *
     * @param string $api_key The API key to verify.
     * @return bool True if the API key is valid, false otherwise.
     */
    protected function perform_api_key_verification(string $api_key): bool {
        $this->logger->info("Verifying API key: " . substr($api_key, 0, 5) . '...');
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
        $this->logger->info("Creating error response: $message (Status: $status)");
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
        $this->logger->info("Creating success response: $message");
        return [
            'success' => true,
            'data' => $message,
        ];
    }
}