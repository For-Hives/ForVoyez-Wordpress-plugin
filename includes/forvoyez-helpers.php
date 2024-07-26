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

    $query = $wpdb->prepare("
        SELECT COUNT(DISTINCT p.ID) 
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_alt ON p.ID = pm_alt.post_id AND pm_alt.meta_key = '_wp_attachment_image_alt'
        WHERE p.post_type = 'attachment' 
        AND p.post_mime_type LIKE %s
        AND (
            p.post_title = '' 
            OR p.post_title = SUBSTRING_INDEX(p.guid, '/', -1)
            OR p.post_title LIKE %s
            OR pm_alt.meta_value IS NULL 
            OR pm_alt.meta_value = ''
            OR p.post_excerpt = ''
        )
    ", 'image/%', '%-scaled');

    $incomplete_count = intval($wpdb->get_var($query));

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
 * Sanitize and validate the ForVoyez API key (JWT).
 *
 * This function sanitizes the input and performs basic validation on the JWT.
 *
 * @since 1.0.0
 * @param string $jwt The JWT to sanitize and validate.
 * @return string|WP_Error The sanitized JWT or WP_Error if validation fails.
 */
function forvoyez_sanitize_api_key($jwt) {
    // Remove any whitespace
    $sanitized_jwt = trim($jwt);

    // Basic JWT format validation (header.payload.signature)
    if (!preg_match('/^[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+$/', $sanitized_jwt)) {
        return new WP_Error('invalid_api_key', __('Invalid API key format. Please enter a valid ForVoyez JWT.', 'forvoyez-auto-alt-text-for-images'));
    }

    return $sanitized_jwt;
}

/**
 * Verify the ForVoyez JWT.
 *
 * This function verifies the JWT signature and checks its claims.
 * Note: This is a basic implementation and might need to be adjusted based on your exact requirements.
 *
 * @since 1.0.0
 * @param string $jwt The JWT to verify.
 * @return bool|WP_Error True if the JWT is valid, WP_Error otherwise.
 */
function forvoyez_verify_jwt($jwt) {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return new WP_Error('invalid_jwt', __('Invalid JWT format', 'forvoyez-auto-alt-text-for-images'));
    }

    list($header, $payload, $signature) = $parts;

    // Decode the payload
    $payload = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

    if (!$payload) {
        return new WP_Error('invalid_payload', __('Invalid JWT payload', 'forvoyez-auto-alt-text-for-images'));
    }

    // Check the issuer
    if (!isset($payload['iss']) || $payload['iss'] !== 'ForVoyez') {
        return new WP_Error('invalid_issuer', __('Invalid JWT issuer', 'forvoyez-auto-alt-text-for-images'));
    }

    // Check the audience
    if (!isset($payload['aud']) || $payload['aud'] !== 'ForVoyez') {
        return new WP_Error('invalid_audience', __('Invalid JWT audience', 'forvoyez-auto-alt-text-for-images'));
    }

    // Check the expiration time
    if (!isset($payload['exp']) || $payload['exp'] < time()) {
        return new WP_Error('expired_token', __('JWT has expired', 'forvoyez-auto-alt-text-for-images'));
    }

    return true;
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