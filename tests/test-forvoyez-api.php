<?php
/**
 * Class TestForvoyezAPI
 *
 * @package ForVoyez
 */
class TestForvoyezAPI extends WP_UnitTestCase {
    private $api;

    public function setUp(): void {
        parent::setUp();
        $this->api = new Forvoyez_API();
    }

    public function testInitAddsAjaxAction() {
        // Initialize the API
        $this->api->init();

        // Check if the action is added
        $this->assertTrue(has_action('wp_ajax_forvoyez_verify_api_key', [$this->api, 'verify_api_key']) !== false);
    }

    public function testVerifyApiKeyNoApiKey() {
        // Mock forvoyez_get_api_key to return an empty value
        add_filter('forvoyez_get_api_key', '__return_empty_string');

        // Capture the JSON response
        add_filter('wp_die_ajax_handler', [$this, 'wpDieHandler'], 10, 1);
        try {
            $this->api->verify_api_key();
        } catch (WPAjaxDieStopException $e) {
            $response = json_decode($e->getMessage(), true);
            $this->assertFalse($response['success']);
            $this->assertEquals('API key is not set', $response['data']);
        }

        // Remove the filter
        remove_filter('forvoyez_get_api_key', '__return_empty_string');
    }

    public function testVerifyApiKeySuccess() {
        // Mock forvoyez_get_api_key to return a valid key
        add_filter('forvoyez_get_api_key', function() {
            return 'valid_api_key';
        });

        // Capture the JSON response
        add_filter('wp_die_ajax_handler', [$this, 'wpDieHandler'], 10, 1);
        try {
            $this->api->verify_api_key();
        } catch (WPAjaxDieStopException $e) {
            $response = json_decode($e->getMessage(), true);
            $this->assertTrue($response['success']);
            $this->assertEquals('API key is valid', $response['data']);
        }

        // Remove the filter
        remove_filter('forvoyez_get_api_key', function() {
            return 'valid_api_key';
        });
    }

    // Custom handler for wp_die to capture JSON responses in tests
    public function wpDieHandler($message) {
        throw new WPAjaxDieStopException($message);
    }
}

// Check if the custom exception class already exists before declaring it
if (!class_exists('WPAjaxDieStopException')) {
    class WPAjaxDieStopException extends Exception {}
}