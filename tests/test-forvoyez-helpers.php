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
        // Create some test images
        $complete_id = $this->create_test_image(true, true, true);   // Complete image
        $no_title_id = $this->create_test_image(false, true, true);  // Missing title
        $no_alt_id = $this->create_test_image(true, false, true);    // Missing alt text
        $no_caption_id = $this->create_test_image(true, true, false);// Missing caption

        $incomplete_count = forvoyez_count_incomplete_images();

        // Debug information
        error_log("Complete image (should not be counted): " . print_r(get_post($complete_id), true));
        error_log("No title image: " . print_r(get_post($no_title_id), true));
        error_log("No alt image: " . print_r(get_post($no_alt_id), true));
        error_log("No caption image: " . print_r(get_post($no_caption_id), true));

        $this->assertEquals(3, $incomplete_count, 'Incorrect count of incomplete images');
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

        // Restore the original instance
        $forvoyez_settings = $original_settings;
    }

    /**
     * Helper function to create a test image with specified metadata.
     */
    private function create_test_image($has_title, $has_alt, $has_caption) {
        $attachment_id = $this->factory->attachment->create_upload_object(__DIR__ . '/assets/test-image.webp');

        if ($has_title) {
            wp_update_post(['ID' => $attachment_id, 'post_title' => 'Test Title']);
        } else {
            // Ensure the title is set to the filename
            $filename = basename(get_attached_file($attachment_id));
            wp_update_post(['ID' => $attachment_id, 'post_title' => $filename]);
        }

        if ($has_alt) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', 'Test Alt Text');
        } else {
            delete_post_meta($attachment_id, '_wp_attachment_image_alt');
        }

        if ($has_caption) {
            wp_update_post(['ID' => $attachment_id, 'post_excerpt' => 'Test Caption']);
        } else {
            wp_update_post(['ID' => $attachment_id, 'post_excerpt' => '']);
        }

        return $attachment_id;
    }
}