<?php
defined('ABSPATH') || exit;

class Forvoyez_API_Manager
{
    private $api_key;
    private $api_url = 'https://forvoyez.com/api/v1/describe';

    public function __construct($api_key)
    {
        $this->api_key = $api_key;
    }

    public function init()
    {
        add_action('wp_ajax_forvoyez_verify_api_key', array($this, 'verify_api_key'));
    }

    public function verify_api_key()
    {
//        here is the logic to verify the API key
    }

//    others methods here (with the logic to interact with the ForVoyez API)
    public function analyze_image($image_url)
    {
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'image_url' => $image_url
            )),
            'method' => 'POST',
            'timeout' => 60
        );

        $response = wp_remote_post($this->api_url, $args);

        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            return array('success' => false, 'message' => $data['error']);
        }

        return array('success' => true, 'data' => $data);
    }
}