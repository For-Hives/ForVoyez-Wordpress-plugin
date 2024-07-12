<?php
defined('ABSPATH') || exit;

class Forvoyez_Image_Processor
{
    public function init()
    {
        add_action('wp_ajax_forvoyez_analyze_image', array($this, 'analyze_image'));
        add_action('wp_ajax_forvoyez_update_image_metadata', array($this, 'update_image_metadata'));
        add_action('wp_ajax_forvoyez_load_more_images', array($this, 'load_more_images'));
    }

    public function analyze_image()
    {
        // Implement image analysis logic here
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

    public function bulk_analyze_images()
    {
        check_ajax_referer('forvoyez_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error('Permission denied');
        }

        $image_ids = isset($_POST['image_ids']) ? array_map('intval', $_POST['image_ids']) : array();

        if (empty($image_ids)) {
            wp_send_json_error('No images selected');
        }

        $processed = 0;
        $processed_ids = array();

        foreach ($image_ids as $image_id) {
            // Here, implement your actual image analysis logic
            // This is a placeholder for the actual API call and metadata update
            $result = $this->analyze_single_image($image_id);

            if ($result) {
                $processed++;
                $processed_ids[] = $image_id;
            }
        }

        wp_send_json_success(array(
            'processed' => $processed,
            'processed_ids' => $processed_ids
        ));
    }

    private function analyze_single_image($image_id)
    {
        $api_key = forvoyez_get_api_key();
        if (empty($api_key)) {
            // Handle the case where the API key is not set
            return false;
        }

        // Use $api_key to make API calls to ForVoyez
        // Implement your actual API call and metadata update logic here

        // For now, we'll just simulate a successful analysis
        update_post_meta($image_id, '_forvoyez_analyzed', true);

        return true;
    }
}