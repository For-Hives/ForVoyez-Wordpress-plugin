<?php
/**
 * Runs tests/js/html-sinks.test.js, which executes the real admin scripts with
 * values from the API and fails if one of them is written as HTML.
 *
 * @package ForVoyez
 */

class TestForvoyezJsHtmlSinks extends WP_UnitTestCase
{
	public function test_scripts_write_api_values_as_text()
	{
		$node = trim((string) shell_exec('command -v node'));
		if ('' === $node) {
			// GitHub runners ship Node: in CI, a missing Node must not hide this test.
			$this->assertFalse((bool) getenv('CI'), 'Node is required in CI.');
			$this->markTestSkipped('Node is not installed.');
		}

		exec(
			escapeshellarg($node) . ' --test ' . escapeshellarg(FORVOYEZ_PLUGIN_DIR . 'tests/js/html-sinks.test.js') . ' 2>&1',
			$output,
			$exit_code,
		);

		$this->assertSame(0, $exit_code, implode("\n", $output));
	}
}
