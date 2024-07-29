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

    public function verify_api_key(): void {
        check_ajax_referer('forvoyez_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(self::ERROR_PERMISSION_DENIED, 403);
        }

        $api_key = forvoyez_get_api_key();
        if (empty($api_key)) {
            wp_send_json_error(self::ERROR_API_KEY_NOT_SET, 400);
        }

        $verification_result = $this->perform_api_key_verification($api_key);

        if ($verification_result) {
            wp_send_json_success(self::SUCCESS_API_KEY_VALID);
        } else {
            wp_send_json_error(self::ERROR_API_KEY_INVALID, 400);
        }
    }

    protected function perform_api_key_verification(string $api_key): bool {
        // TODO: Implement actual API key verification logic here
        return true;
    }
}