<?php
/**
 * Class TestForvoyezAPI
 *
 * @package ForVoyez
 */

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TestForvoyezAPI extends WP_UnitTestCase {
    private $logger;
    private $api;

    public function setUp(): void {
        parent::setUp();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->api = new Forvoyez_API($this->logger);
    }

    public function testConstructor(): void {
        $this->assertInstanceOf(Forvoyez_API::class, $this->api);
    }

    public function testInit(): void {
        $this->logger->expects($this->once())->method('info')->with('Initializing Forvoyez_API');
        $this->api->init();
        $this->assertTrue(has_action('wp_ajax_forvoyez_verify_api_key', [$this->api, 'verify_api_key']));
    }

    public function testVerifyApiKey(): void {
        // Set up mock methods
        $this->logger->expects($this->once())->method('info')->with('Starting verify_api_key method');

        // Mock nonce check
        $apiMock = $this->getMockBuilder(Forvoyez_API::class)
            ->setConstructorArgs([$this->logger])
            ->onlyMethods(['check_nonce', 'check_user_capability', 'get_api_key', 'perform_api_key_verification'])
            ->getMock();

        $apiMock->expects($this->once())->method('check_nonce');
        $apiMock->expects($this->once())->method('check_user_capability');
        $apiMock->expects($this->once())->method('get_api_key')->willReturn('test_api_key');
        $apiMock->expects($this->once())->method('perform_api_key_verification')->with('test_api_key')->willReturn(true);

        $result = $apiMock->verify_api_key();

        $this->assertTrue($result['success']);
        $this->assertEquals(Forvoyez_API::SUCCESS_API_KEY_VALID, $result['data']);
    }

    public function testVerifyApiKeyWithInvalidApiKey(): void {
        $this->logger->expects($this->once())->method('info')->with('Starting verify_api_key method');

        $apiMock = $this->getMockBuilder(Forvoyez_API::class)
            ->setConstructorArgs([$this->logger])
            ->onlyMethods(['check_nonce', 'check_user_capability', 'get_api_key', 'perform_api_key_verification'])
            ->getMock();

        $apiMock->expects($this->once())->method('check_nonce');
        $apiMock->expects($this->once())->method('check_user_capability');
        $apiMock->expects($this->once())->method('get_api_key')->willReturn('');

        $result = $apiMock->verify_api_key();

        $this->assertFalse($result['success']);
        $this->assertEquals(Forvoyez_API::ERROR_API_KEY_NOT_SET, $result['data']);
        $this->assertEquals(400, $result['status']);
    }

    public function testVerifyApiKeyThrowsException(): void {
        $this->logger->expects($this->once())->method('info')->with('Starting verify_api_key method');
        $this->logger->expects($this->once())->method('error')->with('Error in verify_api_key: Test Exception');

        $apiMock = $this->getMockBuilder(Forvoyez_API::class)
            ->setConstructorArgs([$this->logger])
            ->onlyMethods(['check_nonce', 'check_user_capability', 'get_api_key'])
            ->getMock();

        $apiMock->expects($this->once())->method('check_nonce')->will($this->throwException(new Exception('Test Exception', 500)));
        $apiMock->expects($this->never())->method('check_user_capability');
        $apiMock->expects($this->never())->method('get_api_key');

        $result = $apiMock->verify_api_key();

        $this->assertFalse($result['success']);
        $this->assertEquals('Test Exception', $result['data']);
        $this->assertEquals(500, $result['status']);
    }

    public function testCheckNonceInvalid(): void {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid nonce');
        $this->expectExceptionCode(403);

        $apiMock = $this->getMockBuilder(Forvoyez_API::class)
            ->setConstructorArgs([$this->logger])
            ->onlyMethods(['check_nonce'])
            ->getMock();

        $apiMock->method('check_nonce')->will($this->throwException(new Exception('Invalid nonce', 403)));

        $apiMock->verify_api_key();
    }

    public function testCheckUserCapabilityInvalid(): void {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(Forvoyez_API::ERROR_PERMISSION_DENIED);
        $this->expectExceptionCode(403);

        $apiMock = $this->getMockBuilder(Forvoyez_API::class)
            ->setConstructorArgs([$this->logger])
            ->onlyMethods(['check_user_capability'])
            ->getMock();

        $apiMock->method('check_user_capability')->will($this->throwException(new Exception(Forvoyez_API::ERROR_PERMISSION_DENIED, 403)));

        $apiMock->verify_api_key();
    }
}
