<?php
// If uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
	die();
}

// Uninstall function
function forvoyez_uninstall()
{
	// Remove plugin options
	delete_option('forvoyez_encrypted_api_key');
	delete_option('forvoyez_plugin_version');
	delete_option('forvoyez_plugin_activated');
	delete_option('forvoyez_flush_rewrite_rules');

	// Remove custom post meta added by the plugin
	global $wpdb;
	$wpdb->query(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_forvoyez_%'",
	);

	// Clear scheduled cron jobs
	wp_clear_scheduled_hook('forvoyez_daily_cleanup');

	// Clear any transients
	delete_transient('forvoyez_api_check');
}

// Run the uninstallation function
forvoyez_uninstall();
