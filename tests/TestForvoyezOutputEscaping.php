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
	 * Whole fake /api/tokens answer, when a test needs another shape.
	 *
	 * @var array|null
	 */
	private $token_answer = null;

	/**
	 * Number of fake /api/tokens requests.
	 *
	 * @var int
	 */
	private $token_requests = 0;

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
		'Image Metadata Status:' => '<b>Status</b>',
		'Alt Text Status' => '"><img src=x onerror=alert(1)>',
		'Missing alt text' => '"><img src=x onerror=alert(2)>',
		'Title' => '<i>Title</i>',
		'This tool uses AI to analyze your image and generate optimized metadata like alt text, title, and caption. Click the button below to automatically generate SEO-friendly metadata for this image.' => '<u>AI</u>',
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

		++$this->token_requests;

		return [
			'headers' => [ 'content-type' => 'application/json' ],
			'body' => wp_json_encode($this->token_answer ?? [ 'user' => [ 'credits' => $this->credits ], 'subscription' => null ]),
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

	/**
	 * forvoyez_admin_notices() printed "Successfully queued N images" next to
	 * the real bulk notice although nothing was queued, and its other branch
	 * read a transient that nothing writes. It is gone: one notice remains.
	 */
	public function test_bulk_action_redirect_prints_a_single_notice()
	{
		$this->assertFalse(function_exists('forvoyez_admin_notices'));
		$this->assertFalse(function_exists('forvoyez_get_bulk_images'));
		$this->assertFalse(has_action('wp_ajax_forvoyez_get_bulk_images'));
		$this->assertSame(10, has_action('admin_notices', 'forvoyez_bulk_analysis_notice'));
	}

	public function test_analyze_button_escapes_translations()
	{
		$image_id = self::factory()->attachment->create_object([
			'file' => 'photo.jpg',
			'post_mime_type' => 'image/jpeg',
		]);

		$html = forvoyez_add_analyze_button([], get_post($image_id))['forvoyez_analyze']['html'];

		$this->assert_no_raw_markup($html);
		$this->assertStringNotContainsString('onerror=alert(1)>', $html);
		$this->assertStringContainsString('&lt;b&gt;Status&lt;/b&gt;', $html);
		$this->assertStringContainsString('title="&quot;&gt;&lt;img src=x onerror=alert(1)&gt;"', $html);
		$this->assertStringContainsString('title="&quot;&gt;&lt;img src=x onerror=alert(2)&gt;"', $html);
		$this->assertStringContainsString('<span class="status-label">&lt;i&gt;Title&lt;/i&gt;</span>', $html);
		$this->assertStringContainsString('&lt;u&gt;AI&lt;/u&gt;', $html);
	}

	public function test_bulk_results_summary_does_not_run_a_translation_as_javascript()
	{
		$this->store_api_key();
		$this->translations['Analysis completed!'] = '${alert(1)}`';
		$_GET = [
			'forvoyez_bulk_analyze' => '3',
			'forvoyez_bulk_nonce' => wp_create_nonce('forvoyez_bulk_analyze_nonce'),
			'forvoyez_image_ids' => '7,8,9',
		];

		$html = $this->capture('forvoyez_bulk_analysis_notice');

		$start = strpos($html, "$('#forvoyez-results-summary').html(");
		$end = strpos($html, '// Update credit count', (int) $start);
		$this->assertNotFalse($start);
		$this->assertNotFalse($end);
		$code = substr($html, $start, $end - $start);
		$this->assertStringContainsString('"${alert(1)}`"', $code);

		// Without its string literals, the code has no template literal left:
		// esc_js() does not protect ${ } or backticks.
		$without_strings = preg_replace('/\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"/s', '""', $code);
		$this->assertStringNotContainsString('`', $without_strings);
		$this->assertStringNotContainsString('${', $without_strings);
		$this->assertStringContainsString('successCount', $without_strings);
		$this->assertStringContainsString('errorCount', $without_strings);
	}

	/**
	 * @dataProvider token_answers_with_missing_keys
	 */
	public function test_dashboard_tab_survives_a_token_answer_with_missing_keys($answer)
	{
		$this->store_api_key();
		$this->token_answer = $answer;

		// PHPUnit turns the "Undefined array key" warning into an error.
		$html = $this->render_dashboard_tab();

		$this->assertStringContainsString('forvoyez-credit-count', $html);
	}

	public function token_answers_with_missing_keys()
	{
		return [
			'subscription without plan' => [ [ 'user' => [ 'credits' => 3 ], 'subscription' => [ 'isSubscribed' => true ] ] ],
			'no user' => [ [ 'subscription' => [ 'isSubscribed' => false ] ] ],
		];
	}

	public function test_dashboard_tab_shows_a_neutral_badge_when_the_credits_are_unknown()
	{
		$this->store_api_key();
		$this->token_answer = [ 'subscription' => [] ];

		$html = $this->render_dashboard_tab();

		$this->assertMatchesRegularExpression('/forvoyez-credit-count[^"]*bg-gray-200 text-gray-600">\s*\?\s*</', $html);
	}

	public function test_admin_page_does_not_ask_the_api_for_credits_it_does_not_use()
	{
		$this->store_api_key();
		$this->token_answer = [ 'subscription' => [] ];

		$html = $this->capture(static function () {
			include FORVOYEZ_PLUGIN_DIR . 'templates/admin-page.php';
		});

		$this->assertStringContainsString('Auto Alt Text for Images', $html);
		$this->assertSame(0, $this->token_requests);
	}

	public function test_analyze_button_survives_a_token_answer_without_user()
	{
		$this->store_api_key();
		$this->token_answer = [ 'subscription' => [] ];
		$image_id = self::factory()->attachment->create_object([
			'file' => 'photo.jpg',
			'post_mime_type' => 'image/jpeg',
		]);

		$fields = forvoyez_add_analyze_button([], get_post($image_id));

		$this->assertStringContainsString('>?<', $fields['forvoyez_analyze']['html']);
	}

	public function test_bulk_notice_survives_a_token_answer_without_user()
	{
		$this->store_api_key();
		$this->token_answer = [ 'subscription' => [] ];
		$_GET = [
			'forvoyez_bulk_analyze' => '3',
			'forvoyez_bulk_nonce' => wp_create_nonce('forvoyez_bulk_analyze_nonce'),
			'forvoyez_image_ids' => '7,8,9',
		];

		$html = $this->capture('forvoyez_bulk_analysis_notice');

		$this->assertStringContainsString('forvoyez-start-bulk', $html);
	}

	/**
	 * The dashboard tab is included from a Forvoyez_Admin method and uses $this.
	 */
	private function render_dashboard_tab(): string
	{
		$admin = new Forvoyez_Admin($this->createMock(Forvoyez_API_Manager::class));

		return $this->capture(
			Closure::bind(
				function () {
					include FORVOYEZ_PLUGIN_DIR . 'templates/dashboard-tab.php';
				},
				$admin,
				Forvoyez_Admin::class,
			),
		);
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
