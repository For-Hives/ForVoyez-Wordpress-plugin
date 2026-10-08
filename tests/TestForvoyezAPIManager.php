<?php
/**
 * Class TestForvoyezAPIManager
 *
 * @package ForVoyez
 */

class TestForvoyezAPIManager extends WP_UnitTestCase
{
    private $api_manager;
    private $test_image_id;
    private $mock_http_client;
    private $api_url = 'https://forvoyez.com/api/describe';
    private $tokens_url = 'https://forvoyez.com/api/tokens';

    public function setUp(): void
    {
        parent::setUp();
        $this->mock_http_client = $this->createMock(WP_Http::class);
        $this->api_manager = new Forvoyez_API_Manager(
            'test_api_key',
            'en',
            '',
            $this->mock_http_client
        );

        // Create a test image attachment
        $this->test_image_id = $this->factory->attachment->create_upload_object(
            __DIR__ . '/assets/test-image.webp',
            0
        );
    }

    public function tearDown(): void
    {
        wp_delete_attachment($this->test_image_id, true);
        remove_all_filters('forvoyez_api_key');
        parent::tearDown();
    }

    public function testConstructor(): void
    {
        $this->assertInstanceOf(
            Forvoyez_API_Manager::class,
            $this->api_manager
        );
    }

    public function testAnalyzeImage(): void
    {
        // Create a temporary file to simulate the image
        $temp_file = tempnam(sys_get_temp_dir(), 'test_image');
        file_put_contents($temp_file, 'fake image data');

        // Mock the get_attached_file function
        add_filter(
            'get_attached_file',
            function ($file, $attachment_id) use ($temp_file) {
                if ($attachment_id === $this->test_image_id) {
                    return $temp_file;
                }
                return $file;
            },
            10,
            2
        );

        // Mock wp_remote_get for file reading
        add_filter(
            'pre_http_request',
            function ($pre, $parsed_args, $url) use ($temp_file) {
                if ($url === $temp_file) {
                    return [
                        'body' => 'fake image data',
                        'response' => ['code' => 200],
                    ];
                }
                return $pre;
            },
            10,
            3
        );

        // Set context and language
        update_option('forvoyez_context', 'Test Context');
        update_option('forvoyez_language', 'fr');

        // Set up the mock API response
        $mock_api_response = [
            'body' => wp_json_encode([
                'title' => 'Mocked Title',
                'alternativeText' => 'Mocked Alt Text',
                'caption' => 'Mocked Caption',
            ]),
            'response' => ['code' => 200],
        ];

        // Mock the HTTP client to capture the request
        $this->mock_http_client->expects($this->once())
            ->method('post')
            ->with(
                $this->equalTo($this->api_url),
                $this->callback(function($args) {
                    $body = $args['body'];
                    $schema = wp_json_encode(Forvoyez_API_Manager::DESCRIBE_SCHEMA);

                    return $args['headers']['Authorization'] === 'Bearer test_api_key'
                        && strpos($body, 'name="schema"') !== false
                        && strpos($body, $schema) !== false
                        && strpos($body, 'name="language"') !== false
                        && strpos($body, 'name="image"') !== false;
                })
            )
            ->willReturn($mock_api_response);

        $result = $this->api_manager->analyze_image($this->test_image_id);

        $this->assertTrue($result['success']);
        $this->assertEquals('Analysis successful', $result['message']);
        $this->assertArrayHasKey('alt_text', $result['metadata']);
        $this->assertArrayHasKey('title', $result['metadata']);
        $this->assertArrayHasKey('caption', $result['metadata']);

        // Check if metadata was updated with mocked values
        $this->assertEquals(
            'Mocked Alt Text',
            get_post_meta($this->test_image_id, '_wp_attachment_image_alt', true)
        );
        $this->assertEquals(
            'Mocked Title',
            get_post($this->test_image_id)->post_title
        );
        $this->assertEquals(
            'Mocked Caption',
            get_post($this->test_image_id)->post_excerpt
        );
        $this->assertEquals(
            '1',
            get_post_meta($this->test_image_id, '_forvoyez_analyzed', true)
        );

        // Clean up
        unlink($temp_file);
        remove_all_filters('get_attached_file');
        remove_all_filters('pre_http_request');
        delete_option('forvoyez_context');
        delete_option('forvoyez_language');
    }

    public function testAnalyzeImageNotFound(): void
    {
        $result = $this->api_manager->analyze_image(999999); // Non-existent ID

        $this->assertFalse($result['success']);
        $this->assertEquals('image_not_found', $result['error']['code']);
        $this->assertEquals('Image not found', $result['error']['message']);
    }


	public function testFormatError(): void
	{
		$error = $this->callPrivateMethod(
			$this->api_manager,
			'format_error',
			[
				'test_code',
				'Test message',
			]
		);

		$this->assertFalse($error['success']);
		$this->assertEquals('test_code', $error['error']['code']);
		$this->assertEquals('Test message', $error['error']['message']);
	}

	public function testFormatErrorWithDebugInfo(): void
	{
		$debug_info = [ 'key' => 'value' ];
		$error = $this->callPrivateMethod(
			$this->api_manager,
			'format_error',
			[
				'test_code',
				'Test message',
				$debug_info,
			]
		);

		$this->assertFalse($error['success']);
		$this->assertEquals('test_code', $error['error']['code']);
		$this->assertEquals('Test message', $error['error']['message']);
		$this->assertEquals($debug_info, $error['debug_info']);
	}

	public function testBuildDataFiles(): void
	{
		$boundary = 'test_boundary';
		$fields = [ 'field1' => 'value1', 'field2' => 'value2' ];
		$file_name = 'test-image.webp';
		$file_mime = 'image/webp';
		$file_data = 'test_file_data';

		$result = $this->callPrivateMethod(
			$this->api_manager,
			'build_data_files',
			[ $boundary, $fields, $file_name, $file_mime, $file_data ],
		);

		$this->assertStringContainsString(
			'Content-Disposition: form-data; name="field1"',
			$result,
		);
		$this->assertStringContainsString(
			'Content-Disposition: form-data; name="field2"',
			$result,
		);
		$this->assertStringContainsString(
			'Content-Disposition: form-data; name="image"; filename="test-image.webp"',
			$result,
		);
		$this->assertStringContainsString('Content-Type: image/webp', $result);
		$this->assertStringContainsString('test_file_data', $result);
	}

	public function testUpdateImageMetadata(): void
	{
		$metadata = [
			'alt_text' => 'Test Alt',
			'title' => 'Test Title',
			'caption' => 'Test Caption',
		];

		$this->callPrivateMethod(
			$this->api_manager,
			'update_image_metadata',
			[
				$this->test_image_id,
				$metadata,
			]
		);

		$this->assertEquals(
			'Test Alt',
			get_post_meta(
				$this->test_image_id,
				'_wp_attachment_image_alt',
				true,
			),
		);
		$this->assertEquals(
			'Test Title',
			get_post($this->test_image_id)->post_title,
		);
		$this->assertEquals(
			'Test Caption',
			get_post($this->test_image_id)->post_excerpt,
		);
		$this->assertEquals(
			'1',
			get_post_meta($this->test_image_id, '_forvoyez_analyzed', true),
		);
	}

	public function testDescribeSchemaHasTheThreeDefaultKeys(): void
	{
		$this->assertSame(
			[ 'title', 'alternativeText', 'caption' ],
			array_keys(Forvoyez_API_Manager::DESCRIBE_SCHEMA),
		);
		foreach (Forvoyez_API_Manager::DESCRIBE_SCHEMA as $description) {
			$this->assertIsString($description);
			$this->assertNotSame('', $description);
		}
	}

	public function testAnalyzeImageFallsBackToAltTextKey(): void
	{
		$this->mockDescribeResponse(200, wp_json_encode([ 'alt_text' => 'Legacy alt' ]));

		$result = $this->api_manager->analyze_image($this->test_image_id);

		$this->assertTrue($result['success']);
		$this->assertSame('Legacy alt', $result['metadata']['alt_text']);
		$this->assertSame(
			'Legacy alt',
			get_post_meta($this->test_image_id, '_wp_attachment_image_alt', true),
		);
	}

	public function testAnalyzeImageNeverOverwritesExistingValuesWithEmptyOnes(): void
	{
		update_post_meta($this->test_image_id, '_wp_attachment_image_alt', 'Existing alt');
		wp_update_post([
			'ID' => $this->test_image_id,
			'post_title' => 'Existing title',
			'post_excerpt' => 'Existing caption',
		]);

		$this->mockDescribeResponse(
			200,
			wp_json_encode([
				'title' => 'New title',
				'alternativeText' => '',
				'caption' => '   ',
			]),
		);

		$result = $this->api_manager->analyze_image($this->test_image_id);

		$this->assertTrue($result['success']);
		$this->assertSame(
			'Existing alt',
			get_post_meta($this->test_image_id, '_wp_attachment_image_alt', true),
		);
		$this->assertSame('New title', get_post($this->test_image_id)->post_title);
		$this->assertSame('Existing caption', get_post($this->test_image_id)->post_excerpt);
		$this->assertSame('Existing alt', $result['metadata']['alt_text']);
		$this->assertSame('Existing caption', $result['metadata']['caption']);
	}

	public function testAnalyzeImageWithEmptyResponseChangesNothing(): void
	{
		update_post_meta($this->test_image_id, '_wp_attachment_image_alt', 'Existing alt');
		$this->mockDescribeResponse(200, wp_json_encode([ 'title' => '', 'alternativeText' => '' ]));

		$result = $this->api_manager->analyze_image($this->test_image_id);

		$this->assertFalse($result['success']);
		$this->assertSame('empty_response', $result['error']['code']);
		$this->assertSame(
			'Existing alt',
			get_post_meta($this->test_image_id, '_wp_attachment_image_alt', true),
		);
		$this->assertSame('', get_post_meta($this->test_image_id, '_forvoyez_analyzed', true));
	}

	public function testAnalyzeImageWithoutApiKeyDoesNotCallTheApi(): void
	{
		$http_client = $this->createMock(WP_Http::class);
		$http_client->expects($this->never())->method('post');
		$api_manager = new Forvoyez_API_Manager('', 'en', '', $http_client);

		$result = $api_manager->analyze_image($this->test_image_id);

		$this->assertFalse($api_manager->has_api_key());
		$this->assertFalse($result['success']);
		$this->assertSame('missing_api_key', $result['error']['code']);
		$this->assertSame(forvoyez_get_missing_api_key_message(), $result['error']['message']);
	}

	public function testAnalyzeImageSurfacesJsonErrorMessage(): void
	{
		update_post_meta($this->test_image_id, '_wp_attachment_image_alt', 'Existing alt');
		$this->mockDescribeResponse(
			401,
			wp_json_encode([ 'error' => 'Token not found or expired in database' ]),
		);

		$result = $this->api_manager->analyze_image($this->test_image_id);

		$this->assertFalse($result['success']);
		$this->assertSame('api_error', $result['error']['code']);
		$this->assertSame('Token not found or expired in database', $result['error']['message']);
		$this->assertSame(401, $result['debug_info']['response_code']);
		$this->assertSame(
			'Existing alt',
			get_post_meta($this->test_image_id, '_wp_attachment_image_alt', true),
		);
	}

	public function testAnalyzeImageFallsBackToRawBodyForNonJsonErrors(): void
	{
		$this->mockDescribeResponse(401, 'Unauthorized, no credit left');

		$result = $this->api_manager->analyze_image($this->test_image_id);

		$this->assertFalse($result['success']);
		$this->assertSame('api_error', $result['error']['code']);
		$this->assertStringContainsString('HTTP 401', $result['error']['message']);
		$this->assertStringContainsString('Unauthorized, no credit left', $result['error']['message']);
	}

	public function testAnalyzeImageFallsBackToStatusForEmptyErrorBody(): void
	{
		$this->mockDescribeResponse(502, '');

		$result = $this->api_manager->analyze_image($this->test_image_id);

		$this->assertFalse($result['success']);
		$this->assertStringContainsString('HTTP 502', $result['error']['message']);
	}

	public function testAnalyzeImageStripsHtmlFromNonJsonBodies(): void
	{
		$this->mockDescribeResponse(200, '<html><body><h1>Bad gateway</h1><script>alert(1)</script></body></html>');

		$result = $this->api_manager->analyze_image($this->test_image_id);

		$this->assertFalse($result['success']);
		$this->assertSame('invalid_response', $result['error']['code']);
		$this->assertStringContainsString('Bad gateway', $result['error']['message']);
		$this->assertStringNotContainsString('<', $result['error']['message']);
		$this->assertStringNotContainsString('json_decode_error', $result['error']['code']);
	}

	public function testVerifyApiKeyCallsTheTokensEndpoint(): void
	{
		add_filter('forvoyez_api_key', function () {
			return 'saved_api_key';
		});

		$this->mock_http_client->expects($this->once())
			->method('get')
			->with(
				$this->equalTo($this->tokens_url),
				$this->callback(function ($args) {
					return $args['headers']['Authorization'] === 'Bearer saved_api_key';
				}),
			)
			->willReturn($this->httpResponse(200, wp_json_encode([ 'success' => true ])));

		$result = $this->api_manager->verify_api_key();

		$this->assertTrue($result['success']);
		$this->assertSame('API key is valid', $result['message']);
	}

	public function testVerifyApiKeyReturnsTheApiErrorMessage(): void
	{
		add_filter('forvoyez_api_key', function () {
			return 'saved_api_key';
		});

		$this->mock_http_client->method('get')->willReturn(
			$this->httpResponse(401, wp_json_encode([ 'error' => 'Invalid or expired token' ])),
		);

		$result = $this->api_manager->verify_api_key();

		$this->assertFalse($result['success']);
		$this->assertSame('Invalid or expired token', $result['message']);
	}

	public function testVerifyApiKeyWithoutKey(): void
	{
		add_filter('forvoyez_api_key', '__return_empty_string');
		$this->mock_http_client->expects($this->never())->method('get');

		$result = $this->api_manager->verify_api_key();

		$this->assertFalse($result['success']);
	}

	public function testGetTokenInfoReturnsTheDecodedBody(): void
	{
		$body = [
			'success' => true,
			'user' => [ 'credits' => 42 ],
			'subscription' => [ 'isSubscribed' => false ],
		];
		$this->mock_http_client->expects($this->once())
			->method('get')
			->with($this->equalTo($this->tokens_url))
			->willReturn($this->httpResponse(200, wp_json_encode($body)));

		$this->assertSame($body, $this->api_manager->get_token_info());
	}

	public function testGetTokenInfoSurfacesJsonError(): void
	{
		$this->mock_http_client->method('get')->willReturn(
			$this->httpResponse(401, wp_json_encode([ 'error' => 'Token not found or expired in database' ])),
		);

		$result = $this->api_manager->get_token_info();

		$this->assertFalse($result['success']);
		$this->assertSame('token_request_failed', $result['error']['code']);
		$this->assertSame('Token not found or expired in database', $result['error']['message']);
	}

	/**
	 * Make the mocked HTTP client answer the describe request.
	 *
	 * @param int $code HTTP status code.
	 * @param string $body Response body.
	 */
	private function mockDescribeResponse(int $code, string $body): void
	{
		$this->mock_http_client->expects($this->once())
			->method('post')
			->with($this->equalTo($this->api_url))
			->willReturn($this->httpResponse($code, $body));
	}

	/**
	 * Build a WP_Http-like response array.
	 *
	 * @param int $code HTTP status code.
	 * @param string $body Response body.
	 * @return array
	 */
	private function httpResponse(int $code, string $body): array
	{
		return [
			'headers' => [],
			'body' => $body,
			'response' => [ 'code' => $code, 'message' => '' ],
			'cookies' => [],
		];
	}

	/**
	 * Call a private method on an object.
	 *
	 * @param object $object The object containing the method.
	 * @param string $method_name The name of the private method.
	 * @param array $parameters The parameters to pass to the method.
	 * @return mixed The result of the method call.
	 */
	private function callPrivateMethod(
		$object,
		string $method_name,
		array $parameters = [],
	): mixed {
		$reflection = new ReflectionClass(get_class($object));
		$method = $reflection->getMethod($method_name);
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true); // No-op (deprecated) since PHP 8.1.
		}

		return $method->invokeArgs($object, $parameters);
	}
}
