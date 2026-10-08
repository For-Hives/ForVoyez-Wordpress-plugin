<?php

class TestForvoyezAdmin extends WP_UnitTestCase
{
	private $forvoyez_admin;
	private $api_manager_mock;

	public function setUp(): void
	{
		parent::setUp();
		$this->api_manager_mock = $this->createMock(
			Forvoyez_API_Manager::class,
		);
		$this->forvoyez_admin = new Forvoyez_Admin($this->api_manager_mock);
	}

	public function test_parse_and_sanitize_filters()
	{
		$adminMock = new class($this->createMock(Forvoyez_API_Manager::class)) extends Forvoyez_Admin {
			public function publicParseAndSanitizeFilters($raw_filters)
			{
				return $this->parse_and_sanitize_filters($raw_filters);
			}
		};

		$input = [
			[ 'name' => 'filter[]', 'value' => 'alt' ],
			[ 'name' => 'filter[]', 'value' => 'title' ],
			[ 'name' => 'filter[]', 'value' => 'invalid' ],
			[ 'name' => 'not_filter', 'value' => 'caption' ],
		];

		$expected = [ 'alt', 'title' ];

		$errorOutput = '';
		$errorHandler = function ($errno, $errstr) use (&$errorOutput) {
			$errorOutput .= $errstr . "\n";
		};
		set_error_handler($errorHandler, E_USER_NOTICE);

		$result = $adminMock->publicParseAndSanitizeFilters($input);

		restore_error_handler();

		$this->assertEquals($expected, $result, 'Filters were not parsed correctly');
	}

	public function test_build_meta_query()
	{
		$method = new ReflectionMethod(
			Forvoyez_Admin::class,
			'build_meta_query',
		);
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true); // No-op (deprecated) since PHP 8.1.
		}

		$filters = [ 'alt', 'title' ];
		$expected = [
			'relation' => 'OR',
			[
				'key' => '_wp_attachment_image_alt',
				'compare' => 'NOT EXISTS',
			],
			[
				'key' => 'post_title',
				'value' => '',
				'compare' => '=',
			],
		];

		$result = $method->invoke($this->forvoyez_admin, $filters);
		$this->assertEquals($expected, $result);
	}

	public function test_get_query_args()
	{
		$method = new ReflectionMethod(Forvoyez_Admin::class, 'get_query_args');
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true); // No-op (deprecated) since PHP 8.1.
		}

		$paged = 2;
		$per_page = 10;
		$filters = [ 'alt' ];

		$result = $method->invoke(
			$this->forvoyez_admin,
			$paged,
			$per_page,
			$filters,
		);

		$this->assertEquals('attachment', $result['post_type']);
		$this->assertEquals('image', $result['post_mime_type']);
		$this->assertEquals('inherit', $result['post_status']);
		$this->assertEquals(10, $result['posts_per_page']);
		$this->assertEquals(2, $result['paged']);
		$this->assertArrayHasKey('meta_query', $result);
	}

	public function test_get_image_counts()
	{
		// Create some test images
		$this->factory->attachment->create_many(
			5,
			[
				'post_mime_type' => 'image/webp',
			]
		);

		// Create an image without alt text
		$attachment_id = $this->factory->attachment->create(
			[
				'post_mime_type' => 'image/webp',
			]
		);
		delete_post_meta($attachment_id, '_wp_attachment_image_alt');

		$counts = $this->forvoyez_admin->get_image_counts();

		$this->assertArrayHasKey('all', $counts);
		$this->assertArrayHasKey('missing_alt', $counts);
		$this->assertArrayHasKey('missing_all', $counts);
		$this->assertEquals(6, $counts['all']);
		$this->assertGreaterThan(0, $counts['missing_alt']);
	}

	public function test_get_image_ids()
	{
		// Create test images
		$normal_image = $this->factory->attachment->create(
			[
				'post_mime_type' => 'image/webp',
			]
		);
		update_post_meta(
			$normal_image,
			'_wp_attachment_image_alt',
			'Normal Alt Text',
		);

		$missing_alt_image = $this->factory->attachment->create(
			[
				'post_mime_type' => 'image/webp',
			]
		);
		delete_post_meta($missing_alt_image, '_wp_attachment_image_alt');

		// Test for 'all'
		$all_ids = array_map(
			'intval',
			$this->forvoyez_admin->get_image_ids('all'),
		);

		$this->assertContains(
			$normal_image,
			$all_ids,
			"Normal image should be in 'all' results",
		);
		$this->assertContains(
			$missing_alt_image,
			$all_ids,
			"Missing alt image should be in 'all' results",
		);

		// Test for 'missing_alt'
		$missing_alt_ids = array_map(
			'intval',
			$this->forvoyez_admin->get_image_ids('missing_alt'),
		);

		$this->assertNotContains(
			$normal_image,
			$missing_alt_ids,
			"Normal image should not be in 'missing_alt' results",
		);
		$this->assertContains(
			$missing_alt_image,
			$missing_alt_ids,
			"Missing alt image should be in 'missing_alt' results",
		);
	}

	public function test_missing_api_key_notice_is_shown_on_media_screens()
	{
		wp_set_current_user(self::factory()->user->create([ 'role' => 'administrator' ]));
		set_current_screen('upload');
		$this->api_manager_mock->method('has_api_key')->willReturn(false);

		ob_start();
		$this->forvoyez_admin->maybe_show_missing_api_key_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString('notice-warning', $output);
		$this->assertStringContainsString(
			esc_html(forvoyez_get_missing_api_key_message()),
			$output,
		);
		$this->assertStringContainsString('tab=configuration', $output);

		set_current_screen('front');
	}

	public function test_missing_api_key_notice_is_hidden_when_key_is_set()
	{
		wp_set_current_user(self::factory()->user->create([ 'role' => 'administrator' ]));
		set_current_screen('upload');
		$this->api_manager_mock->method('has_api_key')->willReturn(true);

		ob_start();
		$this->forvoyez_admin->maybe_show_missing_api_key_notice();
		$output = ob_get_clean();

		$this->assertSame('', $output);

		set_current_screen('front');
	}

	public function test_missing_api_key_notice_is_hidden_on_unrelated_screens()
	{
		wp_set_current_user(self::factory()->user->create([ 'role' => 'administrator' ]));
		set_current_screen('dashboard');
		$this->api_manager_mock->method('has_api_key')->willReturn(false);

		ob_start();
		$this->forvoyez_admin->maybe_show_missing_api_key_notice();
		$output = ob_get_clean();

		$this->assertSame('', $output);

		set_current_screen('front');
	}

	public function test_admin_assets_are_local()
	{
		$this->forvoyez_admin->enqueue_admin_scripts(
			'toplevel_page_auto-alt-text-for-images',
		);

		$this->assertTrue(wp_style_is('forvoyez-admin-tailwind', 'enqueued'));
		$this->assertFileExists(FORVOYEZ_PLUGIN_DIR . 'assets/css/admin-tailwind.css');

		foreach ([ wp_scripts(), wp_styles() ] as $dependencies) {
			foreach ($dependencies->queue as $handle) {
				if (strpos($handle, 'forvoyez') !== 0) {
					continue;
				}
				$src = $dependencies->registered[ $handle ]->src;
				$this->assertStringStartsWith(
					FORVOYEZ_PLUGIN_URL,
					$src,
					"Asset $handle must be served from the plugin",
				);
				$this->assertFileExists(
					FORVOYEZ_PLUGIN_DIR . substr($src, strlen(FORVOYEZ_PLUGIN_URL)),
					"Asset $handle must exist in the plugin",
				);
				$this->assertSame(
					FORVOYEZ_VERSION,
					$dependencies->registered[ $handle ]->ver,
				);
			}
			$this->assertNotContains('tailwindcss', $dependencies->queue);
		}
	}
}
