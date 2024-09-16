<?php
/**
 * Class TestForVoyezHelpers
 *
 * @package ForVoyez
 */

class TestForVoyezHelpers extends WP_UnitTestCase
{
	/**
	 * Test forvoyez_count_incomplete_images function.
	 */
	public function test_forvoyez_count_incomplete_images()
	{
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
	public function test_forvoyez_get_api_key()
	{
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
		add_filter(
			'forvoyez_api_key',
			function ($key) {
				return 'filtered_' . $key;
			}
		);

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
	public function test_forvoyez_sanitize_api_key()
	{
		// Test valid JWT
		$valid_jwt = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';
		$sanitized_jwt = forvoyez_sanitize_api_key($valid_jwt);
		$this->assertEquals($valid_jwt, $sanitized_jwt, 'Valid JWT not sanitized correctly');

		// Test invalid JWT (wrong format)
		$invalid_jwt = 'not.a.valid.jwt';
		$result = forvoyez_sanitize_api_key($invalid_jwt);
		$this->assertInstanceOf(WP_Error::class, $result, 'Invalid JWT not detected');
		$this->assertEquals('invalid_api_key', $result->get_error_code(), 'Incorrect error code for invalid JWT');

		// Test JWT verification
		$payload = [
			'iss' => 'ForVoyez',
			'aud' => 'ForVoyez',
			'exp' => time() + 3600, // 1 hour from now
		];
		$jwt = $this->generate_test_jwt($payload);
		$verification_result = forvoyez_verify_jwt($jwt);
		$this->assertTrue($verification_result, 'Valid JWT failed verification');

		// Test expired JWT
		$expired_payload = [
			'iss' => 'ForVoyez',
			'aud' => 'ForVoyez',
			'exp' => time() - 3600, // 1 hour ago
		];
		$expired_jwt = $this->generate_test_jwt($expired_payload);
		$expired_verification_result = forvoyez_verify_jwt($expired_jwt);
		$this->assertInstanceOf(WP_Error::class, $expired_verification_result, 'Expired JWT not detected');
		$this->assertEquals('expired_token', $expired_verification_result->get_error_code(), 'Incorrect error code for expired JWT');
	}

	/**
	 * Helper function to generate a test JWT.
	 * Note: This is a very basic implementation for testing purposes only.
	 */
	private function generate_test_jwt($payload)
	{
		$header = [
			'alg' => 'HS256',
			'typ' => 'JWT',
		];
		$header_encoded = $this->base64url_encode(json_encode($header));
		$payload_encoded = $this->base64url_encode(json_encode($payload));
		$signature = hash_hmac('sha256', "$header_encoded.$payload_encoded", 'test_secret', true);
		$signature_encoded = $this->base64url_encode($signature);

		return "$header_encoded.$payload_encoded.$signature_encoded";
	}

	private function base64url_encode($data)
	{
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	/**
	 * Helper function to create a test image with specified metadata.
	 *
	 * @param bool $has_title Whether the image should have a title.
	 * @param bool $has_alt Whether the image should have alt text.
	 * @param bool $has_caption Whether the image should have a caption.
	 * @return int The attachment ID of the created image.
	 */
	private function create_test_image($has_alt, $has_title, $has_caption)
	{
		$attachment_id = $this->factory->attachment->create_upload_object(__DIR__ . '/assets/test-image.webp', 0);

		$post_data = [ 'ID' => $attachment_id ];

		if ($has_alt) {
			update_post_meta($attachment_id, '_wp_attachment_image_alt', 'Test Alt');
		}

		if ($has_title) {
			$post_data['post_title'] = 'Test Title';
		}

		if ($has_caption) {
			$post_data['post_excerpt'] = 'Test Caption';
		}

		wp_update_post($post_data);

		return $attachment_id;
	}
}
