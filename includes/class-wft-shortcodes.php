<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WFT_Shortcodes {
    public static function init(): void {
        add_shortcode( 'wft', array( __CLASS__, 'render' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
        add_action( 'wp_footer', array( __CLASS__, 'output_page_context_helper' ), 90 );
    }

    public static function register_assets(): void {
        wp_register_style(
            'wft-frontend',
            WFT_URL . 'assets/css/frontend.css',
            array(),
            WFT_VERSION
        );
    }

    public static function render( array $atts = array() ): string {
        $atts = shortcode_atts(
            array(
                'media' => 0,
                'url'   => '',
                'text'  => __( 'Download', 'wp-filetrace' ),
                'class' => '',
            ),
            $atts,
            'wft'
        );

        $tracker = WFT_Downloads::get_or_create(
            absint( $atts['media'] ),
            (string) $atts['url']
        );

        if ( is_wp_error( $tracker ) ) {
            if ( current_user_can( 'manage_options' ) ) {
                return '<span class="wft-error">' . esc_html( $tracker->get_error_message() ) . '</span>';
            }
            return '';
        }

        wp_enqueue_style( 'wft-frontend' );

        $classes = array( 'wft-download-button' );
        if ( '' !== trim( (string) $atts['class'] ) ) {
            foreach ( preg_split( '/\s+/', (string) $atts['class'] ) as $class ) {
                $classes[] = sanitize_html_class( $class );
            }
        }

        $via_page_id = is_singular() ? absint( get_queried_object_id() ) : 0;
        $url         = WFT_Downloads::build_tracked_url( $tracker, 'shortcode', $via_page_id );

        return sprintf(
            '<a class="%1$s" href="%2$s" rel="nofollow">%3$s</a>',
            esc_attr( implode( ' ', array_filter( $classes ) ) ),
            esc_url( $url ),
            esc_html( (string) $atts['text'] )
        );
    }

    /**
     * Add the current WordPress content ID to manually embedded WP FileTrace
     * tracked links. This complements shortcode URLs, which already carry the
     * originating content ID when rendered.
     */
    public static function output_page_context_helper(): void {
        if ( is_admin() || ! is_singular() ) {
            return;
        }

        $page_id = absint( get_queried_object_id() );
        if ( $page_id <= 0 ) {
            return;
        }
        ?>
<script id="wft-via-page-helper">
(function () {
    'use strict';

    var pageId = <?php echo (int) $page_id; ?>;
    var downloadPath = /\/wft-download\/[^/]+\/?$/;

    document.addEventListener('click', function (event) {
        var link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
        if (!link) {
            return;
        }

        try {
            var url = new URL(link.href, window.location.href);
            if (url.origin !== window.location.origin) {
                return;
            }

            var isPrettyTrackedLink = downloadPath.test(url.pathname);
            var isPlainTrackedLink = url.searchParams.has('wft_download_key') && url.searchParams.get('wft_download_retry') !== '1';

            if (!isPrettyTrackedLink && !isPlainTrackedLink) {
                return;
            }

            url.searchParams.set('via_page', String(pageId));
            link.href = url.toString();
        } catch (error) {
            // Leave the original link untouched if URL parsing is unavailable.
        }
    }, true);
}());
</script>
        <?php
    }
}
