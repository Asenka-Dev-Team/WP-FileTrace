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
    delete_option( 'wft_ga_global_snippet' );
    delete_option( 'wft_ga_event_snippet' );
    delete_option( 'wft_ga_download_id_parameter' );
    delete_option( 'wft_ga_filename_parameter' );
    delete_option( 'wft_ga_source_parameter' );
    delete_option( 'wft_download_page_html' );
    delete_option( 'wft_download_page_css' );
    delete_option( 'wft_rewrite_version' );
    delete_option( 'wft_sdm_migration_rollback' );
    delete_option( 'wft_sdm_migration_last_run' );
    delete_option( 'wft_enable_sdm_migration' );
    delete_option( 'wft_enable_test_rows' );
    delete_option( 'adt_db_version' );

    // Temporary SDM migration rollback backups are plugin-owned post meta.
    delete_post_meta_by_key( '_wft_sdm_migration_original_content' );
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
    delete_site_option( 'wft_ga_global_snippet' );
    delete_site_option( 'wft_ga_event_snippet' );
    delete_site_option( 'wft_ga_download_id_parameter' );
    delete_site_option( 'wft_ga_filename_parameter' );
    delete_site_option( 'wft_ga_source_parameter' );
    delete_site_option( 'wft_download_page_html' );
    delete_site_option( 'wft_download_page_css' );
    delete_site_option( 'wft_rewrite_version' );
    delete_site_option( 'wft_sdm_migration_rollback' );
    delete_site_option( 'wft_sdm_migration_last_run' );
    delete_site_option( 'wft_enable_sdm_migration' );
    delete_site_option( 'wft_enable_test_rows' );
    delete_site_option( 'adt_db_version' );
} else {
    wft_uninstall_site_data();
}

// Remove GitHub updater diagnostics/cache stored at the site/network level.
delete_site_option( 'wft_github_update_status' );
delete_site_transient( 'wft_github_latest_release' );

// Rebuild rewrite rules without WP FileTrace's /wft-download/ route.
if ( function_exists( 'flush_rewrite_rules' ) ) {
    flush_rewrite_rules( false );
}
