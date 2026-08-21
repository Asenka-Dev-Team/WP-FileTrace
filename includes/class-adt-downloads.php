<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ADT_Downloads {
    public static function init(): void {}

    public static function normalize_url( string $url ): string {
        $url = trim( $url );
        return esc_url_raw( $url, array( 'http', 'https' ) );
    }

    public static function destination_hash( int $attachment_id, string $url ): string {
        return hash( 'sha256', $attachment_id > 0 ? 'attachment:' . $attachment_id : 'url:' . strtolower( $url ) );
    }

    public static function get_or_create( int $attachment_id = 0, string $url = '', string $title = '' ) {
        global $wpdb;

        if ( $attachment_id > 0 ) {
            $attachment_url = wp_get_attachment_url( $attachment_id );
            if ( ! $attachment_url ) {
                return new WP_Error( 'adt_invalid_attachment', __( 'The selected media item does not have a valid URL.', 'asenka-download-tracker' ) );
            }
            $url = $attachment_url;
            if ( '' === $title ) {
                $title = get_the_title( $attachment_id );
            }
        }

        $url = self::normalize_url( $url );
        if ( '' === $url ) {
            return new WP_Error( 'adt_invalid_url', __( 'Please provide a valid HTTP or HTTPS file URL.', 'asenka-download-tracker' ) );
        }

        if ( '' === $title ) {
            $path  = wp_parse_url( $url, PHP_URL_PATH );
            $title = $path ? wp_basename( $path ) : $url;
        }

        $hash  = self::destination_hash( $attachment_id, $url );
        $table = ADT_DB::downloads_table();

        $existing = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE destination_hash = %s LIMIT 1", $hash )
        );

        if ( $existing ) {
            return $existing;
        }

        $now        = current_time( 'mysql' );
        $public_key = self::generate_public_key();

        $inserted = $wpdb->insert(
            $table,
            array(
                'public_key'       => $public_key,
                'attachment_id'    => $attachment_id > 0 ? $attachment_id : null,
                'file_url'         => $url,
                'destination_hash' => $hash,
                'title'            => sanitize_text_field( $title ),
                'created_at'       => $now,
                'updated_at'       => $now,
            ),
            array( '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( false === $inserted ) {
            return new WP_Error( 'adt_db_insert_failed', __( 'The download tracker could not be created.', 'asenka-download-tracker' ) );
        }

        return self::get_by_id( (int) $wpdb->insert_id );
    }

    private static function generate_public_key(): string {
        global $wpdb;
        $table = ADT_DB::downloads_table();

        do {
            $key    = wp_generate_password( 20, false, false );
            $exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE public_key = %s", $key ) );
        } while ( $exists > 0 );

        return $key;
    }

    public static function get_by_id( int $id ) {
        global $wpdb;
        $table = ADT_DB::downloads_table();
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ) );
    }

    public static function get_by_key( string $key ) {
        global $wpdb;
        $table = ADT_DB::downloads_table();
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE public_key = %s LIMIT 1", $key ) );
    }

    public static function get_all(): array {
        global $wpdb;
        $table = ADT_DB::downloads_table();
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" ) ?: array();
    }

    public static function update_tracker( int $id, string $title, string $url, int $attachment_id = 0 ): bool {
        global $wpdb;

        $tracker = self::get_by_id( $id );
        if ( ! $tracker ) {
            return false;
        }

        if ( $attachment_id > 0 && '' === trim( $url ) ) {
            $attachment_url = wp_get_attachment_url( $attachment_id );
            if ( $attachment_url ) {
                $url = $attachment_url;
            }
        }

        $url = self::normalize_url( $url );
        if ( '' === $url ) {
            return false;
        }

        $hash = self::destination_hash( $attachment_id, $url );

        $updated = $wpdb->update(
            ADT_DB::downloads_table(),
            array(
                'title'            => sanitize_text_field( $title ),
                'attachment_id'    => $attachment_id > 0 ? $attachment_id : null,
                'file_url'         => $url,
                'destination_hash' => $hash,
                'updated_at'       => current_time( 'mysql' ),
            ),
            array( 'id' => $id ),
            array( '%s', '%d', '%s', '%s', '%s' ),
            array( '%d' )
        );

        return false !== $updated;
    }

    public static function delete_tracker( int $id ): bool {
        global $wpdb;

        if ( $id <= 0 || ! self::get_by_id( $id ) ) {
            return false;
        }

        $downloads = ADT_DB::downloads_table();
        $events    = ADT_DB::events_table();

        // Keep the tracker row and its event history in sync if either delete fails.
        $wpdb->query( 'START TRANSACTION' );

        $events_deleted = $wpdb->delete( $events, array( 'download_id' => $id ), array( '%d' ) );
        $tracker_deleted = $wpdb->delete( $downloads, array( 'id' => $id ), array( '%d' ) );

        if ( false === $events_deleted || 1 !== $tracker_deleted ) {
            $wpdb->query( 'ROLLBACK' );
            return false;
        }

        $wpdb->query( 'COMMIT' );

        /**
         * Fires after a tracker and all of its stored download events are deleted.
         *
         * @param int $download_id Deleted tracker ID.
         */
        do_action( 'adt_download_tracker_deleted', $id );

        return true;
    }

    public static function build_tracked_url( $tracker, string $source = 'external' ): string {
        $source = self::sanitize_source( $source );

        if ( '' === (string) get_option( 'permalink_structure' ) ) {
            return add_query_arg(
                array(
                    'adt_download_key' => $tracker->public_key,
                    'via'              => $source,
                ),
                home_url( '/' )
            );
        }

        return add_query_arg( 'via', $source, home_url( '/adt-download/' . rawurlencode( $tracker->public_key ) . '/' ) );
    }

    public static function shortcode_for( $tracker, string $button_text = '' ): string {
        $atts = '';

        if ( ! empty( $tracker->attachment_id ) ) {
            $atts = 'media="' . (int) $tracker->attachment_id . '"';
        } else {
            $atts = 'url="' . esc_url_raw( $tracker->file_url ) . '"';
        }

        if ( '' !== trim( $button_text ) ) {
            $atts .= ' text="' . esc_attr( $button_text ) . '"';
        }

        return '[adt ' . $atts . ']';
    }

    public static function track( $tracker, string $source ): void {
        global $wpdb;

        $source       = self::sanitize_source( $source );
        $download_col = 'shortcode' === $source ? 'shortcode_downloads' : 'external_downloads';
        $now          = current_time( 'mysql' );
        $downloads    = ADT_DB::downloads_table();
        $events       = ADT_DB::events_table();

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$downloads}
                 SET total_downloads = total_downloads + 1,
                     {$download_col} = {$download_col} + 1,
                     last_downloaded_at = %s,
                     updated_at = %s
                 WHERE id = %d",
                $now,
                $now,
                (int) $tracker->id
            )
        );

        $wpdb->insert(
            $events,
            array(
                'download_id'   => (int) $tracker->id,
                'source'        => $source,
                'downloaded_at' => $now,
            ),
            array( '%d', '%s', '%s' )
        );

        /**
         * Fires after ADT records a tracked download.
         *
         * Intended as the integration point for GA4/Measurement Protocol or
         * other analytics transports in a future release.
         *
         * @param int    $download_id Tracker ID.
         * @param string $file_url    Final destination URL.
         * @param string $source      shortcode|external.
         * @param object $tracker     Tracker database row.
         */
        do_action( 'adt_download_tracked', (int) $tracker->id, $tracker->file_url, $source, $tracker );
    }

    public static function sanitize_source( string $source ): string {
        return 'shortcode' === $source ? 'shortcode' : 'external';
    }
}
