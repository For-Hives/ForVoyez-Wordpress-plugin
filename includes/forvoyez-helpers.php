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
if ( !defined( 'ABSPATH' ) ) {
	exit( 'Direct access to this file is not allowed.' );
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

	// Define a unique cache key
	$cache_key = 'forvoyez_incomplete_images_count';

	// Try to get the count from cache
	$incomplete_count = wp_cache_get( $cache_key );

	// If the count is not in cache, calculate it
	if ( false === $incomplete_count ) {
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_excerpt, p.guid, pm_alt.meta_value as alt_text
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm_alt ON p.ID = pm_alt.post_id AND pm_alt.meta_key = %s
                WHERE p.post_type = %s 
                AND p.post_mime_type LIKE %s",
				'_wp_attachment_image_alt',
				'attachment',
				'image/%',
			),
		);

		$incomplete_count = 0;
		$debug_info       = array();

		foreach ( $results as $image ) {
			$is_incomplete = false;
			$reason        = array();

			// Check title
			$filename = wp_basename( $image->guid );
			if (
				empty( $image->post_title ) ||
				$image->post_title === $filename ||
				preg_match( '/-scaled$/', $image->post_title )
			) {
				$is_incomplete = true;
				$reason[]      = 'title';
			}

			// Check alt text
			if ( empty( $image->alt_text ) ) {
				$is_incomplete = true;
				$reason[]      = 'alt';
			}

			// Check caption
			if ( empty( $image->post_excerpt ) ) {
				$is_incomplete = true;
				$reason[]      = 'caption';
			}

			if ( $is_incomplete ) {
				++$incomplete_count;
				$debug_info[] = array(
					'id'       => $image->ID,
					'title'    => $image->post_title,
					'alt'      => $image->alt_text,
					'caption'  => $image->post_excerpt,
					'guid'     => $image->guid,
					'filename' => $filename,
					'reason'   => implode( ', ', $reason ),
				);
			}
		}

		// Cache the result for future use
		wp_cache_set( $cache_key, $incomplete_count, '', 3600 ); // Cache for 1 hour
	}

	return apply_filters( 'forvoyez_incomplete_images_count', $incomplete_count );
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

	if (
		!$forvoyez_settings ||
		!( $forvoyez_settings instanceof Forvoyez_Settings )
	) {
		$forvoyez_settings = new Forvoyez_Settings();
	}

	$api_key = $forvoyez_settings->get_api_key();

	return apply_filters( 'forvoyez_api_key', $api_key );
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
function forvoyez_sanitize_api_key( $jwt ) {
	// Remove any whitespace
	$sanitized_jwt = trim( $jwt );

	// Basic JWT format validation (header.payload.signature)
	if (
		!preg_match(
			'/^[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+$/',
			$sanitized_jwt,
		)
	) {
		return new WP_Error(
			'invalid_api_key',
			__(
				'Invalid API key format. Please enter a valid ForVoyez JWT.',
				'auto-alt-text-for-images',
			),
		);
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
function forvoyez_verify_jwt( $jwt ) {
	$parts = explode( '.', $jwt );
	if ( count( $parts ) !== 3 ) {
		return new WP_Error(
			'invalid_jwt',
			__( 'Invalid JWT format', 'auto-alt-text-for-images' ),
		);
	}

	[$header, $payload, $signature] = $parts;

	// Decode the payload
	$payload = json_decode( base64_decode( strtr( $payload, '-_', '+/' ) ), true );

	if ( !$payload ) {
		return new WP_Error(
			'invalid_payload',
			__( 'Invalid JWT payload', 'auto-alt-text-for-images' ),
		);
	}

	// Check the issuer
	if ( !isset( $payload['iss'] ) || $payload['iss'] !== 'ForVoyez' ) {
		return new WP_Error(
			'invalid_issuer',
			__( 'Invalid JWT issuer', 'auto-alt-text-for-images' ),
		);
	}

	// Check the audience
	if ( !isset( $payload['aud'] ) || $payload['aud'] !== 'ForVoyez' ) {
		return new WP_Error(
			'invalid_audience',
			__( 'Invalid JWT audience', 'auto-alt-text-for-images' ),
		);
	}

	// Check the expiration time
	if ( !isset( $payload['exp'] ) || $payload['exp'] < time() ) {
		return new WP_Error(
			'expired_token',
			__( 'JWT has expired', 'auto-alt-text-for-images' ),
		);
	}

	return true;
}
