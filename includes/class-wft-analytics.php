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
    public const OPTION_GLOBAL_SNIPPET = 'wft_ga_global_snippet';
    public const OPTION_EVENT_SNIPPET  = 'wft_ga_event_snippet';
    public const OPTION_FILENAME_PARAM = 'wft_ga_filename_parameter';

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

    public static function get_filename_parameter(): string {
        return self::sanitize_parameter_name( (string) get_option( self::OPTION_FILENAME_PARAM, '' ) );
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
    public static function save_settings( string $global_snippet, string $event_snippet, string $filename_parameter ): void {
        update_option( self::OPTION_GLOBAL_SNIPPET, $global_snippet, false );
        update_option( self::OPTION_EVENT_SNIPPET, $event_snippet, false );
        update_option( self::OPTION_FILENAME_PARAM, self::sanitize_parameter_name( $filename_parameter ), false );
    }

    public static function clear_global_snippet(): void {
        delete_option( self::OPTION_GLOBAL_SNIPPET );
    }

    public static function clear_event_settings(): void {
        delete_option( self::OPTION_EVENT_SNIPPET );
        delete_option( self::OPTION_FILENAME_PARAM );
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
     * Render a lightweight browser handoff page that executes the configured
     * gtag event after WP FileTrace has recorded a download, then redirects to
     * the file. This works for both shortcode and external/email tracked URLs.
     *
     * @param object $tracker Tracker database row.
     * @param string $source  shortcode|external.
     */
    public static function render_download_handoff( $tracker, string $source ): void {
        $event_snippet = self::event_javascript();
        if ( '' === trim( $event_snippet ) ) {
            return;
        }

        $destination = esc_url_raw( (string) $tracker->file_url );
        $parameter   = self::get_filename_parameter();
        $file_name   = self::downloaded_file_name( $tracker );
        $source      = WFT_Downloads::sanitize_source( $source );

        $context = apply_filters(
            'wft_analytics_event_context',
            array(
                'download_id' => (int) $tracker->id,
                'file_name'   => $file_name,
                'file_url'    => $destination,
                'source'      => $source,
                'parameter'   => $parameter,
            ),
            $tracker,
            $source
        );

        if ( is_array( $context ) ) {
            $file_name = isset( $context['file_name'] ) ? sanitize_text_field( (string) $context['file_name'] ) : $file_name;
            $parameter = isset( $context['parameter'] ) ? self::sanitize_parameter_name( (string) $context['parameter'] ) : $parameter;
        }

        status_header( 200 );
        nocache_headers();
        header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );

        ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta http-equiv="refresh" content="3;url=<?php echo esc_attr( $destination ); ?>">
    <title><?php esc_html_e( 'Preparing download…', 'wp-filetrace' ); ?></title>
    <?php wp_head(); ?>
</head>
<body class="wft-download-handoff">
    <p style="font-family:system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;padding:24px;color:#344054;">
        <?php esc_html_e( 'Preparing your download…', 'wp-filetrace' ); ?>
    </p>
    <?php wp_footer(); ?>
    <script>
    (function () {
        'use strict';

        var destination = <?php echo wp_json_encode( $destination ); ?>;
        var fileName = <?php echo wp_json_encode( $file_name ); ?>;
        var fileParameter = <?php echo wp_json_encode( $parameter ); ?>;
        var redirected = false;

        function continueToDownload() {
            if ( redirected ) {
                return;
            }
            redirected = true;
            window.location.replace(destination);
        }

        window.dataLayer = window.dataLayer || [];
        if ( typeof window.gtag !== 'function' ) {
            window.gtag = function () {
                window.dataLayer.push(arguments);
            };
        }

        var baseGtag = window.gtag;

        if ( fileParameter ) {
            window.gtag = function () {
                var args = Array.prototype.slice.call(arguments);

                if ( args[0] === 'event' ) {
                    var eventParams = {};

                    if ( args[2] && typeof args[2] === 'object' && ! Array.isArray(args[2]) ) {
                        Object.keys(args[2]).forEach(function (key) {
                            eventParams[key] = args[2][key];
                        });
                    }

                    eventParams[fileParameter] = fileName;
                    args[2] = eventParams;
                }

                return baseGtag.apply(window, args);
            };
        }

        try {
<?php echo self::indent_javascript( $event_snippet, 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted admin-configured JavaScript. ?>
        } catch (error) {
            if ( window.console && typeof window.console.warn === 'function' ) {
                window.console.warn('WP FileTrace analytics event failed.', error);
            }
        } finally {
            window.gtag = baseGtag;
            window.setTimeout(continueToDownload, 700);
        }
    }());
    </script>
</body>
</html>
        <?php
    }

    /**
     * Convert an optionally wrapped event snippet into JavaScript suitable for
     * inclusion in WP FileTrace's own <script> element.
     */
    private static function event_javascript(): string {
        $snippet = trim( self::get_event_snippet() );

        if ( preg_match( '#^\s*<script\b[^>]*>(.*)</script>\s*$#is', $snippet, $matches ) ) {
            $snippet = trim( (string) $matches[1] );
        }

        return $snippet;
    }

    private static function downloaded_file_name( $tracker ): string {
        $path = (string) wp_parse_url( (string) $tracker->file_url, PHP_URL_PATH );
        $name = '' !== $path ? rawurldecode( wp_basename( $path ) ) : '';

        if ( '' === trim( $name ) ) {
            $name = ! empty( $tracker->title ) ? (string) $tracker->title : __( 'download', 'wp-filetrace' );
        }

        return sanitize_text_field( $name );
    }

    private static function indent_javascript( string $javascript, int $spaces ): string {
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
}
