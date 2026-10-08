<?php
/**
 * Class TestForvoyezAjax
 *
 * AJAX tests for the analysis and API key endpoints. Requests to the ForVoyez
 * API are intercepted with the `pre_http_request` filter.
 *
 * @package ForVoyez
 */

class TestForvoyezAjax extends WP_Ajax_UnitTestCase
{
	const DESCRIBE_URL = 'https://forvoyez.com/api/describe';
	const TOKENS_URL = 'https://forvoyez.com/api/tokens';

	/**
	 * Requests sent to forvoyez.com during a test (each: url, args).
	 *
	 * @var array[]
	 */
	private $forvoyez_requests = [];

	/**
	 * Fake ForVoyez API responses keyed by URL.
	 *
	 * @var array<string, array>
	 */
	private $fake_responses = [];

	public function set_up()
	{
		parent::set_up();

		// _handleAjax() fires admin_init. WP_Ajax_UnitTestCase only unhooks the
		// update checks once per class and the hook backup puts them back, so
		// unhook them for every test: they would call api.wordpress.org.
		remove_action('admin_init', '_maybe_update_core');
		remove_action('admin_init', '_maybe_update_plugins');
		remove_action('admin_init', '_maybe_update_themes');

		delete_option('forvoyez_encrypted_api_key');
		$this->forvoyez_requests = [];
		$this->fake_responses = [];
		add_filter('pre_http_request', [ $this, 'intercept_forvoyez_requests' ], 10, 3);
	}

	public function tear_down()
	{
		remove_filter('pre_http_request', [ $this, 'intercept_forvoyez_requests' ], 10);
		delete_option('forvoyez_encrypted_api_key');
		delete_option('forvoyez_auto_analyze_enabled');
		$_POST = [];
		parent::tear_down();
	}

	/**
	 * Record requests to the ForVoyez API and answer them with the queued
	 * fake response (or a WP_Error when none is queued).
	 */
	public function intercept_forvoyez_requests($pre, $args, $url)
	{
		if (strpos($url, 'forvoyez.com') !== false) {
			$this->forvoyez_requests[] = [ 'url' => $url, 'args' => $args ];

			if (isset($this->fake_responses[ $url ])) {
				return $this->fake_responses[ $url ];
			}

			return new WP_Error('blocked', 'HTTP requests are blocked in tests');
		}

		return $pre;
	}

	public function test_analyze_image_without_api_key_returns_clear_error()
	{
		$this->_setRole('administrator');
		$attachment_id = $this->create_image();
		update_post_meta($attachment_id, '_wp_attachment_image_alt', 'Existing alt');

		$_POST['nonce'] = wp_create_nonce('forvoyez_verify_ajax_request_nonce');
		$_POST['image_id'] = $attachment_id;

		$response = $this->call_ajax('forvoyez_analyze_image');

		$this->assertFalse($response['success']);
		$this->assertSame('missing_api_key', $response['data']['code']);
		$this->assertSame(forvoyez_get_missing_api_key_message(), $response['data']['message']);
		$this->assertSame([], $this->forvoyez_requests, 'The API must not be called without an API key');
		$this->assertSame(
			'Existing alt',
			get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
		);
	}

	public function test_process_image_batch_without_api_key_returns_clear_error()
	{
		$this->_setRole('administrator');
		$attachment_id = $this->create_image();

		$_POST['nonce'] = wp_create_nonce('forvoyez_verify_ajax_request_nonce');
		$_POST['image_ids'] = [ $attachment_id ];

		$response = $this->call_ajax('forvoyez_process_image_batch');

		$this->assertFalse($response['success']);
		$this->assertSame('missing_api_key', $response['data']['code']);
		$this->assertSame([], $this->forvoyez_requests, 'The API must not be called without an API key');
	}

	public function test_verify_api_key_checks_the_saved_key_against_the_tokens_endpoint()
	{
		$this->_setRole('administrator');

		$_POST['nonce'] = wp_create_nonce('forvoyez_save_api_key_nonce');
		$_POST['api_key'] = 'saved.jwt.for-tests';
		$saved = $this->call_ajax('forvoyez_save_api_key');
		$this->assertTrue($saved['success']);
		$this->assertSame([], $this->forvoyez_requests, 'Saving the key must not call the API');

		$this->fake_response(self::TOKENS_URL, 200, [
			'user' => [ 'credits' => 3 ],
			'subscription' => null,
		]);
		$_POST = [ 'nonce' => wp_create_nonce('forvoyez_verify_api_key_nonce') ];
		$response = $this->call_ajax('forvoyez_verify_api_key');

		$this->assertTrue($response['success']);
		$this->assertSame('API key is valid', $response['data']);
		$this->assertCount(1, $this->forvoyez_requests);
		$this->assertSame(self::TOKENS_URL, $this->forvoyez_requests[0]['url']);
		$this->assertSame('GET', $this->forvoyez_requests[0]['args']['method']);
		$this->assertSame(
			'Bearer saved.jwt.for-tests',
			$this->forvoyez_requests[0]['args']['headers']['Authorization'],
		);
	}

	public function test_save_api_key_refuses_a_value_that_is_not_a_forvoyez_key()
	{
		$this->_setRole('administrator');
		update_option('forvoyez_encrypted_api_key', 'previous-encrypted-key');

		// e.g. a site password the browser filled into the API key field
		$_POST['nonce'] = wp_create_nonce('forvoyez_save_api_key_nonce');
		$_POST['api_key'] = 'aB3$xY9!qW7#zK2@mN5p';
		$response = $this->call_ajax('forvoyez_save_api_key');

		$this->assertFalse($response['success']);
		$this->assertSame('Invalid API key format. Please enter a valid ForVoyez JWT.', $response['data']);
		$this->assertSame('previous-encrypted-key', get_option('forvoyez_encrypted_api_key'), 'The saved key must not change');
		$this->assertSame([], $this->forvoyez_requests);
	}

	public function test_verify_api_key_surfaces_the_json_error()
	{
		$this->_setRole('administrator');
		$this->store_api_key('revoked.jwt.for-tests');
		$this->fake_response(self::TOKENS_URL, 401, [ 'error' => 'Token not found or expired in database' ]);

		$_POST['nonce'] = wp_create_nonce('forvoyez_verify_api_key_nonce');
		$response = $this->call_ajax('forvoyez_verify_api_key');

		$this->assertFalse($response['success']);
		$this->assertSame('Token not found or expired in database', $response['data']);
	}

	public function test_analyze_image_saves_the_alternative_text()
	{
		$this->_setRole('administrator');
		$attachment_id = $this->create_image();
		$this->use_image_processor_with_key('valid.jwt.for-tests');
		$this->fake_response(self::DESCRIBE_URL, 200, [
			'title' => 'Mountain lake',
			'alternativeText' => 'A calm lake below snowy mountains',
			'caption' => 'Morning at the lake.',
		]);

		$_POST['nonce'] = wp_create_nonce('forvoyez_verify_ajax_request_nonce');
		$_POST['image_id'] = $attachment_id;
		$response = $this->call_ajax('forvoyez_analyze_image');

		$this->assertTrue($response['success']);
		$this->assertSame('A calm lake below snowy mountains', $response['data']['metadata']['alt_text']);
		$this->assertSame(
			'A calm lake below snowy mountains',
			get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
		);
		$this->assertCount(1, $this->forvoyez_requests);
		$this->assertSame(
			'Bearer valid.jwt.for-tests',
			$this->forvoyez_requests[0]['args']['headers']['Authorization'],
		);
	}

	public function test_analyze_image_surfaces_the_json_error()
	{
		$this->_setRole('administrator');
		$attachment_id = $this->create_image();
		update_post_meta($attachment_id, '_wp_attachment_image_alt', 'Existing alt');
		$this->use_image_processor_with_key('valid.jwt.for-tests');
		$this->fake_response(self::DESCRIBE_URL, 403, [ 'error' => 'No credits left on this account' ]);

		$_POST['nonce'] = wp_create_nonce('forvoyez_verify_ajax_request_nonce');
		$_POST['image_id'] = $attachment_id;
		$response = $this->call_ajax('forvoyez_analyze_image');

		$this->assertFalse($response['success']);
		$this->assertSame('api_error', $response['data']['code']);
		$this->assertSame('No credits left on this account', $response['data']['message']);
		$this->assertSame(
			'Existing alt',
			get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
		);
	}

	public function test_analyze_image_refuses_an_image_the_user_cannot_edit()
	{
		$owner_id = self::factory()->user->create([ 'role' => 'administrator' ]);
		$this->_setRole('author');
		$attachment_id = $this->create_image([ 'post_author' => $owner_id ]);
		update_post_meta($attachment_id, '_wp_attachment_image_alt', 'Owner alt');
		$this->use_image_processor_with_key('valid.jwt.for-tests');
		$this->fake_describe_success();

		$_POST['nonce'] = wp_create_nonce('forvoyez_verify_ajax_request_nonce');
		$_POST['image_id'] = $attachment_id;
		$response = $this->call_ajax('forvoyez_analyze_image');

		$this->assertFalse($response['success']);
		$this->assertSame('permission_denied', $response['data']['code']);
		$this->assertSame([], $this->forvoyez_requests);
		$this->assertSame('Owner alt', get_post_meta($attachment_id, '_wp_attachment_image_alt', true));
	}

	public function test_process_image_batch_only_sends_images_the_user_can_edit()
	{
		$owner_id = self::factory()->user->create([ 'role' => 'administrator' ]);
		$this->_setRole('author');
		$own_image = $this->create_image();
		$others_image = $this->create_image([ 'post_author' => $owner_id ]);
		update_post_meta($others_image, '_wp_attachment_image_alt', 'Owner alt');
		$own_pdf = self::factory()->attachment->create_object([
			'file' => 'document.pdf',
			'post_mime_type' => 'application/pdf',
			'post_title' => 'document',
		]);
		$this->use_image_processor_with_key('valid.jwt.for-tests');
		$this->fake_describe_success();

		$_POST['nonce'] = wp_create_nonce('forvoyez_verify_ajax_request_nonce');
		$_POST['image_ids'] = [ $own_image, $others_image, $own_pdf ];
		$response = $this->call_ajax('forvoyez_process_image_batch');

		$this->assertTrue($response['success']);
		$results = array_column($response['data']['results'], null, 'id');
		$this->assertTrue($results[ $own_image ]['success']);
		$this->assertFalse($results[ $others_image ]['success']);
		$this->assertSame('permission_denied', $results[ $others_image ]['code']);
		$this->assertFalse($results[ $own_pdf ]['success']);
		$this->assertSame('invalid_image', $results[ $own_pdf ]['code']);
		$this->assertCount(1, $this->forvoyez_requests, 'Only the editable image is sent to the API');
		$this->assertSame('Owner alt', get_post_meta($others_image, '_wp_attachment_image_alt', true));
		$this->assertSame('A calm lake', get_post_meta($own_image, '_wp_attachment_image_alt', true));
	}

	public function test_toggle_auto_analyze_off_disables_the_analysis_on_upload()
	{
		$this->_setRole('administrator');

		// jQuery posts the JavaScript booleans as the strings "true" / "false".
		$_POST = [
			'nonce' => wp_create_nonce('forvoyez_toggle_auto_analyze_nonce'),
			'enabled' => 'true',
		];
		$enabled = $this->call_ajax('forvoyez_toggle_auto_analyze');

		$this->assertTrue($enabled['success']);
		$this->assertSame('true', get_option('forvoyez_auto_analyze_enabled'));
		$this->assertTrue(forvoyez_is_auto_analyze_enabled());

		$_POST = [
			'nonce' => wp_create_nonce('forvoyez_toggle_auto_analyze_nonce'),
			'enabled' => 'false',
		];
		$disabled = $this->call_ajax('forvoyez_toggle_auto_analyze');

		$this->assertTrue($disabled['success']);
		$this->assertSame('false', get_option('forvoyez_auto_analyze_enabled'));
		$this->assertFalse(forvoyez_is_auto_analyze_enabled());
	}

	public function test_update_image_metadata_keeps_backslashes()
	{
		$this->_setRole('administrator');
		$attachment_id = $this->create_image();

		// WordPress slashes the request data (wp_magic_quotes()).
		$_POST = wp_slash([
			'nonce' => wp_create_nonce('forvoyez_update_image_metadata'),
			'image_id' => $attachment_id,
			'metadata' => [
				'alt_text' => 'C:\\photos\\cat.jpg',
				'title' => 'Folder C:\\photos',
				'caption' => 'Saved in C:\\photos\\cat.jpg',
			],
		]);
		$response = $this->call_ajax('forvoyez_update_image_metadata');

		$this->assertTrue($response['success']);
		$this->assertSame(
			'C:\\photos\\cat.jpg',
			get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
		);
		$this->assertSame('Folder C:\\photos', get_post($attachment_id)->post_title);
		$this->assertSame('Saved in C:\\photos\\cat.jpg', get_post($attachment_id)->post_excerpt);
	}

	/**
	 * Create an image attachment pointing at the test asset (no upload).
	 *
	 * @param array $args Extra post fields (e.g. post_author).
	 * @return int The attachment ID.
	 */
	private function create_image(array $args = [])
	{
		return self::factory()->attachment->create_object(array_merge([
			'file' => __DIR__ . '/assets/test-image.webp',
			'post_mime_type' => 'image/webp',
			'post_title' => 'test-image',
		], $args));
	}

	/**
	 * Queue a successful describe response.
	 */
	private function fake_describe_success()
	{
		$this->fake_response(self::DESCRIBE_URL, 200, [
			'title' => 'Lake',
			'alternativeText' => 'A calm lake',
			'caption' => 'Morning.',
		]);
	}

	/**
	 * Save an API key the way the settings screen does (encrypted option).
	 *
	 * @param string $api_key The API key.
	 */
	private function store_api_key($api_key)
	{
		$settings = new Forvoyez_Settings();
		$encrypt = new ReflectionMethod(Forvoyez_Settings::class, 'encrypt');
		if (PHP_VERSION_ID < 80100) {
			$encrypt->setAccessible(true); // No-op (deprecated) since PHP 8.1.
		}
		update_option('forvoyez_encrypted_api_key', $encrypt->invoke($settings, $api_key));
	}

	/**
	 * Store an API key and route the analyze AJAX actions to an image
	 * processor built with it (the plugin's own instance was built without a
	 * key).
	 *
	 * @param string $api_key The API key.
	 */
	private function use_image_processor_with_key($api_key)
	{
		$this->store_api_key($api_key);
		$image_processor = new Forvoyez_Image_Processor();
		remove_all_actions('wp_ajax_forvoyez_analyze_image');
		add_action('wp_ajax_forvoyez_analyze_image', [ $image_processor, 'ajax_analyze_image' ]);
		remove_all_actions('wp_ajax_forvoyez_process_image_batch');
		add_action('wp_ajax_forvoyez_process_image_batch', [ $image_processor, 'process_image_batch' ]);
	}

	/**
	 * Queue a JSON response for a ForVoyez API URL.
	 *
	 * @param string $url  Request URL.
	 * @param int    $code HTTP status code.
	 * @param array  $body Response body, JSON encoded.
	 */
	private function fake_response($url, $code, array $body)
	{
		$this->fake_responses[ $url ] = [
			'headers' => [ 'content-type' => 'application/json' ],
			'body' => wp_json_encode($body),
			'response' => [
				'code' => $code,
				'message' => get_status_header_desc($code),
			],
			'cookies' => [],
			'filename' => null,
		];
	}

	/**
	 * Run an AJAX action and return the decoded JSON response.
	 *
	 * @param string $action The AJAX action.
	 * @return array
	 */
	private function call_ajax($action)
	{
		$this->_last_response = '';

		try {
			$this->_handleAjax($action);
		} catch (WPAjaxDieContinueException $e) {
			// Expected: wp_send_json_*() ends the request.
		}

		return json_decode($this->_last_response, true);
	}
}
