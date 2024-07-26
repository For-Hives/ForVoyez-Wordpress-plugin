<?php
/**
 * Class TestForVoyezImageRenderer
 *
 * @package ForVoyez
 */

class TestForVoyezImageRenderer extends WP_UnitTestCase {
    /**
     * Test render_image_item method.
     */
    public function test_render_image_item() {
        // Create a test image
        $attachment_id = $this->factory->attachment->create_upload_object(__DIR__ . '/assets/test-image.webp', 0);
        $image = get_post($attachment_id);

        // Set some metadata
        update_post_meta($attachment_id, '_wp_attachment_image_alt', 'Test Alt Text');
        $update_result = wp_update_post([
            'ID' => $attachment_id,
            'post_title' => 'Test Title',
            'post_excerpt' => 'Test Caption',
        ]);

        // Debug: Check if wp_update_post was successful
        $this->assertNotWPError($update_result, 'Failed to update post');

        // Refresh the post object
        $image = get_post($attachment_id);

        // Debug: Check the current state of the image post
        error_log('Image post after update: ' . print_r($image, true));

        $rendered_html = Forvoyez_Image_Renderer::render_image_item($image);

        // Debug: Output the rendered HTML
        error_log('Rendered HTML: ' . $rendered_html);

        // Test that the rendered HTML is not empty
        $this->assertNotEmpty($rendered_html);

        // Test that essential information is present
        $this->assertStringContainsString('data-image-id="' . $attachment_id . '"', $rendered_html);
        $this->assertStringContainsString('Test Alt Text', $rendered_html);
        $this->assertStringContainsString('Test Title', $rendered_html, 'Title not found in rendered HTML');
        $this->assertStringContainsString('Test Caption', $rendered_html);

        // Test that appropriate CSS classes are applied
        $this->assertStringContainsString('all-complete', $rendered_html);
        $this->assertStringNotContainsString('alt-missing', $rendered_html);
        $this->assertStringNotContainsString('title-missing', $rendered_html);
        $this->assertStringNotContainsString('caption-missing', $rendered_html);

        // Test with missing metadata
        $update_result = wp_update_post([
            'ID' => $attachment_id,
            'post_title' => '',
            'post_excerpt' => '',
        ]);

        // Debug: Check if wp_update_post was successful
        $this->assertNotWPError($update_result, 'Failed to update post (remove metadata)');

        delete_post_meta($attachment_id, '_wp_attachment_image_alt');

        // Refresh the post object
        $image = get_post($attachment_id);

        // Debug: Check the current state of the image post after removing metadata
        error_log('Image post after removing metadata: ' . print_r($image, true));

        $rendered_html = Forvoyez_Image_Renderer::render_image_item($image);

        // Debug: Output the rendered HTML after removing metadata
        error_log('Rendered HTML after removing metadata: ' . $rendered_html);

        $this->assertStringContainsString('alt-missing', $rendered_html);
        $this->assertStringContainsString('title-missing', $rendered_html);
        $this->assertStringContainsString('caption-missing', $rendered_html);
        $this->assertStringNotContainsString('all-complete', $rendered_html);

        // Clean up
        wp_delete_attachment($attachment_id, true);
    }
}