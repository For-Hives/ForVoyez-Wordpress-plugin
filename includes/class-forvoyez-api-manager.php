<?php
/**
 * Class Forvoyez_API_Manager
 *
 * Manages API interactions for the ForVoyez plugin.
 *
 * @package ForVoyez
 * @since 1.0.0
 */

defined('ABSPATH') || exit('Direct access to this file is not allowed.');

class Forvoyez_API_Manager {
    /**
     * @var string The API key for ForVoyez service.
     */
    private $api_key;

    /**
     * @var string The URL of the ForVoyez API endpoint.
     */
    private $api_url = 'https://forvoyez.com/api/describe';

    /**
     * Constructor.
     *
     * @param string $api_key The API key for ForVoyez service.
     */
    public function __construct(string $api_key) {
        $this->api_key = $api_key;
    }

    /**
     * Initialize the API manager.
     *
     * @return void
     */
    public function init(): void {
        add_action('wp_ajax_forvoyez_verify_api_key', [$this, 'verify_api_key']);
    }

    /**
     * Verify the API key.
     */
    public function verify_api_key() {
        $api_key = forvoyez_get_api_key();
        if (empty($api_key)) {
            return ['success' => false, 'message' => 'API key is not set'];
        }

        $response = wp_remote_get($this->api_url . '/verify', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
            ],
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (wp_remote_retrieve_response_code($response) === 200) {
            return ['success' => true, 'message' => 'API key is valid'];
        } else {
            return ['success' => false, 'message' => $data['error'] ?? 'Invalid API key'];
        }
    }

    /**
     * Analyze an image using the ForVoyez API.
     *
     * @param int $image_id The ID of the image to analyze.
     * @return array The analysis result.
     */
    public function analyze_image(int $image_id): array {
        $image_path = get_attached_file($image_id);
        if (!$image_path) {
            return $this->format_error('image_not_found', 'Image not found');
        }

        $image_url = wp_get_attachment_url($image_id);
        $image_mime = get_post_mime_type($image_id);
        $image_name = basename($image_path);

        $file_data = file_get_contents($image_path);
        if ($file_data === false) {
            return $this->format_error('read_error', 'Failed to read image file');
        }

        $data = [
            'data' => json_encode([
                'context' => '',
                'schema' => [
                    'title' => 'string',
                    'alternativeText' => 'string',
                    'caption' => 'string'
                ]
            ])
        ];

        $boundary = wp_generate_password(24);
        $delimiter = '-------------' . $boundary;

        $post_data = $this->build_data_files($boundary, $data, $image_name, $image_mime, $file_data);

        $args = [
            'method' => 'POST',
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'multipart/form-data; boundary=' . $delimiter,
                'Content-Length' => strlen($post_data)
            ],
            'body' => $post_data,
        ];

        $response = wp_remote_post($this->api_url, $args);

        if (is_wp_error($response)) {
            return $this->format_error('api_request_failed', $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->format_error('json_decode_error', 'Failed to decode API response', [
                'response_code' => wp_remote_retrieve_response_code($response),
                'body' => substr($body, 0, 1000),
                'image_url' => $image_url,
                'api_url' => $this->api_url,
            ]);
        }

        if (isset($data['error'])) {
            return $this->format_error('api_error', $data['error']);
        }

        $metadata = [
            'alt_text' => $data['alternativeText'] ?? '',
            'title' => $data['title'] ?? '',
            'caption' => $data['caption'] ?? ''
        ];

        $this->update_image_metadata($image_id, $metadata);

        return [
            'success' => true,
            'message' => 'Analysis successful',
            'metadata' => $metadata,
        ];
    }

    /**
     * Update image metadata in WordPress.
     *
     * @param int $image_id The ID of the image to update.
     * @param array $metadata The metadata to update.
     * @return void
     */
    private function update_image_metadata(int $image_id, array $metadata): void {
        update_post_meta($image_id, '_wp_attachment_image_alt', $metadata['alt_text']);
        wp_update_post([
            'ID' => $image_id,
            'post_title' => $metadata['title'],
            'post_excerpt' => $metadata['caption'],
        ]);
        update_post_meta($image_id, '_forvoyez_analyzed', 1);
    }

    /**
     * Format an error response.
     *
     * @param string $code The error code.
     * @param string $message The error message.
     * @param array|null $debug_info Optional debug information.
     * @return array The formatted error.
     */
    private function format_error(string $code, string $message, ?array $debug_info = null): array {
        $error = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($debug_info) {
            $error['debug_info'] = $debug_info;
        }

        return $error;
    }

    /**
     * Build multipart data for file upload.
     *
     * @param string $boundary The boundary string for multipart data.
     * @param array $fields The fields to include in the data.
     * @param string $file_name The name of the file.
     * @param string $file_mime The MIME type of the file.
     * @param string $file_data The file data.
     * @return string The built multipart data.
     */
    private function build_data_files(string $boundary, array $fields, string $file_name, string $file_mime, string $file_data): string {
        $data = '';
        $delimiter = '-------------' . $boundary;

        foreach ($fields as $name => $content) {
            $data .= "--{$delimiter}\r\n";
            $data .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
            $data .= "{$content}\r\n";
        }

        $data .= "--{$delimiter}\r\n";
        $data .= "Content-Disposition: form-data; name=\"image\"; filename=\"{$file_name}\"\r\n";
        $data .= "Content-Type: {$file_mime}\r\n\r\n";
        $data .= $file_data . "\r\n";
        $data .= "--{$delimiter}--\r\n";

        return $data;
    }
}