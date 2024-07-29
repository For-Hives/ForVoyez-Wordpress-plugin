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

    public function testGetApiKeyWhenNotSet() {
        // Simuler une API key non définie
        add_filter('forvoyez_get_api_key', '__return_empty_string');
        $this->assertEmpty(forvoyez_get_api_key());
        remove_filter('forvoyez_get_api_key', '__return_empty_string');
    }

    public function testGetApiKeyWhenSet() {
        // Simuler une API key définie
        add_filter('forvoyez_get_api_key', function() {
            return 'valid_api_key';
        });
        $this->assertEquals('valid_api_key', forvoyez_get_api_key());
        remove_filter('forvoyez_get_api_key', function() {
            return 'valid_api_key';
        });
    }

    public function testSanitizeApiKeyValid() {
        $jwt = 'header.payload.signature';
        $sanitized = forvoyez_sanitize_api_key($jwt);
        $this->assertEquals($jwt, $sanitized);
    }

    public function testSanitizeApiKeyInvalid() {
        $invalid_jwt = 'invalid_jwt_format';
        $sanitized = forvoyez_sanitize_api_key($invalid_jwt);
        $this->assertWPError($sanitized);
        $this->assertEquals('invalid_api_key', $sanitized->get_error_code());
    }

    public function testVerifyJwtValid() {
        $jwt = 'header.payload.signature';
        $payload = base64_encode(json_encode(['iss' => 'ForVoyez', 'aud' => 'ForVoyez', 'exp' => time() + 3600]));
        $valid_jwt = "header.$payload.signature";
        $result = forvoyez_verify_jwt($valid_jwt);
        $this->assertTrue($result);
    }

    public function testVerifyJwtInvalid() {
        $invalid_jwt = 'header.payload.invalid_signature';
        $result = forvoyez_verify_jwt($invalid_jwt);
        $this->assertWPError($result);
        $this->assertEquals('invalid_payload', $result->get_error_code());
    }
}
