<?php
/**
 * Class PluginMainTest
 *
 * @package ForVoyez
 */

class PluginMainTest extends WP_UnitTestCase {

    /**
     * Test if plugin constants are defined correctly.
     */
    public function test_plugin_constants() {
        $this->assertTrue(defined('FORVOYEZ_VERSION'));
        $this->assertTrue(defined('FORVOYEZ_PLUGIN_DIR'));
        $this->assertTrue(defined('FORVOYEZ_PLUGIN_URL'));
        $this->assertTrue(defined('FORVOYEZ_PLUGIN_BASENAME'));
    }

    /**
     * Test if required files are included.
     */
    public function test_required_files_included() {
        $this->assertTrue(function_exists('forvoyez_get_api_key'), 'forvoyez-helpers.php not included');
        $this->assertTrue(class_exists('Forvoyez_Admin'), 'class-forvoyez-admin.php not included');
        $this->assertTrue(class_exists('Forvoyez_API'), 'class-forvoyez-api.php not included');
        $this->assertTrue(class_exists('Forvoyez_API_Manager'), 'class-forvoyez-api-manager.php not included');
        $this->assertTrue(class_exists('Forvoyez_Image_Processor'), 'class-forvoyez-image-processor.php not included');
        $this->assertTrue(class_exists('Forvoyez_Settings'), 'class-forvoyez-settings.php not included');
        $this->assertTrue(class_exists('Forvoyez_Image_Renderer'), 'class-forvoyez-image-renderer.php not included');
    }

    /**
     * Test if initialization function exists.
     */
    public function test_init_function_exists() {
        $this->assertTrue(function_exists('forvoyez_init'));
    }

    /**
     * Test if activation and deactivation hooks are registered.
     */
    public function test_hooks_registered() {
        global $wp_filter;

        $this->assertNotFalse(has_action('plugins_loaded', 'forvoyez_init'), 'forvoyez_init is not hooked to plugins_loaded');

        $this->assertArrayHasKey('forvoyez_activate', $wp_filter['activate_' . FORVOYEZ_PLUGIN_BASENAME][10], 'Activation hook is not registered');
        $this->assertArrayHasKey('forvoyez_deactivate', $wp_filter['deactivate_' . FORVOYEZ_PLUGIN_BASENAME][10], 'Deactivation hook is not registered');
    }

    /**
     * Test activation function.
     */
    public function test_activation() {
        // Ensure options are not set before activation
        delete_option('forvoyez_plugin_activated');
        delete_option('forvoyez_plugin_version');
        delete_option('forvoyez_flush_rewrite_rules');

        forvoyez_activate();

        $this->assertTrue(get_option('forvoyez_plugin_activated'));
        $this->assertEquals(FORVOYEZ_VERSION, get_option('forvoyez_plugin_version'));
        $this->assertTrue(get_option('forvoyez_flush_rewrite_rules'));
    }

    /**
     * Test deactivation function.
     */
    public function test_deactivation() {
        // Set up the options as if the plugin was activated
        update_option('forvoyez_plugin_activated', true);
        update_option('forvoyez_flush_rewrite_rules', true);

        forvoyez_deactivate();

        $this->assertFalse(get_option('forvoyez_plugin_activated'));
        $this->assertFalse(get_option('forvoyez_flush_rewrite_rules'));
    }
}