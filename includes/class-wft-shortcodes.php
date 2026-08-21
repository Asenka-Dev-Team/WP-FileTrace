<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WFT_Shortcodes {
    public static function init(): void {
        add_shortcode( 'wft', array( __CLASS__, 'render' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
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

        $url = WFT_Downloads::build_tracked_url( $tracker, 'shortcode' );

        return sprintf(
            '<a class="%1$s" href="%2$s" rel="nofollow">%3$s</a>',
            esc_attr( implode( ' ', array_filter( $classes ) ) ),
            esc_url( $url ),
            esc_html( (string) $atts['text'] )
        );
    }
}
