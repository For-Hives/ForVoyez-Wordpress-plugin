<?php
/**
 * Helper functions for the ForVoyez plugin.
 *
 * This file contains utility functions used throughout the ForVoyez plugin.
 *
 * @package ForVoyez
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit('Direct access to this file is not allowed.');
}

/**
 * Count the number of images with incomplete metadata.
 *
 * This function counts images that are missing either a title,
 * alt text, or caption. It uses WordPress' built-in functions
 * to query the database efficiently.
 *
 * @since 1.0.0
 * @return int The number of images with incomplete metadata.
 */
function forvoyez_count_incomplete_images() {
    global $wpdb;

    $query = "
        SELECT COUNT(DISTINCT p.ID) 
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_alt ON p.ID = pm_alt.post_id AND pm_alt.meta_key = '_wp_attachment_image_alt'
        WHERE p.post_type = 'attachment' 
        AND p.post_mime_type LIKE 'image/%'
        AND (
            p.post_title = '' 
            OR p.post_title = SUBSTRING_INDEX(p.guid, '/', -1)
            OR pm_alt.meta_value IS NULL OR pm_alt.meta_value = ''
            OR p.post_excerpt = ''
        )
    ";

    $incomplete_count = intval($wpdb->get_var($query));

    /**
     * Filters the count of incomplete images.
     *
     * @since 1.0.0
     * @param int $incomplete_count The number of incomplete images.
     */
    return apply_filters('forvoyez_incomplete_images_count', $incomplete_count);
}

/**
 * Get the ForVoyez API key.
 *
 * This function retrieves the API key from the plugin settings.
 * It ensures that the settings object is instantiated if it doesn't exist.
 *
 * @since 1.0.0
 * @return string The ForVoyez API key.
 */
function forvoyez_get_api_key() {
    global $forvoyez_settings;

    if (!$forvoyez_settings || !($forvoyez_settings instanceof Forvoyez_Settings)) {
        $forvoyez_settings = new Forvoyez_Settings();
    }

    $api_key = $forvoyez_settings->get_api_key();

    /**
     * Filters the retrieved API key.
     *
     * @since 1.0.0
     * @param string $api_key The ForVoyez API key.
     */
    return apply_filters('forvoyez_api_key', $api_key);
}

/**
 * Sanitize and validate the ForVoyez API key.
 *
 * This function sanitizes the input and performs basic validation on the API key.
 *
 * @since 1.0.0
 * @param string $api_key The API key to sanitize and validate.
 * @return string|WP_Error The sanitized API key or WP_Error if validation fails.
 */
function forvoyez_sanitize_api_key($api_key) {
    $sanitized_key = sanitize_text_field($api_key);

    // Basic validation: ensure the key is not empty and meets a minimum length requirement
    if (empty($sanitized_key) || strlen($sanitized_key) < 32) {
        return new WP_Error('invalid_api_key', __('Invalid API key. Please enter a valid ForVoyez API key.', 'forvoyez-auto-alt-text-for-images'));
    }

    return $sanitized_key;
}

/**
 * Log messages for debugging purposes.
 *
 * This function provides a standardized way to log messages for debugging.
 * It only logs messages if WP_DEBUG is true.
 *
 * @since 1.0.0
 * @param string $message The message to log.
 * @param string $level The log level (e.g., 'info', 'warning', 'error').
 */
function forvoyez_debug_log($message, $level = 'info') {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf('[ForVoyez %s] %s', strtoupper($level), $message));
    }
}