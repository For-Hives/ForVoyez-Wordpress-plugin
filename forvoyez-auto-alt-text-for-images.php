<?php
/**
 * ForVoyez Auto Alt Text for Images
 *
 * This plugin automatically generates alt text and SEO metadata for images using the ForVoyez API.
 *
 * @package     ForVoyez
 * @author      ForVoyez
 * @copyright   2023 ForVoyez
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name: Auto Alt Text for Images
 * Plugin URI:  https://forvoyez.com/plugins/auto-alt-text
 * Description: Automatically generate alt text and SEO metadata for images using ForVoyez API.
 * Version:     1.0.0
 * Author:      ForVoyez
 * Author URI:  https://forvoyez.com
 * Text Domain: forvoyez-auto-alt-text-for-images
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if ( !defined( 'ABSPATH' ) ) {
	exit( 'Direct access to this file is not allowed.' );
}

// Define plugin constants
define( 'FORVOYEZ_VERSION', '1.0.0' );
define( 'FORVOYEZ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FORVOYEZ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FORVOYEZ_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Include required files
$required_files = array(
	'includes/forvoyez-helpers.php',
	'includes/class-forvoyez-admin.php',
	'includes/class-forvoyez-api-manager.php',
	'includes/class-forvoyez-image-processor.php',
	'includes/class-forvoyez-settings.php',
	'includes/class-forvoyez-image-renderer.php',
);

foreach ( $required_files as $file ) {
	require_once FORVOYEZ_PLUGIN_DIR . $file;
}

/**
 * Initialize the plugin components.
 *
 * This function is hooked into the 'plugins_loaded' action to ensure
 * that it's only executed after all plugins have been loaded.
 *
 * @since 1.0.0
 */
function forvoyez_init() {
	$settings        = new Forvoyez_Settings();
	$api_manager     = new Forvoyez_API_Manager( forvoyez_get_api_key() );
	$image_processor = new Forvoyez_Image_Processor( $api_manager );
	$admin           = new Forvoyez_Admin( $api_manager, $settings, $image_processor );

	$settings->init();
	$api_manager->init();
	$image_processor->init();
	$admin->init();
}
add_action( 'plugins_loaded', 'forvoyez_init' );

/**
 * Load the plugin text domain for translation.
 * @return void
 */
function forvoyez_load_textdomain() {
	load_plugin_textdomain( 'forvoyez-auto-alt-text-for-images', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'forvoyez_load_textdomain' );

/**
 * Activate the plugin.
 *
 * Sets up necessary options when the plugin is activated.
 *
 * @since 1.0.0
 */
function forvoyez_activate() {
	update_option( 'forvoyez_plugin_activated', true );
	update_option( 'forvoyez_plugin_version', FORVOYEZ_VERSION );
	update_option( 'forvoyez_flush_rewrite_rules', true );
}
register_activation_hook( __FILE__, 'forvoyez_activate' );

/**
 * Deactivate the plugin.
 *
 * Cleans up options when the plugin is deactivated.
 *
 * @since 1.0.0
 */
function forvoyez_deactivate() {
	delete_option( 'forvoyez_plugin_activated' );
	delete_option( 'forvoyez_flush_rewrite_rules' );
	// Optionally, keep the version number for future reference
	// delete_option('forvoyez_plugin_version');
}
register_deactivation_hook( __FILE__, 'forvoyez_deactivate' );

/**
 * Flush rewrite rules if necessary.
 *
 * This function checks if the rewrite rules need to be flushed
 * and does so if required. It's hooked to the 'init' action.
 *
 * @since 1.0.0
 */
function forvoyez_maybe_flush_rewrite_rules() {
	if ( get_option( 'forvoyez_flush_rewrite_rules' ) ) {
		flush_rewrite_rules();
		delete_option( 'forvoyez_flush_rewrite_rules' );
	}
}
add_action( 'init', 'forvoyez_maybe_flush_rewrite_rules' );
