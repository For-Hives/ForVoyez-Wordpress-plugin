<?php
/**
 * Class TestForVoyezSettings
 *
 * @package ForVoyez
 */

class TestForVoyezSettings extends WP_UnitTestCase
{
	/**
	 * @var Forvoyez_Settings
	 */
	private $settings;

	public function setUp(): void
	{
		parent::setUp();
		$this->settings = new Forvoyez_Settings();
	}

	public function test_encrypt_decrypt()
	{
		$original_key = 'test_api_key_12345';
		$encrypted = $this->invokeMethod(
			$this->settings,
			'encrypt',
			[
				$original_key,
			]
		);
		$decrypted = $this->invokeMethod(
			$this->settings,
			'decrypt',
			[
				$encrypted,
			]
		);

		$this->assertEquals(
			$original_key,
			$decrypted,
			'Decrypted value should match the original',
		);
	}

	public function test_get_api_key_empty()
	{
		delete_option('forvoyez_encrypted_api_key');
		$this->assertEmpty(
			$this->settings->get_api_key(),
			'API key should be empty when not set',
		);
	}

	public function test_get_api_key_set()
	{
		$test_key = 'test_api_key_67890';
		$encrypted = $this->invokeMethod(
			$this->settings,
			'encrypt',
			[
				$test_key,
			]
		);
		update_option('forvoyez_encrypted_api_key', $encrypted);

		$this->assertEquals(
			$test_key,
			$this->settings->get_api_key(),
			'Retrieved API key should match the set value',
		);
	}

	public function test_sanitize_api_key()
	{
		$dirty_key = ' Test<script>alert("XSS")</script>Key ';
		$clean_key = $this->settings->sanitize_api_key($dirty_key);

		$this->assertEquals(
			'TestKey',
			$clean_key,
			'API key should be properly sanitized',
		);
	}

	/**
	 * Call protected/private method of a class.
	 *
	 * @param object &$object    Instantiated object that we will run method on.
	 * @param string $methodName Method name to call
	 * @param array  $parameters Array of parameters to pass into method.
	 *
	 * @return mixed Method return.
	 */
	public function invokeMethod(
		&$object,
		$methodName,
		array $parameters = [],
	) {
		$reflection = new \ReflectionClass(get_class($object));
		$method = $reflection->getMethod($methodName);
		$method->setAccessible(true);

		return $method->invokeArgs($object, $parameters);
	}

	 public function test_get_context_empty()
    {
        delete_option('forvoyez_context');
        $this->assertEmpty(
            $this->settings->get_context(),
            'Context should be empty when not set'
        );
    }

    public function test_get_context_set()
    {
        $test_context = 'Test Context';
        update_option('forvoyez_context', $test_context);

        $this->assertEquals(
            $test_context,
            $this->settings->get_context(),
            'Retrieved context should match the set value'
        );
    }

    public function test_get_language_empty()
    {
        delete_option('forvoyez_language');
        $this->assertEquals(
            '',
            $this->settings->get_language(),
            'Language should default to nothing when not set'
        );
    }

    public function test_get_language_set()
    {
        $test_language = 'fr';
        update_option('forvoyez_language', $test_language);

        $this->assertEquals(
            $test_language,
            $this->settings->get_language(),
            'Retrieved language should match the set value'
        );
    }
}
