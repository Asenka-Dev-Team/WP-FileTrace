<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WFT_Router {
    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'register_rewrite_rule' ) );
        add_action( 'init', array( __CLASS__, 'maybe_refresh_rewrite_rules' ), 99 );
        add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
        add_action( 'template_redirect', array( __CLASS__, 'handle_download' ), 0 );
    }

    public static function register_rewrite_rule(): void {
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
        return $vars;
    }

    public static function handle_download(): void {
        $key = (string) get_query_var( 'wft_download_key' );
        if ( '' === $key ) {
            return;
        }

        $tracker = WFT_Downloads::get_by_key( sanitize_text_field( $key ) );
        if ( ! $tracker ) {
            status_header( 404 );
            nocache_headers();
            wp_die( esc_html__( 'This tracked download link could not be found.', 'wp-filetrace' ), esc_html__( 'Download not found', 'wp-filetrace' ), array( 'response' => 404 ) );
        }

        $method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';

        // Retry requests are intentionally not tracked. The original tracked
        // request already incremented counters and, if configured, fired gtag.
        $is_retry = '1' === (string) get_query_var( 'wft_download_retry' );
        if ( $is_retry ) {
            self::redirect_to_file( $tracker );
        }

        $source  = isset( $_GET['via'] ) ? sanitize_key( wp_unslash( $_GET['via'] ) ) : 'external';
        $tracked = false;

        if ( 'GET' === $method && ! self::looks_like_prefetch_or_bot() ) {
            $source  = WFT_Downloads::sanitize_source( $source );
            $tracked = WFT_Downloads::track( $tracker, $source );
        }

        if ( $tracked ) {
            WFT_Download_Page::render_handoff( $tracker, $source );
            exit;
        }

        self::redirect_to_file( $tracker );
    }

    private static function redirect_to_file( $tracker ): void {
        nocache_headers();
        // The destination is saved by a manage_options user and may intentionally live off-domain.
        wp_redirect( esc_url_raw( $tracker->file_url ), 302, 'WP FileTrace' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
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
        if ( false !== stripos( $purpose, 'prefetch' ) ) {
            return true;
        }

        $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
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
        );

        foreach ( $known_scanners as $needle ) {
            if ( false !== strpos( $ua, $needle ) ) {
                return true;
            }
        }

        return (bool) apply_filters( 'wft_is_prefetch_or_bot', false, $ua, $purpose );
    }
}
