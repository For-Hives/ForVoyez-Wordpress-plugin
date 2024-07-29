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

    public function tearDown(): void {
        remove_all_filters('forvoyez_api_key');
        parent::tearDown();
    }

    public function testGetApiKeyWhenNotSet() {
        $this->assertEmpty(forvoyez_get_api_key(), 'API key should be empty when not set');
    }

    public function testGetApiKeyWhenSet() {
        $test_key = 'valid_api_key';

        add_filter('forvoyez_api_key', function() use ($test_key) {
            return $test_key;
        });

        $this->assertEquals($test_key, forvoyez_get_api_key(), 'API key should match the set value');
    }

    public function testSanitizeApiKey() {
        $dirty_key = ' Test<script>alert("XSS")</script>Key ';
        $expected_clean_key = 'TestKey';

        $clean_key = $this->api->sanitize_api_key($dirty_key);

        $this->assertEquals($expected_clean_key, $clean_key, 'API key should be properly sanitized');
    }

    public function testValidateApiKeyFormatValid() {
        $valid_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';

        $this->assertTrue($this->api->validate_api_key_format($valid_key), 'Valid API key format should be recognized');
    }

    public function testValidateApiKeyFormatInvalid() {
        $invalid_key = 'not a valid jwt token';

        $this->assertFalse($this->api->validate_api_key_format($invalid_key), 'Invalid API key format should be recognized');
    }

    public function testVerifyApiKeyNotSet() {
        add_filter('forvoyez_api_key', '__return_empty_string');

        $this->expectException('WPDieException');
        $this->expectOutputRegex('/{"success":false,"data":"API key is not set"}/');

        $this->api->verify_api_key();
    }

    public function testVerifyApiKeySet() {
        add_filter('forvoyez_api_key', function() {
            return 'valid_api_key';
        });

        $this->expectException('WPDieException');
        $this->expectOutputRegex('/{"success":true,"data":"API key is valid"}/');

        $this->api->verify_api_key();
    }
}