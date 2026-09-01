<?php
/**
 * Frontend routing for WP FileTrace tracked links.
 *
 * v0.1.14 adds an early, scanner-resistant handoff path so burst traffic does
 * not run the normal WordPress frontend stack or write tracking data until a
 * browser-confirmation POST is received.
 *
 * Primary Developer: Brian McLendon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WFT_Router {
    private const TRACK_HEADER_VALUE = 'browser';
    private const TRACKER_CACHE_GROUP = 'wp-filetrace';
    private const TRACKER_CACHE_TTL   = 60;

    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'register_rewrite_rule' ) );
        add_action( 'init', array( __CLASS__, 'maybe_refresh_rewrite_rules' ), 99 );
        add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
        add_action( 'parse_request', array( __CLASS__, 'handle_parsed_request' ), 0 );
        add_action( 'template_redirect', array( __CLASS__, 'handle_download' ), 0 );
    }

    public static function register_rewrite_rule(): void {
        add_rewrite_rule( '^wft-download/([^/]+)/track/?$', 'index.php?wft_download_key=$matches[1]&wft_download_track=1', 'top' );
        add_rewrite_rule( '^wft-download/([^/]+)/retry/?$', 'index.php?wft_download_key=$matches[1]&wft_download_retry=1', 'top' );
        add_rewrite_rule( '^wft-download/([^/]+)/?$', 'index.php?wft_download_key=$matches[1]', 'top' );
    }

    public static function maybe_refresh_rewrite_rules(): void {
        $rewrite_version = (string) get_option( 'wft_rewrite_version', '' );
        if ( WFT_VERSION === $rewrite_version ) {
            return;
        }

        flush_rewrite_rules( false );
        update_option( 'wft_rewrite_version', WFT_VERSION, false );
    }

    public static function query_vars( array $vars ): array {
        $vars[] = 'wft_download_key';
        $vars[] = 'wft_download_retry';
        $vars[] = 'wft_download_track';

        return $vars;
    }

    /**
     * Short-circuit tracked-link requests at plugins_loaded priority 0.
     *
     * This avoids running normal init/theme/frontend hooks for the handoff and
     * browser-confirmation endpoints. The normal rewrite/template_redirect path
     * remains as a compatibility fallback.
     */
    public static function maybe_handle_early_request(): void {
        if ( wp_doing_cron() || wp_doing_ajax() ) {
            return;
        }

        $route = self::route_from_request();
        if ( ! $route ) {
            return;
        }

        // Let browser-confirmation POSTs wait until `init` has completed so any
        // integrations that register `wft_download_tracked` callbacks on init
        // keep the same hook timing they had before v0.1.14.
        if ( 'track' === $route['action'] ) {
            return;
        }

        self::handle_route( $route['key'], $route['action'] );
    }

    /**
     * Handle the confirmation route after init but before the main query/theme.
     * This keeps the POST lightweight while preserving init-registered hooks.
     */
    public static function handle_parsed_request( $wp ): void {
        if ( ! is_object( $wp ) || empty( $wp->query_vars['wft_download_key'] ) ) {
            return;
        }

        $key = sanitize_text_field( (string) $wp->query_vars['wft_download_key'] );
        if ( '' === $key ) {
            return;
        }

        if ( ! empty( $wp->query_vars['wft_download_track'] ) ) {
            self::handle_route( $key, 'track' );
        }

        if ( ! empty( $wp->query_vars['wft_download_retry'] ) ) {
            self::handle_route( $key, 'retry' );
        }
    }

    /**
     * Fallback router for environments where the early URI parser cannot resolve
     * the request. Pretty and plain-permalink routes are both supported.
     */
    public static function handle_download(): void {
        $key = (string) get_query_var( 'wft_download_key' );
        if ( '' === $key ) {
            return;
        }

        $action = 'handoff';
        if ( '1' === (string) get_query_var( 'wft_download_track' ) ) {
            $action = 'track';
        } elseif ( '1' === (string) get_query_var( 'wft_download_retry' ) ) {
            $action = 'retry';
        }

        self::handle_route( sanitize_text_field( $key ), $action );
    }

    private static function route_from_request(): ?array {
        if ( isset( $_GET['wft_download_key'] ) ) {
            $key = sanitize_text_field( wp_unslash( $_GET['wft_download_key'] ) );
            if ( '' !== $key ) {
                $action = 'handoff';
                if ( isset( $_GET['wft_download_track'] ) && '1' === (string) wp_unslash( $_GET['wft_download_track'] ) ) {
                    $action = 'track';
                } elseif ( isset( $_GET['wft_download_retry'] ) && '1' === (string) wp_unslash( $_GET['wft_download_retry'] ) ) {
                    $action = 'retry';
                }

                return array(
                    'key'    => $key,
                    'action' => $action,
                );
            }
        }

        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        if ( '' === $request_uri ) {
            return null;
        }

        $path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
        if ( '' === $path ) {
            return null;
        }

        if ( ! preg_match( '#(?:^|/)wft-download/([^/]+)(?:/(retry|track))?/?$#i', $path, $matches ) ) {
            return null;
        }

        $key = sanitize_text_field( rawurldecode( (string) $matches[1] ) );
        if ( '' === $key ) {
            return null;
        }

        $action = isset( $matches[2] ) && '' !== $matches[2]
            ? strtolower( sanitize_key( (string) $matches[2] ) )
            : 'handoff';

        return array(
            'key'    => $key,
            'action' => $action,
        );
    }

    private static function handle_route( string $key, string $action ): void {
        $method = self::request_method();

        if ( 'track' === $action ) {
            self::handle_tracking_post( $key, $method );
        }

        if ( 'retry' === $action ) {
            if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
                self::method_not_allowed( 'GET, HEAD' );
            }

            $tracker = self::get_tracker( $key, false );
            if ( ! $tracker ) {
                self::not_found();
            }

            self::redirect_to_file( $tracker );
        }

        if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
            self::method_not_allowed( 'GET, HEAD' );
        }

        // HEAD and recognizable preview/scanner probes never write tracking data
        // and are answered without a database lookup whenever possible.
        if ( 'HEAD' === $method ) {
            self::probe_response( 200 );
        }

        if ( self::looks_like_prefetch_or_bot() ) {
            self::probe_response( 204 );
        }

        $tracker = self::get_tracker( $key, true );
        if ( ! $tracker ) {
            self::not_found();
        }

        $source         = isset( $_GET['via'] ) ? sanitize_key( wp_unslash( $_GET['via'] ) ) : 'external';
        $source         = WFT_Downloads::sanitize_source( $source );
        $via_page_id    = isset( $_GET['via_page'] ) ? absint( wp_unslash( $_GET['via_page'] ) ) : 0;
        $via_page_title = '';

        if ( $via_page_id > 0 ) {
            $via_post = get_post( $via_page_id );
            if ( ! $via_post || ! is_post_publicly_viewable( $via_post ) ) {
                $via_page_id = 0;
            } else {
                $via_page_title = sanitize_text_field( get_the_title( $via_page_id ) );
            }
        }

        WFT_Download_Page::render_handoff( $tracker, $source, $via_page_id, $via_page_title );
        exit;
    }

    private static function handle_tracking_post( string $key, string $method ): void {
        if ( 'POST' !== $method ) {
            self::method_not_allowed( 'POST' );
        }

        self::send_no_store_headers();

        // Browser handoff JavaScript adds this same-origin custom header. Basic
        // email/link scanners normally fetch only the initial GET and therefore
        // never satisfy this confirmation step.
        $browser_header = isset( $_SERVER['HTTP_X_WP_FILETRACE'] )
            ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_FILETRACE'] ) ) )
            : '';

        if ( self::TRACK_HEADER_VALUE !== $browser_header || self::looks_like_prefetch_or_bot() ) {
            status_header( 204 );
            exit;
        }

        $tracker = self::get_tracker( $key, false );
        if ( ! $tracker ) {
            self::not_found();
        }

        $token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
        if ( ! WFT_Download_Page::validate_track_token( $tracker, $token ) ) {
            status_header( 403 );
            exit;
        }

        $source  = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : 'external';
        $source  = WFT_Downloads::sanitize_source( $source );
        $tracked = WFT_Downloads::track( $tracker, $source );

        status_header( $tracked ? 204 : 503 );
        exit;
    }

    private static function request_method(): string {
        return isset( $_SERVER['REQUEST_METHOD'] )
            ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
            : 'GET';
    }

    private static function get_tracker( string $key, bool $allow_cache ) {
        $key = sanitize_text_field( $key );
        if ( '' === $key ) {
            return null;
        }

        if ( ! $allow_cache ) {
            return WFT_Downloads::get_by_key( $key );
        }

        $cache_key = 'tracker_' . md5( $key );
        $cached    = wp_cache_get( $cache_key, self::TRACKER_CACHE_GROUP );
        if ( false !== $cached ) {
            return '__missing__' === $cached ? null : $cached;
        }

        $tracker = WFT_Downloads::get_by_key( $key );
        wp_cache_set(
            $cache_key,
            $tracker ?: '__missing__',
            self::TRACKER_CACHE_GROUP,
            self::TRACKER_CACHE_TTL
        );

        return $tracker ?: null;
    }

    private static function redirect_to_file( $tracker ): void {
        self::send_no_store_headers();

        // The destination is saved by a manage_options user and may
        // intentionally live off-domain.
        wp_redirect( esc_url_raw( $tracker->file_url ), 302, 'WP FileTrace' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
        exit;
    }

    private static function probe_response( int $status ): void {
        status_header( $status );
        header( 'Cache-Control: public, max-age=300, s-maxage=3600, stale-while-revalidate=60', true );
        header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
        header( 'X-WP-FileTrace-Probe: 1', true );
        header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset', 'UTF-8' ), true );
        exit;
    }

    private static function send_no_store_headers(): void {
        nocache_headers();
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true );
        header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
    }

    private static function not_found(): void {
        status_header( 404 );
        self::send_no_store_headers();
        header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset', 'UTF-8' ), true );
        echo esc_html__( 'This tracked download link could not be found.', 'wp-filetrace' );
        exit;
    }

    private static function method_not_allowed( string $allow ): void {
        status_header( 405 );
        self::send_no_store_headers();
        header( 'Allow: ' . $allow, true );
        exit;
    }

    private static function looks_like_prefetch_or_bot(): bool {
        $purpose = '';
        if ( isset( $_SERVER['HTTP_PURPOSE'] ) ) {
            $purpose .= ' ' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_PURPOSE'] ) );
        }
        if ( isset( $_SERVER['HTTP_SEC_PURPOSE'] ) ) {
            $purpose .= ' ' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_PURPOSE'] ) );
        }

        if ( false !== stripos( $purpose, 'prefetch' ) || false !== stripos( $purpose, 'preview' ) ) {
            return true;
        }

        $ua = isset( $_SERVER['HTTP_USER_AGENT'] )
            ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) )
            : '';

        if ( '' === $ua ) {
            return false;
        }

        $known_scanners = array(
            'googleimageproxy',
            'google-inspectiontool',
            'facebookexternalhit',
            'slackbot',
            'discordbot',
            'microsoft office existence discovery',
            'microsoftpreview',
            'skypeuripreview',
            'urlpreview',
            'linkpreview',
            'whatsapp',
            'telegrambot',
            'twitterbot',
            'linkedinbot',
        );

        foreach ( $known_scanners as $needle ) {
            if ( false !== strpos( $ua, $needle ) ) {
                return true;
            }
        }

        return (bool) apply_filters( 'wft_is_prefetch_or_bot', false, $ua, $purpose );
    }
}
