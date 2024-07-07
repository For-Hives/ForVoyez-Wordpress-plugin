<?php
/**
 * Plugin Name: Auto Alt Text for Images
 * Description: Automatically generate alt text and SEO metadata for images using ForVoyez API.
 * Version: 1.0.0
 * Author: ForVoyez
 * Author URI: https://forvoyez.com
 * Text Domain: forvoyez-auto-alt-text-for-images
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FORVOYEZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FORVOYEZ_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once FORVOYEZ_PLUGIN_DIR . 'includes/class-forvoyez-admin.php';
require_once FORVOYEZ_PLUGIN_DIR . 'includes/class-forvoyez-api.php';
require_once FORVOYEZ_PLUGIN_DIR . 'includes/class-forvoyez-image-processor.php';
require_once FORVOYEZ_PLUGIN_DIR . 'includes/class-forvoyez-settings.php';

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

add_action('plugins_loaded', 'forvoyez_init');