<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WFT_Downloads {
    public static function init(): void {}

    public static function normalize_url( string $url ): string {
        $url = trim( $url );
        return esc_url_raw( $url, array( 'http', 'https' ) );
    }

    public static function destination_hash( int $attachment_id, string $url ): string {
        return hash( 'sha256', $attachment_id > 0 ? 'attachment:' . $attachment_id : 'url:' . strtolower( $url ) );
    }

    public static function get_or_create( int $attachment_id = 0, string $url = '', string $title = '', string $button_text = '' ) {
        global $wpdb;

        if ( $attachment_id > 0 ) {
            $attachment_url = wp_get_attachment_url( $attachment_id );
            if ( ! $attachment_url ) {
                return new WP_Error( 'wft_invalid_attachment', __( 'The selected media item does not have a valid URL.', 'wp-filetrace' ) );
            }
            $url = $attachment_url;
            if ( '' === $title ) {
                $title = get_the_title( $attachment_id );
            }
        }

        $url = self::normalize_url( $url );
        if ( '' === $url ) {
            return new WP_Error( 'wft_invalid_url', __( 'Please provide a valid HTTP or HTTPS file URL.', 'wp-filetrace' ) );
        }

        if ( '' === $title ) {
            $path  = wp_parse_url( $url, PHP_URL_PATH );
            $title = $path ? wp_basename( $path ) : $url;
        }

        $button_text = sanitize_text_field( trim( $button_text ) );

        $hash  = self::destination_hash( $attachment_id, $url );
        $table = WFT_DB::downloads_table();

        $existing = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE destination_hash = %s LIMIT 1", $hash )
        );

        if ( $existing ) {
            if ( '' !== $button_text && ( ! isset( $existing->button_text ) || $button_text !== (string) $existing->button_text ) ) {
                $wpdb->update(
                    $table,
                    array(
                        'button_text' => $button_text,
                        'updated_at'  => current_time( 'mysql' ),
                    ),
                    array( 'id' => (int) $existing->id ),
                    array( '%s', '%s' ),
                    array( '%d' )
                );
                $existing->button_text = $button_text;
            }
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
                'button_text'      => '' !== $button_text ? $button_text : __( 'Download', 'wp-filetrace' ),
                'created_at'       => $now,
                'updated_at'       => $now,
            ),
            array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( false === $inserted ) {
            return new WP_Error( 'wft_db_insert_failed', __( 'The tracked file could not be created.', 'wp-filetrace' ) );
        }

        return self::get_by_id( (int) $wpdb->insert_id );
    }

    private static function generate_public_key(): string {
        global $wpdb;
        $table = WFT_DB::downloads_table();

        do {
            $key    = wp_generate_password( 20, false, false );
            $exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE public_key = %s", $key ) );
        } while ( $exists > 0 );

        return $key;
    }

    public static function get_by_id( int $id ) {
        global $wpdb;
        $table = WFT_DB::downloads_table();
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ) );
    }

    public static function get_by_key( string $key ) {
        global $wpdb;
        $table = WFT_DB::downloads_table();
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE public_key = %s LIMIT 1", $key ) );
    }

    public static function get_all(): array {
        global $wpdb;
        $table = WFT_DB::downloads_table();
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC" ) ?: array();
    }

    public static function get_count(): int {
        global $wpdb;
        $table = WFT_DB::downloads_table();
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    }

    public static function get_created_desc_page_for_id( int $id, int $per_page = 20 ): int {
        global $wpdb;

        $tracker = self::get_by_id( $id );
        if ( ! $tracker ) {
            return 1;
        }

        $per_page = max( 1, $per_page );
        $table    = WFT_DB::downloads_table();
        $before   = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE created_at > %s
                    OR (created_at = %s AND id > %d)",
                $tracker->created_at,
                $tracker->created_at,
                $id
            )
        );

        return (int) floor( $before / $per_page ) + 1;
    }

    public static function get_page( int $page = 1, int $per_page = 20, string $orderby = 'created_at', string $order = 'DESC' ): array {
        global $wpdb;

        $allowed_orderby = array(
            'title'               => 'title',
            'total_downloads'     => 'total_downloads',
            'shortcode_downloads' => 'shortcode_downloads',
            'external_downloads'  => 'external_downloads',
            'last_downloaded_at'  => 'last_downloaded_at',
            'created_at'          => 'created_at',
        );

        $orderby_sql = $allowed_orderby[ $orderby ] ?? 'created_at';
        $order_sql   = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';
        $per_page    = max( 1, min( 200, $per_page ) );
        $page        = max( 1, $page );
        $offset      = ( $page - 1 ) * $per_page;
        $table       = WFT_DB::downloads_table();

        // The column and direction are selected exclusively from the allowlists above.
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY {$orderby_sql} {$order_sql}, id {$order_sql} LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );

        return $wpdb->get_results( $sql ) ?: array();
    }

    public static function update_tracker( int $id, string $title, string $url, int $attachment_id = 0, string $button_text = '' ): bool {
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
            WFT_DB::downloads_table(),
            array(
                'title'            => sanitize_text_field( $title ),
                'button_text'      => '' !== trim( $button_text ) ? sanitize_text_field( $button_text ) : __( 'Download', 'wp-filetrace' ),
                'attachment_id'    => $attachment_id > 0 ? $attachment_id : null,
                'file_url'         => $url,
                'destination_hash' => $hash,
                'updated_at'       => current_time( 'mysql' ),
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%d', '%s', '%s', '%s' ),
            array( '%d' )
        );

        return false !== $updated;
    }

    public static function delete_tracker( int $id ): bool {
        global $wpdb;

        if ( $id <= 0 || ! self::get_by_id( $id ) ) {
            return false;
        }

        $downloads = WFT_DB::downloads_table();
        $events    = WFT_DB::events_table();

        // Keep the tracker row and its event history in sync if either delete fails.
        $wpdb->query( 'START TRANSACTION' );

        $events_deleted  = $wpdb->delete( $events, array( 'download_id' => $id ), array( '%d' ) );
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
        do_action( 'wft_download_tracker_deleted', $id );

        return true;
    }


    /**
     * Permanently delete multiple trackers and all associated event history.
     *
     * @param array<int> $ids Tracker IDs.
     * @return int|false Number of tracker rows deleted, or false on failure.
     */
    public static function delete_trackers( array $ids ) {
        global $wpdb;

        $ids = array_values(
            array_unique(
                array_filter(
                    array_map( 'absint', $ids )
                )
            )
        );

        if ( empty( $ids ) ) {
            return 0;
        }

        $downloads    = WFT_DB::downloads_table();
        $events       = WFT_DB::events_table();
        $placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

        $existing_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$downloads} WHERE id IN ({$placeholders})",
                ...$ids
            )
        );
        $existing_ids = array_values( array_map( 'absint', $existing_ids ?: array() ) );

        if ( empty( $existing_ids ) ) {
            return 0;
        }

        $existing_placeholders = implode( ', ', array_fill( 0, count( $existing_ids ), '%d' ) );

        $wpdb->query( 'START TRANSACTION' );

        $events_deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$events} WHERE download_id IN ({$existing_placeholders})",
                ...$existing_ids
            )
        );
        $trackers_deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$downloads} WHERE id IN ({$existing_placeholders})",
                ...$existing_ids
            )
        );

        if ( false === $events_deleted || false === $trackers_deleted || count( $existing_ids ) !== (int) $trackers_deleted ) {
            $wpdb->query( 'ROLLBACK' );
            return false;
        }

        $wpdb->query( 'COMMIT' );

        foreach ( $existing_ids as $id ) {
            do_action( 'wft_download_tracker_deleted', $id );
        }

        return (int) $trackers_deleted;
    }

    /**
     * Permanently delete every tracker and every stored download event.
     *
     * @return int|false Number of tracker rows deleted, or false on failure.
     */
    public static function delete_all_trackers() {
        global $wpdb;

        $downloads = WFT_DB::downloads_table();
        $events    = WFT_DB::events_table();
        $ids       = array_values( array_map( 'absint', $wpdb->get_col( "SELECT id FROM {$downloads}" ) ?: array() ) );

        if ( empty( $ids ) ) {
            return 0;
        }

        $wpdb->query( 'START TRANSACTION' );

        $events_deleted   = $wpdb->query( "DELETE FROM {$events}" );
        $trackers_deleted = $wpdb->query( "DELETE FROM {$downloads}" );

        if ( false === $events_deleted || false === $trackers_deleted || count( $ids ) !== (int) $trackers_deleted ) {
            $wpdb->query( 'ROLLBACK' );
            return false;
        }

        $wpdb->query( 'COMMIT' );

        foreach ( $ids as $id ) {
            do_action( 'wft_download_tracker_deleted', $id );
        }

        return (int) $trackers_deleted;
    }

    /**
     * Create synthetic rows for admin pagination/sorting testing.
     *
     * These rows intentionally do not create event-history records. Aggregate
     * counters and timestamps are enough to exercise the reporting interface.
     */
    public static function create_test_rows( int $count = 200 ): int {
        global $wpdb;

        $count     = max( 1, min( 1000, $count ) );
        $table     = WFT_DB::downloads_table();
        $batch     = strtoupper( substr( wp_generate_password( 8, false, false ), 0, 6 ) );
        $now_ts    = (int) current_time( 'timestamp' );
        $created   = 0;

        for ( $i = 1; $i <= $count; $i++ ) {
            $shortcode_count = wp_rand( 0, 900 );
            $external_count  = wp_rand( 0, 500 );
            $total_count     = $shortcode_count + $external_count;
            $created_ts      = $now_ts - wp_rand( 0, 365 * DAY_IN_SECONDS );
            $last_ts         = $total_count > 0 ? wp_rand( $created_ts, $now_ts ) : 0;
            $created_at      = gmdate( 'Y-m-d H:i:s', $created_ts );
            $last_at         = $last_ts ? gmdate( 'Y-m-d H:i:s', $last_ts ) : null;
            $updated_at      = $last_at ?: $created_at;
            $url             = 'https://example.com/wft-test-data/' . strtolower( $batch ) . '/file-' . str_pad( (string) $i, 3, '0', STR_PAD_LEFT ) . '.pdf';
            $title           = sprintf( 'WFT Test File %03d — %s', $i, $batch );

            $inserted = $wpdb->insert(
                $table,
                array(
                    'public_key'          => self::generate_public_key(),
                    'attachment_id'       => null,
                    'file_url'            => $url,
                    'destination_hash'    => self::destination_hash( 0, $url ),
                    'title'               => $title,
                    'button_text'         => __( 'Download', 'wp-filetrace' ),
                    'total_downloads'     => $total_count,
                    'shortcode_downloads' => $shortcode_count,
                    'external_downloads'  => $external_count,
                    'last_downloaded_at'  => $last_at,
                    'created_at'          => $created_at,
                    'updated_at'          => $updated_at,
                ),
                array( '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s' )
            );

            if ( false !== $inserted ) {
                $created++;
            }
        }

        return $created;
    }

    public static function build_tracked_url( $tracker, string $source = 'external' ): string {
        $source = self::sanitize_source( $source );

        if ( '' === (string) get_option( 'permalink_structure' ) ) {
            return add_query_arg(
                array(
                    'wft_download_key' => $tracker->public_key,
                    'via'              => $source,
                ),
                home_url( '/' )
            );
        }

        return add_query_arg( 'via', $source, home_url( '/wft-download/' . rawurlencode( $tracker->public_key ) . '/' ) );
    }

    public static function shortcode_for( $tracker, string $button_text = '' ): string {
        $atts = '';
        $button_text = trim( $button_text );
        if ( '' === $button_text && isset( $tracker->button_text ) ) {
            $button_text = trim( (string) $tracker->button_text );
        }

        if ( ! empty( $tracker->attachment_id ) ) {
            $atts = 'media="' . (int) $tracker->attachment_id . '"';
        } else {
            $atts = 'url="' . esc_url_raw( $tracker->file_url ) . '"';
        }

        if ( '' !== $button_text && __( 'Download', 'wp-filetrace' ) !== $button_text ) {
            $atts .= ' text="' . esc_attr( $button_text ) . '"';
        }

        return '[wft ' . $atts . ']';
    }

    public static function track( $tracker, string $source ): void {
        global $wpdb;

        $source       = self::sanitize_source( $source );
        $download_col = 'shortcode' === $source ? 'shortcode_downloads' : 'external_downloads';
        $now          = current_time( 'mysql' );
        $downloads    = WFT_DB::downloads_table();
        $events       = WFT_DB::events_table();

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
         * Fires after WFT records a tracked download.
         *
         * Intended as the integration point for GA4/Measurement Protocol or
         * other analytics transports in a future release.
         *
         * @param int    $download_id Tracker ID.
         * @param string $file_url    Final destination URL.
         * @param string $source      shortcode|external.
         * @param object $tracker     Tracker database row.
         */
        do_action( 'wft_download_tracked', (int) $tracker->id, $tracker->file_url, $source, $tracker );
    }

    public static function sanitize_source( string $source ): string {
        return 'shortcode' === $source ? 'shortcode' : 'external';
    }
}
