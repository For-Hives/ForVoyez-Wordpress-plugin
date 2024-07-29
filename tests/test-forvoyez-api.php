<?php
/**
 * Class TestForvoyezAPI
 *
 * @package ForVoyez
 */

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TestForvoyezAPI extends TestCase {
    private $api;
    private $logger;

    protected function setUp(): void {
        parent::setUp();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->api = new Forvoyez_API($this->logger);

        // Set up WordPress function mocks
        if (!function_exists('wp_create_nonce')) {
            function wp_create_nonce($action) {
                return 'test_nonce';
            }
        }
        if (!function_exists('check_ajax_referer')) {
            function check_ajax_referer($action, $query_arg, $die) {
                return true;
            }
        }
        if (!function_exists('current_user_can')) {
            function current_user_can($capability) {
                return true;
            }
        }
        if (!function_exists('forvoyez_get_api_key')) {
            function forvoyez_get_api_key() {
                return 'test_api_key';
            }
        }
    }

    public function testInit(): void {
        $this->logger->expects($this->once())
            ->method('info')
            ->with('Initializing Forvoyez_API');

        $this->api->init();

        $this->assertTrue(has_action('wp_ajax_forvoyez_verify_api_key', [$this->api, 'verify_api_key']));
    }

    public function testVerifyApiKeySuccess(): void {
        $this->logger->expects($this->exactly(5))
            ->method('info');

        $_REQUEST['nonce'] = wp_create_nonce('forvoyez_nonce');

        $result = $this->api->verify_api_key();

        $this->assertTrue($result['success']);
        $this->assertEquals(Forvoyez_API::SUCCESS_API_KEY_VALID, $result['data']);
    }

    public function testVerifyApiKeyNoApiKey(): void {
        $this->logger->expects($this->exactly(4))
            ->method('info');

        $_REQUEST['nonce'] = wp_create_nonce('forvoyez_nonce');

        // Override forvoyez_get_api_key to return empty string
        global $forvoyez_get_api_key_override;
        $forvoyez_get_api_key_override = true;

        $result = $this->api->verify_api_key();

        $this->assertFalse($result['success']);
        $this->assertEquals(Forvoyez_API::ERROR_API_KEY_NOT_SET, $result['data']);
        $this->assertEquals(400, $result['status']);

        $forvoyez_get_api_key_override = false;
    }

    public function testVerifyApiKeyInvalidNonce(): void {
        $this->logger->expects($this->once())
            ->method('info');

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Invalid nonce'));

        $_REQUEST['nonce'] = 'invalid_nonce';

        $result = $this->api->verify_api_key();

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid nonce', $result['data']);
        $this->assertEquals(403, $result['status']);
    }

    public function testVerifyApiKeyNoPermission(): void {
        $this->logger->expects($this->exactly(2))
            ->method('info');

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains(Forvoyez_API::ERROR_PERMISSION_DENIED));

        $_REQUEST['nonce'] = wp_create_nonce('forvoyez_nonce');

        // Override current_user_can to return false
        global $current_user_can_override;
        $current_user_can_override = true;

        $result = $this->api->verify_api_key();

        $this->assertFalse($result['success']);
        $this->assertEquals(Forvoyez_API::ERROR_PERMISSION_DENIED, $result['data']);
        $this->assertEquals(403, $result['status']);

        $current_user_can_override = false;
    }
}

// Helper function to override WordPress functions
function forvoyez_get_api_key() {
    global $forvoyez_get_api_key_override;
    if (isset($forvoyez_get_api_key_override) && $forvoyez_get_api_key_override) {
        return '';
    }
    return 'test_api_key';
}

function current_user_can($capability) {
    global $current_user_can_override;
    if (isset($current_user_can_override) && $current_user_can_override) {
        return false;
    }
    return true;
}