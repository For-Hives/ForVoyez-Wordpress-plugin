<?php
/**
 * Class TestForvoyezImageProcessor
 *
 * Unit tests for the Forvoyez_Image_Processor class logic.
 *
 * @package ForVoyez
 */

class TestForvoyezImageProcessor extends WP_UnitTestCase {
    /**
     * @var Forvoyez_Image_Processor
     */
    private $image_processor;

    /**
     * @var Forvoyez_API_Manager|PHPUnit_Framework_MockObject_MockObject
     */
    private $mock_api_client;

    public function setUp(): void {
        parent::setUp();

        // Mock the API client
        $this->mock_api_client = $this->createMock(Forvoyez_API_Manager::class);

        // Create an instance of the image processor with the mocked API client
        $this->image_processor = new Forvoyez_Image_Processor();
        $reflection = new ReflectionClass($this->image_processor);
        $property = $reflection->getProperty('api_client');
        $property->setAccessible(true);
        $property->setValue($this->image_processor, $this->mock_api_client);
    }

    /**
     * Test sanitize_metadata method.
     */
    public function test_sanitize_metadata() {
        $raw_metadata = [
            'alt_text' => 'Test <script>alert("XSS")</script>',
            'title' => 'Test Title',
            'caption' => '<p>Test <strong>Caption</strong></p>',
            'extra_field' => 'Should be removed'
        ];

        $expected = [
            'alt_text' => 'Test alert("XSS")',
            'title' => 'Test Title',
            'caption' => '<p>Test <strong>Caption</strong></p>'
        ];

        $result = $this->call_private_method($this->image_processor, 'sanitize_metadata', [$raw_metadata]);

        $this->assertEquals($expected, $result, 'Metadata was not sanitized correctly');
    }

    /**
     * Test update_image_meta method.
     */
    public function test_update_image_meta() {
        // Create a test image
        $attachment_id = $this->factory->attachment->create_upload_object(__DIR__ . '/assets/test-image.jpg', 0);

        $metadata = [
            'alt_text' => 'New Alt Text',
            'title' => 'New Title',
            'caption' => 'New Caption'
        ];

        $this->call_private_method($this->image_processor, 'update_image_meta', [$attachment_id, $metadata]);

        // Check if metadata was updated correctly
        $this->assertEquals('New Alt Text', get_post_meta($attachment_id, '_wp_attachment_image_alt', true), 'Alt text was not updated correctly');
        $this->assertEquals('New Title', get_post($attachment_id)->post_title, 'Title was not updated correctly');
        $this->assertEquals('New Caption', get_post($attachment_id)->post_excerpt, 'Caption was not updated correctly');
        $this->assertTrue(get_post_meta($attachment_id, '_forvoyez_analyzed', true), 'Image was not marked as analyzed');

        // Clean up
        wp_delete_attachment($attachment_id, true);
    }

    /**
     * Test get_incomplete_images method.
     */
    public function test_get_incomplete_images() {
        // Create test images
        $complete_id = $this->create_test_image(true, true, true);
        $no_alt_id = $this->create_test_image(false, true, true);
        $no_title_id = $this->create_test_image(true, false, true);
        $no_caption_id = $this->create_test_image(true, true, false);

        $incomplete_images = $this->call_private_method($this->image_processor, 'get_incomplete_images', [0, 10]);

        $this->assertCount(3, $incomplete_images, 'Incorrect number of incomplete images returned');
        $this->assertContains($no_alt_id, wp_list_pluck($incomplete_images, 'ID'), 'Image with no alt text should be included');
        $this->assertContains($no_title_id, wp_list_pluck($incomplete_images, 'ID'), 'Image with no title should be included');
        $this->assertContains($no_caption_id, wp_list_pluck($incomplete_images, 'ID'), 'Image with no caption should be included');
        $this->assertNotContains($complete_id, wp_list_pluck($incomplete_images, 'ID'), 'Complete image should not be included');

        // Clean up
        wp_delete_attachment($complete_id, true);
        wp_delete_attachment($no_alt_id, true);
        wp_delete_attachment($no_title_id, true);
        wp_delete_attachment($no_caption_id, true);
    }

    /**
     * Test is_image_incomplete method.
     */
    public function test_is_image_incomplete() {
        // Create test images
        $complete_id = $this->create_test_image(true, true, true);
        $incomplete_id = $this->create_test_image(false, true, false);

        $complete_image = get_post($complete_id);
        $incomplete_image = get_post($incomplete_id);

        $this->assertFalse($this->call_private_method($this->image_processor, 'is_image_incomplete', [$complete_image]), 'Complete image should not be marked as incomplete');
        $this->assertTrue($this->call_private_method($this->image_processor, 'is_image_incomplete', [$incomplete_image]), 'Incomplete image should be marked as incomplete');

        // Clean up
        wp_delete_attachment($complete_id, true);
        wp_delete_attachment($incomplete_id, true);
    }

    /**
     * Test process_images method.
     */
    public function test_process_images() {
        $image_ids = [1, 2, 3];

        $this->mock_api_client->expects($this->exactly(3))
            ->method('analyze_image')
            ->willReturnOnConsecutiveCalls(
                ['success' => true, 'message' => 'Success 1', 'metadata' => ['alt' => 'Alt 1']],
                ['success' => false, 'error' => ['message' => 'Error 2', 'code' => 'error_code']],
                ['success' => true, 'message' => 'Success 3', 'metadata' => ['alt' => 'Alt 3']]
            );

        $results = $this->call_private_method($this->image_processor, 'process_images', [$image_ids]);

        $this->assertCount(3, $results, 'Incorrect number of results returned');
        $this->assertTrue($results[0]['success'], 'First result should be successful');
        $this->assertFalse($results[1]['success'], 'Second result should be unsuccessful');
        $this->assertTrue($results[2]['success'], 'Third result should be successful');
    }

    /**
     * Helper method to call private methods for testing.
     *
     * @param object $object The object containing the private method.
     * @param string $method_name The name of the private method.
     * @param array $parameters The parameters to pass to the method.
     * @return mixed The result of the method call.
     */
    private function call_private_method($object, $method_name, array $parameters = []) {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($method_name);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Helper method to create a test image with specified metadata.
     *
     * @param bool $has_alt Whether the image should have alt text.
     * @param bool $has_title Whether the image should have a title.
     * @param bool $has_caption Whether the image should have a caption.
     * @return int The attachment ID of the created image.
     */
    private function create_test_image($has_alt, $has_title, $has_caption) {
        $attachment_id = $this->factory->attachment->create_upload_object(__DIR__ . '/assets/test-image.jpg', 0);

        if ($has_alt) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', 'Test Alt');
        }

        if ($has_title) {
            wp_update_post(['ID' => $attachment_id, 'post_title' => 'Test Title']);
        }

        if ($has_caption) {
            wp_update_post(['ID' => $attachment_id, 'post_excerpt' => 'Test Caption']);
        }

        return $attachment_id;
    }
}