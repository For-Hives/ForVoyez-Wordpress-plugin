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

	// Define a unique cache key
	$cache_key = 'forvoyez_incomplete_images_count';

	// Try to get the count from cache
	$incomplete_count = wp_cache_get($cache_key);

	// If the count is not in cache, calculate it
	if (false === $incomplete_count) {
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
		$debug_info = [];

		foreach ($results as $image) {
			$is_incomplete = false;
			$reason = [];

			// Check title
			$filename = wp_basename($image->guid);
			if (
				empty($image->post_title) ||
				$image->post_title === $filename ||
				preg_match('/-scaled$/', $image->post_title)
			) {
				$is_incomplete = true;
				$reason[] = 'title';
			}

			// Check alt text
			if (empty($image->alt_text)) {
				$is_incomplete = true;
				$reason[] = 'alt';
			}

			// Check caption
			if (empty($image->post_excerpt)) {
				$is_incomplete = true;
				$reason[] = 'caption';
			}

			if ($is_incomplete) {
				++$incomplete_count;
				$debug_info[] = [
					'id' => $image->ID,
					'title' => $image->post_title,
					'alt' => $image->alt_text,
					'caption' => $image->post_excerpt,
					'guid' => $image->guid,
					'filename' => $filename,
					'reason' => implode(', ', $reason),
				];
			}
		}

		// Cache the result for future use
		wp_cache_set($cache_key, $incomplete_count, '', 3600); // Cache for 1 hour
	}

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

	if (
		!$forvoyez_settings ||
		!($forvoyez_settings instanceof Forvoyez_Settings)
	) {
		$forvoyez_settings = new Forvoyez_Settings();
	}

	$api_key = $forvoyez_settings->get_api_key();

	return apply_filters('forvoyez_api_key', $api_key);
}

/**
 * Get the ForVoyez language.
 *
 * This function retrieves the language from the plugin settings.
 * It ensures that the settings object is instantiated if it doesn't exist.
 *
 * @since 1.0.0
 * @return mixed|null
 */
function forvoyez_get_language() {
	global $forvoyez_settings;

	if (
		!$forvoyez_settings ||
		!($forvoyez_settings instanceof Forvoyez_Settings)
	) {
		$forvoyez_settings = new Forvoyez_Settings();
	}

	$language = $forvoyez_settings->get_language();

	return apply_filters('forvoyez_language', $language);
}

/**
 * Get the ForVoyez context.
 *
 * This function retrieves the context from the plugin settings.
 * It ensures that the settings object is instantiated if it doesn't exist.
 *
 * @since 1.0.0
 * @return mixed|null
 */
function forvoyez_get_context() {
	global $forvoyez_settings;

	if (
		!$forvoyez_settings ||
		!($forvoyez_settings instanceof Forvoyez_Settings)
	) {
		$forvoyez_settings = new Forvoyez_Settings();
	}

	$context = $forvoyez_settings->get_context();

	return apply_filters('forvoyez_context', $context);
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
	if (
		!preg_match(
			'/^[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+$/',
			$sanitized_jwt,
		)
	) {
		return new WP_Error(
			'invalid_api_key',
			esc_html__(
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
function forvoyez_verify_jwt($jwt) {
	$parts = explode('.', $jwt);
	if (count($parts) !== 3) {
		return new WP_Error(
			'invalid_jwt',
			esc_html__('Invalid JWT format', 'auto-alt-text-for-images'),
		);
	}

	[$header, $payload, $signature] = $parts;

	// Decode the payload
	$payload = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

	if (!$payload) {
		return new WP_Error(
			'invalid_payload',
			esc_html__('Invalid JWT payload', 'auto-alt-text-for-images'),
		);
	}

	// Check the issuer
	if (!isset($payload['iss']) || $payload['iss'] !== 'ForVoyez') {
		return new WP_Error(
			'invalid_issuer',
			esc_html__('Invalid JWT issuer', 'auto-alt-text-for-images'),
		);
	}

	// Check the audience
	if (!isset($payload['aud']) || $payload['aud'] !== 'ForVoyez') {
		return new WP_Error(
			'invalid_audience',
			esc_html__('Invalid JWT audience', 'auto-alt-text-for-images'),
		);
	}

	// Check the expiration time
	if (!isset($payload['exp']) || $payload['exp'] < time()) {
		return new WP_Error(
			'expired_token',
			esc_html__('JWT has expired', 'auto-alt-text-for-images'),
		);
	}

	return true;
}

/**
 * Message shown when no ForVoyez API key is configured.
 *
 * @since 1.1.41
 * @return string Plain-text message (escape it on output).
 */
function forvoyez_get_missing_api_key_message() {
	return __(
		'Your ForVoyez API key is not configured. Add it in the Auto Alt Text configuration tab to generate alt text.',
		'auto-alt-text-for-images',
	);
}

/**
 * Whether new uploads are analyzed automatically.
 *
 * The configuration toggle stores the string 'true' or 'false', and older
 * versions stored whatever the AJAX request sent, so the string 'false' must
 * read as disabled.
 *
 * @since 1.1.41
 * @return bool
 */
function forvoyez_is_auto_analyze_enabled() {
	return filter_var(
		get_option('forvoyez_auto_analyze_enabled', false),
		FILTER_VALIDATE_BOOLEAN,
	);
}

/**
 * URL of the plugin configuration tab.
 *
 * @since 1.1.41
 * @return string
 */
function forvoyez_get_configuration_url() {
	return admin_url(
		'admin.php?page=auto-alt-text-for-images&tab=configuration',
	);
}

/**
 * Add campaign parameters to a link to forvoyez.com or doc.forvoyez.com.
 *
 * The values are static: they name the plugin, the screen and the link,
 * never the site, the user or the API key. Any other host is returned as is.
 *
 * @since 1.1.43
 * @param string $url Link target.
 * @param string $campaign Screen showing the link (configuration, credits-widget...).
 * @param string $content Link name (signup, dashboard, api-docs, recharge...).
 * @return string Unescaped URL (escape it on output).
 */
function forvoyez_get_tracked_url($url, $campaign, $content) {
	$host = wp_parse_url($url, PHP_URL_HOST);
	if (!in_array($host, ['forvoyez.com', 'doc.forvoyez.com'], true)) {
		return $url;
	}

	return add_query_arg(
		[
			'utm_source' => 'wordpress-plugin',
			'utm_medium' => 'plugin',
			'utm_campaign' => sanitize_key($campaign),
			'utm_content' => sanitize_key($content),
		],
		$url,
	);
}

/**
 * Get ForVoyez token information including remaining credits.
 *
 * @since 1.0.0
 * @return array Token information or error data.
 */
function forvoyez_get_token_info() {
	global $forvoyez_settings;

	if (
		!$forvoyez_settings ||
		!($forvoyez_settings instanceof Forvoyez_Settings)
	) {
		$forvoyez_settings = new Forvoyez_Settings();
	}

	$api_key = $forvoyez_settings->get_api_key();

	if (empty($api_key)) {
		return [
			'success' => false,
			'error' => [
				'code' => 'missing_api_key',
				'message' => forvoyez_get_missing_api_key_message(),
			],
		];
	}

	$api_client = new Forvoyez_API_Manager($api_key, '', '');
	$token_info = $api_client->get_token_info();

	return $token_info;
}

/**
 * Translate a plural string for the admin scripts.
 *
 * The scripts pick the form by count: index 0 for no item, 1 for one item
 * and 2 for more. This matches the plural rules of every bundled language;
 * a language with more than two plural forms gets its form for 2 above 1.
 *
 * @since 1.1.45
 * @param array $nooped Plural strings registered with _n_noop().
 * @return string[] The translated forms for 0, 1 and 2 items.
 */
function forvoyez_translate_plural_for_js($nooped) {
	return [
		translate_nooped_plural($nooped, 0, 'auto-alt-text-for-images'),
		translate_nooped_plural($nooped, 1, 'auto-alt-text-for-images'),
		translate_nooped_plural($nooped, 2, 'auto-alt-text-for-images'),
	];
}

/**
 * Translations of the strings written by the admin scripts.
 *
 * Each key is the English string used in assets/js (forvoyezI18n.t() and
 * forvoyezI18n.n()), so a missing key shows the English text. The values are
 * plain text: the scripts write them with .text() or textContent, never as
 * HTML. TestForvoyezJsTranslations checks that every string of the scripts
 * is listed here.
 *
 * @since 1.1.45
 * @return array<string, string|string[]> English string => translation, or
 *                                        singular => forms for 0, 1 and 2.
 */
function forvoyez_get_js_translations() {
	$strings = [
		'Active' => __('Active', 'auto-alt-text-for-images'),
		'AJAX request failed' => __('AJAX request failed', 'auto-alt-text-for-images'),
		/* translators: %s: error status returned by the browser, e.g. "timeout" */
		'AJAX request failed: %s' => __('AJAX request failed: %s', 'auto-alt-text-for-images'),
		'Alt text' => __('Alt text', 'auto-alt-text-for-images'),
		'Alt Text Status' => __('Alt Text Status', 'auto-alt-text-for-images'),
		'An error occurred. Please try again.' => __('An error occurred. Please try again.', 'auto-alt-text-for-images'),
		/* translators: 1: number of images analyzed, 2: number of images that failed */
		'Analysis complete. Successful: %1$d, Failed: %2$d' => __('Analysis complete. Successful: %1$d, Failed: %2$d', 'auto-alt-text-for-images'),
		'Analysis complete! Metadata has been updated.' => __('Analysis complete! Metadata has been updated.', 'auto-alt-text-for-images'),
		'Analyze with ForVoyez' => __('Analyze with ForVoyez', 'auto-alt-text-for-images'),
		'Analyzing image...' => __('Analyzing image...', 'auto-alt-text-for-images'),
		'Analyzing...' => __('Analyzing...', 'auto-alt-text-for-images'),
		'API key saved successfully' => __('API key saved successfully', 'auto-alt-text-for-images'),
		'Batch processing failed' => __('Batch processing failed', 'auto-alt-text-for-images'),
		'Caption' => __('Caption', 'auto-alt-text-for-images'),
		'Caption Status' => __('Caption Status', 'auto-alt-text-for-images'),
		'Context saved successfully' => __('Context saved successfully', 'auto-alt-text-for-images'),
		'Credits:' => __('Credits:', 'auto-alt-text-for-images'),
		/* translators: %s: error message */
		'Error: %s' => __('Error: %s', 'auto-alt-text-for-images'),
		'Error: Failed to fetch image IDs' => __('Error: Failed to fetch image IDs', 'auto-alt-text-for-images'),
		'Error loading data' => __('Error loading data', 'auto-alt-text-for-images'),
		/* translators: 1: image ID, 2: error message */
		'Error processing image %1$d: %2$s' => __('Error processing image %1$d: %2$s', 'auto-alt-text-for-images'),
		'Failed to fetch the image count' => __('Failed to fetch the image count', 'auto-alt-text-for-images'),
		'Failed to save API key' => __('Failed to save API key', 'auto-alt-text-for-images'),
		/* translators: %s: error message */
		'Failed to save API key: %s' => __('Failed to save API key: %s', 'auto-alt-text-for-images'),
		'Failed to save context' => __('Failed to save context', 'auto-alt-text-for-images'),
		/* translators: %s: error message */
		'Failed to save context: %s' => __('Failed to save context: %s', 'auto-alt-text-for-images'),
		'Failed to save language' => __('Failed to save language', 'auto-alt-text-for-images'),
		/* translators: %s: error message */
		'Failed to save language: %s' => __('Failed to save language: %s', 'auto-alt-text-for-images'),
		'ForVoyez Analysis' => __('ForVoyez Analysis', 'auto-alt-text-for-images'),
		'ForVoyez Credits:' => __('ForVoyez Credits:', 'auto-alt-text-for-images'),
		'Generate optimized alt text, title, and caption with AI.' => __('Generate optimized alt text, title, and caption with AI.', 'auto-alt-text-for-images'),
		'Has alt text' => __('Has alt text', 'auto-alt-text-for-images'),
		'Has caption' => __('Has caption', 'auto-alt-text-for-images'),
		'Has title' => __('Has title', 'auto-alt-text-for-images'),
		'Language saved successfully' => __('Language saved successfully', 'auto-alt-text-for-images'),
		'Loading subscription status...' => __('Loading subscription status...', 'auto-alt-text-for-images'),
		/* translators: %s: "recharge now" link */
		'Low credits! Please %s.' => __('Low credits! Please %s.', 'auto-alt-text-for-images'),
		/* translators: %d: image ID */
		'Metadata updated successfully for image %d' => __('Metadata updated successfully for image %d', 'auto-alt-text-for-images'),
		'Missing alt text' => __('Missing alt text', 'auto-alt-text-for-images'),
		'Missing caption' => __('Missing caption', 'auto-alt-text-for-images'),
		'Missing title' => __('Missing title', 'auto-alt-text-for-images'),
		'Not set' => __('Not set', 'auto-alt-text-for-images'),
		'Please select at least one image to analyze' => __('Please select at least one image to analyze', 'auto-alt-text-for-images'),
		/* translators: 1: progress in percent, 2: number of images analyzed, 3: number of images that failed */
		'Processing: %1$d%% complete. Successful: %2$d, Failed: %3$d' => __('Processing: %1$d%% complete. Successful: %2$d, Failed: %3$d', 'auto-alt-text-for-images'),
		/* translators: Link text, inside "Low credits! Please %s." */
		'recharge now' => __('recharge now', 'auto-alt-text-for-images'),
		'Title' => __('Title', 'auto-alt-text-for-images'),
		'Title Status' => __('Title Status', 'auto-alt-text-for-images'),
		'Unable to load credit information. Please refresh the page or check your API key configuration.' => __('Unable to load credit information. Please refresh the page or check your API key configuration.', 'auto-alt-text-for-images'),
		'Unknown error' => __('Unknown error', 'auto-alt-text-for-images'),
		'Unknown error occurred' => __('Unknown error occurred', 'auto-alt-text-for-images'),
		/* translators: 1: number of credits left, 2: number of credits the analysis needs */
		'Warning: You only have %1$d credits, but this operation requires %2$d credits.' => __('Warning: You only have %1$d credits, but this operation requires %2$d credits.', 'auto-alt-text-for-images'),
	];

	$plurals = [
		/* translators: %d: number of images */
		_n_noop('Are you sure you want to analyze %d image?', 'Are you sure you want to analyze all %d images?', 'auto-alt-text-for-images'),
		/* translators: %d: number of images */
		_n_noop('Are you sure you want to analyze %d image with missing alt text?', 'Are you sure you want to analyze %d images with missing alt text?', 'auto-alt-text-for-images'),
		/* translators: %d: number of images */
		_n_noop('Are you sure you want to analyze %d image with missing alt text, title, or caption?', 'Are you sure you want to analyze %d images with missing alt text, title, or caption?', 'auto-alt-text-for-images'),
		/* translators: %d: number of images */
		_n_noop('Are you sure you want to analyze %d selected image?', 'Are you sure you want to analyze %d selected images?', 'auto-alt-text-for-images'),
		/* translators: %d: number of credits */
		_n_noop('Cost: %d ForVoyez credit', 'Cost: %d ForVoyez credits', 'auto-alt-text-for-images'),
		/* translators: 1: number of images shown, 2: number of images matching the filters */
		_n_noop('Displaying %1$d of %2$d image', 'Displaying %1$d of %2$d images', 'auto-alt-text-for-images'),
		/* translators: %d: number of images */
		_n_noop('Starting analysis of %d image...', 'Starting analysis of %d images...', 'auto-alt-text-for-images'),
		/* translators: %d: number of credits */
		_n_noop('You currently have %d credit remaining.', 'You currently have %d credits remaining.', 'auto-alt-text-for-images'),
	];

	foreach ($plurals as $nooped) {
		$strings[ $nooped['singular'] ] = forvoyez_translate_plural_for_js($nooped);
	}

	return $strings;
}

/**
 * Register the script that translates the admin scripts.
 *
 * admin-script.js and media-script.js depend on it. Registered once, so the
 * translations are printed once when both scripts load on the same page.
 *
 * @since 1.1.45
 * @return void
 */
function forvoyez_register_i18n_script() {
	if (wp_script_is('forvoyez-i18n', 'registered')) {
		return;
	}

	wp_register_script(
		'forvoyez-i18n',
		FORVOYEZ_PLUGIN_URL . 'assets/js/i18n.js',
		[],
		FORVOYEZ_VERSION,
		true,
	);
	wp_localize_script('forvoyez-i18n', 'forvoyezL10n', forvoyez_get_js_translations());
}
