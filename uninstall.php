<?php
/**
 * Asenka Download Tracker v0.1.1 uninstall handler.
 *
 * Permanently removes all plugin tracking data when the plugin is deleted
 * through WordPress.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$downloads_table = $wpdb->prefix . 'adt_downloads';
$events_table    = $wpdb->prefix . 'adt_download_events';

// Remove event history first, then the tracked-file records.
$wpdb->query( "DROP TABLE IF EXISTS {$events_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$downloads_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Remove plugin-owned options.
delete_option( 'adt_db_version' );

// Ensure the /adt-download/... rewrite endpoint is removed from cached rules.
flush_rewrite_rules();
