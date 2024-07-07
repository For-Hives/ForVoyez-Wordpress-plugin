<?php

class Forvoyez_Settings
{
    public function init()
    {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_forvoyez_save_api_key', array($this, 'ajax_save_api_key'));
    }

    public function register_settings()
    {
        register_setting('forvoyez_settings', 'forvoyez_api_key');
    }

    public function ajax_save_api_key()
    {
        check_ajax_referer('forvoyez_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

        update_option('forvoyez_api_key', $api_key);
        wp_send_json_success('API key saved successfully');
    }
}