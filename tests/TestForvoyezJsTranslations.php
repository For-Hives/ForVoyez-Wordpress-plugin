<?php
/**
 * The admin scripts take their texts from forvoyez_get_js_translations():
 * every forvoyezI18n.t() / .n() string of the scripts is listed there, under
 * its English text, and the list holds no string the scripts do not use.
 *
 * @package ForVoyez
 */

class TestForvoyezJsTranslations extends WP_UnitTestCase
{
	private const DOMAIN = 'auto-alt-text-for-images';

	public function tear_down()
	{
		unload_textdomain(self::DOMAIN);
		parent::tear_down();
	}

	/**
	 * Code of the scripts: assets/js and the inline script of the main file.
	 */
	private function scripts_code(): string
	{
		$code = '';
		foreach ([ 'admin-script.js', 'media-script.js' ] as $script) {
			$code .= file_get_contents(FORVOYEZ_PLUGIN_DIR . 'assets/js/' . $script);
		}

		return $code . file_get_contents(FORVOYEZ_PLUGIN_DIR . 'auto-alt-text-for-images.php');
	}

	/**
	 * Strings passed to t('…') and n('…', '…', count) in the scripts.
	 *
	 * @return array{0: string[], 1: array<string, string>} Singles, and plurals (singular => plural).
	 */
	private function strings_of_the_scripts(): array
	{
		$code = $this->scripts_code();
		$literal = '(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")';

		preg_match_all('/\bt\(\s*' . $literal . '\s*\)/', $code, $singles);
		preg_match_all('/\bn\(\s*' . $literal . '\s*,\s*' . $literal . '\s*,/', $code, $plurals);

		$unquote = static function (string $literal): string {
			return stripcslashes(substr($literal, 1, -1));
		};

		return [
			array_values(array_unique(array_map($unquote, $singles[1]))),
			array_combine(array_map($unquote, $plurals[1]), array_map($unquote, $plurals[2])),
		];
	}

	public function test_every_script_string_is_translated_by_php()
	{
		[ $singles, $plurals ] = $this->strings_of_the_scripts();
		$this->assertGreaterThan(40, count($singles), 'The t() calls were not found.');
		$this->assertGreaterThan(5, count($plurals), 'The n() calls were not found.');

		$translations = forvoyez_get_js_translations();

		foreach ($singles as $text) {
			$this->assertArrayHasKey($text, $translations, "Add '$text' to forvoyez_get_js_translations().");
			// Without a translation loaded, the value is the msgid itself: the
			// key and the __() string are the same text.
			$this->assertSame($text, $translations[ $text ], "The key and the msgid of '$text' differ.");
		}

		foreach ($plurals as $single => $plural) {
			$this->assertArrayHasKey($single, $translations, "Add the plural '$single' to forvoyez_get_js_translations().");
			$this->assertSame([ $plural, $single, $plural ], $translations[ $single ], "The plural of '$single' differs.");
		}

		$unused = array_diff(array_keys($translations), $singles, array_keys($plurals));
		$this->assertSame([], array_values($unused), 'Strings no script uses.');
	}

	public function test_french_plurals_use_the_singular_for_zero_and_one()
	{
		load_textdomain(self::DOMAIN, FORVOYEZ_PLUGIN_DIR . 'languages/' . self::DOMAIN . '-fr_FR.mo', 'fr_FR');

		$translations = forvoyez_get_js_translations();

		$this->assertSame(
			[ 'Coût : %d crédit ForVoyez', 'Coût : %d crédit ForVoyez', 'Coût : %d crédits ForVoyez' ],
			$translations['Cost: %d ForVoyez credit'],
		);
		$this->assertSame('Texte alternatif manquant', $translations['Missing alt text']);
	}

	public function test_scripts_load_after_their_translations()
	{
		$admin = new Forvoyez_Admin($this->createMock(Forvoyez_API_Manager::class));
		$admin->enqueue_admin_scripts('toplevel_page_auto-alt-text-for-images');
		forvoyez_enqueue_media_scripts('upload.php');

		$this->assertContains('forvoyez-i18n', wp_scripts()->registered['forvoyez-admin-script']->deps);
		$this->assertContains('forvoyez-i18n', wp_scripts()->registered['forvoyez-media-script']->deps);
		// Localized once, even with both scripts on the page.
		$data = (string) wp_scripts()->get_data('forvoyez-i18n', 'data');
		$this->assertSame(1, substr_count($data, 'var forvoyezL10n = '));
	}
}
