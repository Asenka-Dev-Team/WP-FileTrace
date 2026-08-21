<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ADT_Export {
    public static function init(): void {
        add_action( 'admin_post_adt_export_csv', array( __CLASS__, 'export_csv' ) );
    }

    public static function export_csv(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to export download data.', 'asenka-download-tracker' ) );
        }

        check_admin_referer( 'adt_export_csv' );

        $rows = ADT_Downloads::get_all();
        $name = 'asenka-download-tracker-' . gmdate( 'Y-m-d' ) . '.csv';

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $name . '"' );

        $output = fopen( 'php://output', 'w' );
        if ( false === $output ) {
            wp_die( esc_html__( 'Unable to create CSV output.', 'asenka-download-tracker' ) );
        }

        fwrite( $output, "\xEF\xBB\xBF" );
        fputcsv( $output, array( 'Title', 'Media ID', 'URL', 'Total Downloads', 'Shortcode Downloads', 'External Downloads', 'Last Download', 'Created' ) );

        foreach ( $rows as $row ) {
            fputcsv(
                $output,
                array(
                    $row->title,
                    $row->attachment_id,
                    $row->file_url,
                    $row->total_downloads,
                    $row->shortcode_downloads,
                    $row->external_downloads,
                    $row->last_downloaded_at,
                    $row->created_at,
                )
            );
        }

        fclose( $output );
        exit;
    }
}
