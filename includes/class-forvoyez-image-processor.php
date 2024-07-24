<?php
defined('ABSPATH') || exit();

class Forvoyez_Image_Processor {
	private $api_client;

	public function __construct() {
		$api_key = forvoyez_get_api_key();
		$this->api_client = new Forvoyez_API_Manager($api_key);
	}

	public function init() {
		add_action('wp_ajax_forvoyez_analyze_image', [
			$this,
			'ajax_analyze_image',
		]);
		add_action('wp_ajax_forvoyez_update_image_metadata', [
			$this,
			'update_image_metadata',
		]);
		add_action('wp_ajax_forvoyez_load_more_images', [
			$this,
			'load_more_images',
		]);
		add_action('wp_ajax_forvoyez_bulk_analyze_images', [
			$this,
			'bulk_analyze_images',
		]);
		add_action('wp_ajax_forvoyez_analyze_single_image', [
			$this,
			'analyze_single_image',
		]);
		add_action('wp_ajax_forvoyez_process_image_batch', [
			$this,
			'process_image_batch',
		]);
	}

	public function update_image_metadata() {
		check_ajax_referer('forvoyez_nonce', 'nonce');

		if (!current_user_can('upload_files')) {
			wp_send_json_error('Permission denied');
		}

		$image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
		$metadata = isset($_POST['metadata']) ? $_POST['metadata'] : [];

		if (!$image_id || empty($metadata)) {
			wp_send_json_error('Invalid data');
		}

		// Update alt text
		if (isset($metadata['alt_text'])) {
			update_post_meta(
				$image_id,
				'_wp_attachment_image_alt',
				sanitize_text_field($metadata['alt_text']),
			);
		}

		// Update title
		if (isset($metadata['title'])) {
			wp_update_post([
				'ID' => $image_id,
				'post_title' => sanitize_text_field($metadata['title']),
			]);
		}

		// Update caption
		if (isset($metadata['caption'])) {
			wp_update_post([
				'ID' => $image_id,
				'post_excerpt' => wp_kses_post($metadata['caption']),
			]);
		}

		// Mark as analyzed
		update_post_meta($image_id, '_forvoyez_analyzed', true);

		wp_send_json_success('Metadata updated successfully');
	}

	public function ajax_analyze_image() {
		check_ajax_referer('forvoyez_nonce', 'nonce');

		if (!current_user_can('upload_files')) {
			wp_send_json_error('Permission denied');
		}

		$image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;

		if (!$image_id) {
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

	public function load_more_images() {
		check_ajax_referer('forvoyez_nonce', 'nonce');

		$offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
		$limit = isset($_POST['limit']) ? intval($_POST['limit']) : 21;

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
		$html = '';
		$count = 0;

		ob_start();
		foreach ($query_images->posts as $image) {
			if (
				empty($image->post_title) ||
				empty(
					get_post_meta($image->ID, '_wp_attachment_image_alt', true)
				) ||
				empty($image->post_excerpt)
			) {
				Forvoyez_Image_Renderer::render_image_item($image);
				++$count;
			}
		}
		$html = ob_get_clean();

		wp_send_json_success([
			'html' => $html,
			'count' => $count,
			'total' => forvoyez_count_incomplete_images(),
		]);
	}

	public function bulk_analyze_images() {
		check_ajax_referer('forvoyez_nonce', 'nonce');

		if (!current_user_can('upload_files')) {
			wp_send_json_error('Permission denied');
		}

		$image_ids = isset($_POST['image_ids'])
			? array_map('intval', $_POST['image_ids'])
			: [];

		if (empty($image_ids)) {
			wp_send_json_error('No images selected');
		}

		// We no longer process images here, just return the list of IDs
		wp_send_json_success([
			'message' => 'Processing started',
			'total' => count($image_ids),
			'image_ids' => $image_ids,
		]);
	}

	public function analyze_single_image() {
		check_ajax_referer('forvoyez_nonce', 'nonce');

		if (!current_user_can('upload_files')) {
			wp_send_json_error('Permission denied');
		}

		$image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;

		if (!$image_id) {
			wp_send_json_error('Invalid image ID');
		}

		$result = $this->api_client->analyze_image($image_id);

		wp_send_json_success($result);
	}

	public function process_image_batch() {
		check_ajax_referer('forvoyez_nonce', 'nonce');

		if (!current_user_can('upload_files')) {
			wp_send_json_error('Permission denied');
		}

		$image_ids = isset($_POST['image_ids'])
			? array_map('intval', $_POST['image_ids'])
			: [];

		if (empty($image_ids)) {
			wp_send_json_error('No images provided');
		}

		$results = [];
		foreach ($image_ids as $image_id) {
			$result = $this->api_client->analyze_image($image_id);
			$results[] = [
				'id' => $image_id,
				'success' => $result['success'],
				'message' => $result['success']
					? $result['message']
					: $result['error']['message'],
				'code' => $result['success']
					? null
					: $result['error']['code'] ?? 'unknown_error',
				'metadata' => $result['success'] ? $result['metadata'] : null,
			];
		}

		wp_send_json_success([
			'results' => $results,
		]);
	}
}
