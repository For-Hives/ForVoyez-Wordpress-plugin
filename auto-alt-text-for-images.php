<?php
/**
 * Auto Alt Text for Images
 *
 * This plugin automatically generates alt text and SEO metadata for images using the ForVoyez API.
 *
 * @package     ForVoyez
 * @author      ForVoyez
 * @copyright   2024 ForVoyez
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name: Auto Alt Text for Images
 * Plugin URI:  https://doc.forvoyez.com/wordpress-plugin
 * Description: Automatically generate alt text and SEO metadata for images using ForVoyez API.
 * Version:     1.1.1
 * Author:      ForVoyez
 * Author URI:  https://forvoyez.com
 * Text Domain: auto-alt-text-for-images
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if ( !defined( 'ABSPATH' ) ) {
	exit( 'Direct access to this file is not allowed.' );
}

// Define plugin constants
define( 'FORVOYEZ_VERSION', '1.1.1' );
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
	$api_manager     = new Forvoyez_API_Manager( forvoyez_get_api_key(), forvoyez_get_language(), forvoyez_get_context() );
	$image_processor = new Forvoyez_Image_Processor( $api_manager );
	$admin           = new Forvoyez_Admin( $api_manager, $settings, $image_processor );

	$settings->init();
	$api_manager->init();
	$image_processor->init();
	$admin->init();

	add_action( 'add_attachment', 'forvoyez_clear_image_cache' );
	add_action( 'edit_attachment', 'forvoyez_clear_image_cache' );
	add_action( 'delete_attachment', 'forvoyez_clear_image_cache' );
	add_action( 'admin_enqueue_scripts', 'forvoyez_enqueue_media_scripts' );
	add_filter( 'attachment_fields_to_edit', 'forvoyez_add_analyze_button', 10, 2 );
}
add_action( 'plugins_loaded', 'forvoyez_init' );

/**
 * Clear the image cache.
 *
 * This function clears the cache for image data.
 *
 * @since 1.0.0
 */
function forvoyez_clear_image_cache() {
	wp_cache_delete( 'forvoyez_image_ids_all' );
	wp_cache_delete( 'forvoyez_image_ids_missing_alt' );
	wp_cache_delete( 'forvoyez_image_ids_missing_all' );
	wp_cache_delete( 'forvoyez_incomplete_images_count' );
}

/**
 * Load the plugin text domain for translation.
 * @return void
 */
function forvoyez_load_textdomain() {
	load_plugin_textdomain(
		'auto-alt-text-for-images',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages/',
	);
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

/**
	 * Enqueue the media scripts.
	 * @param $hook
	 *
	 * @return void
	 */
	function forvoyez_enqueue_media_scripts( $hook ) {
	    if ( 'upload.php' === $hook || 'post.php' === $hook || 'post-new.php' === $hook ) {
	        wp_enqueue_script( 'forvoyez-media-script', plugin_dir_url( __FILE__ ) . 'assets/js/media-script.js', array( 'jquery' ), '1.0', true );
	        wp_localize_script(
                'forvoyez-media-script',
                'forvoyezData',
                array(
					'ajaxurl'                => admin_url( 'admin-ajax.php' ),
					'verifyAjaxRequestNonce' => wp_create_nonce( 'forvoyez_verify_ajax_request_nonce' ),
                )
            );
	    }
	}

	/**
	 * Add an "Analyze with ForVoyez" button to the media library.
	 *
	 * @param $form_fields
	 * @param $post
	 *
	 * @return mixed
	 */
	function forvoyez_add_analyze_button($form_fields, $post) {
	    if (wp_attachment_is_image($post->ID)) {
	        $form_fields['forvoyez_analyze'] = array(
	            'label' => '',
	            'input' => 'html',
	            'html' => '
	                <style>
	                    .forvoyez-analyze-button {
	                        background-color: #007cba;
	                        color: #fff;
	                        border: none;
	                        padding: 5px 10px;
	                        border-radius: 3px;
	                        cursor: pointer;
	                    }
	                </style>
	                <p>Click the button below to analyze this image with ForVoyez.</p>
	                <p><strong>Note:</strong> This will overwrite the existing alt text and caption.</p>
	                <p><strong>Warning:</strong> This action cannot be undone.</p>
	                <p><strong>if you don\'t have configured the API key, please go to the settings page and configure it.</strong></p>
	            <button type="button" class="button forvoyez-analyze-button" data-image-id="' . esc_attr($post->ID) . '">Analyze with ForVoyez</button>
	            ',
	        );
	    }
	    return $form_fields;
	}
