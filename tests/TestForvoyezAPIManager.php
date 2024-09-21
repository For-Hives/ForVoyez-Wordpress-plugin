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

    public function setUp(): void
    {
        parent::setUp();
        $this->mock_http_client = $this->createMock(WP_Http::class);
        $this->api_manager = new Forvoyez_API_Manager(
            'test_api_key',
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
                    $data = json_decode($body, true);

                    // Check if context and language are included in the request
                    $this->assertEquals('Test Context', $data['context']);
                    $this->assertEquals('fr', $data['language']);

                    return true;
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
		$method->setAccessible(true);

		return $method->invokeArgs($object, $parameters);
	}
}
