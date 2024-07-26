<?php
/**
 * Class TestForVoyezHelpers
 *
 * @package ForVoyez
 */

class TestForVoyezHelpers extends WP_UnitTestCase {

    /**
     * Test forvoyez_count_incomplete_images function.
     */
    public function test_forvoyez_count_incomplete_images() {
        // Create test images
        $complete_id = $this->create_test_image(true, true, true);
        $no_title_id = $this->create_test_image(false, true, true);
        $no_alt_id = $this->create_test_image(true, false, true);
        $no_caption_id = $this->create_test_image(true, true, false);

        $incomplete_count = forvoyez_count_incomplete_images();

        $this->assertEquals(3, $incomplete_count, 'Incorrect count of incomplete images');

        // Clean up
        wp_delete_attachment($complete_id, true);
        wp_delete_attachment($no_title_id, true);
        wp_delete_attachment($no_alt_id, true);
        wp_delete_attachment($no_caption_id, true);
    }

    /**
     * Test forvoyez_get_api_key function.
     */
    public function test_forvoyez_get_api_key() {
        global $forvoyez_settings;

        // Save the original instance if it exists
        $original_settings = $forvoyez_settings;

        // Create and set the mock
        $mock_settings = $this->createMock(Forvoyez_Settings::class);
        $mock_settings->method('get_api_key')->willReturn('test_api_key');
        $forvoyez_settings = $mock_settings;

        $api_key = forvoyez_get_api_key();

        $this->assertEquals('test_api_key', $api_key, 'Incorrect API key returned');

        // Test the filter
        add_filter('forvoyez_api_key', function($key) {
            return 'filtered_' . $key;
        });

        $filtered_api_key = forvoyez_get_api_key();
        $this->assertEquals('filtered_test_api_key', $filtered_api_key, 'API key filter not applied correctly');

        // Remove the filter
        remove_all_filters('forvoyez_api_key');

        // Restore the original instance
        $forvoyez_settings = $original_settings;
    }

    /**
     * Test forvoyez_sanitize_api_key function.
     */
    public function test_forvoyez_sanitize_api_key() {
        // Test valid key
        $valid_key = 'abcdefghijklmnopqrstuvwxyz123456';
        $sanitized_key = forvoyez_sanitize_api_key($valid_key);
        $this->assertEquals($valid_key, $sanitized_key, 'Valid API key not sanitized correctly');

        // Test invalid key (too short)
        $invalid_key = 'short';
        $result = forvoyez_sanitize_api_key($invalid_key);
        $this->assertInstanceOf(WP_Error::class, $result, 'Invalid API key not detected');
        $this->assertEquals('invalid_api_key', $result->get_error_code(), 'Incorrect error code for invalid API key');

        // Test with potential XSS
        $xss_key = '<script>alert("XSS")</script>validkey12345678901234567890';
        $sanitized_xss_key = forvoyez_sanitize_api_key($xss_key);
        $this->assertEquals('validkey12345678901234567890', $sanitized_xss_key, 'XSS not properly sanitized from API key');
    }

    /**
     * Helper function to create a test image with specified metadata.
     *
     * @param bool $has_title Whether the image should have a title.
     * @param bool $has_alt Whether the image should have alt text.
     * @param bool $has_caption Whether the image should have a caption.
     * @return int The attachment ID of the created image.
     */
    private function create_test_image($has_title, $has_alt, $has_caption) {
        $filename = plugin_dir_path(__FILE__) . 'assets/test-image.webp';
        $contents = file_get_contents($filename);
        $upload = wp_upload_bits(basename($filename), null, $contents);
        $this->assertTrue(empty($upload['error']), 'Upload failed: ' . ($upload['error'] ?? 'Unknown error'));

        $attachment_id = $this->factory->attachment->create_object($upload['file'], 0, [
            'post_mime_type' => 'image/webp',
            'post_title'     => $has_title ? 'Test Title' : basename($upload['file']),
            'post_excerpt'   => $has_caption ? 'Test Caption' : '',
        ]);

        if ($has_alt) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', 'Test Alt Text');
        }

        return $attachment_id;
    }
}