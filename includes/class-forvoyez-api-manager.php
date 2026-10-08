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
	 * Output schema sent with every describe request (key => description).
	 *
	 * Sent explicitly so the plugin does not depend on the server defaults.
	 */
	const DESCRIBE_SCHEMA = [
		'title' => 'A short, descriptive title for the image (a few words).',
		'alternativeText' =>
			'Concise alt text describing the image for screen readers and SEO (one sentence, at most 125 characters).',
		'caption' => 'A short caption for the image (one or two sentences).',
	];

	/**
	 * Maximum length of an error message surfaced to the user.
	 */
	const MAX_ERROR_MESSAGE_LENGTH = 300;

	/**
	 * Maximum lengths (characters) of the generated metadata that is saved.
	 */
	const MAX_ALT_TEXT_LENGTH = 250;
	const MAX_TITLE_LENGTH = 200;
	const MAX_CAPTION_LENGTH = 500;

	/**
	 * @var string The API key for ForVoyez service.
	 */
	private $api_key;

	/**
	 * @var string The URL of the ForVoyez API endpoint.
	 */
	private $api_url;

	/**
	 * @var string The URL of the ForVoyez token information endpoint.
	 */
	private $tokens_url;

	/**
	 * @var WP_Http The HTTP client for making requests.
	 */
	private $http_client;

	/**
	 * @var string The context for image analysis.
	 */
	private $context;

	/**
	 * @var string The language for image analysis.
	 */
	private $language;

	/**
	 * Constructor.
	 *
	 * @param string $api_key The API key for ForVoyez service.
	 * @param string $language The language for image analysis.
	 * @param string $context The context for image analysis.
	 * @param WP_Http|null $http_client Optional HTTP client.
	 */
	public function __construct(
		string $api_key,
		string $language,
		string $context,
		$http_client = null,
	) {
		$this->api_key = $api_key;
		$this->api_url = 'https://forvoyez.com/api/describe';
		$this->tokens_url = 'https://forvoyez.com/api/tokens';
		$this->http_client = $http_client ?: new WP_Http();
		$this->context = $context;
		$this->language = $language;
	}

	/**
	 * Initialize the API manager.
	 *
	 * The `wp_ajax_forvoyez_verify_api_key` action is handled by
	 * Forvoyez_Admin::ajax_verify_api_key(), which calls verify_api_key().
	 *
	 * @return void
	 */
	public function init(): void {
	}

	/**
	 * Whether an API key is configured.
	 *
	 * @return bool
	 */
	public function has_api_key(): bool {
		return trim($this->api_key) !== '';
	}

	/**
	 * Verify the API key against GET /api/tokens (200 means the key is valid).
	 *
	 * @return array{success: bool, message: string}
	 */
	public function verify_api_key(): array {
		$api_key = forvoyez_get_api_key();
		if (empty($api_key)) {
			return [
				'success' => false,
				'message' => esc_html__(
					'API key is not set',
					'auto-alt-text-for-images',
				),
			];
		}

		$response = $this->http_client->get(
			$this->tokens_url,
			$this->get_request_args($api_key),
		);

		if (is_wp_error($response)) {
			return [
				'success' => false,
				'message' => $response->get_error_message(),
			];
		}

		if ((int) wp_remote_retrieve_response_code($response) === 200) {
			return [
				'success' => true,
				'message' => esc_html__(
					'API key is valid',
					'auto-alt-text-for-images',
				),
			];
		}

		return [
			'success' => false,
			'message' => $this->get_error_message_from_response($response),
		];
	}

	/**
	 * Analyze an image using the ForVoyez API.
	 *
	 * @param int $image_id The ID of the image to analyze.
	 *
	 * @return array The analysis result.
	 */
	public function analyze_image(int $image_id): array {
		if (!$this->has_api_key()) {
			return $this->format_error(
				'missing_api_key',
				forvoyez_get_missing_api_key_message(),
			);
		}

		$image_path = get_attached_file($image_id);
		if (!$image_path) {
			return $this->format_error(
				'image_not_found',
				esc_html__('Image not found', 'auto-alt-text-for-images'),
			);
		}

		$image_url = wp_get_attachment_url($image_id);
		$image_mime = get_post_mime_type($image_id);
		$image_name = basename($image_path);

		$file_data = file_get_contents($image_path);
		if ($file_data === false) {
			return $this->format_error(
				'read_error',
				esc_html__(
					'Failed to read image file',
					'auto-alt-text-for-images',
				),
			);
		}

		$data = [
			'context' => $this->context,
			'language' => $this->language !== '' ? $this->language : 'en',
			'schema' => wp_json_encode(self::DESCRIBE_SCHEMA),
		];

		$boundary = wp_generate_password(24, false);
		$delimiter = '-------------' . $boundary;

		$post_data = $this->build_data_files(
			$boundary,
			$data,
			$image_name,
			$image_mime,
			$file_data,
		);

		$args = $this->get_request_args($this->api_key, 30);
		$args['method'] = 'POST';
		$args['headers']['Content-Type'] =
			'multipart/form-data; boundary=' . $delimiter;
		$args['headers']['Content-Length'] = strlen($post_data);
		$args['body'] = $post_data;

		$response = $this->http_client->post($this->api_url, $args);

		if (is_wp_error($response)) {
			return $this->format_error(
				'api_request_failed',
				$response->get_error_message(),
			);
		}

		$response_code = (int) wp_remote_retrieve_response_code($response);
		$body = (string) wp_remote_retrieve_body($response);
		$debug_info = [
			'response_code' => $response_code,
			'image_url' => $image_url,
			'api_url' => $this->api_url,
		];

		if ($response_code < 200 || $response_code >= 300) {
			return $this->format_error(
				'api_error',
				$this->get_error_message_from_response($response),
				$debug_info,
			);
		}

		$data = json_decode($body, true);

		if (!is_array($data)) {
			$debug_info['body'] = substr($body, 0, 1000);

			return $this->format_error(
				'invalid_response',
				$this->get_error_message_from_response($response),
				$debug_info,
			);
		}

		if (isset($data['error'])) {
			return $this->format_error(
				'api_error',
				$this->get_error_message_from_response($response),
				$debug_info,
			);
		}

		$metadata = $this->parse_metadata($data);

		if (
			$metadata['alt_text'] === '' &&
			$metadata['title'] === '' &&
			$metadata['caption'] === ''
		) {
			return $this->format_error(
				'empty_response',
				esc_html__(
					'The ForVoyez API returned no alt text, title or caption. Nothing was changed.',
					'auto-alt-text-for-images',
				),
				$debug_info,
			);
		}

		$this->update_image_metadata($image_id, $metadata);

		return [
			'success' => true,
			'message' => esc_html__(
				'Analysis successful',
				'auto-alt-text-for-images',
			),
			'metadata' => $this->get_stored_metadata($image_id),
		];
	}

	/**
	 * Extract the image metadata from a describe response.
	 *
	 * The API answers with the schema keys (`alternativeText`); `alt_text` is
	 * accepted as a fallback.
	 *
	 * @param array $data The decoded API response.
	 *
	 * @return array{alt_text: string, title: string, caption: string}
	 */
	private function parse_metadata(array $data): array {
		$alt_text = '';
		foreach (['alternativeText', 'alt_text'] as $key) {
			if (
				isset($data[$key]) &&
				is_string($data[$key]) &&
				trim($data[$key]) !== ''
			) {
				$alt_text = $data[$key];

				break;
			}
		}

		$title =
			isset($data['title']) && is_string($data['title'])
				? $data['title']
				: '';
		$caption =
			isset($data['caption']) && is_string($data['caption'])
				? $data['caption']
				: '';

		return [
			'alt_text' => $this->to_plain_text(
				$alt_text,
				self::MAX_ALT_TEXT_LENGTH,
			),
			'title' => $this->to_plain_text($title, self::MAX_TITLE_LENGTH),
			'caption' => $this->to_plain_text(
				$caption,
				self::MAX_CAPTION_LENGTH,
			),
		];
	}

	/**
	 * Reduce a generated value to capped plain text.
	 *
	 * The schema asks for plain text. Captions are copied into post content,
	 * so links, images or shortcodes injected through the image content must
	 * not survive. Square brackets are removed as well: strip_shortcodes()
	 * turns the escaped `[[tag]]` into a live `[tag]` and only knows the
	 * shortcodes registered in the current request (not front-end ones while
	 * analyzing in admin-ajax or WP-Cron).
	 *
	 * @param string $text The value returned by the API.
	 * @param int $max_length Maximum length in characters.
	 *
	 * @return string
	 */
	private function to_plain_text(string $text, int $max_length): string {
		$text = sanitize_text_field(strip_shortcodes($text));
		$text = trim(str_replace(['[', ']'], '', $text));

		if (mb_strlen($text) > $max_length) {
			$text = trim(mb_substr($text, 0, $max_length));
		}

		return $text;
	}

	/**
	 * Update image metadata in WordPress.
	 *
	 * Empty values are skipped so an existing alt text, title or caption is
	 * never replaced by an empty one.
	 *
	 * @param int $image_id The ID of the image to update.
	 * @param array $metadata The metadata to update.
	 *
	 * @return void
	 */
	private function update_image_metadata(
		int $image_id,
		array $metadata,
	): void {
		if (($metadata['alt_text'] ?? '') !== '') {
			update_post_meta(
				$image_id,
				'_wp_attachment_image_alt',
				wp_slash($metadata['alt_text']),
			);
		}

		$post_data = [];
		if (($metadata['title'] ?? '') !== '') {
			$post_data['post_title'] = $metadata['title'];
		}
		if (($metadata['caption'] ?? '') !== '') {
			$post_data['post_excerpt'] = $metadata['caption'];
		}

		if (!empty($post_data)) {
			$post_data['ID'] = $image_id;
			wp_update_post(wp_slash($post_data));
		}

		update_post_meta($image_id, '_forvoyez_analyzed', 1);
	}

	/**
	 * Read the metadata currently stored for an image.
	 *
	 * @param int $image_id The image ID.
	 *
	 * @return array{alt_text: string, title: string, caption: string}
	 */
	private function get_stored_metadata(int $image_id): array {
		$image = get_post($image_id);

		return [
			'alt_text' => (string) get_post_meta(
				$image_id,
				'_wp_attachment_image_alt',
				true,
			),
			'title' => $image ? $image->post_title : '',
			'caption' => $image ? $image->post_excerpt : '',
		];
	}

	/**
	 * Build the common arguments of an authenticated API request.
	 *
	 * Redirects are not followed: WordPress would resend the Authorization
	 * header to the new location, whatever its host or scheme. A 3xx answer is
	 * reported as an error instead.
	 *
	 * @param string $api_key The API key sent as Bearer token.
	 * @param int $timeout Request timeout in seconds.
	 *
	 * @return array
	 */
	private function get_request_args(
		string $api_key,
		int $timeout = 15,
	): array {
		return [
			'method' => 'GET',
			'timeout' => $timeout,
			'redirection' => 0,
			'httpversion' => '1.1',
			'blocking' => true,
			'headers' => [
				'Authorization' => 'Bearer ' . $api_key,
			],
		];
	}

	/**
	 * Build a human readable error message from an API response.
	 *
	 * Uses the `error` field of a JSON body (`{"error": "..."}`) and falls back
	 * to the raw body and HTTP status for non-JSON responses.
	 *
	 * @param array $response The HTTP response.
	 *
	 * @return string The error message (plain text).
	 */
	private function get_error_message_from_response($response): string {
		$response_code = (int) wp_remote_retrieve_response_code($response);
		$body = (string) wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if (is_array($data) && isset($data['error'])) {
			$error = $data['error'];
			if (is_array($error) && isset($error['message'])) {
				$error = $error['message'];
			}
			if (is_string($error)) {
				$message = $this->clean_error_message($error);
				if ($message !== '') {
					return $message;
				}
			}
		}

		$raw_body = $this->clean_error_message($body);
		if ($raw_body !== '') {
			return sprintf(
				// translators: 1: HTTP status code, 2: raw response body (truncated).
				__(
					'Unexpected response from the ForVoyez API (HTTP %1$d): %2$s',
					'auto-alt-text-for-images',
				),
				$response_code,
				$raw_body,
			);
		}

		return sprintf(
			// translators: %d: HTTP status code.
			__(
				'The ForVoyez API request failed (HTTP %d).',
				'auto-alt-text-for-images',
			),
			$response_code,
		);
	}

	/**
	 * Turn an API message or raw body into a short plain-text string
	 * (tags stripped, whitespace collapsed, truncated).
	 *
	 * @param string $message The raw message.
	 *
	 * @return string
	 */
	private function clean_error_message(string $message): string {
		return trim(
			wp_html_excerpt($message, self::MAX_ERROR_MESSAGE_LENGTH, '...'),
		);
	}

	/**
	 * Format an error response.
	 *
	 * @param string $code The error code.
	 * @param string $message The error message.
	 * @param array|null $debug_info Optional debug information.
	 *
	 * @return array The formatted error.
	 */
	private function format_error(
		string $code,
		string $message,
		?array $debug_info = null,
	): array {
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
	 *
	 * @return string The built multipart data.
	 */
	private function build_data_files(
		string $boundary,
		array $fields,
		string $file_name,
		string $file_mime,
		string $file_data,
	): string {
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

	/**
	 * Get token information from the ForVoyez API.
	 *
	 * @return array Response containing user's remaining credits and subscription status.
	 */
	public function get_token_info() {
		if (!$this->has_api_key()) {
			return $this->format_error(
				'missing_api_key',
				forvoyez_get_missing_api_key_message(),
			);
		}

		$response = $this->http_client->get(
			$this->tokens_url,
			$this->get_request_args($this->api_key),
		);

		if (is_wp_error($response)) {
			return $this->format_error(
				'token_request_failed',
				$response->get_error_message(),
			);
		}

		$response_code = (int) wp_remote_retrieve_response_code($response);
		$body = (string) wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($response_code < 200 || $response_code >= 300) {
			return $this->format_error(
				'token_request_failed',
				$this->get_error_message_from_response($response),
				[
					'response_code' => $response_code,
					'api_url' => $this->tokens_url,
				],
			);
		}

		if (!is_array($data)) {
			return $this->format_error(
				'invalid_response',
				$this->get_error_message_from_response($response),
				[
					'response_code' => $response_code,
					'body' => substr($body, 0, 1000),
					'api_url' => $this->tokens_url,
				],
			);
		}

		if (!isset($data['success'])) {
			$data['success'] = !isset($data['error']);
		}

		if (!$data['success'] && !isset($data['error']['message'])) {
			return $this->format_error(
				'token_request_failed',
				$this->get_error_message_from_response($response),
			);
		}

		return $data;
	}
}
