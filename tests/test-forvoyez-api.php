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
        remove_all_filters('forvoyez_get_api_key');
        parent::tearDown();
    }

    public function testGetApiKeyWhenNotSet() {
        $this->assertEmpty(forvoyez_get_api_key(), 'API key should be empty when not set');
    }

    public function testGetApiKeyWhenSet() {
        add_filter('forvoyez_get_api_key', function() {
            return 'valid_api_key';
        });
        $this->assertEquals('valid_api_key', forvoyez_get_api_key(), 'API key should match the set value');
    }

    public function testSanitizeApiKeyValid() {
        $jwt = 'header.payload.signature';
        $sanitized = forvoyez_sanitize_api_key($jwt);
        $this->assertEquals($jwt, $sanitized);
    }

    public function testSanitizeApiKeyInvalid() {
        $invalid_jwt = 'invalid_jwt_format';
        $sanitized = forvoyez_sanitize_api_key($invalid_jwt);
        $this->assertInstanceOf(WP_Error::class, $sanitized);
        $this->assertEquals('invalid_api_key', $sanitized->get_error_code());
    }

    public function testVerifyJwtValid() {
        $payload = base64_encode(json_encode(['iss' => 'ForVoyez', 'aud' => 'ForVoyez', 'exp' => time() + 3600]));
        $valid_jwt = "header.$payload.signature";
        $result = forvoyez_verify_jwt($valid_jwt);
        $this->assertTrue($result);
    }

    public function testVerifyJwtInvalid() {
        $invalid_jwt = 'header.payload.invalid_signature';
        $result = forvoyez_verify_jwt($invalid_jwt);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_payload', $result->get_error_code());
    }
}