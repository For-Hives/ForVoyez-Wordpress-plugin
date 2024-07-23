<?php
defined( 'ABSPATH' ) || exit;

class Forvoyez_API_Manager {

	private $api_key;
	private $api_url = 'https://forvoyez.com/api/describe';

	public function __construct( $api_key ) {
		$this->api_key = $api_key;
	}

	public function init() {
		add_action( 'wp_ajax_forvoyez_verify_api_key', array( $this, 'verify_api_key' ) );
	}

	public function verify_api_key() {
		// here is the logic to verify the API key
	}

	// others methods here (with the logic to interact with the ForVoyez API)
	public function analyze_image( $image_id ) {
		// $image_path = get_attached_file($image_id);
		// if (!$image_path) {
		// return $this->format_error('image_not_found', 'Image not found');
		// }
		//
		// $image_url = wp_get_attachment_url($image_id);
		// $image_mime = get_post_mime_type($image_id);
		// $image_name = basename($image_path);
		//
		// Prepare the data for the API request
		// $data = array(
		// 'data' => json_encode(array(
		// 'context' => '', // You can add context if needed
		// 'schema' => array(
		// 'title' => 'string',
		// 'alternativeText' => 'string',
		// 'caption' => 'string'
		// )
		// ))
		// );
		//
		// Prepare the file for upload
		// $file_data = file_get_contents($image_path);
		// if ($file_data === false) {
		// return array('success' => false, 'message' => 'Failed to read image file', 'metadata' => null);
		// }
		//
		// Create a boundary for multipart data
		// $boundary = wp_generate_password(24);
		// $delimiter = '-------------' . $boundary;
		//
		// Build the multipart data
		// $post_data = $this->build_data_files($boundary, $data, $image_name, $image_mime, $file_data);
		//
		// Set up the request
		// $args = array(
		// 'method' => 'POST',
		// 'timeout' => 30,
		// 'redirection' => 5,
		// 'httpversion' => '1.1',
		// 'blocking' => true,
		// 'headers' => array(
		// 'Authorization' => 'Bearer ' . $this->api_key,
		// 'Content-Type' => 'multipart/form-data; boundary=' . $delimiter,
		// 'Content-Length' => strlen($post_data)
		// ),
		// 'body' => $post_data,
		// );
		//
		// Make the request
		// $response = wp_remote_post($this->api_url, $args);
		//
		// if (is_wp_error($response)) {
		// return $this->format_error('api_request_failed', $response->get_error_message());
		// }
		//
		// $body = wp_remote_retrieve_body($response);
		// $data = json_decode($body, true);
		//
		// if (json_last_error() !== JSON_ERROR_NONE) {
		// return $this->format_error($response['response']['code'], $response['body'], array(
		// 'response_code' => wp_remote_retrieve_response_code($response),
		// 'body' => substr($body, 0, 1000),
		// 'image_url' => wp_get_attachment_url($image_id),
		// 'api_url' => $this->api_url,
		// ));
		// }
		//
		// if (isset($data['error'])) {
		// return $this->format_error('api_error', $data['error']);
		// }
		//
		// $metadata = array(
		// 'alt_text' => $data['alternativeText'] ?? '',
		// 'title' => $data['title'] ?? '',
		// 'caption' => $data['caption'] ?? ''
		// );
		//
		// update_post_meta($image_id, '_wp_attachment_image_alt', $metadata['alt_text']);
		// wp_update_post(array(
		// 'ID' => $image_id,
		// 'post_title' => $metadata['title'],
		// 'post_excerpt' => $metadata['caption']
		// ));
		// update_post_meta($image_id, '_forvoyez_analyzed', true);
		//
		// return array(
		// 'success' => true,
		// 'message' => 'Analysis successful',
		// 'metadata' => $metadata
		// );

		$image_path = get_attached_file( $image_id );
		if ( ! $image_path ) {
			return $this->format_error( 'image_not_found', 'Image not found' );
		}

		$image_url  = wp_get_attachment_url( $image_id );
		$image_mime = get_post_mime_type( $image_id );
		$image_name = basename( $image_path );

		$file_data = file_get_contents( $image_path );
		if ( $file_data === false ) {
			return array(
				'success'  => false,
				'message'  => 'Failed to read image file',
				'metadata' => null,
			);
		}

		$metadata = array(
			'alt_text' => 'completed alt_text',
			'title'    => '',
			'caption'  => '',
		);

		update_post_meta( $image_id, '_wp_attachment_image_alt', $metadata['alt_text'] );
		wp_update_post(
			array(
				'ID'           => $image_id,
				'post_title'   => $metadata['title'],
				'post_excerpt' => $metadata['caption'],
			)
		);
		update_post_meta( $image_id, '_forvoyez_analyzed', true );

		return array(
			'success'  => true,
			'message'  => 'Analysis successful',
			'metadata' => $metadata,
		);
	}

	private function format_error( $code, $message, $debug_info = null ) {
		$error = array(
			'success' => false,
			'error'   => array(
				'code'    => $code,
				'message' => $message,
			),
		);

		if ( $debug_info ) {
			$error['debug_info'] = $debug_info;
		}

		return $error;
	}

	private function build_data_files( $boundary, $fields, $file_name, $file_mime, $file_data ) {
		$data      = '';
		$delimiter = '-------------' . $boundary;

		foreach ( $fields as $name => $content ) {
			$data .= '--' . $delimiter . "\r\n"
				. 'Content-Disposition: form-data; name="' . $name . "\"\r\n\r\n"
				. $content . "\r\n";
		}

		$data .= '--' . $delimiter . "\r\n"
			. 'Content-Disposition: form-data; name="image"; filename="' . $file_name . '"' . "\r\n"
			. 'Content-Type: ' . $file_mime . "\r\n\r\n"
			. $file_data . "\r\n";

		$data .= '--' . $delimiter . "--\r\n";

		return $data;
	}
}
