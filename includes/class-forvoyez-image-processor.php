<?php
defined('ABSPATH') || exit;

class Forvoyez_Image_Processor
{
    public function init()
    {
        add_action('wp_ajax_forvoyez_analyze_image', array($this, 'analyze_image'));
        add_action('wp_ajax_forvoyez_update_image_metadata', array($this, 'update_image_metadata'));
        add_action('wp_ajax_forvoyez_load_more_images', array($this, 'load_more_images'));
        add_action('wp_ajax_forvoyez_bulk_analyze_images', array($this, 'bulk_analyze_images'));
    }

    public function analyze_image()
    {
        // TODO: Implement image analysis logic here
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
        $api_key = forvoyez_get_api_key();
        if (empty($api_key)) {
            return array('success' => false, 'message' => 'API key not set', 'metadata' => null);
        }

//        TODO: HERE IS THE LOGIC TO IMPLEMENT WITH THE API
        $metadata = array(
            'alt_text' => 'Generated alt text for image ' . $image_id,
            'title' => 'Generated title for image ' . $image_id,
            'caption' => 'Generated caption for image ' . $image_id
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