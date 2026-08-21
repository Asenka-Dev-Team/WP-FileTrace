<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ADT_DB {
    public const DB_VERSION = '0.1.0';

    public static function init(): void {
        add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ), 20 );
    }

    public static function downloads_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'adt_downloads';
    }

    public static function events_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'adt_download_events';
    }

    public static function install(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $downloads       = self::downloads_table();
        $events          = self::events_table();

        $sql_downloads = "CREATE TABLE {$downloads} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            public_key varchar(32) NOT NULL,
            attachment_id bigint(20) unsigned NULL,
            file_url text NOT NULL,
            destination_hash char(64) NOT NULL,
            title varchar(255) NOT NULL DEFAULT '',
            total_downloads bigint(20) unsigned NOT NULL DEFAULT 0,
            shortcode_downloads bigint(20) unsigned NOT NULL DEFAULT 0,
            external_downloads bigint(20) unsigned NOT NULL DEFAULT 0,
            last_downloaded_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY public_key (public_key),
            UNIQUE KEY destination_hash (destination_hash),
            KEY attachment_id (attachment_id),
            KEY last_downloaded_at (last_downloaded_at)
        ) {$charset_collate};";

        $sql_events = "CREATE TABLE {$events} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            download_id bigint(20) unsigned NOT NULL,
            source varchar(20) NOT NULL DEFAULT 'external',
            downloaded_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY download_id (download_id),
            KEY source (source),
            KEY downloaded_at (downloaded_at)
        ) {$charset_collate};";

        dbDelta( $sql_downloads );
        dbDelta( $sql_events );

        update_option( 'adt_db_version', self::DB_VERSION );
    }

    public static function maybe_upgrade(): void {
        if ( get_option( 'adt_db_version' ) !== self::DB_VERSION ) {
            self::install();
        }
    }
}
