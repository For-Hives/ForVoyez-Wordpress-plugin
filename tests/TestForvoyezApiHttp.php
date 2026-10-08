<?php
/**
 * Class TestForvoyezApiHttp
 *
 * Tests the ForVoyez API client through the real WordPress HTTP API. Every
 * request is intercepted with the `pre_http_request` filter and answered with
 * a fake response, so nothing ever leaves the test machine.
 *
 * @package ForVoyez
 */

class TestForvoyezApiHttp extends WP_UnitTestCase
{
	const DESCRIBE_URL = 'https://forvoyez.com/api/describe';
	const TOKENS_URL = 'https://forvoyez.com/api/tokens';
	const API_KEY = 'fake.jwt.for-tests';

	/**
	 * Requests seen by `pre_http_request` (each: url, args).
	 *
	 * @var array[]
	 */
	private $requests = [];

	/**
	 * Fake responses keyed by URL.
	 *
	 * @var array<string, array>
	 */
	private $responses = [];

	/**
	 * Image attachment pointing at tests/assets/test-image.webp.
	 *
	 * @var int
	 */
	private $image_id;

	public function set_up()
	{
		parent::set_up();
		$this->requests = [];
		$this->responses = [];
		delete_option('forvoyez_encrypted_api_key');
		add_filter('pre_http_request', [ $this, 'intercept_http_request' ], 10, 3);

		// Point the attachment at the test asset directly: no upload, no sub-sizes.
		$this->image_id = self::factory()->attachment->create_object([
			'file' => __DIR__ . '/assets/test-image.webp',
			'post_mime_type' => 'image/webp',
			'post_title' => 'test-image',
		]);
	}

	public function tear_down()
	{
		remove_filter('pre_http_request', [ $this, 'intercept_http_request' ], 10);
		delete_option('forvoyez_encrypted_api_key');
		delete_option('forvoyez_auto_analyze_enabled');
		parent::tear_down();
	}

	/**
	 * Record every outgoing request and answer it with the queued fake
	 * response; unexpected requests fail instead of reaching the network.
	 */
	public function intercept_http_request($preempt, $args, $url)
	{
		$this->requests[] = [ 'url' => $url, 'args' => $args ];

		if (isset($this->responses[ $url ])) {
			return $this->responses[ $url ];
		}

		return new WP_Error('http_request_blocked', 'Unexpected HTTP request in tests: ' . $url);
	}

	public function test_describe_request_follows_the_api_contract()
	{
		$this->fake_response(self::DESCRIBE_URL, 200, [
			'title' => 'Red bicycle',
			'alternativeText' => 'A red bicycle leaning against a wall',
			'caption' => 'A bicycle in the sun.',
		]);

		$result = $this->api_manager()->analyze_image($this->image_id);

		$this->assertTrue($result['success']);
		$this->assertCount(1, $this->requests);

		$request = $this->requests[0];
		$this->assertSame(self::DESCRIBE_URL, $request['url']);
		$this->assertSame('POST', $request['args']['method']);
		$this->assertSame('Bearer ' . self::API_KEY, $request['args']['headers']['Authorization']);
		$this->assertMatchesRegularExpression(
			'/^multipart\/form-data; boundary=(\S+)$/',
			$request['args']['headers']['Content-Type'],
		);

		$body = $request['args']['body'];
		$this->assertStringContainsString(
			"name=\"image\"; filename=\"test-image.webp\"\r\nContent-Type: image/webp\r\n",
			$body,
		);
		$this->assertStringContainsString("name=\"language\"\r\n\r\nen\r\n", $body);
		$this->assertStringContainsString(
			"name=\"schema\"\r\n\r\n" . wp_json_encode(Forvoyez_API_Manager::DESCRIBE_SCHEMA) . "\r\n",
			$body,
		);
	}

	public function test_alternative_text_is_saved_as_the_image_alt_text()
	{
		$this->fake_response(self::DESCRIBE_URL, 200, [
			'title' => 'Red bicycle',
			'alternativeText' => 'A red bicycle leaning against a wall',
			'caption' => 'A bicycle in the sun.',
		]);

		$result = $this->api_manager()->analyze_image($this->image_id);

		$this->assertTrue($result['success']);
		$this->assertSame('A red bicycle leaning against a wall', $this->stored_alt());
		$this->assertSame('Red bicycle', get_post($this->image_id)->post_title);
		$this->assertSame('A bicycle in the sun.', get_post($this->image_id)->post_excerpt);
		$this->assertSame('1', get_post_meta($this->image_id, '_forvoyez_analyzed', true));
		$this->assertSame(
			[
				'alt_text' => 'A red bicycle leaning against a wall',
				'title' => 'Red bicycle',
				'caption' => 'A bicycle in the sun.',
			],
			$result['metadata'],
		);
	}

	public function test_alternative_text_takes_precedence_over_alt_text()
	{
		$this->fake_response(self::DESCRIBE_URL, 200, [
			'alternativeText' => 'New key',
			'alt_text' => 'Legacy key',
		]);

		$result = $this->api_manager()->analyze_image($this->image_id);

		$this->assertTrue($result['success']);
		$this->assertSame('New key', $this->stored_alt());
	}

	public function test_alt_text_is_used_when_alternative_text_is_missing()
	{
		$this->fake_response(self::DESCRIBE_URL, 200, [
			'title' => 'Legacy title',
			'alt_text' => 'Legacy alt',
		]);

		$result = $this->api_manager()->analyze_image($this->image_id);

		$this->assertTrue($result['success']);
		$this->assertSame('Legacy alt', $this->stored_alt());
		$this->assertSame('Legacy alt', $result['metadata']['alt_text']);
	}

	public function test_alt_text_is_used_when_alternative_text_is_empty()
	{
		$this->fake_response(self::DESCRIBE_URL, 200, [
			'alternativeText' => '  ',
			'alt_text' => 'Legacy alt',
		]);

		$this->api_manager()->analyze_image($this->image_id);

		$this->assertSame('Legacy alt', $this->stored_alt());
	}

	/**
	 * @dataProvider data_empty_alt_values
	 */
	public function test_empty_alt_text_never_overwrites_the_existing_alt($empty_alt)
	{
		update_post_meta($this->image_id, '_wp_attachment_image_alt', 'Existing alt');
		$this->fake_response(self::DESCRIBE_URL, 200, [
			'title' => 'New title',
			'alternativeText' => $empty_alt,
			'caption' => 'New caption',
		]);

		$result = $this->api_manager()->analyze_image($this->image_id);

		$this->assertTrue($result['success']);
		$this->assertSame('Existing alt', $this->stored_alt());
		$this->assertSame('Existing alt', $result['metadata']['alt_text']);
		$this->assertSame('New title', get_post($this->image_id)->post_title);
		$this->assertSame('New caption', get_post($this->image_id)->post_excerpt);
	}

	public static function data_empty_alt_values()
	{
		return [
			'empty string' => [ '' ],
			'whitespace only' => [ " \n\t " ],
			'null' => [ null ],
			'markup only' => [ '<br />' ],
		];
	}

	public function test_fully_empty_response_changes_nothing()
	{
		update_post_meta($this->image_id, '_wp_attachment_image_alt', 'Existing alt');
		$this->fake_response(self::DESCRIBE_URL, 200, [
			'title' => '',
			'alternativeText' => '',
			'caption' => '',
		]);

		$result = $this->api_manager()->analyze_image($this->image_id);

		$this->assertFalse($result['success']);
		$this->assertSame('empty_response', $result['error']['code']);
		$this->assertSame('Existing alt', $this->stored_alt());
		$this->assertSame('test-image', get_post($this->image_id)->post_title);
		$this->assertSame('', get_post_meta($this->image_id, '_forvoyez_analyzed', true));
	}

	public function test_no_request_is_sent_without_an_api_key()
	{
		update_option('forvoyez_auto_analyze_enabled', '1');
		$api_manager = new Forvoyez_API_Manager('', 'en', '');

		$analysis = $api_manager->analyze_image($this->image_id);
		$token_info = $api_manager->get_token_info();
		$verification = $api_manager->verify_api_key();
		$helper_token_info = forvoyez_get_token_info();

		$image_processor = new Forvoyez_Image_Processor();
		$image_processor->analyze_image_on_upload($this->image_id);
		$image_processor->cron_analyze_single_image($this->image_id);
		$image_processor->schedule_image_analysis($this->image_id);

		$this->assertSame([], $this->requests, 'No HTTP request may be sent without an API key');
		$this->assertSame('missing_api_key', $analysis['error']['code']);
		$this->assertSame('missing_api_key', $token_info['error']['code']);
		$this->assertSame('missing_api_key', $helper_token_info['error']['code']);
		$this->assertFalse($verification['success']);
		$this->assertFalse(
			wp_next_scheduled('forvoyez_analyze_single_image', [ $this->image_id ]),
		);
		$this->assertSame('', $this->stored_alt());
	}

	public function test_verify_api_key_checks_the_stored_key_against_the_tokens_endpoint()
	{
		$this->store_api_key('stored.jwt.for-tests');
		$this->fake_response(self::TOKENS_URL, 200, [
			'user' => [ 'credits' => 12 ],
			'subscription' => null,
		]);

		$result = $this->api_manager()->verify_api_key();

		$this->assertTrue($result['success']);
		$this->assertSame('API key is valid', $result['message']);
		$this->assertCount(1, $this->requests);
		$this->assertSame(self::TOKENS_URL, $this->requests[0]['url']);
		$this->assertSame('GET', $this->requests[0]['args']['method']);
		$this->assertSame(
			'Bearer stored.jwt.for-tests',
			$this->requests[0]['args']['headers']['Authorization'],
		);
	}

	public function test_verify_api_key_surfaces_the_json_error()
	{
		$this->store_api_key('stored.jwt.for-tests');
		$this->fake_response(self::TOKENS_URL, 401, [ 'error' => 'Invalid or expired token' ]);

		$result = $this->api_manager()->verify_api_key();

		$this->assertFalse($result['success']);
		$this->assertSame('Invalid or expired token', $result['message']);
	}

	public function test_token_info_helper_uses_the_stored_key()
	{
		$this->store_api_key('stored.jwt.for-tests');
		$body = [
			'user' => [ 'credits' => 7 ],
			'subscription' => [ 'plan' => 'test' ],
		];
		$this->fake_response(self::TOKENS_URL, 200, $body);

		$result = forvoyez_get_token_info();

		$this->assertTrue($result['success']);
		$this->assertSame(7, $result['user']['credits']);
		$this->assertSame(
			'Bearer stored.jwt.for-tests',
			$this->requests[0]['args']['headers']['Authorization'],
		);
	}

	public function test_token_info_surfaces_the_json_error()
	{
		$this->fake_response(self::TOKENS_URL, 401, [ 'error' => 'Token not found or expired in database' ]);

		$result = $this->api_manager()->get_token_info();

		$this->assertFalse($result['success']);
		$this->assertSame('token_request_failed', $result['error']['code']);
		$this->assertSame('Token not found or expired in database', $result['error']['message']);
	}

	/**
	 * @dataProvider data_json_errors
	 */
	public function test_describe_json_error_is_surfaced_and_changes_nothing($status, $message)
	{
		update_post_meta($this->image_id, '_wp_attachment_image_alt', 'Existing alt');
		$this->fake_response(self::DESCRIBE_URL, $status, [ 'error' => $message ]);

		$result = $this->api_manager()->analyze_image($this->image_id);

		$this->assertFalse($result['success']);
		$this->assertSame('api_error', $result['error']['code']);
		$this->assertSame($message, $result['error']['message']);
		$this->assertSame($status, $result['debug_info']['response_code']);
		$this->assertSame('Existing alt', $this->stored_alt());
		$this->assertSame('', get_post_meta($this->image_id, '_forvoyez_analyzed', true));
		$this->assertStringNotContainsString(
			self::API_KEY,
			wp_json_encode($result),
			'The API key must never be echoed back in an error',
		);
	}

	/**
	 * Errors the describe route returns (ForVoyez src/app/api/describe/route.js).
	 */
	public static function data_json_errors()
	{
		return [
			'400 invalid image' => [ 400, 'Bad Request, Invalid image file' ],
			'400 invalid schema' => [ 400, 'Invalid schema: at most 20 fields are allowed' ],
			'401 invalid token' => [ 401, 'Unauthorized, invalid token' ],
			'401 no credit left' => [ 401, 'Unauthorized, no credit left' ],
			'500 image processing' => [
				500,
				'Image processing failed: Image size exceeds the maximum limit of 10 MB',
			],
			'500 server error' => [ 500, 'Internal Server Error' ],
		];
	}

	public function test_transport_error_is_surfaced()
	{
		// No fake response queued: the interceptor answers with a WP_Error.
		$result = $this->api_manager()->analyze_image($this->image_id);

		$this->assertFalse($result['success']);
		$this->assertSame('api_request_failed', $result['error']['code']);
		$this->assertStringContainsString('Unexpected HTTP request in tests', $result['error']['message']);
	}

	public function test_generated_metadata_is_saved_as_plain_text()
	{
		$this->fake_response(self::DESCRIBE_URL, 200, [
			'title' => 'Lake <em>view</em>',
			'alternativeText' => 'A lake <a href="https://evil.example">at dawn</a>',
			'caption' => 'Morning at the lake. <a href="https://evil.example">Download</a>'
				. '<img src="https://tracker.example/p.gif"> [gallery ids="1"]',
		]);

		$result = $this->api_manager()->analyze_image($this->image_id);

		$this->assertTrue($result['success']);
		$this->assertSame('A lake at dawn', $this->stored_alt());
		$this->assertSame('Lake view', get_post($this->image_id)->post_title);
		$this->assertSame('Morning at the lake. Download', get_post($this->image_id)->post_excerpt);
		$stored = wp_json_encode($result['metadata']);
		$this->assertStringNotContainsString('evil.example', $stored);
		$this->assertStringNotContainsString('tracker.example', $stored);
		$this->assertStringNotContainsString('[gallery', $stored);
	}

	public function test_escaped_and_unregistered_shortcodes_are_not_saved()
	{
		// strip_shortcodes() turns the escaped [[gallery]] into a live [gallery]
		// and keeps the shortcodes that are not registered in this request.
		$this->fake_response(self::DESCRIBE_URL, 200, [
			'title' => 'Lake [[gallery ids="1,2,3"]]',
			'alternativeText' => 'A lake [contact-form-7 id="9"]',
			'caption' => 'Sunset [[gallery ids="1"]] at the lake',
		]);

		$result = $this->api_manager()->analyze_image($this->image_id);

		$this->assertTrue($result['success']);
		$caption = get_post($this->image_id)->post_excerpt;
		$this->assertSame('Sunset gallery ids="1" at the lake', $caption);
		$this->assertSame('A lake contact-form-7 id="9"', $this->stored_alt());
		$this->assertSame('Lake gallery ids="1,2,3"', get_post($this->image_id)->post_title);
		$this->assertSame($caption, do_shortcode($caption), 'The saved caption must not run a shortcode');
	}

	public function test_generated_metadata_length_is_capped()
	{
		$this->fake_response(self::DESCRIBE_URL, 200, [
			'title' => str_repeat('t', 300),
			'alternativeText' => str_repeat('é', 400),
			'caption' => str_repeat('c', 800),
		]);

		$result = $this->api_manager()->analyze_image($this->image_id);

		$this->assertTrue($result['success']);
		$this->assertSame(str_repeat('é', Forvoyez_API_Manager::MAX_ALT_TEXT_LENGTH), $this->stored_alt());
		$this->assertSame(
			str_repeat('t', Forvoyez_API_Manager::MAX_TITLE_LENGTH),
			get_post($this->image_id)->post_title,
		);
		$this->assertSame(
			str_repeat('c', Forvoyez_API_Manager::MAX_CAPTION_LENGTH),
			get_post($this->image_id)->post_excerpt,
		);
	}

	public function test_redirects_are_not_followed_with_the_api_key()
	{
		$this->responses[ self::DESCRIBE_URL ] = [
			'headers' => [ 'location' => 'http://elsewhere.example/api/describe' ],
			'body' => '',
			'response' => [ 'code' => 301, 'message' => 'Moved Permanently' ],
			'cookies' => [],
			'filename' => null,
		];
		$this->fake_response(self::TOKENS_URL, 200, [ 'user' => [ 'credits' => 3 ] ]);
		$this->store_api_key(self::API_KEY);

		$result = $this->api_manager()->analyze_image($this->image_id);
		$this->api_manager()->verify_api_key();
		$this->api_manager()->get_token_info();

		$this->assertFalse($result['success']);
		$this->assertSame('api_error', $result['error']['code']);
		$this->assertStringContainsString('301', $result['error']['message']);
		$this->assertCount(3, $this->requests);
		foreach ($this->requests as $request) {
			$this->assertStringStartsWith('https://forvoyez.com/', $request['url']);
			$this->assertSame(0, $request['args']['redirection']);
		}
	}

	/**
	 * An API manager with a key and the real WP_Http client.
	 */
	private function api_manager(): Forvoyez_API_Manager
	{
		return new Forvoyez_API_Manager(self::API_KEY, 'en', '');
	}

	/**
	 * Queue a JSON response for a URL, as the ForVoyez API sends it.
	 *
	 * @param string       $url  Request URL.
	 * @param int          $code HTTP status code.
	 * @param array|string $body Response body (arrays are JSON encoded).
	 */
	private function fake_response(string $url, int $code, $body): void
	{
		$this->responses[ $url ] = [
			'headers' => [ 'content-type' => 'application/json' ],
			'body' => is_string($body) ? $body : wp_json_encode($body),
			'response' => [
				'code' => $code,
				'message' => get_status_header_desc($code),
			],
			'cookies' => [],
			'filename' => null,
		];
	}

	/**
	 * Save an API key the way the settings screen does (encrypted option).
	 */
	private function store_api_key(string $api_key): void
	{
		$settings = new Forvoyez_Settings();
		$encrypt = new ReflectionMethod(Forvoyez_Settings::class, 'encrypt');
		if (PHP_VERSION_ID < 80100) {
			$encrypt->setAccessible(true); // No-op (deprecated) since PHP 8.1.
		}
		update_option('forvoyez_encrypted_api_key', $encrypt->invoke($settings, $api_key));
	}

	private function stored_alt(): string
	{
		return (string) get_post_meta($this->image_id, '_wp_attachment_image_alt', true);
	}
}
