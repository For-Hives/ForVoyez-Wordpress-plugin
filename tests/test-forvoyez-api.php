<?php
/**
 * Class TestForvoyezAPI
 *
 * @package ForVoyez
 */

if (!function_exists('custom_error_log')) {
    function custom_error_log($message) {
        error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, '/tmp/forvoyez-test-debug.log');
    }
}

if (!function_exists('forvoyez_get_api_key')) {
    function forvoyez_get_api_key() {
        custom_error_log("forvoyez_get_api_key called");
        return apply_filters('forvoyez_get_api_key', '');
    }
}

class TestForvoyezAPI extends WP_UnitTestCase {
    /**
     * @var Forvoyez_API
     */
    private $api;

    public function setUp(): void {
        parent::setUp();
        $this->api = new Forvoyez_API();

        custom_error_log("-------------------------");
        custom_error_log("Starting new test: " . $this->getName());
    }

    public function testInit(): void {
        custom_error_log("Testing init method");
        $this->api->init();
        $has_action = has_action('wp_ajax_forvoyez_verify_api_key', [$this->api, 'verify_api_key']);
        custom_error_log("Action hook added: " . ($has_action !== false ? "Yes" : "No"));
        $this->assertEquals(10, $has_action);
    }

    public function testVerifyApiKeyWithoutPermission(): void {
        custom_error_log("Setting current user to 0 (no user)");
        wp_set_current_user(0);
        custom_error_log("Setting nonce");
        $_REQUEST['_wpnonce'] = wp_create_nonce('forvoyez_nonce');

        custom_error_log("Calling verify_api_key");
        $response = $this->api->verify_api_key();
        custom_error_log("Response received: " . print_r($response, true));

        $this->assertFalse($response['success']);
        $this->assertEquals(Forvoyez_API::ERROR_PERMISSION_DENIED, $response['data']);
        $this->assertEquals(403, $response['status']);
        custom_error_log("Test assertions completed");
    }

    public function testVerifyApiKeyWithEmptyKey(): void {
        custom_error_log("Creating administrator user");
        $user_id = $this->factory->user->create(['role' => 'administrator']);
        custom_error_log("Setting current user to administrator (ID: $user_id)");
        wp_set_current_user($user_id);
        custom_error_log("Setting nonce");
        $_REQUEST['_wpnonce'] = wp_create_nonce('forvoyez_nonce');

        custom_error_log("Adding filter to return empty API key");
        add_filter('forvoyez_get_api_key', '__return_empty_string');

        custom_error_log("Calling verify_api_key");
        $response = $this->api->verify_api_key();
        custom_error_log("Response received: " . print_r($response, true));

        $this->assertFalse($response['success']);
        $this->assertEquals(Forvoyez_API::ERROR_API_KEY_NOT_SET, $response['data']);
        $this->assertEquals(400, $response['status']);
        custom_error_log("Test assertions completed");

        custom_error_log("Removing filter");
        remove_filter('forvoyez_get_api_key', '__return_empty_string');
    }

    public function testVerifyApiKeySuccess(): void {
        custom_error_log("Creating administrator user");
        $user_id = $this->factory->user->create(['role' => 'administrator']);
        custom_error_log("Setting current user to administrator (ID: $user_id)");
        wp_set_current_user($user_id);
        custom_error_log("Setting nonce");
        $_REQUEST['_wpnonce'] = wp_create_nonce('forvoyez_nonce');

        custom_error_log("Adding filter to return test API key");
        add_filter('forvoyez_get_api_key', function() {
            return 'test_api_key';
        });

        custom_error_log("Creating mock API object");
        $mock_api = $this->getMockBuilder(Forvoyez_API::class)
            ->setMethods(['perform_api_key_verification'])
            ->getMock();

        custom_error_log("Setting up mock expectation");
        $mock_api->expects($this->once())
            ->method('perform_api_key_verification')
            ->with('test_api_key')
            ->willReturn(true);

        custom_error_log("Calling verify_api_key on mock object");
        $response = $mock_api->verify_api_key();
        custom_error_log("Response received: " . print_r($response, true));

        $this->assertTrue($response['success']);
        $this->assertEquals(Forvoyez_API::SUCCESS_API_KEY_VALID, $response['data']);
        custom_error_log("Test assertions completed");

        custom_error_log("Removing filter");
        remove_filter('forvoyez_get_api_key', function() { return 'test_api_key'; });
    }

    public function testVerifyApiKeyFailure(): void {
        custom_error_log("Creating administrator user");
        $user_id = $this->factory->user->create(['role' => 'administrator']);
        custom_error_log("Setting current user to administrator (ID: $user_id)");
        wp_set_current_user($user_id);
        custom_error_log("Setting nonce");
        $_REQUEST['_wpnonce'] = wp_create_nonce('forvoyez_nonce');

        custom_error_log("Adding filter to return invalid API key");
        add_filter('forvoyez_get_api_key', function() {
            return 'invalid_api_key';
        });

        custom_error_log("Creating mock API object");
        $mock_api = $this->getMockBuilder(Forvoyez_API::class)
            ->setMethods(['perform_api_key_verification'])
            ->getMock();

        custom_error_log("Setting up mock expectation");
        $mock_api->expects($this->once())
            ->method('perform_api_key_verification')
            ->with('invalid_api_key')
            ->willReturn(false);

        custom_error_log("Calling verify_api_key on mock object");
        $response = $mock_api->verify_api_key();
        custom_error_log("Response received: " . print_r($response, true));

        $this->assertFalse($response['success']);
        $this->assertEquals(Forvoyez_API::ERROR_API_KEY_INVALID, $response['data']);
        $this->assertEquals(400, $response['status']);
        custom_error_log("Test assertions completed");

        custom_error_log("Removing filter");
        remove_filter('forvoyez_get_api_key', function() { return 'invalid_api_key'; });
    }

    public function testVerifyApiKeyInvalidNonce(): void {
        custom_error_log("Creating administrator user");
        $user_id = $this->factory->user->create(['role' => 'administrator']);
        custom_error_log("Setting current user to administrator (ID: $user_id)");
        wp_set_current_user($user_id);
        custom_error_log("Setting invalid nonce");
        $_REQUEST['_wpnonce'] = 'invalid_nonce';

        custom_error_log("Calling verify_api_key and expecting exception");
        try {
            $response = $this->api->verify_api_key();
            custom_error_log("Unexpected response received: " . print_r($response, true));
            $this->fail('A WPDieException was expected');
        } catch (WPDieException $e) {
            custom_error_log("WPDieException caught as expected");
            $this->assertTrue(true); // Exception was thrown as expected
        }
        custom_error_log("Test completed");
    }
}