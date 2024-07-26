<?php
/**
 * Class Forvoyez_Image_Processor
 *
 * Handles image processing and metadata management for the Forvoyez plugin.
 *
 * @package ForVoyez
 * @since 1.0.0
 */

defined('ABSPATH') || exit('Direct access to this file is not allowed.');

class Forvoyez_Image_Processor {
    /**
     * @var Forvoyez_API_Manager The API client instance.
     */
    private $api_client;

    /**
     * Constructor.
     */
    public function __construct() {
        $api_key = forvoyez_get_api_key();
        $this->api_client = new Forvoyez_API_Manager($api_key);
    }

    /**
     * Initialize the class and set up WordPress hooks.
     */
    public function init() {
        add_action('wp_ajax_forvoyez_analyze_image', [$this, 'ajax_analyze_image']);
        add_action('wp_ajax_forvoyez_update_image_metadata', [$this, 'update_image_metadata']);
        add_action('wp_ajax_forvoyez_load_more_images', [$this, 'load_more_images']);
        add_action('wp_ajax_forvoyez_bulk_analyze_images', [$this, 'bulk_analyze_images']);
        add_action('wp_ajax_forvoyez_analyze_single_image', [$this, 'analyze_single_image']);
        add_action('wp_ajax_forvoyez_process_image_batch', [$this, 'process_image_batch']);
    }

    /**
     * Sanitize image metadata.
     *
     * @param array $metadata Raw metadata.
     * @return array Sanitized metadata.
     */
    private function sanitize_metadata($metadata) {
        $sanitized = [];
        if (isset($metadata['alt_text'])) {
            $sanitized['alt_text'] = sanitize_text_field($metadata['alt_text']);
        }
        if (isset($metadata['title'])) {
            $sanitized['title'] = sanitize_text_field($metadata['title']);
        }
        if (isset($metadata['caption'])) {
            $sanitized['caption'] = wp_kses_post($metadata['caption']);
        }
        return $sanitized;
    }

    /**
     * Update image metadata via AJAX.
     */
    public function update_image_metadata() {
        $this->verify_ajax_request();

        $image_id = isset($_POST['image_id']) ? absint(wp_unslash($_POST['image_id'])) : 0;
        $metadata = isset($_POST['metadata']) ? $this->sanitize_metadata(wp_unslash($_POST['metadata'])) : [];

        if (!$image_id || empty($metadata)) {
            wp_send_json_error('Invalid data');
        }

        $this->update_image_meta($image_id, $metadata);

        wp_send_json_success('Metadata updated successfully');
    }

    /**
     * Update image meta data.
     *
     * @param int $image_id The image ID.
     * @param array $metadata The metadata to update.
     */
    private function update_image_meta($image_id, $metadata) {
        if (isset($metadata['alt_text'])) {
            update_post_meta($image_id, '_wp_attachment_image_alt', $metadata['alt_text']);
        }

        $post_data = [];
        if (isset($metadata['title'])) {
            $post_data['post_title'] = $metadata['title'];
        }
        if (isset($metadata['caption'])) {
            $post_data['post_excerpt'] = $metadata['caption'];
        }

        if (!empty($post_data)) {
            $post_data['ID'] = $image_id;
            wp_update_post($post_data);
        }

        update_post_meta($image_id, '_forvoyez_analyzed', true);
    }

    /**
     * Analyze image via AJAX.
     */
    public function ajax_analyze_image() {
        $this->verify_ajax_request();

        $image_id = isset($_POST['image_id']) ? absint(wp_unslash($_POST['image_id'])) : 0;

        if (!$image_id || !wp_attachment_is_image($image_id)) {
            wp_send_json_error('Invalid image ID');
        }

        $result = $this->api_client->analyze_image($image_id);

        if ($result['success']) {
            wp_send_json_success([
                'message' => 'Analysis successful',
                'metadata' => $result['metadata'],
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['error']['message'],
                'code' => $result['error']['code'] ?? 'unknown_error',
            ]);
        }
    }

    /**
     * Load more images via AJAX.
     */
    public function load_more_images() {
        check_ajax_referer('forvoyez_nonce', 'nonce');

        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 21;

        $images = $this->get_incomplete_images($offset, $limit);

        ob_start();
        foreach ($images as $image) {
            Forvoyez_Image_Renderer::render_image_item($image);
        }
        $html = ob_get_clean();

        wp_send_json_success([
            'html' => $html,
            'count' => count($images),
            'total' => forvoyez_count_incomplete_images(),
        ]);
    }

    /**
     * Get incomplete images.
     *
     * @param int $offset Offset for query.
     * @param int $limit Limit for query.
     * @return array Array of image posts.
     */
    private function get_incomplete_images($offset, $limit) {
        $args = [
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => $limit,
            'offset' => $offset,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_wp_attachment_image_alt',
                    'value' => '',
                    'compare' => '=',
                ],
                [
                    'key' => '_wp_attachment_image_alt',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ];

        $query_images = new WP_Query($args);
        return array_filter($query_images->posts, [$this, 'is_image_incomplete']);
    }

    /**
     * Check if an image is incomplete (missing title, alt text, or caption).
     *
     * @param WP_Post $image The image post object.
     * @return bool True if the image is incomplete, false otherwise.
     */
    private function is_image_incomplete($image) {
        return empty($image->post_title) ||
            empty(get_post_meta($image->ID, '_wp_attachment_image_alt', true)) ||
            empty($image->post_excerpt);
    }

    /**
     * Bulk analyze images via AJAX.
     */
    public function bulk_analyze_images() {
        $this->verify_ajax_request();

        $image_ids = isset($_POST['image_ids']) ? array_map('absint', wp_unslash($_POST['image_ids'])) : [];
        $image_ids = array_filter($image_ids, 'wp_attachment_is_image');

        if (empty($image_ids)) {
            wp_send_json_error('No valid images selected');
        }

        wp_send_json_success([
            'message' => 'Processing started',
            'total' => count($image_ids),
            'image_ids' => $image_ids,
        ]);
    }

    /**
     * Analyze a single image via AJAX.
     */
    public function analyze_single_image() {
        $this->verify_ajax_request();

        $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;

        if (!$image_id) {
            wp_send_json_error('Invalid image ID');
        }

        $result = $this->api_client->analyze_image($image_id);

        wp_send_json_success($result);
    }

    /**
     * Process a batch of images via AJAX.
     */
    public function process_image_batch() {
        $this->verify_ajax_request();

        $image_ids = isset($_POST['image_ids']) ? array_map('intval', $_POST['image_ids']) : [];

        if (empty($image_ids)) {
            wp_send_json_error('No images provided');
        }

        $results = $this->process_images($image_ids);

        wp_send_json_success(['results' => $results]);
    }

    /**
     * Process multiple images.
     *
     * @param array $image_ids Array of image IDs to process.
     * @return array Results of image processing.
     */
    private function process_images($image_ids) {
        $results = [];
        foreach ($image_ids as $image_id) {
            $result = $this->api_client->analyze_image($image_id);
            $results[] = [
                'id' => $image_id,
                'success' => $result['success'],
                'message' => $result['success'] ? $result['message'] : $result['error']['message'],
                'code' => $result['success'] ? null : ($result['error']['code'] ?? 'unknown_error'),
                'metadata' => $result['success'] ? $result['metadata'] : null,
            ];
        }
        return $results;
    }

    /**
     * Verify AJAX request.
     *
     * @throws WP_Error If the request is invalid or user doesn't have permission.
     */
    private function verify_ajax_request() {
        check_ajax_referer('forvoyez_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error('Permission denied');
        }
    }
}