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
    public function analyze_image($image_id) {
        $image_url = wp_get_attachment_url($image_id);
        if (!$image_url) {
            return array('success' => false, 'message' => 'Image not found', 'metadata' => null);
        }

        $response = wp_remote_post($this->api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'image_url' => $image_url
            ))
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            return array('success' => false, 'message' => $data['error']);
        }

        $metadata = array(
            'alt_text' => $data['alt_text'] ?? '',
            'title' => $data['title'] ?? '',
            'caption' => $data['caption'] ?? ''
        );

        update_post_meta($image_id, '_wp_attachment_image_alt', $metadata['alt_text']);
        wp_update_post(array(
            'ID' => $image_id,
            'post_title' => $metadata['title'],
            'post_excerpt' => $metadata['caption']
        ));
        update_post_meta($image_id, '_forvoyez_analyzed', true);

        return array('success' => true, 'message' => 'Analysis successful', 'metadata' => $metadata);
    }
}