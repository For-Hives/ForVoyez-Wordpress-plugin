<?php
/**
 * Plugin Name: Auto Alt Text for Images
 * Description: Automatically generate alt text and SEO metadata for images using ForVoyez API.
 * Version: 1.0.0
 * Author: ForVoyez
 * Author URI: https://forvoyez.com
 * Text Domain: forvoyez-auto-alt-text-for-images
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package ForVoyez
 */

// If this file is called directly, abort.
defined('ABSPATH') || exit('Direct access to this file is not allowed.');

/**
 * Define plugin constants
 */
define('FORVOYEZ_VERSION', '1.0.0');
define('FORVOYEZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FORVOYEZ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FORVOYEZ_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Include required files
 */
require_once FORVOYEZ_PLUGIN_DIR . 'includes/forvoyez-constants.php';
require_once FORVOYEZ_PLUGIN_DIR . 'includes/forvoyez-helpers.php';
require_once FORVOYEZ_PLUGIN_DIR . 'includes/class-forvoyez-admin.php';
require_once FORVOYEZ_PLUGIN_DIR . 'includes/class-forvoyez-api.php';
require_once FORVOYEZ_PLUGIN_DIR . 'includes/class-forvoyez-api-manager.php';
require_once FORVOYEZ_PLUGIN_DIR . 'includes/class-forvoyez-image-processor.php';
require_once FORVOYEZ_PLUGIN_DIR . 'includes/class-forvoyez-settings.php';
require_once FORVOYEZ_PLUGIN_DIR . 'includes/class-forvoyez-image-renderer.php';

/**
 * Initialize the plugin components
 *
 * This function is hooked into the 'plugins_loaded' action, which ensures
 * that it's only executed after all plugins have been loaded.
 *
 * @since 1.0.0
 */
function forvoyez_init() {
    $admin = new Forvoyez_Admin();
    $admin->init();

    $api = new Forvoyez_API();
    $api->init();

    $image_processor = new Forvoyez_Image_Processor();
    $image_processor->init();

    $settings = new Forvoyez_Settings();
    $settings->init();
}

// Hook the initialization function to the 'plugins_loaded' action
add_action('plugins_loaded', 'forvoyez_init');

/**
 * Activation hook
 *
 * This function is run when the plugin is activated.
 *
 * @since 1.0.0
 */
function forvoyez_activate() {
    // Activation logic here
    update_option('forvoyez_plugin_activated', true);
    update_option('forvoyez_plugin_version', FORVOYEZ_VERSION);

    update_option('forvoyez_flush_rewrite_rules', true);
}

register_activation_hook(__FILE__, 'forvoyez_activate');

/**
 * Deactivation hook
 *
 * This function is run when the plugin is deactivated.
 *
 * @since 1.0.0
 */
function forvoyez_deactivate() {
    // Deactivation logic here
    delete_option('forvoyez_plugin_activated');
    delete_option('forvoyez_flush_rewrite_rules');
    // Optionally, you might want to keep the version number
    // delete_option('forvoyez_plugin_version');
}
register_deactivation_hook(__FILE__, 'forvoyez_deactivate');