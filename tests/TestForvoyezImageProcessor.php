<?php
/**
 * Class TestForvoyezImageProcessor
 *
 * Unit tests for the Forvoyez_Image_Processor class logic.
 *
 * @package ForVoyez
 */

class TestForvoyezImageProcessor extends WP_UnitTestCase
{
	private $image_processor;
	private $mock_api_client;

	public function setUp(): void
	{
		parent::setUp();
		$this->mock_api_client = $this->createMock(Forvoyez_API_Manager::class);
		$this->image_processor = new Forvoyez_Image_Processor();
		$reflection = new ReflectionClass($this->image_processor);
		$property = $reflection->getProperty('api_client');
		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true); // No-op (deprecated) since PHP 8.1.
		}
		$property->setValue($this->image_processor, $this->mock_api_client);
	}

	public function test_sanitize_and_validate_metadata()
	{
		$raw_metadata = [
			'alt_text' => 'Test <script>alert("XSS")</script>',
			'title' => 'Test Title',
			'caption' => '<p>Test <strong>Caption</strong></p><script>alert("XSS")</script>',
			'extra_field' => 'Should be removed',
		];

		$expected = [
			'alt_text' => 'Test',
			'title' => 'Test Title',
			'caption' => '<p>Test <strong>Caption</strong></p>alert("XSS")',
		];

		$reflection = new ReflectionClass(Forvoyez_Image_Processor::class);
		$method = $reflection->getMethod('sanitize_and_validate_metadata');
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true); // No-op (deprecated) since PHP 8.1.
		}

		$result = $method->invokeArgs($this->image_processor, [ $raw_metadata ]);

		$this->assertEquals($expected, $result, 'Metadata was not sanitized correctly');

		$this->assertEquals('Test', $result['alt_text'], 'Alt text was not sanitized correctly');
		$this->assertEquals('Test Title', $result['title'], 'Title was not sanitized correctly');
		$this->assertEquals('<p>Test <strong>Caption</strong></p>alert("XSS")', $result['caption'], 'Caption was not sanitized correctly');
		$this->assertArrayNotHasKey('extra_field', $result, 'Extra field was not removed');
	}

	public function test_update_image_meta()
	{
		$attachment_id = $this->factory->attachment->create_upload_object(
			__DIR__ . '/assets/test-image.webp',
			0,
		);

		$metadata = [
			'alt_text' => 'New Alt Text',
			'title' => 'New Title',
			'caption' => 'New Caption',
		];

		$this->call_private_method(
			$this->image_processor,
			'update_image_meta',
			[ $attachment_id, $metadata ],
		);

		$this->assertEquals(
			'New Alt Text',
			get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
			'Alt text was not updated correctly',
		);
		$this->assertEquals(
			'New Title',
			get_post($attachment_id)->post_title,
			'Title was not updated correctly',
		);
		$this->assertEquals(
			'New Caption',
			get_post($attachment_id)->post_excerpt,
			'Caption was not updated correctly',
		);
		$this->assertEquals(
			'1',
			get_post_meta($attachment_id, '_forvoyez_analyzed', true),
			'Image was not marked as analyzed',
		);

		wp_delete_attachment($attachment_id, true);
	}

	public function test_get_incomplete_images()
	{
		$complete_id = $this->create_test_image(true, true, true);
		$no_alt_id = $this->create_test_image(false, true, true);
		$no_title_id = $this->create_test_image(true, false, true);
		$no_caption_id = $this->create_test_image(true, true, false);

		$incomplete_images = $this->call_private_method(
			$this->image_processor,
			'get_incomplete_images',
			[ 0, 10 ],
		);

		$this->assertCount(
			3,
			$incomplete_images,
			'Incorrect number of incomplete images returned',
		);
		$this->assertContains(
			$no_alt_id,
			wp_list_pluck($incomplete_images, 'ID'),
			'Image with no alt text should be included',
		);
		$this->assertContains(
			$no_title_id,
			wp_list_pluck($incomplete_images, 'ID'),
			'Image with no title should be included',
		);
		$this->assertContains(
			$no_caption_id,
			wp_list_pluck($incomplete_images, 'ID'),
			'Image with no caption should be included',
		);
		$this->assertNotContains(
			$complete_id,
			wp_list_pluck($incomplete_images, 'ID'),
			'Complete image should not be included',
		);

		wp_delete_attachment($complete_id, true);
		wp_delete_attachment($no_alt_id, true);
		wp_delete_attachment($no_title_id, true);
		wp_delete_attachment($no_caption_id, true);
	}

	public function test_is_image_incomplete()
	{
		$complete_id = $this->create_test_image(true, true, true);
		$incomplete_id = $this->create_test_image(false, true, false);

		$complete_image = get_post($complete_id);
		$incomplete_image = get_post($incomplete_id);

		$this->assertFalse(
			$this->call_private_method(
				$this->image_processor,
				'is_image_incomplete',
				[ $complete_image ],
			),
			'Complete image should not be marked as incomplete',
		);
		$this->assertTrue(
			$this->call_private_method(
				$this->image_processor,
				'is_image_incomplete',
				[ $incomplete_image ],
			),
			'Incomplete image should be marked as incomplete',
		);

		wp_delete_attachment($complete_id, true);
		wp_delete_attachment($incomplete_id, true);
	}

	public function test_process_images()
	{
		// process_images() only sends images the current user may edit.
		wp_set_current_user(self::factory()->user->create([ 'role' => 'administrator' ]));
		$image_ids = [];
		for ($i = 0; $i < 3; $i++) {
			$image_ids[] = self::factory()->attachment->create_object([
				'file' => __DIR__ . '/assets/test-image.webp',
				'post_mime_type' => 'image/webp',
				'post_title' => 'test-image',
			]);
		}

		$this->mock_api_client
			->expects($this->exactly(3))
			->method('analyze_image')
			->willReturnOnConsecutiveCalls(
				[
					'success' => true,
					'message' => 'Success 1',
					'metadata' => [ 'alt' => 'Alt 1' ],
				],
				[
					'success' => false,
					'error' => [ 'message' => 'Error 2', 'code' => 'error_code' ],
				],
				[
					'success' => true,
					'message' => 'Success 3',
					'metadata' => [ 'alt' => 'Alt 3' ],
				],
			);

		$results = $this->call_private_method(
			$this->image_processor,
			'process_images',
			[ $image_ids ],
		);

		$this->assertCount(3, $results, 'Incorrect number of results returned');
		$this->assertTrue(
			$results[0]['success'],
			'First result should be successful',
		);
		$this->assertFalse(
			$results[1]['success'],
			'Second result should be unsuccessful',
		);
		$this->assertTrue(
			$results[2]['success'],
			'Third result should be successful',
		);
	}

	public function test_schedule_image_analysis_without_api_key_does_not_schedule()
	{
		update_option('forvoyez_auto_analyze_enabled', true);
		$this->mock_api_client->method('has_api_key')->willReturn(false);
		$attachment_id = $this->factory->attachment->create_upload_object(
			__DIR__ . '/assets/test-image.webp',
			0,
		);

		$this->image_processor->schedule_image_analysis($attachment_id);

		$this->assertFalse(
			wp_next_scheduled('forvoyez_analyze_single_image', [ $attachment_id ]),
			'No analysis should be scheduled without an API key',
		);

		wp_delete_attachment($attachment_id, true);
		delete_option('forvoyez_auto_analyze_enabled');
	}

	public function test_schedule_image_analysis_with_api_key_schedules()
	{
		update_option('forvoyez_auto_analyze_enabled', true);
		$this->mock_api_client->method('has_api_key')->willReturn(true);
		$attachment_id = $this->factory->attachment->create_upload_object(
			__DIR__ . '/assets/test-image.webp',
			0,
		);

		$this->image_processor->schedule_image_analysis($attachment_id);

		$this->assertNotFalse(
			wp_next_scheduled('forvoyez_analyze_single_image', [ $attachment_id ]),
			'An analysis should be scheduled when an API key is configured',
		);

		wp_clear_scheduled_hook('forvoyez_analyze_single_image', [ $attachment_id ]);
		wp_delete_attachment($attachment_id, true);
		delete_option('forvoyez_auto_analyze_enabled');
	}

	public function test_schedule_image_analysis_when_turned_off_does_not_schedule()
	{
		// The toggle used to store the AJAX string as is: 'false' is truthy.
		update_option('forvoyez_auto_analyze_enabled', 'false');
		$this->mock_api_client->method('has_api_key')->willReturn(true);
		$attachment_id = $this->factory->attachment->create_upload_object(
			__DIR__ . '/assets/test-image.webp',
			0,
		);

		$this->image_processor->schedule_image_analysis($attachment_id);

		$this->assertFalse(
			wp_next_scheduled('forvoyez_analyze_single_image', [ $attachment_id ]),
			'No analysis should be scheduled once automatic analysis is turned off',
		);

		wp_delete_attachment($attachment_id, true);
		delete_option('forvoyez_auto_analyze_enabled');
	}

	public function test_cron_analyze_without_api_key_does_not_call_api()
	{
		update_option('forvoyez_auto_analyze_enabled', 'true');
		$this->mock_api_client->method('has_api_key')->willReturn(false);
		$this->mock_api_client->expects($this->never())->method('analyze_image');

		$this->image_processor->cron_analyze_single_image(123);

		delete_option('forvoyez_auto_analyze_enabled');
	}

	public function test_cron_analyze_when_turned_off_does_not_call_api()
	{
		update_option('forvoyez_auto_analyze_enabled', 'false');
		$this->mock_api_client->method('has_api_key')->willReturn(true);
		$this->mock_api_client->expects($this->never())->method('analyze_image');

		$this->image_processor->cron_analyze_single_image(123);

		delete_option('forvoyez_auto_analyze_enabled');
	}

	public function test_cron_analyze_with_api_key_calls_api_once()
	{
		update_option('forvoyez_auto_analyze_enabled', 'true');
		$this->mock_api_client->method('has_api_key')->willReturn(true);
		$this->mock_api_client
			->expects($this->once())
			->method('analyze_image')
			->with(123)
			->willReturn([ 'success' => true, 'message' => 'ok', 'metadata' => [] ]);

		$this->image_processor->cron_analyze_single_image(123);

		delete_option('forvoyez_auto_analyze_enabled');
	}

	public function test_analyze_image_on_upload_without_api_key_does_not_call_api()
	{
		$this->mock_api_client->method('has_api_key')->willReturn(false);
		$this->mock_api_client->expects($this->never())->method('analyze_image');
		$attachment_id = $this->factory->attachment->create_upload_object(
			__DIR__ . '/assets/test-image.webp',
			0,
		);

		$this->image_processor->analyze_image_on_upload($attachment_id);

		wp_delete_attachment($attachment_id, true);
	}

	private function call_private_method(
		$object,
		$method_name,
		array $parameters = [],
	) {
		$reflection = new ReflectionClass(get_class($object));
		$method = $reflection->getMethod($method_name);
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true); // No-op (deprecated) since PHP 8.1.
		}

		return $method->invokeArgs($object, $parameters);
	}

	private function create_test_image($has_alt, $has_title, $has_caption)
	{
		$attachment_id = $this->factory->attachment->create_upload_object(
			__DIR__ . '/assets/test-image.webp',
			0,
		);

		$post_data = [ 'ID' => $attachment_id ];

		if ($has_alt) {
			update_post_meta(
				$attachment_id,
				'_wp_attachment_image_alt',
				'Test Alt',
			);
		} else {
			delete_post_meta($attachment_id, '_wp_attachment_image_alt');
		}

		if ($has_title) {
			$post_data['post_title'] = 'Test Title';
		} else {
			$post_data['post_title'] = ''; // Explicitly set an empty title
		}

		if ($has_caption) {
			$post_data['post_excerpt'] = 'Test Caption';
		} else {
			$post_data['post_excerpt'] = ''; // Explicitly set an empty caption
		}

		wp_update_post($post_data);

		return $attachment_id;
	}
}
