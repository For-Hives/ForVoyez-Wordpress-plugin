<?php
/**
 * Translated strings and API values printed by the media-library bulk notices
 * and the low-credits footer are escaped (Plugin Check EscapeOutput).
 *
 * A gettext filter stands in for a translation that contains markup, quotes
 * and an apostrophe; HTTP calls to the ForVoyez API are faked.
 *
 * @package ForVoyez
 */

class TestForvoyezOutputEscaping extends WP_UnitTestCase
{
	private const TOKENS_URL = 'https://forvoyez.com/api/tokens';

	/**
	 * Credits returned by the fake /api/tokens answer.
	 *
	 * @var int
	 */
	private $credits = 2;

	/**
	 * Fake translations: msgid => translation.
	 *
	 * @var array<string, string>
	 */
	private $translations = [
		'Start Analysis' => 'Start <b>"Analysis"</b> & go',
		'Dismiss' => '<i>Dismiss</i>',
		'Processing...' => 'L\'analyse <i>"en cours"</i>',
		'Complete!' => '</script><script>alert(1)</script>',
		'ForVoyez: Ready to analyze %d images.' => '<b>%d</b> images ready',
		'ForVoyez: Successfully queued %d images for analysis.' => '<img src=x onerror=alert(1)> %d queued',
		'Warning: This operation requires %1$d credits, but you only have %2$d credits available.' => '<u>Need %1$d, have %2$d</u>',
		'You only have %d credits remaining. Please consider recharging to continue using the ForVoyez service.' => '<script>alert(%d)</script>',
	];

	public function set_up()
	{
		parent::set_up();
		wp_set_current_user(self::factory()->user->create([ 'role' => 'administrator' ]));
		add_filter('gettext_auto-alt-text-for-images', [ $this, 'translate' ], 10, 2);
		add_filter('ngettext_auto-alt-text-for-images', [ $this, 'translate_plural' ], 10, 4);
		add_filter('pre_http_request', [ $this, 'fake_tokens_response' ], 10, 3);
	}

	public function tear_down()
	{
		remove_filter('gettext_auto-alt-text-for-images', [ $this, 'translate' ], 10);
		remove_filter('ngettext_auto-alt-text-for-images', [ $this, 'translate_plural' ], 10);
		remove_filter('pre_http_request', [ $this, 'fake_tokens_response' ], 10);
		delete_option('forvoyez_encrypted_api_key');
		delete_transient('forvoyez_bulk_analyze_images');
		$_GET = [];
		$_REQUEST = [];
		$GLOBALS['current_screen'] = null;
		parent::tear_down();
	}

	public function translate($translation, $text)
	{
		return $this->translations[ $text ] ?? $translation;
	}

	public function translate_plural($translation, $single, $plural, $number)
	{
		return $this->translations[ 1 === $number ? $single : $plural ] ?? $translation;
	}

	public function fake_tokens_response($preempt, $args, $url)
	{
		if (self::TOKENS_URL !== $url) {
			return new WP_Error('http_request_blocked', 'Unexpected HTTP request in tests: ' . $url);
		}

		return [
			'headers' => [ 'content-type' => 'application/json' ],
			'body' => wp_json_encode([ 'user' => [ 'credits' => $this->credits ], 'subscription' => null ]),
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'cookies' => [],
			'filename' => null,
		];
	}

	private function store_api_key(): void
	{
		$encrypt = new ReflectionMethod(Forvoyez_Settings::class, 'encrypt');
		if (PHP_VERSION_ID < 80100) {
			$encrypt->setAccessible(true); // No-op (deprecated) since PHP 8.1.
		}
		update_option('forvoyez_encrypted_api_key', $encrypt->invoke(new Forvoyez_Settings(), 'fake.jwt.for-tests'));
	}

	private function capture(callable $render): string
	{
		ob_start();
		$render();
		return ob_get_clean();
	}

	/**
	 * Assert that none of the fake translations reached the page as markup.
	 */
	private function assert_no_raw_markup(string $html): void
	{
		foreach ([ '<b>', '<i>', '<u>', '<img', '<script>alert', '</script><script>' ] as $markup) {
			$this->assertStringNotContainsString($markup, $html, "Unescaped $markup in the output.");
		}
	}

	public function test_bulk_analysis_notice_escapes_translations_and_credits()
	{
		$this->store_api_key();
		$_GET = [
			'forvoyez_bulk_analyze' => '3',
			'forvoyez_bulk_nonce' => wp_create_nonce('forvoyez_bulk_analyze_nonce'),
			'forvoyez_image_ids' => '7,8,9',
		];

		$html = $this->capture('forvoyez_bulk_analysis_notice');

		$this->assert_no_raw_markup($html);
		$this->assertStringContainsString('&lt;b&gt;3&lt;/b&gt; images ready', $html);
		$this->assertStringContainsString('Start &lt;b&gt;&quot;Analysis&quot;&lt;/b&gt; &amp; go', $html);
		$this->assertStringContainsString('&lt;i&gt;Dismiss&lt;/i&gt;', $html);
		// Not enough credits: the warning text is escaped, its box is kept.
		$this->assertStringContainsString(
			'<div class="notice-warning" style="padding: 8px; margin-bottom: 10px; border-left: 4px solid #ffb900;">&lt;u&gt;Need 3, have 2&lt;/u&gt;</div>',
			$html,
		);
		$this->assertStringContainsString('<span class="forvoyez-credit-count credits-danger">2</span>', $html);
		// JavaScript strings: the apostrophe cannot end the string literal.
		$this->assertStringContainsString(".text('L\\'analyse &lt;i&gt;&quot;en cours&quot;&lt;/i&gt;')", $html);
	}

	public function test_bulk_analysis_notice_has_no_warning_with_enough_credits()
	{
		$this->store_api_key();
		$this->credits = 15;
		$_GET = [
			'forvoyez_bulk_analyze' => '3',
			'forvoyez_bulk_nonce' => wp_create_nonce('forvoyez_bulk_analyze_nonce'),
			'forvoyez_image_ids' => '7,8,9',
		];

		$html = $this->capture('forvoyez_bulk_analysis_notice');

		$this->assertStringNotContainsString('notice-warning', $html);
		$this->assertStringContainsString('<span class="forvoyez-credit-count credits-warning">15</span>', $html);
	}

	public function test_admin_notices_escape_translations()
	{
		$_REQUEST = [ 'forvoyez_bulk_analyze' => '3' ];

		$queued = $this->capture('forvoyez_admin_notices');
		set_transient('forvoyez_bulk_analyze_images', [ 7, 8, 9 ]);
		$ready = $this->capture('forvoyez_admin_notices');

		$this->assertSame(
			'<div class="notice notice-success is-dismissible"><p>&lt;img src=x onerror=alert(1)&gt; 3 queued</p></div>',
			$queued,
		);
		$this->assert_no_raw_markup($ready);
		$this->assertStringContainsString('&lt;b&gt;3&lt;/b&gt; images ready', $ready);
		$this->assertStringContainsString('<span id="forvoyez-bulk-progress-text">0 / 3 images processed</span>', $ready);
	}

	public function test_low_credits_footer_escapes_the_message()
	{
		$this->store_api_key();
		$this->credits = 4;
		set_current_screen('toplevel_page_auto-alt-text');
		$admin = new Forvoyez_Admin($this->createMock(Forvoyez_API_Manager::class));

		$html = $this->capture([ $admin, 'maybe_show_low_credits_warning' ]);

		$this->assert_no_raw_markup($html);
		$this->assertStringContainsString('&lt;script&gt;alert(4)&lt;/script&gt;', $html);
	}
}
