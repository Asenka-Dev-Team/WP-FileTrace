<?php
/**
 * Optional Google Analytics / gtag integration for WP FileTrace.
 *
 * Primary Developer: Brian McLendon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WFT_Analytics {
    public const OPTION_GLOBAL_SNIPPET      = 'wft_ga_global_snippet';
    public const OPTION_EVENT_SNIPPET       = 'wft_ga_event_snippet';
    public const OPTION_DOWNLOAD_ID_PARAM   = 'wft_ga_download_id_parameter';
    public const OPTION_FILENAME_PARAM      = 'wft_ga_filename_parameter';
    public const OPTION_SOURCE_PARAM        = 'wft_ga_source_parameter';
    public const OPTION_VIA_PAGE_ID_PARAM   = 'wft_ga_via_page_id_parameter';
    public const OPTION_VIA_PAGE_TITLE_PARAM = 'wft_ga_via_page_title_parameter';

    /**
     * Register frontend analytics output.
     */
    public static function init(): void {
        add_action( 'wp_head', array( __CLASS__, 'output_global_snippet' ), 1 );
    }

    public static function get_global_snippet(): string {
        return (string) get_option( self::OPTION_GLOBAL_SNIPPET, '' );
    }

    public static function get_event_snippet(): string {
        return (string) get_option( self::OPTION_EVENT_SNIPPET, '' );
    }

    public static function get_download_id_parameter(): string {
        return self::sanitize_parameter_name( (string) get_option( self::OPTION_DOWNLOAD_ID_PARAM, '' ) );
    }

    public static function get_filename_parameter(): string {
        return self::sanitize_parameter_name( (string) get_option( self::OPTION_FILENAME_PARAM, '' ) );
    }

    public static function get_source_parameter(): string {
        return self::sanitize_parameter_name( (string) get_option( self::OPTION_SOURCE_PARAM, '' ) );
    }

    public static function get_via_page_id_parameter(): string {
        return self::sanitize_parameter_name( (string) get_option( self::OPTION_VIA_PAGE_ID_PARAM, '' ) );
    }

    public static function get_via_page_title_parameter(): string {
        return self::sanitize_parameter_name( (string) get_option( self::OPTION_VIA_PAGE_TITLE_PARAM, '' ) );
    }

    public static function has_event_snippet(): bool {
        return '' !== trim( self::get_event_snippet() );
    }

    /**
     * Save all optional analytics settings.
     *
     * Snippets are intentionally stored verbatim. Only trusted administrators
     * with unfiltered_html should be allowed to configure executable scripts.
     */
    public static function save_settings(
        string $global_snippet,
        string $event_snippet,
        string $download_id_parameter,
        string $filename_parameter,
        string $source_parameter,
        string $via_page_id_parameter = '',
        string $via_page_title_parameter = ''
    ): void {
        update_option( self::OPTION_GLOBAL_SNIPPET, $global_snippet, false );
        update_option( self::OPTION_EVENT_SNIPPET, $event_snippet, false );
        update_option( self::OPTION_DOWNLOAD_ID_PARAM, self::sanitize_parameter_name( $download_id_parameter ), false );
        update_option( self::OPTION_FILENAME_PARAM, self::sanitize_parameter_name( $filename_parameter ), false );
        update_option( self::OPTION_SOURCE_PARAM, self::sanitize_parameter_name( $source_parameter ), false );
        update_option( self::OPTION_VIA_PAGE_ID_PARAM, self::sanitize_parameter_name( $via_page_id_parameter ), false );
        update_option( self::OPTION_VIA_PAGE_TITLE_PARAM, self::sanitize_parameter_name( $via_page_title_parameter ), false );
    }

    public static function clear_global_snippet(): void {
        delete_option( self::OPTION_GLOBAL_SNIPPET );
    }

    public static function clear_event_settings(): void {
        delete_option( self::OPTION_EVENT_SNIPPET );
        delete_option( self::OPTION_DOWNLOAD_ID_PARAM );
        delete_option( self::OPTION_FILENAME_PARAM );
        delete_option( self::OPTION_SOURCE_PARAM );
        delete_option( self::OPTION_VIA_PAGE_ID_PARAM );
        delete_option( self::OPTION_VIA_PAGE_TITLE_PARAM );
    }

    public static function sanitize_parameter_name( string $name ): string {
        $name = preg_replace( '/[^A-Za-z0-9_]/', '', trim( $name ) );
        return substr( (string) $name, 0, 64 );
    }

    /**
     * Print the optional complete global-site-tag snippet near the beginning
     * of <head> on frontend pages.
     */
    public static function output_global_snippet(): void {
        if ( is_admin() ) {
            return;
        }

        $snippet = trim( self::get_global_snippet() );
        if ( '' === $snippet ) {
            return;
        }

        echo "\n<!-- WP FileTrace: global analytics snippet -->\n";
        echo $snippet; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted admin-configured executable markup.
        echo "\n<!-- /WP FileTrace: global analytics snippet -->\n";
    }

    /**
     * Build the runtime values used to augment custom gtag events.
     *
     * @param object $tracker        Tracker database row.
     * @param string $source         shortcode|external.
     * @param int    $via_page_id    Originating WordPress content ID, when known.
     * @param string $via_page_title Originating WordPress content title, when known.
     * @return array<string,mixed>
     */
    public static function get_event_context( $tracker, string $source, int $via_page_id = 0, string $via_page_title = '' ): array {
        $destination             = esc_url_raw( (string) $tracker->file_url );
        $download_id             = (int) $tracker->id;
        $download_id_parameter   = self::get_download_id_parameter();
        $filename_parameter      = self::get_filename_parameter();
        $source_parameter        = self::get_source_parameter();
        $via_page_id_parameter   = self::get_via_page_id_parameter();
        $via_page_title_parameter = self::get_via_page_title_parameter();
        $file_name               = self::downloaded_file_name( $tracker );
        $source                  = WFT_Downloads::sanitize_source( $source );
        $via_page_id             = absint( $via_page_id );
        $via_page_title          = sanitize_text_field( $via_page_title );

        if ( $via_page_id > 0 && '' === $via_page_title ) {
            $via_post = get_post( $via_page_id );
            if ( $via_post && is_post_publicly_viewable( $via_post ) ) {
                $resolved_title = get_the_title( $via_page_id );
                if ( is_string( $resolved_title ) ) {
                    $via_page_title = sanitize_text_field( $resolved_title );
                }
            } else {
                $via_page_id = 0;
            }
        }

        $context = apply_filters(
            'wft_analytics_event_context',
            array(
                'download_id'              => $download_id,
                'file_name'                => $file_name,
                'file_url'                 => $destination,
                'source'                   => $source,
                'via_page_id'              => $via_page_id,
                'via_page_title'           => $via_page_title,
                // `parameter` is retained for backwards compatibility with the v0.1.5 filter shape.
                'parameter'                => $filename_parameter,
                'filename_parameter'       => $filename_parameter,
                'id_parameter'             => $download_id_parameter,
                'source_parameter'         => $source_parameter,
                'via_page_id_parameter'    => $via_page_id_parameter,
                'via_page_title_parameter' => $via_page_title_parameter,
            ),
            $tracker,
            $source
        );

        if ( ! is_array( $context ) ) {
            $context = array();
        }

        $context['download_id']    = isset( $context['download_id'] ) ? absint( $context['download_id'] ) : $download_id;
        $context['file_name']      = isset( $context['file_name'] ) ? sanitize_text_field( (string) $context['file_name'] ) : $file_name;
        $context['source']         = isset( $context['source'] ) ? WFT_Downloads::sanitize_source( (string) $context['source'] ) : $source;
        $context['via_page_id']    = isset( $context['via_page_id'] ) ? absint( $context['via_page_id'] ) : $via_page_id;
        $context['via_page_title'] = isset( $context['via_page_title'] ) ? sanitize_text_field( (string) $context['via_page_title'] ) : $via_page_title;

        if ( isset( $context['filename_parameter'] ) ) {
            $context['filename_parameter'] = self::sanitize_parameter_name( (string) $context['filename_parameter'] );
        } elseif ( isset( $context['parameter'] ) ) {
            $context['filename_parameter'] = self::sanitize_parameter_name( (string) $context['parameter'] );
        } else {
            $context['filename_parameter'] = $filename_parameter;
        }

        $context['id_parameter'] = isset( $context['id_parameter'] )
            ? self::sanitize_parameter_name( (string) $context['id_parameter'] )
            : $download_id_parameter;

        $context['source_parameter'] = isset( $context['source_parameter'] )
            ? self::sanitize_parameter_name( (string) $context['source_parameter'] )
            : $source_parameter;

        $context['via_page_id_parameter'] = isset( $context['via_page_id_parameter'] )
            ? self::sanitize_parameter_name( (string) $context['via_page_id_parameter'] )
            : $via_page_id_parameter;

        $context['via_page_title_parameter'] = isset( $context['via_page_title_parameter'] )
            ? self::sanitize_parameter_name( (string) $context['via_page_title_parameter'] )
            : $via_page_title_parameter;

        return $context;
    }

    /**
     * Backwards-compatible wrapper for code that called the old Analytics
     * handoff renderer directly.
     */
    public static function render_download_handoff( $tracker, string $source, int $via_page_id = 0, string $via_page_title = '' ): void {
        if ( class_exists( 'WFT_Download_Page' ) ) {
            WFT_Download_Page::render_handoff( $tracker, $source, $via_page_id, $via_page_title );
        }
    }

    /**
     * Convert an optionally wrapped event snippet into JavaScript suitable for
     * inclusion in WP FileTrace's own <script> element.
     */
    public static function event_javascript(): string {
        $snippet = trim( self::get_event_snippet() );

        if ( preg_match( '#^\s*<script\b[^>]*>(.*)</script>\s*$#is', $snippet, $matches ) ) {
            $snippet = trim( (string) $matches[1] );
        }

        return $snippet;
    }

    public static function indent_javascript( string $javascript, int $spaces ): string {
        $padding = str_repeat( ' ', max( 0, $spaces ) );
        $lines   = preg_split( '/\R/', $javascript );

        if ( ! is_array( $lines ) ) {
            return $padding . $javascript;
        }

        return implode(
            "\n",
            array_map(
                static fn( string $line ): string => $padding . $line,
                $lines
            )
        );
    }

    private static function downloaded_file_name( $tracker ): string {
        $path = (string) wp_parse_url( (string) $tracker->file_url, PHP_URL_PATH );
        $name = '' !== $path ? rawurldecode( wp_basename( $path ) ) : '';

        if ( '' === trim( $name ) ) {
            $name = ! empty( $tracker->title ) ? (string) $tracker->title : __( 'download', 'wp-filetrace' );
        }

        return sanitize_text_field( $name );
    }
}
