<?php

class Forvoyez_Settings
{
    public function init()
    {
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings()
    {
        register_setting('forvoyez_settings', 'forvoyez_api_key', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_api_key'),
            'default' => ''
        ));
    }

    public function sanitize_api_key($key)
    {
        return preg_replace('/[^a-zA-Z0-9]/', '', $key);
    }
}

?>