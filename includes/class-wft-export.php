<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WFT_Export {
    public static function init(): void {
        add_action( 'admin_post_wft_export_csv', array( __CLASS__, 'export_csv' ) );
    }

    public static function export_csv(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to export download data.', 'wp-filetrace' ) );
        }

        check_admin_referer( 'wft_export_csv' );

        $rows = WFT_Downloads::get_all();
        $name = 'wp-filetrace-' . gmdate( 'Y-m-d' ) . '.csv';

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $name . '"' );

        $output = fopen( 'php://output', 'w' );
        if ( false === $output ) {
            wp_die( esc_html__( 'Unable to create CSV output.', 'wp-filetrace' ) );
        }

        fwrite( $output, "\xEF\xBB\xBF" );
        fputcsv( $output, array( 'Title', 'Button Text', 'Media ID', 'URL', 'Total Downloads', 'Shortcode Downloads', 'External Downloads', 'Created On', 'Last Download' ) );

        foreach ( $rows as $row ) {
            fputcsv(
                $output,
                array(
                    $row->title,
                    isset( $row->button_text ) ? $row->button_text : 'Download',
                    $row->attachment_id,
                    $row->file_url,
                    $row->total_downloads,
                    $row->shortcode_downloads,
                    $row->external_downloads,
                    $row->created_at,
                    $row->last_downloaded_at,
                )
            );
        }

        fclose( $output );
        exit;
    }
}
