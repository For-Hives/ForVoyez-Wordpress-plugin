<?php
class TestForvoyezAPI extends WP_UnitTestCase {
    private $api;

    public function setUp(): void {
        parent::setUp();
        $this->api = new Forvoyez_API();
    }

    public function testInit(): void {
        $this->api->init();
        $this->assertEquals(10, has_action('wp_ajax_forvoyez_verify_api_key', [$this->api, 'verify_api_key']));
    }

    public function testVerifyApiKeyWithoutPermission(): void {
        wp_set_current_user(0);
        $_REQUEST['_wpnonce'] = wp_create_nonce('forvoyez_nonce');

        $response = $this->api->verify_api_key();
        $this->assertFalse($response['success']);
        $this->assertEquals(Forvoyez_API::ERROR_PERMISSION_DENIED, $response['data']);
        $this->assertEquals(403, $response['status']);
    }

    public function testVerifyApiKeyWithEmptyKey(): void {
        $user_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        $_REQUEST['_wpnonce'] = wp_create_nonce('forvoyez_nonce');

        add_filter('forvoyez_get_api_key', '__return_empty_string');

        $response = $this->api->verify_api_key();
        $this->assertFalse($response['success']);
        $this->assertEquals(Forvoyez_API::ERROR_API_KEY_NOT_SET, $response['data']);
        $this->assertEquals(400, $response['status']);
    }

    public function testVerifyApiKeySuccess(): void {
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
        $this->assertTrue($response['success']);
        $this->assertEquals(Forvoyez_API::SUCCESS_API_KEY_VALID, $response['data']);
    }

    public function testVerifyApiKeyFailure(): void {
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
        $this->assertFalse($response['success']);
        $this->assertEquals(Forvoyez_API::ERROR_API_KEY_INVALID, $response['data']);
        $this->assertEquals(400, $response['status']);
    }

    public function testVerifyApiKeyInvalidNonce(): void {
        $user_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        $_REQUEST['_wpnonce'] = 'invalid_nonce';

        $this->expectException('WPDieException');
        $this->api->verify_api_key();
    }
}