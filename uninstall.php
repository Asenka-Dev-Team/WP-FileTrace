<?php
/**
 * WP FileTrace uninstall handler.
 *
 * Permanently removes all WP FileTrace database tables and plugin options.
 * Also cleans up legacy Asenka Download Tracker tables/options from the
 * pre-v0.1.2 namespace, if they still exist.
 *
 * WARNING: This cannot be undone.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Remove WP FileTrace data for the currently active blog/site.
 */
function wft_uninstall_site_data(): void {
    global $wpdb;

    $tables = array(
        $wpdb->prefix . 'wft_download_events',
        $wpdb->prefix . 'wft_downloads',

        // Legacy pre-v0.1.2 Asenka Download Tracker tables.
        $wpdb->prefix . 'adt_download_events',
        $wpdb->prefix . 'adt_downloads',
    );

    foreach ( $tables as $table ) {
        // Table names are constructed only from WordPress's trusted table prefix
        // plus fixed plugin-owned suffixes.
        $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    delete_option( 'wft_db_version' );
    delete_option( 'adt_db_version' );
}

if ( is_multisite() ) {
    $site_ids = get_sites(
        array(
            'fields' => 'ids',
            'number' => 0,
        )
    );

    foreach ( $site_ids as $site_id ) {
        switch_to_blog( (int) $site_id );
        wft_uninstall_site_data();
        restore_current_blog();
    }

    // Defensive cleanup in case a future/network-level version ever stores these.
    delete_site_option( 'wft_db_version' );
    delete_site_option( 'adt_db_version' );
} else {
    wft_uninstall_site_data();
}

// Rebuild rewrite rules without WP FileTrace's /wft-download/ route.
if ( function_exists( 'flush_rewrite_rules' ) ) {
    flush_rewrite_rules( false );
}
