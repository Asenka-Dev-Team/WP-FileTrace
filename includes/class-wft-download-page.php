<?php
/**
 * Frontend download handoff page and preview tools for WP FileTrace.
 *
 * Primary Developer: Brian McLendon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WFT_Download_Page {
    public const OPTION_HTML           = 'wft_download_page_html';
    public const OPTION_CSS            = 'wft_download_page_css';
    public const OPTION_LOGO_ID        = 'wft_download_page_logo_id';
    public const OPTION_HIDE_SITE_NAME = 'wft_download_page_hide_site_name';

    private const AUTO_START_DELAY_MS = 700;
    private const RETRY_REVEAL_MS     = 2200;
    private const SPINNER_HIDE_MS     = 2750;

    public static function init(): void {
        add_action( 'admin_post_wft_download_page_preview', array( __CLASS__, 'preview' ) );
    }

    public static function get_html(): string {
        return (string) get_option( self::OPTION_HTML, '' );
    }

    public static function get_css(): string {
        return (string) get_option( self::OPTION_CSS, '' );
    }

    public static function get_logo_id(): int {
        return absint( get_option( self::OPTION_LOGO_ID, 0 ) );
    }

    public static function get_logo_url(): string {
        $logo_id = self::get_logo_id();
        if ( $logo_id <= 0 ) {
            return '';
        }

        $url = wp_get_attachment_url( $logo_id );
        return $url ? esc_url_raw( $url ) : '';
    }

    public static function hide_site_name(): bool {
        return '1' === (string) get_option( self::OPTION_HIDE_SITE_NAME, '0' );
    }

    public static function save_settings( string $html, string $css, int $logo_id = 0, bool $hide_site_name = false ): void {
        update_option( self::OPTION_HTML, self::sanitize_html_template( $html ), false );
        update_option( self::OPTION_CSS, self::sanitize_css( $css ), false );

        $logo_id = self::sanitize_logo_id( $logo_id );
        if ( $logo_id > 0 ) {
            update_option( self::OPTION_LOGO_ID, $logo_id, false );
        } else {
            delete_option( self::OPTION_LOGO_ID );
        }

        update_option( self::OPTION_HIDE_SITE_NAME, $hide_site_name ? '1' : '0', false );
    }

    public static function clear_html(): void {
        delete_option( self::OPTION_HTML );
    }

    public static function clear_css(): void {
        delete_option( self::OPTION_CSS );
    }

    public static function clear_all(): void {
        self::clear_html();
        self::clear_css();
        delete_option( self::OPTION_LOGO_ID );
        delete_option( self::OPTION_HIDE_SITE_NAME );
    }

    public static function preview_url(): string {
        return wp_nonce_url(
            admin_url( 'admin-post.php?action=wft_download_page_preview' ),
            'wft_download_page_preview'
        );
    }

    /**
     * Retry URL used after the tracked request has already been recorded.
     * This endpoint never increments counters or fires analytics again.
     */
    public static function retry_url( $tracker ): string {
        $key = isset( $tracker->public_key ) ? (string) $tracker->public_key : '';
        if ( '' === $key ) {
            return '';
        }

        if ( get_option( 'permalink_structure' ) ) {
            return home_url( '/wft-download/' . rawurlencode( $key ) . '/retry/' );
        }

        return add_query_arg(
            array(
                'wft_download_key'   => $key,
                'wft_download_retry' => '1',
            ),
            home_url( '/' )
        );
    }

    /**
     * Render the tracked download handoff page.
     *
     * @param object $tracker        Tracker database row.
     * @param string $source         shortcode|external.
     * @param int    $via_page_id    Originating WordPress content ID, when known.
     * @param string $via_page_title Originating WordPress content title, when known.
     */
    public static function render_handoff( $tracker, string $source, int $via_page_id = 0, string $via_page_title = '' ): void {
        $source  = WFT_Downloads::sanitize_source( $source );
        $context = self::context_for_tracker( $tracker, $source, false, $via_page_id, $via_page_title );

        status_header( 200 );
        nocache_headers();
        header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );

        self::render_document( $context, false );
    }

    public static function preview(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wp-filetrace' ), '', array( 'response' => 403 ) );
        }

        check_admin_referer( 'wft_download_page_preview' );

        $sample = (object) array(
            'id'         => 123,
            'public_key' => 'preview-only',
            'title'      => __( '2026 Charts Zip', 'wp-filetrace' ),
            'file_url'   => 'https://example.com/files/2026-charts.zip',
        );

        $context = self::context_for_tracker(
            $sample,
            'shortcode',
            true,
            456,
            __( 'Charts & Reports', 'wp-filetrace' )
        );

        status_header( 200 );
        nocache_headers();
        header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );

        self::render_document( $context, true );
        exit;
    }

    private static function context_for_tracker(
        $tracker,
        string $source,
        bool $preview,
        int $via_page_id = 0,
        string $via_page_title = ''
    ): array {
        $title = ! empty( $tracker->title )
            ? sanitize_text_field( (string) $tracker->title )
            : self::file_name( $tracker );

        $retry_url     = $preview ? '#' : self::retry_url( $tracker );
        $via_page_id   = absint( $via_page_id );
        $via_page_title = sanitize_text_field( $via_page_title );

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

        return array(
            'download_id'     => isset( $tracker->id ) ? absint( $tracker->id ) : 0,
            'download_name'   => $title,
            'file_name'       => self::file_name( $tracker ),
            'download_url'    => $retry_url,
            'download_source' => WFT_Downloads::sanitize_source( $source ),
            'via_page_id'     => $via_page_id,
            'via_page_title'  => $via_page_title,
            'site_name'       => sanitize_text_field( (string) get_bloginfo( 'name' ) ),
            'site_url'        => esc_url_raw( home_url( '/' ) ),
            'logo_url'        => self::get_logo_url(),
            'hide_site_name'  => self::hide_site_name(),
            'preview'         => $preview,
            'tracker'         => $tracker,
        );
    }

    private static function render_document( array $context, bool $preview ): void {
        $custom_html = trim( self::get_html() );
        $custom_css  = trim( self::get_css() );
        $page_html   = '' !== $custom_html ? self::replace_tokens( $custom_html, $context ) : self::default_markup( $context );

        $analytics = $preview
            ? array()
            : WFT_Analytics::get_event_context(
                $context['tracker'],
                (string) $context['download_source'],
                (int) $context['via_page_id'],
                (string) $context['via_page_title']
            );
        $event_javascript = $preview ? '' : WFT_Analytics::event_javascript();

        $retry_url = (string) $context['download_url'];
        $site_url  = (string) $context['site_url'];
        $title     = sprintf(
            /* translators: %s: download title. */
            __( 'Preparing %s download', 'wp-filetrace' ),
            (string) $context['download_name']
        );
        ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title><?php echo esc_html( $title ); ?></title>
    <?php if ( ! $preview ) : ?>
        <?php wp_head(); ?>
        <noscript><meta http-equiv="refresh" content="0;url=<?php echo esc_attr( $retry_url ); ?>"></noscript>
    <?php endif; ?>
    <style id="wft-download-page-base-css">
<?php echo self::base_css(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plugin-owned CSS. ?>
    </style>
    <?php if ( '' !== $custom_css ) : ?>
        <style id="wft-download-page-custom-css">
<?php echo $custom_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized administrator CSS. ?>
        </style>
    <?php endif; ?>
</head>
<body class="wft-download-page-body<?php echo $preview ? ' is-preview' : ''; ?>">
    <?php if ( $preview ) : ?>
        <div class="wft-download-preview-badge" role="status"><?php esc_html_e( 'Preview Mode — no download or analytics event will run', 'wp-filetrace' ); ?></div>
    <?php endif; ?>

    <main class="wft-download-page" aria-live="polite">
        <div class="wft-download-page-content">
            <?php echo $page_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized template with escaped runtime tokens. ?>

            <div class="wft-download-retry" id="wft-download-retry" hidden>
                <p><?php esc_html_e( 'If your download has not started, please use the link below.', 'wp-filetrace' ); ?></p>
                <a class="wft-download-retry-button" id="wft-download-retry-link" href="<?php echo esc_url( $retry_url ); ?>">
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: %s: download title. */
                            __( 'Download %s', 'wp-filetrace' ),
                            (string) $context['download_name']
                        )
                    );
                    ?>
                </a>
            </div>

            <noscript>
                <div class="wft-download-retry is-visible">
                    <p><?php esc_html_e( 'JavaScript is disabled. Use the link below to continue your download.', 'wp-filetrace' ); ?></p>
                    <a class="wft-download-retry-button" href="<?php echo esc_url( $retry_url ); ?>">
                        <?php esc_html_e( 'Continue Download', 'wp-filetrace' ); ?>
                    </a>
                </div>
            </noscript>

            <div class="wft-download-main-site">
                <a href="<?php echo esc_url( $site_url ); ?>"><?php esc_html_e( 'Back to main site', 'wp-filetrace' ); ?></a>
            </div>
        </div>
    </main>

    <?php if ( ! $preview ) : ?>
        <?php wp_footer(); ?>
    <?php endif; ?>

    <script>
    (function () {
        'use strict';

        var isPreview = <?php echo $preview ? 'true' : 'false'; ?>;
        var retryUrl = <?php echo wp_json_encode( $retry_url ); ?>;
        var retryBox = document.getElementById('wft-download-retry');
        var retryLink = document.getElementById('wft-download-retry-link');
        var spinners = document.querySelectorAll('.wft-download-spinner');
        var started = false;

        function revealRetry() {
            if (retryBox) {
                retryBox.hidden = false;
                retryBox.classList.add('is-visible');
            }
        }

        function hideSpinners() {
            spinners.forEach(function (spinner) {
                spinner.classList.add('is-complete');
                window.setTimeout(function () {
                    spinner.hidden = true;
                }, 340);
            });
        }

        if (retryLink && isPreview) {
            retryLink.addEventListener('click', function (event) {
                event.preventDefault();
            });
        }

        window.setTimeout(revealRetry, <?php echo (int) self::RETRY_REVEAL_MS; ?>);
        window.setTimeout(hideSpinners, <?php echo (int) self::SPINNER_HIDE_MS; ?>);

        if (isPreview) {
            return;
        }

        function continueToDownload() {
            if (started || !retryUrl) {
                return;
            }
            started = true;
            window.location.assign(retryUrl);
        }

        var hasAnalyticsEvent = <?php echo '' !== trim( $event_javascript ) ? 'true' : 'false'; ?>;

        if (!hasAnalyticsEvent) {
            window.setTimeout(continueToDownload, <?php echo (int) self::AUTO_START_DELAY_MS; ?>);
            return;
        }

        var downloadId = <?php echo wp_json_encode( isset( $analytics['download_id'] ) ? (int) $analytics['download_id'] : 0 ); ?>;
        var downloadIdParameter = <?php echo wp_json_encode( isset( $analytics['id_parameter'] ) ? (string) $analytics['id_parameter'] : '' ); ?>;
        var fileName = <?php echo wp_json_encode( isset( $analytics['file_name'] ) ? (string) $analytics['file_name'] : '' ); ?>;
        var fileParameter = <?php echo wp_json_encode( isset( $analytics['filename_parameter'] ) ? (string) $analytics['filename_parameter'] : '' ); ?>;
        var downloadSource = <?php echo wp_json_encode( isset( $analytics['source'] ) ? (string) $analytics['source'] : (string) $context['download_source'] ); ?>;
        var sourceParameter = <?php echo wp_json_encode( isset( $analytics['source_parameter'] ) ? (string) $analytics['source_parameter'] : '' ); ?>;
        var viaPageId = <?php echo wp_json_encode( isset( $analytics['via_page_id'] ) ? (int) $analytics['via_page_id'] : (int) $context['via_page_id'] ); ?>;
        var viaPageIdParameter = <?php echo wp_json_encode( isset( $analytics['via_page_id_parameter'] ) ? (string) $analytics['via_page_id_parameter'] : '' ); ?>;
        var viaPageTitle = <?php echo wp_json_encode( isset( $analytics['via_page_title'] ) ? (string) $analytics['via_page_title'] : (string) $context['via_page_title'] ); ?>;
        var viaPageTitleParameter = <?php echo wp_json_encode( isset( $analytics['via_page_title_parameter'] ) ? (string) $analytics['via_page_title_parameter'] : '' ); ?>;

        window.dataLayer = window.dataLayer || [];
        if (typeof window.gtag !== 'function') {
            window.gtag = function () {
                window.dataLayer.push(arguments);
            };
        }

        var baseGtag = window.gtag;

        if (downloadIdParameter || fileParameter || sourceParameter || viaPageIdParameter || viaPageTitleParameter) {
            window.gtag = function () {
                var args = Array.prototype.slice.call(arguments);

                if (args[0] === 'event') {
                    var eventParams = {};

                    if (args[2] && typeof args[2] === 'object' && !Array.isArray(args[2])) {
                        Object.keys(args[2]).forEach(function (key) {
                            eventParams[key] = args[2][key];
                        });
                    }

                    if (downloadIdParameter) {
                        eventParams[downloadIdParameter] = downloadId;
                    }

                    if (fileParameter) {
                        eventParams[fileParameter] = fileName;
                    }

                    if (sourceParameter) {
                        eventParams[sourceParameter] = downloadSource;
                    }

                    if (viaPageIdParameter) {
                        eventParams[viaPageIdParameter] = viaPageId;
                    }

                    if (viaPageTitleParameter) {
                        eventParams[viaPageTitleParameter] = viaPageTitle;
                    }

                    args[2] = eventParams;
                }

                return baseGtag.apply(window, args);
            };
        }

        try {
<?php echo WFT_Analytics::indent_javascript( $event_javascript, 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted admin-configured JavaScript. ?>
        } catch (error) {
            if (window.console && typeof window.console.warn === 'function') {
                window.console.warn('WP FileTrace analytics event failed.', error);
            }
        } finally {
            window.gtag = baseGtag;
            window.setTimeout(continueToDownload, <?php echo (int) self::AUTO_START_DELAY_MS; ?>);
        }
    }());
    </script>
</body>
</html>
        <?php
    }

    private static function replace_tokens( string $html, array $context ): string {
        $tokens = array(
            '{{download_name}}'   => esc_html( (string) $context['download_name'] ),
            '{{file_name}}'       => esc_html( (string) $context['file_name'] ),
            '{{download_url}}'    => esc_url( (string) $context['download_url'] ),
            '{{download_source}}' => esc_html( (string) $context['download_source'] ),
            '{{via_page_id}}'     => esc_html( (string) $context['via_page_id'] ),
            '{{via_page_title}}'  => esc_html( (string) $context['via_page_title'] ),
            '{{site_name}}'       => esc_html( (string) $context['site_name'] ),
            '{{site_url}}'        => esc_url( (string) $context['site_url'] ),
            '{{logo_url}}'        => esc_url( (string) $context['logo_url'] ),
        );

        return strtr( $html, $tokens );
    }

    private static function default_markup( array $context ): string {
        $download_name = esc_html( (string) $context['download_name'] );
        $site_name     = esc_html( (string) $context['site_name'] );
        $logo_url      = esc_url( (string) $context['logo_url'] );
        $hide_site     = ! empty( $context['hide_site_name'] );
        $branding      = '';

        if ( '' !== $logo_url ) {
            $branding .= '<img class="wft-download-brand-logo" src="' . $logo_url . '" alt="">';
        }

        if ( ! $hide_site && '' !== $site_name ) {
            $branding .= '<div class="wft-download-site-name">' . $site_name . '</div>';
        }

        return sprintf(
            '<div class="wft-download-card">'
            . '%1$s'
            . '<div class="wft-download-spinner" aria-hidden="true"></div>'
            . '<h1>%2$s</h1>'
            . '<p>%3$s</p>'
            . '</div>',
            $branding,
            sprintf(
                /* translators: %s: download title. */
                esc_html__( 'Preparing your %s download', 'wp-filetrace' ),
                $download_name
            ),
            esc_html__( 'Your download should begin automatically.', 'wp-filetrace' )
        );
    }

    private static function file_name( $tracker ): string {
        $path = (string) wp_parse_url( (string) ( $tracker->file_url ?? '' ), PHP_URL_PATH );
        $name = '' !== $path ? rawurldecode( wp_basename( $path ) ) : '';

        if ( '' === trim( $name ) ) {
            $name = ! empty( $tracker->title ) ? (string) $tracker->title : __( 'download', 'wp-filetrace' );
        }

        return sanitize_text_field( $name );
    }

    private static function sanitize_logo_id( int $attachment_id ): int {
        $attachment_id = absint( $attachment_id );
        if ( $attachment_id <= 0 ) {
            return 0;
        }

        $url = wp_get_attachment_url( $attachment_id );
        if ( ! $url ) {
            return 0;
        }

        $mime = (string) get_post_mime_type( $attachment_id );
        $path = (string) wp_parse_url( $url, PHP_URL_PATH );
        $ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

        if ( 0 !== strpos( $mime, 'image/' ) && 'svg' !== $ext ) {
            return 0;
        }

        return $attachment_id;
    }

    private static function sanitize_html_template( string $html ): string {
        // Sanitize after replacing URL tokens with valid temporary URLs so
        // wp_kses_post can validate href/src attributes safely.
        $placeholders = array(
            '{{download_url}}' => 'https://wft-preview.invalid/retry',
            '{{site_url}}'     => 'https://wft-preview.invalid/',
            '{{logo_url}}'     => 'https://wft-preview.invalid/logo.png',
        );

        $html = strtr( $html, $placeholders );
        $html = wp_kses_post( $html );

        return strtr( $html, array_flip( $placeholders ) );
    }

    private static function sanitize_css( string $css ): string {
        $css = str_replace( array( "\0", '</style', '</script' ), '', $css );
        return trim( wp_strip_all_tags( $css, true ) );
    }

    private static function base_css(): string {
        return <<<'CSS'
:root {
    color-scheme: light;
}
* {
    box-sizing: border-box;
}
html,
body.wft-download-page-body {
    min-height: 100%;
    margin: 0;
}
body.wft-download-page-body {
    min-height: 100vh;
    background: #f4f6f8;
    color: #1f2937;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}
.wft-download-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 32px 20px;
}
.wft-download-page-content {
    width: min(100%, 620px);
    text-align: center;
}
.wft-download-card {
    padding: 36px 32px;
    border: 1px solid #d9dde3;
    border-radius: 14px;
    background: linear-gradient(180deg, #ffffff 0%, #fbfcfd 100%);
    box-shadow: 0 1px 2px rgba(15, 23, 42, .07), 0 12px 32px rgba(15, 23, 42, .07);
}
.wft-download-card h1 {
    margin: 16px 0 8px;
    color: #111827;
    font-size: clamp(24px, 4vw, 34px);
    line-height: 1.18;
    letter-spacing: -.025em;
}
.wft-download-card p,
.wft-download-retry p {
    margin: 0;
    color: #667085;
    font-size: 15px;
    line-height: 1.55;
}
.wft-download-brand-logo {
    display: block;
    width: auto;
    max-width: min(220px, 72%);
    max-height: 78px;
    margin: 0 auto 14px;
    object-fit: contain;
}
.wft-download-site-name {
    color: #667085;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
}
.wft-download-spinner {
    width: 34px;
    height: 34px;
    margin: 22px auto 0;
    border: 3px solid #dfe4ea;
    border-top-color: #667085;
    border-radius: 999px;
    animation: wft-download-spin .8s linear infinite;
    opacity: 1;
    transition: opacity .3s ease, transform .3s ease;
}
.wft-download-spinner.is-complete {
    animation: none;
    opacity: 0;
    transform: scale(.88);
}
.wft-download-retry {
    margin-top: 18px;
    padding: 18px 20px;
    border: 1px solid #d9dde3;
    border-radius: 12px;
    background: #ffffff;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
}
.wft-download-retry.is-visible {
    animation: wft-download-retry-in .18s ease-out both;
}
.wft-download-retry-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    margin-top: 12px;
    padding: 8px 16px;
    border: 1px solid rgba(0, 0, 0, .18);
    border-radius: 7px;
    background: linear-gradient(180deg, #ffffff 0%, #edf0f3 100%);
    box-shadow: 0 1px 2px rgba(0, 0, 0, .12), inset 0 1px 0 rgba(255, 255, 255, .9);
    color: #20252b;
    font-weight: 650;
    text-decoration: none;
}
.wft-download-retry-button:hover,
.wft-download-retry-button:focus {
    background: linear-gradient(180deg, #ffffff 0%, #e5e9ed 100%);
    color: #111827;
}
.wft-download-main-site {
    margin-top: 16px;
}
.wft-download-main-site a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 32px;
    padding: 5px 11px;
    border: 1px solid #d9dde3;
    border-radius: 7px;
    background: rgba(255, 255, 255, .68);
    color: #667085;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
}
.wft-download-main-site a:hover,
.wft-download-main-site a:focus {
    background: #fff;
    color: #344054;
}
.wft-download-preview-badge {
    position: fixed;
    z-index: 9999;
    top: 12px;
    left: 50%;
    transform: translateX(-50%);
    max-width: calc(100% - 24px);
    padding: 7px 11px;
    border: 1px solid #c99316;
    border-radius: 999px;
    background: #fff7d6;
    color: #6a4c00;
    font-size: 12px;
    font-weight: 700;
    text-align: center;
}
@keyframes wft-download-spin {
    to { transform: rotate(360deg); }
}
@keyframes wft-download-retry-in {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}
@media (prefers-reduced-motion: reduce) {
    .wft-download-spinner { animation: none; transition: none; }
    .wft-download-retry.is-visible { animation: none; }
}
CSS;
    }
}
