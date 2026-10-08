<?php
/**
 * Uninstall Auto Alt Text for Images.
 *
 * WordPress runs this file when the plugin is deleted from wp-admin. It removes
 * everything the plugin stored, on every site of a network: its options (API
 * key, context, language, auto-analyze toggle, version flags), its transients,
 * its `_forvoyez_*` post meta and its cron events. The alt texts, titles and
 * captions written into the media library stay: they are site content.
 *
 * @package ForVoyez
 */

// If uninstall.php is not called by WordPress, die
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die();
}

if ( !function_exists( 'forvoyez_uninstall_site' ) ) {
	/**
	 * Remove the plugin data of the current site.
	 *
	 * @return void
	 */
	function forvoyez_uninstall_site() {
		global $wpdb;

		// Every option and transient of the plugin starts with "forvoyez_".
		// The list names the known ones; the query catches any other one.
		$option_names = array(
			'forvoyez_encrypted_api_key',
			'forvoyez_api_key', // Plain-text key of the first 2024 builds.
			'forvoyez_context',
			'forvoyez_language',
			'forvoyez_auto_analyze_enabled',
			'forvoyez_plugin_version',
			'forvoyez_plugin_activated',
			'forvoyez_flush_rewrite_rules',
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall: no API lists options by prefix.
		$found_options = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( 'forvoyez_' ) . '%',
				$wpdb->esc_like( '_transient_forvoyez_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_forvoyez_' ) . '%',
			),
		);
		foreach ( array_unique( array_merge( $option_names, $found_options ) ) as $option_name ) {
			delete_option( $option_name );
		}

		// Transients held by a persistent object cache never reach the options table.
		delete_transient( 'forvoyez_api_check' );
		delete_transient( 'forvoyez_bulk_analyze_images' );

		// Post meta added by the plugin (_forvoyez_analyzed, ...).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall: no API lists meta keys by prefix.
		$meta_keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
				$wpdb->esc_like( '_forvoyez_' ) . '%',
			),
		);
		foreach ( array_unique( array_merge( array( '_forvoyez_analyzed' ), $meta_keys ) ) as $meta_key ) {
			delete_post_meta_by_key( $meta_key );
		}

		// Cron events, whatever their arguments.
		wp_unschedule_hook( 'forvoyez_analyze_single_image' );
		wp_unschedule_hook( 'forvoyez_daily_cleanup' );

		// Image lists kept in a persistent object cache.
		foreach ( array( 'forvoyez_image_ids_all', 'forvoyez_image_ids_missing_alt', 'forvoyez_image_ids_missing_all', 'forvoyez_incomplete_images_count' ) as $cache_key ) {
			wp_cache_delete( $cache_key );
		}
	}

	/**
	 * Remove network options and site transients named "forvoyez_*".
	 *
	 * The plugin does not create any today; this keeps a network clean if a
	 * version ever did.
	 *
	 * @return void
	 */
	function forvoyez_uninstall_network() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall: no API lists network options by prefix.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT site_id, meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s",
				$wpdb->esc_like( 'forvoyez_' ) . '%',
				$wpdb->esc_like( '_site_transient_forvoyez_' ) . '%',
				$wpdb->esc_like( '_site_transient_timeout_forvoyez_' ) . '%',
			),
		);
		foreach ( $rows as $row ) {
			delete_network_option( (int) $row->site_id, $row->meta_key );
		}
	}
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $forvoyez_site_id ) {
		switch_to_blog( $forvoyez_site_id );
		forvoyez_uninstall_site();
		restore_current_blog();
	}
	forvoyez_uninstall_network();
} else {
	forvoyez_uninstall_site();
}
