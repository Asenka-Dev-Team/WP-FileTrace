<?php
/**
 * GitHub release updater for WP FileTrace.
 *
 * Primary Developer: Brian McLendon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WFT_Updater {
    private const CACHE_KEY = 'wft_github_latest_release';
    private const CACHE_TTL = 21600; // 6 hours.

    private static string $plugin_basename = '';

    public static function init(): void {
        self::$plugin_basename = plugin_basename( WFT_FILE );

        if ( ! self::has_repository() ) {
            if ( is_admin() ) {
                add_action( 'admin_notices', array( __CLASS__, 'repository_not_configured_notice' ) );
            }
            return;
        }

        add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_update' ) );
        add_filter( 'plugins_api', array( __CLASS__, 'plugin_information' ), 20, 3 );
        add_filter( 'upgrader_source_selection', array( __CLASS__, 'normalize_github_source_directory' ), 10, 4 );
        add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_cache_after_upgrade' ), 10, 2 );
        add_action( 'delete_site_transient_update_plugins', array( __CLASS__, 'clear_release_cache' ) );
    }


    public static function repository_not_configured_notice(): void {
        if ( ! current_user_can( 'update_plugins' ) ) {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__( 'WP FileTrace GitHub updates are not configured yet. Set WFT_GITHUB_REPOSITORY in wp-filetrace.php to your GitHub owner/repository value before publishing this build.', 'wp-filetrace' );
        echo '</p></div>';
    }

    private static function has_repository(): bool {
        return defined( 'WFT_GITHUB_REPOSITORY' )
            && is_string( WFT_GITHUB_REPOSITORY )
            && '' !== trim( WFT_GITHUB_REPOSITORY )
            && ! in_array( WFT_GITHUB_REPOSITORY, array( 'OWNER/wp-filetrace', 'OWNER/WP-FileTrace' ), true );
    }

    public static function check_for_update( $transient ) {
        if ( ! is_object( $transient ) ) {
            return $transient;
        }

        $release = self::get_latest_release();

        if ( ! $release ) {
            return $transient;
        }

        $remote_version = self::normalize_version( (string) ( $release['tag_name'] ?? '' ) );
        $package_url    = self::find_release_package( $release, $remote_version );

        if ( '' === $remote_version || ! version_compare( WFT_VERSION, $remote_version, '<' ) || '' === $package_url ) {
            return $transient;
        }

        $update = (object) array(
            'id'           => self::repository_url(),
            'slug'         => 'wp-filetrace',
            'plugin'       => self::$plugin_basename,
            'new_version'  => $remote_version,
            'url'          => self::repository_url(),
            'package'      => $package_url,
            'requires_php' => '8.0',
            'tested'       => '',
        );

        $transient->response[ self::$plugin_basename ] = $update;

        return $transient;
    }

    public static function plugin_information( $result, string $action, $args ) {
        if ( 'plugin_information' !== $action || empty( $args->slug ) || 'wp-filetrace' !== $args->slug ) {
            return $result;
        }

        $release = self::get_latest_release();

        if ( ! $release ) {
            return $result;
        }

        $remote_version = self::normalize_version( (string) ( $release['tag_name'] ?? '' ) );
        $release_notes  = isset( $release['body'] ) && is_string( $release['body'] ) ? $release['body'] : '';

        return (object) array(
            'name'          => 'WP FileTrace',
            'slug'          => 'wp-filetrace',
            'version'       => $remote_version ?: WFT_VERSION,
            'author'        => '<a href="https://asenka.com/">Asenka Interactive</a>',
            'homepage'      => self::repository_url(),
            'requires'      => '6.4',
            'requires_php'  => '8.0',
            'download_link' => self::find_release_package( $release, $remote_version ),
            'sections'      => array(
                'description' => 'Tracked downloads and file analytics for WordPress.',
                'changelog'   => '' !== trim( $release_notes )
                    ? wpautop( esc_html( $release_notes ) )
                    : '<p>See changelog.md in the WP FileTrace repository for release history.</p>',
            ),
        );
    }

    public static function clear_release_cache(): void {
        delete_site_transient( self::CACHE_KEY );
    }

    public static function clear_cache_after_upgrade( $upgrader, array $options ): void {
        if ( 'update' !== ( $options['action'] ?? '' ) || 'plugin' !== ( $options['type'] ?? '' ) ) {
            return;
        }

        $plugins = isset( $options['plugins'] ) && is_array( $options['plugins'] ) ? $options['plugins'] : array();

        if ( in_array( self::$plugin_basename, $plugins, true ) ) {
            delete_site_transient( self::CACHE_KEY );
        }
    }

    private static function get_latest_release(): ?array {
        $cached = get_site_transient( self::CACHE_KEY );

        if ( is_array( $cached ) ) {
            return $cached;
        }

        $endpoint = sprintf(
            'https://api.github.com/repos/%s/releases/latest',
            rawurlencode( self::repository_owner() ) . '/' . rawurlencode( self::repository_name() )
        );

        $response = wp_remote_get(
            $endpoint,
            array(
                'timeout' => 10,
                'headers' => array(
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'WP-FileTrace/' . WFT_VERSION,
                ),
            )
        );

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return null;
        }

        $release = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $release ) || ! empty( $release['draft'] ) || ! empty( $release['prerelease'] ) ) {
            return null;
        }

        set_site_transient( self::CACHE_KEY, $release, self::CACHE_TTL );

        return $release;
    }

    private static function find_release_package( array $release, string $version ): string {
        unset( $version );

        if ( empty( $release['zipball_url'] ) || ! is_string( $release['zipball_url'] ) ) {
            return '';
        }

        return esc_url_raw( $release['zipball_url'] );
    }

    /**
     * GitHub source archives extract to a repository/tag-specific directory.
     * Normalize that directory to wp-filetrace so WordPress replaces the
     * existing plugin folder instead of installing a second version beside it.
     *
     * @param string|WP_Error $source        Extracted source directory.
     * @param string          $remote_source Working directory used by the upgrader.
     * @param WP_Upgrader     $upgrader      Current upgrader instance.
     * @param array           $hook_extra    Upgrade context.
     * @return string|WP_Error
     */
    public static function normalize_github_source_directory( $source, string $remote_source, $upgrader, array $hook_extra ) {
        unset( $upgrader );

        if ( is_wp_error( $source ) || ! self::is_our_upgrade( $hook_extra ) ) {
            return $source;
        }

        global $wp_filesystem;

        if ( ! $wp_filesystem ) {
            return $source;
        }

        $source = trailingslashit( (string) $source );
        if ( ! $wp_filesystem->exists( $source . 'wp-filetrace.php' ) ) {
            return new WP_Error(
                'wft_invalid_update_package',
                __( 'The WP FileTrace GitHub archive does not contain wp-filetrace.php at its repository root.', 'wp-filetrace' )
            );
        }

        $normalized_source = trailingslashit( $remote_source ) . 'wp-filetrace/';

        if ( untrailingslashit( $source ) === untrailingslashit( $normalized_source ) ) {
            return $source;
        }

        if ( $wp_filesystem->exists( $normalized_source ) ) {
            $wp_filesystem->delete( $normalized_source, true );
        }

        if ( ! $wp_filesystem->move( $source, $normalized_source, true ) ) {
            return new WP_Error(
                'wft_update_directory_normalization_failed',
                __( 'WP FileTrace could not normalize the GitHub update directory.', 'wp-filetrace' )
            );
        }

        return $normalized_source;
    }

    private static function is_our_upgrade( array $hook_extra ): bool {
        if ( isset( $hook_extra['plugin'] ) && self::$plugin_basename === $hook_extra['plugin'] ) {
            return true;
        }

        if ( isset( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) && in_array( self::$plugin_basename, $hook_extra['plugins'], true ) ) {
            return true;
        }

        return isset( $hook_extra['slug'] ) && 'wp-filetrace' === $hook_extra['slug'];
    }

    private static function normalize_version( string $tag ): string {
        $version = ltrim( trim( $tag ), "vV \t\n\r\0\x0B" );

        return preg_match( '/^[0-9]+(?:\.[0-9A-Za-z-]+)+$/', $version ) ? $version : '';
    }

    private static function repository_url(): string {
        return 'https://github.com/' . self::repository_owner() . '/' . self::repository_name() . '/';
    }

    private static function repository_owner(): string {
        $parts = explode( '/', trim( WFT_GITHUB_REPOSITORY, '/' ), 2 );
        return $parts[0] ?? '';
    }

    private static function repository_name(): string {
        $parts = explode( '/', trim( WFT_GITHUB_REPOSITORY, '/' ), 2 );
        return $parts[1] ?? '';
    }
}
