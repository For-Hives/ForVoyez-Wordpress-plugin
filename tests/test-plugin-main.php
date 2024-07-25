<?php
/**
 * Class PluginMainTest
 *
 * @package ForVoyez
 */

class PluginMainTest extends WP_UnitTestCase {

    /**
     * Test if plugin constants are defined correctly.
     *
     * @return void
     */
    public function test_plugin_constants() {
        $expected_constants = [
            'FORVOYEZ_VERSION',
            'FORVOYEZ_PLUGIN_DIR',
            'FORVOYEZ_PLUGIN_URL',
            'FORVOYEZ_PLUGIN_BASENAME'
        ];

        foreach ($expected_constants as $constant) {
            $this->assertTrue(defined($constant), "Constant $constant is not defined.");
        }
    }

    /**
     * Test if required files are included.
     *
     * @return void
     */
    public function test_required_files_included() {
        $expected_components = [
            'function' => ['forvoyez_get_api_key'],
            'class' => [
                'Forvoyez_Admin',
                'Forvoyez_API',
                'Forvoyez_API_Manager',
                'Forvoyez_Image_Processor',
                'Forvoyez_Settings',
                'Forvoyez_Image_Renderer'
            ]
        ];

        foreach ($expected_components['function'] as $function) {
            $this->assertTrue(function_exists($function), "Function $function is not defined.");
        }

        foreach ($expected_components['class'] as $class) {
            $this->assertTrue(class_exists($class), "Class $class is not defined.");
        }
    }

    /**
     * Test if initialization function exists.
     *
     * @return void
     */
    public function test_init_function_exists() {
        $this->assertTrue(function_exists('forvoyez_init'), 'Function forvoyez_init does not exist.');
    }

    /**
     * Test if activation and deactivation hooks are registered.
     *
     * @return void
     */
    public function test_hooks_registered() {
        global $wp_filter;

        $this->assertNotFalse(has_action('plugins_loaded', 'forvoyez_init'), 'forvoyez_init is not hooked to plugins_loaded');

        $this->assertArrayHasKey('forvoyez_activate', $wp_filter['activate_' . FORVOYEZ_PLUGIN_BASENAME][10], 'Activation hook is not registered');
        $this->assertArrayHasKey('forvoyez_deactivate', $wp_filter['deactivate_' . FORVOYEZ_PLUGIN_BASENAME][10], 'Deactivation hook is not registered');
    }

    /**
     * Test activation function.
     *
     * @return void
     */
    public function test_activation() {
        // Ensure options are not set before activation
        delete_option('forvoyez_plugin_activated');
        delete_option('forvoyez_plugin_version');
        delete_option('forvoyez_flush_rewrite_rules');

        forvoyez_activate();

        $this->assertTrue(get_option('forvoyez_plugin_activated'), 'Plugin activation flag not set.');
        $this->assertEquals(FORVOYEZ_VERSION, get_option('forvoyez_plugin_version'), 'Plugin version not set correctly.');
        $this->assertTrue(get_option('forvoyez_flush_rewrite_rules'), 'Flush rewrite rules flag not set.');
    }

    /**
     * Test deactivation function.
     *
     * @return void
     */
    public function test_deactivation() {
        // Set up the options as if the plugin was activated
        update_option('forvoyez_plugin_activated', true);
        update_option('forvoyez_flush_rewrite_rules', true);

        forvoyez_deactivate();

        $this->assertFalse(get_option('forvoyez_plugin_activated'), 'Plugin activation flag not removed.');
        $this->assertFalse(get_option('forvoyez_flush_rewrite_rules'), 'Flush rewrite rules flag not removed.');
    }
}