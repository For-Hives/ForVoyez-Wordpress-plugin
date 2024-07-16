<?php
defined('ABSPATH') || exit;

class Forvoyez_Settings
{
    private $encryption_key;

    public function __construct()
    {
        $this->encryption_key = $this->generate_site_specific_key();
    }

    public function init()
    {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_forvoyez_save_api_key', array($this, 'ajax_save_api_key'));
        add_action('wp_ajax_forvoyez_bulk_analyze_images', array($this, 'bulk_analyze_images'));
    }

    public function register_settings()
    {
        register_setting('forvoyez_settings', 'forvoyez_encrypted_api_key');
    }

    public function ajax_save_api_key()
    {
        check_ajax_referer('forvoyez_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

        $encrypted_api_key = $this->encrypt($api_key);
        update_option('forvoyez_encrypted_api_key', $encrypted_api_key);

        wp_send_json_success('API key saved successfully');
    }

    public function get_api_key()
    {
        $encrypted_api_key = get_option('forvoyez_encrypted_api_key');
        if (empty($encrypted_api_key)) {
            return '';
        }

        return $this->decrypt($encrypted_api_key);
    }

    private function encrypt($data)
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $this->encryption_key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }

    private function decrypt($data)
    {
        if (empty($data)) {
            return '';
        }

        $decoded = base64_decode($data);
        if ($decoded === false) {
            return '';
        }

        list($encrypted_data, $iv) = array_pad(explode('::', $decoded, 2), 2, null);
        if ($iv === null) {
            return '';
        }

        $decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', $this->encryption_key, 0, $iv);
        return $decrypted !== false ? $decrypted : '';
    }

    private function generate_site_specific_key()
    {
        $site_url = get_site_url();
        $auth_key = defined('AUTH_KEY') ? AUTH_KEY : '';
        $secure_auth_key = defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : '';

        $raw_key = $site_url . $auth_key . $secure_auth_key;

        return hash('sha256', $raw_key, true);
    }
}