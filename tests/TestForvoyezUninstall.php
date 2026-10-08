<?php
/**
 * uninstall.php removes everything the plugin stored.
 *
 * Run the multisite case with: WP_MULTISITE=1 ./vendor/bin/phpunit --filter TestForvoyezUninstall
 *
 * @package ForVoyez
 */

class TestForvoyezUninstall extends WP_UnitTestCase
{
	/**
	 * Options the plugin writes (current and older versions).
	 */
	private const PLUGIN_OPTIONS = [
		'forvoyez_encrypted_api_key' => 'encrypted-key',
		'forvoyez_api_key' => 'plain-key',
		'forvoyez_context' => 'A cat shelter in Lyon',
		'forvoyez_language' => 'fr',
		'forvoyez_auto_analyze_enabled' => 'true',
		'forvoyez_plugin_version' => '1.1.40',
		'forvoyez_plugin_activated' => '1',
		'forvoyez_flush_rewrite_rules' => '1',
		'forvoyez_option_from_a_future_version' => 'x',
	];

	/**
	 * Store plugin data on the current site and return the attachment id.
	 */
	private function seed_site(): int
	{
		foreach (self::PLUGIN_OPTIONS as $name => $value) {
			update_option($name, $value);
		}
		set_transient('forvoyez_bulk_analyze_images', [ 1, 2 ], HOUR_IN_SECONDS);
		set_transient('forvoyez_api_check', 'ok');
		set_transient('forvoyez_transient_from_a_future_version', 'x', HOUR_IN_SECONDS);

		$attachment_id = self::factory()->attachment->create([
			'post_mime_type' => 'image/jpeg',
			'post_title' => 'Fox at a desk',
		]);
		update_post_meta($attachment_id, '_forvoyez_analyzed', '1');
		update_post_meta($attachment_id, '_forvoyez_meta_from_a_future_version', 'x');
		update_post_meta($attachment_id, '_wp_attachment_image_alt', 'A fox at a desk');
		// "_" is a LIKE wildcard: an unescaped '_forvoyez_%' also matches this key.
		update_post_meta($attachment_id, 'xforvoyezx_other_plugin', 'keep');

		wp_schedule_single_event(time() + 10, 'forvoyez_analyze_single_image', [ $attachment_id ]);
		wp_schedule_single_event(time() + 20, 'forvoyez_analyze_single_image', [ $attachment_id + 1 ]);
		wp_schedule_event(time() + 30, 'daily', 'forvoyez_daily_cleanup');

		update_option('other_plugin_option', 'keep');
		set_transient('other_plugin_transient', 'keep', HOUR_IN_SECONDS);

		return $attachment_id;
	}

	private function run_uninstall(): void
	{
		if (!defined('WP_UNINSTALL_PLUGIN')) {
			define('WP_UNINSTALL_PLUGIN', FORVOYEZ_PLUGIN_BASENAME);
		}
		include dirname(__DIR__) . '/uninstall.php';
	}

	/**
	 * Check the current site: plugin data gone, site content and other data kept.
	 */
	private function assert_site_is_clean(int $attachment_id): void
	{
		global $wpdb;

		wp_cache_flush();

		foreach (array_keys(self::PLUGIN_OPTIONS) as $name) {
			$this->assertFalse(get_option($name), "Option $name was left behind.");
		}
		$this->assertSame(
			[],
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
					'%' . $wpdb->esc_like('forvoyez') . '%',
				),
			),
			'Some forvoyez option or transient is still in the options table.',
		);
		$this->assertFalse(get_transient('forvoyez_bulk_analyze_images'));
		$this->assertFalse(get_transient('forvoyez_api_check'));

		$this->assertSame('', get_post_meta($attachment_id, '_forvoyez_analyzed', true));
		$this->assertSame('', get_post_meta($attachment_id, '_forvoyez_meta_from_a_future_version', true));
		$this->assertSame('A fox at a desk', get_post_meta($attachment_id, '_wp_attachment_image_alt', true));
		$this->assertSame('Fox at a desk', get_post($attachment_id)->post_title);
		$this->assertSame('keep', get_post_meta($attachment_id, 'xforvoyezx_other_plugin', true));

		$this->assertFalse(wp_next_scheduled('forvoyez_analyze_single_image', [ $attachment_id ]));
		$this->assertFalse(wp_next_scheduled('forvoyez_analyze_single_image', [ $attachment_id + 1 ]));
		$this->assertFalse(wp_next_scheduled('forvoyez_daily_cleanup'));

		$this->assertSame('keep', get_option('other_plugin_option'));
		$this->assertSame('keep', get_transient('other_plugin_transient'));
	}

	public function test_uninstall_removes_the_plugin_data_of_the_site()
	{
		$attachment_id = $this->seed_site();

		$this->run_uninstall();

		$this->assert_site_is_clean($attachment_id);
	}

	public function test_uninstall_removes_the_plugin_data_of_every_site_of_a_network()
	{
		if (!is_multisite()) {
			$this->markTestSkipped('Multisite only: run with WP_MULTISITE=1.');
		}

		$attachments = [ get_current_blog_id() => $this->seed_site() ];
		foreach (self::factory()->blog->create_many(2) as $blog_id) {
			switch_to_blog($blog_id);
			$attachments[$blog_id] = $this->seed_site();
			restore_current_blog();
		}
		update_site_option('forvoyez_network_option', 'x');
		set_site_transient('forvoyez_site_transient', 'x', HOUR_IN_SECONDS);
		update_site_option('other_plugin_network_option', 'keep');

		$this->run_uninstall();

		foreach ($attachments as $blog_id => $attachment_id) {
			switch_to_blog($blog_id);
			$this->assert_site_is_clean($attachment_id);
			restore_current_blog();
		}
		$this->assertFalse(get_site_option('forvoyez_network_option'));
		$this->assertFalse(get_site_transient('forvoyez_site_transient'));
		$this->assertSame('keep', get_site_option('other_plugin_network_option'));
	}
}
