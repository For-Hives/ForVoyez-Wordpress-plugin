<?php
defined('ABSPATH') || exit;

class Forvoyez_API_Manager
{
    private $api_key;

    public function init()
    {
        add_action('wp_ajax_forvoyez_verify_api_key', array($this, 'verify_api_key'));
    }

    public function verify_api_key()
    {
//        here is the logic to verify the API key
    }
}