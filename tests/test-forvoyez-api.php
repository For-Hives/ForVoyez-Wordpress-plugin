<?php
/**
 * Class TestForvoyezAPI
 *
 * @package ForVoyez
 */

class TestForvoyezAPI extends WP_UnitTestCase {
    /**
     * @var Forvoyez_API
     */
    private $api;

    public function setUp(): void {
        parent::setUp();
        $this->api = new Forvoyez_API();

        // Enable error logging
        ini_set('log_errors', 1);
        ini_set('error_log', '/tmp/wordpress-tests-errors.log');
    }

    public function testInit(): void {
        $this->api->init();
        $this->assertEquals(10, has_action('wp_ajax_forvoyez_verify_api_key', [$this->api, 'verify_api_key']));
    }

    public function testVerifyApiKeyWithoutPermission(): void {
        error_log('Start of test: testVerifyApiKeyWithoutPermission');
        wp_set_current_user(0);
        $_REQUEST['_wpnonce'] = wp_create_nonce('forvoyez_nonce');

        $response = $this->api->verify_api_key();
        error_log('Response received: ' . print_r($response, true));

        $this->assertFalse($response['success']);
        $this->assertEquals(Forvoyez_API::ERROR_PERMISSION_DENIED, $response['data']);
        $this->assertEquals(403, $response['status']);
    }

    public function testVerifyApiKeyWithEmptyKey(): void {
        error_log('Start of test: testVerifyApiKeyWithEmptyKey');
        $user_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        $_REQUEST['_wpnonce'] = wp_create_nonce('forvoyez_nonce');

        add_filter('forvoyez_get_api_key', '__return_empty_string');

        $response = $this->api->verify_api_key();
        error_log('Response received: ' . print_r($response, true));

        $this->assertFalse($response['success']);
        $this->assertEquals(Forvoyez_API::ERROR_API_KEY_NOT_SET, $response['data']);
        $this->assertEquals(400, $response['status']);
    }

    public function testVerifyApiKeySuccess(): void {
        error_log('Start of test: testVerifyApiKeySuccess');
        $user_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        $_REQUEST['_wpnonce'] = wp_create_nonce('forvoyez_nonce');

        add_filter('forvoyez_get_api_key', function() {
            return 'test_api_key';
        });

        $mock_api = $this->getMockBuilder(Forvoyez_API::class)
            ->setMethods(['perform_api_key_verification'])
            ->getMock();

        $mock_api->expects($this->once())
            ->method('perform_api_key_verification')
            ->with('test_api_key')
            ->willReturn(true);

        $response = $mock_api->verify_api_key();
        error_log('Response received: ' . print_r($response, true));

        $this->assertTrue($response['success']);
        $this->assertEquals(Forvoyez_API::SUCCESS_API_KEY_VALID, $response['data']);
    }

    public function testVerifyApiKeyFailure(): void {
        error_log('Start of test: testVerifyApiKeyFailure');
        $user_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        $_REQUEST['_wpnonce'] = wp_create_nonce('forvoyez_nonce');

        add_filter('forvoyez_get_api_key', function() {
            return 'invalid_api_key';
        });

        $mock_api = $this->getMockBuilder(Forvoyez_API::class)
            ->setMethods(['perform_api_key_verification'])
            ->getMock();

        $mock_api->expects($this->once())
            ->method('perform_api_key_verification')
            ->with('invalid_api_key')
            ->willReturn(false);

        $response = $mock_api->verify_api_key();
        error_log('Response received: ' . print_r($response, true));

        $this->assertFalse($response['success']);
        $this->assertEquals(Forvoyez_API::ERROR_API_KEY_INVALID, $response['data']);
        $this->assertEquals(400, $response['status']);
    }

    public function testVerifyApiKeyInvalidNonce(): void {
        error_log('Start of test: testVerifyApiKeyInvalidNonce');
        $user_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        $_REQUEST['_wpnonce'] = 'invalid_nonce';

        try {
            $response = $this->api->verify_api_key();
            error_log('Unexpected response received: ' . print_r($response, true));
            $this->fail('A WPDieException was expected');
        } catch (WPDieException $e) {
            error_log('WPDieException caught as expected');
            $this->assertTrue(true); // Exception was thrown as expected
        }
    }
}