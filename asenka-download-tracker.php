<?php
/**
 * Plugin Name: Asenka Download Tracker
 * Plugin URI: https://asenka.com/
 * Description: Track WordPress media and external file downloads through shortcodes and shareable tracked links.
 * Version: 0.1.1
 * Author: Asenka Interactive
 * Author URI: https://asenka.com/
 * Text Domain: asenka-download-tracker
 * Requires at least: 6.4
 * Requires PHP: 8.0
 *
 * Primary Developer: Brian McLendon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ADT_VERSION', '0.1.1' );
define( 'ADT_FILE', __FILE__ );
define( 'ADT_PATH', plugin_dir_path( __FILE__ ) );
define( 'ADT_URL', plugin_dir_url( __FILE__ ) );

require_once ADT_PATH . 'includes/class-adt-db.php';
require_once ADT_PATH . 'includes/class-adt-downloads.php';
require_once ADT_PATH . 'includes/class-adt-shortcodes.php';
require_once ADT_PATH . 'includes/class-adt-router.php';
require_once ADT_PATH . 'includes/class-adt-export.php';
require_once ADT_PATH . 'includes/class-adt-admin.php';

final class Asenka_Download_Tracker {
    private static ?Asenka_Download_Tracker $instance = null;

    public static function instance(): Asenka_Download_Tracker {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        ADT_DB::init();
        ADT_Downloads::init();
        ADT_Shortcodes::init();
        ADT_Router::init();
        ADT_Export::init();

        if ( is_admin() ) {
            ADT_Admin::init();
        }
    }

    public static function activate(): void {
        ADT_DB::install();
        ADT_Router::register_rewrite_rule();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }
}

register_activation_hook( __FILE__, array( 'Asenka_Download_Tracker', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Asenka_Download_Tracker', 'deactivate' ) );

add_action( 'plugins_loaded', static function (): void {
    Asenka_Download_Tracker::instance();
} );
