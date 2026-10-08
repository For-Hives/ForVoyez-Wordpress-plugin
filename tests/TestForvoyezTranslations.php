<?php
/**
 * The bundled translations cover the POT and the compiled .mo files match
 * the .po sources (a stale .mo left the strings of 1.1.41 in English).
 *
 * @package ForVoyez
 */

class TestForvoyezTranslations extends WP_UnitTestCase
{
	private const DOMAIN = 'auto-alt-text-for-images';

	public static function set_up_before_class()
	{
		parent::set_up_before_class();
		require_once ABSPATH . WPINC . '/pomo/po.php';
	}

	public function tear_down()
	{
		unload_textdomain(self::DOMAIN);
		parent::tear_down();
	}

	private function languages_dir(): string
	{
		return dirname(__DIR__) . '/languages/';
	}

	private function load_locale(string $locale): void
	{
		unload_textdomain(self::DOMAIN);
		$this->assertTrue(
			load_textdomain(self::DOMAIN, $this->languages_dir() . self::DOMAIN . "-$locale.mo", $locale),
			"Could not load the $locale .mo file.",
		);
	}

	public function provide_locales(): array
	{
		return [
			'fr_FR' => [ 'fr_FR' ],
			'de_DE' => [ 'de_DE' ],
			'es_ES' => [ 'es_ES' ],
		];
	}

	/**
	 * Every string of the POT has a translation in the .po file.
	 *
	 * @dataProvider provide_locales
	 */
	public function test_po_translates_every_pot_string(string $locale)
	{
		$pot = new PO();
		$this->assertTrue($pot->import_from_file($this->languages_dir() . self::DOMAIN . '.pot'));
		$po = new PO();
		$this->assertTrue($po->import_from_file($this->languages_dir() . self::DOMAIN . "-$locale.po"));

		$missing = [];
		foreach ($pot->entries as $key => $entry) {
			$translation = $po->entries[ $key ] ?? null;
			if (!$translation || '' === implode('', $translation->translations)) {
				$missing[] = $entry->singular;
			}
		}

		$this->assertSame([], $missing, "Untranslated in $locale.");
		$this->assertNotEmpty($po->get_header('Plural-Forms'), "$locale.po has no Plural-Forms header.");
	}

	/**
	 * The .mo file holds exactly the translations of the .po file.
	 *
	 * @dataProvider provide_locales
	 */
	public function test_mo_is_compiled_from_the_current_po(string $locale)
	{
		$po = new PO();
		$this->assertTrue($po->import_from_file($this->languages_dir() . self::DOMAIN . "-$locale.po"));
		$mo = new MO();
		$this->assertTrue($mo->import_from_file($this->languages_dir() . self::DOMAIN . "-$locale.mo"));

		$from_po = [];
		foreach ($po->entries as $key => $entry) {
			if ('' !== implode('', $entry->translations)) {
				$from_po[ $key ] = $entry->translations;
			}
		}
		$from_mo = array_map(
			static function ($entry) {
				return $entry->translations;
			},
			$mo->entries,
		);
		ksort($from_po);
		ksort($from_mo);

		$this->assertSame($from_po, $from_mo, "Run msgfmt: languages/*-$locale.mo is out of date.");
	}

	public function test_strings_of_1_1_41_are_translated()
	{
		$expected = [
			'fr_FR' => [
				'Configure the API key' => 'Configurer la clé API',
				'The ForVoyez API request failed (HTTP %d).' => "La requête à l'API ForVoyez a échoué (HTTP %d).",
				'Start Analysis' => "Lancer l'analyse",
			],
			'de_DE' => [
				'Configure the API key' => 'API-Schlüssel konfigurieren',
				'The ForVoyez API request failed (HTTP %d).' => 'Die Anfrage an die ForVoyez-API ist fehlgeschlagen (HTTP %d).',
				'Start Analysis' => 'Analyse starten',
			],
			'es_ES' => [
				'Configure the API key' => 'Configurar la clave API',
				'The ForVoyez API request failed (HTTP %d).' => 'La solicitud a la API de ForVoyez ha fallado (HTTP %d).',
				'Start Analysis' => 'Iniciar análisis',
			],
		];

		foreach ($expected as $locale => $strings) {
			$this->load_locale($locale);
			foreach ($strings as $msgid => $msgstr) {
				// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Test data.
				$this->assertSame($msgstr, __($msgid, 'auto-alt-text-for-images'), "$locale: $msgid");
			}
			$this->assertStringStartsNotWith(
				'Your ForVoyez API key is not configured.',
				forvoyez_get_missing_api_key_message(),
				"$locale: the missing-key notice is still in English.",
			);
		}
	}

	public function test_plural_forms_follow_the_locale()
	{
		$this->load_locale('fr_FR');
		// French uses the singular for 0 and 1.
		$this->assertSame('ForVoyez : prêt à analyser 0 image.', sprintf(_n('ForVoyez: Ready to analyze %d image.', 'ForVoyez: Ready to analyze %d images.', 0, 'auto-alt-text-for-images'), 0));
		$this->assertSame('ForVoyez : prêt à analyser 2 images.', sprintf(_n('ForVoyez: Ready to analyze %d image.', 'ForVoyez: Ready to analyze %d images.', 2, 'auto-alt-text-for-images'), 2));

		$this->load_locale('de_DE');
		$this->assertSame('ForVoyez: Bereit, 0 Bilder zu analysieren.', sprintf(_n('ForVoyez: Ready to analyze %d image.', 'ForVoyez: Ready to analyze %d images.', 0, 'auto-alt-text-for-images'), 0));
		$this->assertSame('ForVoyez: Bereit, 1 Bild zu analysieren.', sprintf(_n('ForVoyez: Ready to analyze %d image.', 'ForVoyez: Ready to analyze %d images.', 1, 'auto-alt-text-for-images'), 1));
	}
}
