<?php
/**
 * Plugin Name: WP FileTrace
 * Plugin URI: https://asenka.com/
 * Description: Track WordPress media and external file downloads through shortcodes and shareable tracked links.
 * Version: 0.1.8
 * Author: Asenka Interactive
 * Author URI: https://asenka.com/
 * Text Domain: wp-filetrace
 * Requires at least: 6.4
 * Requires PHP: 8.0
 *
 * Primary Developer: Brian McLendon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WFT_VERSION', '0.1.8' );
define( 'WFT_FILE', __FILE__ );
define( 'WFT_PATH', plugin_dir_path( __FILE__ ) );
define( 'WFT_URL', plugin_dir_url( __FILE__ ) );

/**
 * GitHub repository used for release-based plugin updates.
 */
define( 'WFT_GITHUB_REPOSITORY', 'Asenka-Dev-Team/WP-FileTrace' );

/**
 * Beta/experimental feature defaults. The saved Settings value controls the
 * runtime boolean, while the filter keeps this extensible for developers.
 */
define( 'WFT_ENABLE_SDM_MIGRATION_DEFAULT', false );
define( 'WFT_ENABLE_TEST_ROWS_DEFAULT', false );

function wft_sdm_migration_enabled(): bool {
    $saved = get_option( 'wft_enable_sdm_migration', WFT_ENABLE_SDM_MIGRATION_DEFAULT ? '1' : '0' );
    $enabled = in_array( $saved, array( true, 1, '1', 'yes', 'on' ), true );

    return (bool) apply_filters( 'wft_enable_migration', $enabled );
}

function wft_test_rows_enabled(): bool {
    $saved = get_option( 'wft_enable_test_rows', WFT_ENABLE_TEST_ROWS_DEFAULT ? '1' : '0' );
    $enabled = in_array( $saved, array( true, 1, '1', 'yes', 'on' ), true );

    return (bool) apply_filters( 'wft_enable_test_rows', $enabled );
}

require_once WFT_PATH . 'includes/class-wft-db.php';
require_once WFT_PATH . 'includes/class-wft-downloads.php';
require_once WFT_PATH . 'includes/class-wft-shortcodes.php';
require_once WFT_PATH . 'includes/class-wft-router.php';
require_once WFT_PATH . 'includes/class-wft-export.php';

if ( wft_sdm_migration_enabled() ) {
    require_once WFT_PATH . 'includes/class-wft-sdm-migration.php';
}

require_once WFT_PATH . 'includes/class-wft-admin.php';
require_once WFT_PATH . 'includes/class-wft-analytics.php';
require_once WFT_PATH . 'includes/class-wft-updater.php';

final class WP_FileTrace {
    private static ?WP_FileTrace $instance = null;

    public static function instance(): WP_FileTrace {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        WFT_DB::init();
        WFT_Downloads::init();
        WFT_Shortcodes::init();
        WFT_Router::init();
        WFT_Export::init();
        WFT_Analytics::init();
        WFT_Updater::init();

        if ( is_admin() ) {
            WFT_Admin::init();
        }
    }

    public static function activate(): void {
        WFT_DB::install();
        WFT_Router::register_rewrite_rule();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }
}

register_activation_hook( __FILE__, array( 'WP_FileTrace', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WP_FileTrace', 'deactivate' ) );

add_action( 'plugins_loaded', static function (): void {
    WP_FileTrace::instance();
} );
