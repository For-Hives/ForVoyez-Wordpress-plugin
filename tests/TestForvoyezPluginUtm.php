<?php
/**
 * Class TestForvoyezPluginUtm
 *
 * Links to forvoyez.com carry fixed campaign parameters and API requests carry
 * the X-ForVoyez-Client header. Both only name the plugin, a screen, a link
 * or a version: nothing about the site, the user or the API key
 * (WordPress.org guideline 7). HTTP calls are intercepted with
 * `pre_http_request`.
 *
 * @package ForVoyez
 */

class TestForvoyezPluginUtm extends WP_UnitTestCase
{
	const DESCRIBE_URL = 'https://forvoyez.com/api/describe';
	const TOKENS_URL = 'https://forvoyez.com/api/tokens';
	const API_KEY = 'fake.jwt.for-tests';
	const PLUGIN_UTM = 'utm_source=wordpress-plugin&utm_medium=plugin';
	const README_UTM = 'utm_source=wordpress.org&utm_medium=readme&utm_campaign=listing';

	/**
	 * Requests seen by `pre_http_request` (each: url, args).
	 *
	 * @var array[]
	 */
	private $requests = [];

	public function set_up()
	{
		parent::set_up();
		$this->requests = [];
		delete_option('forvoyez_encrypted_api_key');
		add_filter('pre_http_request', [ $this, 'intercept_http_request' ], 10, 3);
	}

	public function tear_down()
	{
		remove_filter('pre_http_request', [ $this, 'intercept_http_request' ], 10);
		delete_option('forvoyez_encrypted_api_key');
		parent::tear_down();
	}

	/**
	 * Record every outgoing request and answer it with a valid body for both
	 * endpoints, so nothing reaches the network.
	 */
	public function intercept_http_request($preempt, $args, $url)
	{
		$this->requests[] = [ 'url' => $url, 'args' => $args ];

		return [
			'headers' => [ 'content-type' => 'application/json' ],
			'body' => wp_json_encode([
				'title' => 'Red bicycle',
				'alternativeText' => 'A red bicycle leaning against a wall',
				'caption' => 'A bicycle in the sun.',
				'user' => [ 'credits' => 12 ],
			]),
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'cookies' => [],
			'filename' => null,
		];
	}

	public function test_tracked_url_adds_the_fixed_campaign_parameters()
	{
		$this->assertSame(
			'https://forvoyez.com/sign-up?' . self::PLUGIN_UTM . '&utm_campaign=configuration&utm_content=signup',
			forvoyez_get_tracked_url('https://forvoyez.com/sign-up', 'configuration', 'signup'),
		);
		$this->assertSame(
			'https://doc.forvoyez.com/?' . self::PLUGIN_UTM . '&utm_campaign=configuration&utm_content=api-docs',
			forvoyez_get_tracked_url('https://doc.forvoyez.com/', 'configuration', 'api-docs'),
		);
	}

	public function test_tracked_url_keeps_the_existing_query_and_fragment()
	{
		$this->assertSame(
			'https://doc.forvoyez.com/describe?lang=php&' . self::PLUGIN_UTM
				. '&utm_campaign=configuration&utm_content=api-docs#request',
			forvoyez_get_tracked_url(
				'https://doc.forvoyez.com/describe?lang=php#request',
				'configuration',
				'api-docs',
			),
		);
	}

	/**
	 * @dataProvider data_other_hosts
	 */
	public function test_tracked_url_leaves_other_hosts_untouched($url)
	{
		$this->assertSame($url, forvoyez_get_tracked_url($url, 'configuration', 'signup'));
	}

	public static function data_other_hosts()
	{
		return [
			'wordpress.org' => [ 'https://wordpress.org/plugins/auto-alt-text-for-images/' ],
			'github' => [ 'https://github.com/For-Hives/ForVoyez-Wordpress-plugin' ],
			'lookalike host' => [ 'https://forvoyez.com.example.org/sign-up' ],
			'relative admin url' => [ 'admin.php?page=auto-alt-text-for-images' ],
		];
	}

	public function test_tracked_url_reduces_campaign_and_content_to_slugs()
	{
		$url = forvoyez_get_tracked_url('https://forvoyez.com/app', 'Configuration Tab!', 'Sign up & <b>go</b>');
		parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);

		$this->assertSame(
			[
				'utm_source' => 'wordpress-plugin',
				'utm_medium' => 'plugin',
				'utm_campaign' => 'configurationtab',
				'utm_content' => 'signupbgob',
			],
			$query,
		);
	}

	public function test_configuration_tab_tags_its_links_except_the_legal_ones()
	{
		ob_start();
		include FORVOYEZ_PLUGIN_DIR . 'templates/configuration-tab.php';
		$html = ob_get_clean();

		$this->assertSame(
			[
				'https://forvoyez.com/sign-up?' . self::PLUGIN_UTM . '&utm_campaign=configuration&utm_content=signup',
				'https://forvoyez.com/app?' . self::PLUGIN_UTM . '&utm_campaign=configuration&utm_content=dashboard',
				'https://doc.forvoyez.com/?' . self::PLUGIN_UTM . '&utm_campaign=configuration&utm_content=api-docs',
				'https://forvoyez.com/app/legals/terms',
				'https://forvoyez.com/app/legals/privacy-policy',
				'https://forvoyez.com/app/legals/legal-notice',
			],
			$this->hrefs($html),
		);
	}

	public function test_recharge_url_is_passed_to_the_admin_script()
	{
		$admin = new Forvoyez_Admin($this->createMock(Forvoyez_API_Manager::class));
		$admin->enqueue_admin_scripts('toplevel_page_auto-alt-text-for-images');

		// One `var forvoyezData = {...};` line per localize call: read the last one.
		$data = (string) wp_scripts()->get_data('forvoyez-admin-script', 'data');
		$this->assertGreaterThan(0, preg_match_all('/^var forvoyezData = (\{.*\});$/m', $data, $matches));
		$localized = json_decode(end($matches[1]), true);

		$this->assertSame(
			[
				'recharge' => 'https://forvoyez.com/app/plans?' . self::PLUGIN_UTM
					. '&utm_campaign=credits-widget&utm_content=recharge',
			],
			$localized['urls'],
		);
	}

	public function test_scripts_do_not_hardcode_forvoyez_urls()
	{
		foreach ([ 'admin-script.js', 'media-script.js' ] as $script) {
			$this->assertStringNotContainsString(
				'forvoyez.com',
				file_get_contents(FORVOYEZ_PLUGIN_DIR . 'assets/js/' . $script),
				"$script must read its ForVoyez URLs from forvoyezData.urls",
			);
		}
	}

	public function test_readme_links_use_the_listing_campaign_except_the_legal_ones()
	{
		$readme = file_get_contents(FORVOYEZ_PLUGIN_DIR . 'readme.txt');
		preg_match_all('#\]\((https://(?:doc\.)?forvoyez\.com[^)\s]*)\)#', $readme, $matches);

		$contents = [];
		$untagged = [];
		foreach ($matches[1] as $url) {
			$parts = explode('?' . self::README_UTM . '&utm_content=', $url);
			if (2 === count($parts)) {
				$contents[] = $parts[1];
			} else {
				$untagged[] = $url;
			}
		}

		$this->assertSame(
			[ 'signup', 'dashboard', 'homepage-faq', 'docs', 'contact', 'homepage' ],
			$contents,
		);
		$this->assertSame(
			[
				'https://forvoyez.com/app/legals/terms',
				'https://forvoyez.com/app/legals/privacy-policy',
				'https://forvoyez.com/app/legals/legal-notice',
			],
			$untagged,
		);
		// The plugin campaign belongs to the admin pages, not to the listing.
		$this->assertStringNotContainsString('utm_source=wordpress-plugin', $readme);
	}

	public function test_plugin_header_uris_have_no_campaign_parameters()
	{
		// Both are translatable strings of the POT and shown on WordPress.org.
		$header = get_file_data(
			FORVOYEZ_PLUGIN_DIR . 'auto-alt-text-for-images.php',
			[ 'plugin_uri' => 'Plugin URI', 'author_uri' => 'Author URI' ],
		);

		$this->assertSame('https://doc.forvoyez.com/wordpress-plugin', $header['plugin_uri']);
		$this->assertSame('https://forvoyez.com', $header['author_uri']);
	}

	public function test_every_api_request_names_the_plugin_and_its_version()
	{
		$this->store_api_key(self::API_KEY);
		$api_manager = new Forvoyez_API_Manager(self::API_KEY, 'en', '');

		$analysis = $api_manager->analyze_image($this->create_image());
		$api_manager->get_token_info();
		$api_manager->verify_api_key();

		$this->assertTrue($analysis['success']);
		$this->assertSame(
			[ self::DESCRIBE_URL, self::TOKENS_URL, self::TOKENS_URL ],
			wp_list_pluck($this->requests, 'url'),
		);
		$this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', FORVOYEZ_VERSION);
		foreach ($this->requests as $request) {
			$this->assertSame(
				'wordpress-plugin/' . FORVOYEZ_VERSION,
				$request['args']['headers']['X-ForVoyez-Client'],
				$request['url'],
			);
		}
	}

	public function test_plugin_sets_no_other_request_header()
	{
		$api_manager = new Forvoyez_API_Manager(self::API_KEY, 'en', '');

		$api_manager->analyze_image($this->create_image());
		$api_manager->get_token_info();

		$this->assertSame(
			[ 'Authorization', 'X-ForVoyez-Client', 'Content-Type', 'Content-Length' ],
			array_keys($this->requests[0]['args']['headers']),
		);
		$this->assertSame(
			[ 'Authorization', 'X-ForVoyez-Client' ],
			array_keys($this->requests[1]['args']['headers']),
		);
	}

	/**
	 * The href values of a piece of HTML, entities decoded.
	 *
	 * @return string[]
	 */
	private function hrefs(string $html): array
	{
		preg_match_all('/href="([^"]*)"/', $html, $matches);

		return array_map(
			static function ($href) {
				return html_entity_decode($href, ENT_QUOTES);
			},
			$matches[1],
		);
	}

	/**
	 * Image attachment pointing at tests/assets/test-image.webp (no upload).
	 */
	private function create_image(): int
	{
		return self::factory()->attachment->create_object([
			'file' => __DIR__ . '/assets/test-image.webp',
			'post_mime_type' => 'image/webp',
			'post_title' => 'test-image',
		]);
	}

	/**
	 * Save an API key the way the settings screen does (encrypted option).
	 */
	private function store_api_key(string $api_key): void
	{
		$settings = new Forvoyez_Settings();
		$encrypt = new ReflectionMethod(Forvoyez_Settings::class, 'encrypt');
		if (PHP_VERSION_ID < 80100) {
			$encrypt->setAccessible(true); // No-op (deprecated) since PHP 8.1.
		}
		update_option('forvoyez_encrypted_api_key', $encrypt->invoke($settings, $api_key));
	}
}
