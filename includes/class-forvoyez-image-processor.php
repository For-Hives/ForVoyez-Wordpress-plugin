<?php
defined('ABSPATH') || exit;

class Forvoyez_Image_Processor
{
    private $api_client;

    public function __construct()
    {
        $api_key = forvoyez_get_api_key();
        $this->api_client = new Forvoyez_API_Manager($api_key);
    }

    public function init()
    {
        add_action('wp_ajax_forvoyez_analyze_image', array($this, 'analyze_image'));
        add_action('wp_ajax_forvoyez_update_image_metadata', array($this, 'update_image_metadata'));
        add_action('wp_ajax_forvoyez_load_more_images', array($this, 'load_more_images'));
        add_action('wp_ajax_forvoyez_bulk_analyze_images', array($this, 'bulk_analyze_images'));
    }

    public function analyze_image()
    {
        check_ajax_referer('forvoyez_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error('Permission denied');
        }

        $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;

        if (!$image_id) {
            wp_send_json_error('Invalid image ID');
        }

        forvoyez_log('Starting analysis for image ID: ' . $image_id);

        $result = $this->analyze_single_image($image_id);

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => 'Analysis successful',
                'metadata' => $result['metadata']
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }

    public function update_image_metadata()
    {
        check_ajax_referer('forvoyez_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error('Permission denied');
        }

        $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
        $metadata = isset($_POST['metadata']) ? $_POST['metadata'] : array();

        if (!$image_id || empty($metadata)) {
            wp_send_json_error('Invalid data');
        }

        // Update alt text
        if (isset($metadata['alt_text'])) {
            update_post_meta($image_id, '_wp_attachment_image_alt', sanitize_text_field($metadata['alt_text']));
        }

        // Update title
        if (isset($metadata['title'])) {
            wp_update_post(array(
                'ID' => $image_id,
                'post_title' => sanitize_text_field($metadata['title']),
            ));
        }

        // Update caption
        if (isset($metadata['caption'])) {
            wp_update_post(array(
                'ID' => $image_id,
                'post_excerpt' => wp_kses_post($metadata['caption']),
            ));
        }

        // Mark as analyzed
        update_post_meta($image_id, '_forvoyez_analyzed', true);

        wp_send_json_success('Metadata updated successfully');
    }

    public function load_more_images()
    {
        check_ajax_referer('forvoyez_nonce', 'nonce');

        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 21;

        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => $limit,
            'offset' => $offset,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_wp_attachment_image_alt',
                    'value' => '',
                    'compare' => '='
                ),
                array(
                    'key' => '_wp_attachment_image_alt',
                    'compare' => 'NOT EXISTS'
                )
            )
        );

        $query_images = new WP_Query($args);
        $html = '';
        $count = 0;

        ob_start();
        foreach ($query_images->posts as $image) {
            if (empty($image->post_title) || empty(get_post_meta($image->ID, '_wp_attachment_image_alt', true)) || empty($image->post_excerpt)) {
                Forvoyez_Image_Renderer::render_image_item($image);
                $count++;
            }
        }
        $html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $html,
            'count' => $count,
            'total' => forvoyez_count_incomplete_images()
        ));
    }

    public function bulk_analyze_images() {
        check_ajax_referer('forvoyez_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error('Permission denied');
        }

        $image_ids = isset($_POST['image_ids']) ? array_map('intval', $_POST['image_ids']) : array();

        if (empty($image_ids)) {
            wp_send_json_error('No images selected');
        }

        $results = array();

        foreach ($image_ids as $image_id) {
            $result = $this->analyze_single_image($image_id);
            $results[] = array(
                'id' => $image_id,
                'success' => $result['success'],
                'message' => $result['message'],
                'metadata' => $result['metadata']
            );
        }

        wp_send_json_success($results);
    }

    private function analyze_single_image($image_id) {
        $image_url = wp_get_attachment_url($image_id);
        if (!$image_url) {
            return array('success' => false, 'message' => 'Image not found', 'metadata' => null);
        }

        $result = $this->api_client->analyze_image($image_url);

        if (!$result['success']) {
            return $result;
        }

        $metadata = array(
            'alt_text' => $result['data']['alt_text'] ?? '',
            'title' => $result['data']['title'] ?? '',
            'caption' => $result['data']['caption'] ?? ''
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