<?php
/**
 * Media-library bulk action notice ("Analyze with ForVoyez" on upload.php).
 *
 * @package ForVoyez
 */

class TestForvoyezMediaBulkNotice extends WP_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		wp_set_current_user(self::factory()->user->create([ 'role' => 'administrator' ]));
	}

	public function tearDown(): void
	{
		$_GET = [];
		parent::tearDown();
	}

	/**
	 * Render the notice the way upload.php does after the bulk action redirect.
	 */
	private function render_notice(string $ids): string
	{
		$_GET = [
			'forvoyez_bulk_analyze' => (string) count(explode(',', $ids)),
			'forvoyez_bulk_nonce' => wp_create_nonce('forvoyez_bulk_analyze_nonce'),
			'forvoyez_image_ids' => $ids,
		];

		ob_start();
		forvoyez_bulk_analysis_notice();
		return ob_get_clean();
	}

	public function provide_selections(): array
	{
		return [
			'one image' => [ '42', 1 ],
			'several images' => [ '42,43,44', 3 ],
		];
	}

	/**
	 * @dataProvider provide_selections
	 */
	public function test_notice_lists_the_selected_ids(string $ids, int $count)
	{
		$html = $this->render_notice($ids);

		$this->assertStringContainsString('data-ids="' . $ids . '"', $html);
		$this->assertStringContainsString('<span id="forvoyez-progress-count">0 / ' . $count . '</span>', $html);
	}

	/**
	 * jQuery .data('ids') returns the Number 42 for data-ids="42", and a
	 * Number has no split(): with one selected image "Start Analysis" threw
	 * "$button.data(...).split is not a function" and sent nothing.
	 *
	 * @dataProvider provide_selections
	 */
	public function test_start_button_reads_the_ids_as_a_string(string $ids)
	{
		$html = $this->render_notice($ids);

		$this->assertStringNotContainsString(".data('ids')", $html);
		$this->assertStringContainsString(
			"const imageIds = String(\$button.attr('data-ids') || '').split(',').filter(Boolean);",
			$html,
		);
	}

	public function test_notice_needs_a_valid_nonce()
	{
		$_GET = [
			'forvoyez_bulk_analyze' => '1',
			'forvoyez_bulk_nonce' => 'not-a-nonce',
			'forvoyez_image_ids' => '42',
		];

		ob_start();
		forvoyez_bulk_analysis_notice();
		$this->assertSame('', ob_get_clean());
	}
}
