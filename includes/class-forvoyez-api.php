<?php
defined('ABSPATH') || exit('Direct access to this file is not allowed.');

class Forvoyez_API {
    const ERROR_PERMISSION_DENIED = 'Permission denied';
    const ERROR_API_KEY_NOT_SET = 'API key is not set';
    const ERROR_API_KEY_INVALID = 'API key is invalid';
    const SUCCESS_API_KEY_VALID = 'API key is valid';

    public function init(): void {
        add_action('wp_ajax_forvoyez_verify_api_key', [$this, 'verify_api_key']);
    }

    public function verify_api_key(): array {
        check_ajax_referer('forvoyez_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            return $this->error_response(self::ERROR_PERMISSION_DENIED, 403);
        }

        $api_key = forvoyez_get_api_key();
        if (empty($api_key)) {
            return $this->error_response(self::ERROR_API_KEY_NOT_SET, 400);
        }

        $verification_result = $this->perform_api_key_verification($api_key);

        if ($verification_result) {
            return $this->success_response(self::SUCCESS_API_KEY_VALID);
        } else {
            return $this->error_response(self::ERROR_API_KEY_INVALID, 400);
        }
    }

    protected function perform_api_key_verification(string $api_key): bool {
        // TODO: Implement actual API key verification logic here
        return true;
    }

    private function error_response(string $message, int $status = 400): array {
        return [
            'success' => false,
            'data' => $message,
            'status' => $status,
        ];
    }

    private function success_response(string $message): array {
        return [
            'success' => true,
            'data' => $message,
        ];
    }
}