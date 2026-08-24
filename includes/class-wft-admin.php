<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WFT_Admin {
    private const PAGE_SLUG = 'wp-filetrace';
    private const PER_PAGE  = 20;

    public static function init(): void {
        add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
        add_action( 'admin_head', array( __CLASS__, 'admin_menu_icon_css' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'wp_ajax_wft_create_tracker', array( __CLASS__, 'ajax_create_tracker' ) );
        add_action( 'wp_ajax_wft_render_admin_view', array( __CLASS__, 'ajax_render_admin_view' ) );
        add_action( 'wp_ajax_wft_update_tracker', array( __CLASS__, 'ajax_update_tracker' ) );
        add_action( 'wp_ajax_wft_delete_tracker', array( __CLASS__, 'ajax_delete_tracker' ) );
        add_action( 'wp_ajax_wft_delete_selected_trackers', array( __CLASS__, 'ajax_delete_selected_trackers' ) );
        add_action( 'wp_ajax_wft_delete_all_trackers', array( __CLASS__, 'ajax_delete_all_trackers' ) );
        add_action( 'wp_ajax_wft_generate_test_rows', array( __CLASS__, 'ajax_generate_test_rows' ) );
        add_action( 'wp_ajax_wft_save_analytics', array( __CLASS__, 'ajax_save_analytics' ) );
        add_action( 'wp_ajax_wft_check_updates', array( __CLASS__, 'ajax_check_updates' ) );
        add_action( 'wp_ajax_wft_save_settings', array( __CLASS__, 'ajax_save_settings' ) );

        if ( wft_sdm_migration_enabled() ) {
            add_action( 'wp_ajax_wft_sdm_scan', array( __CLASS__, 'ajax_sdm_scan' ) );
            add_action( 'wp_ajax_wft_sdm_apply', array( __CLASS__, 'ajax_sdm_apply' ) );
            add_action( 'wp_ajax_wft_sdm_rollback', array( __CLASS__, 'ajax_sdm_rollback' ) );
            add_action( 'wp_ajax_wft_sdm_discard_rollback', array( __CLASS__, 'ajax_sdm_discard_rollback' ) );
        }
        add_action( 'admin_post_wft_update_tracker', array( __CLASS__, 'update_tracker' ) );
        add_action( 'admin_post_wft_delete_tracker', array( __CLASS__, 'delete_tracker' ) );
        add_action( 'admin_post_wft_delete_selected_trackers', array( __CLASS__, 'delete_selected_trackers' ) );
        add_action( 'admin_post_wft_delete_all_trackers', array( __CLASS__, 'delete_all_trackers' ) );
        add_action( 'admin_post_wft_generate_test_rows', array( __CLASS__, 'generate_test_rows' ) );
        add_action( 'admin_post_wft_save_analytics', array( __CLASS__, 'save_analytics' ) );
        add_action( 'admin_post_wft_check_updates', array( __CLASS__, 'check_updates' ) );
        add_action( 'admin_post_wft_save_settings', array( __CLASS__, 'save_settings' ) );

        if ( wft_sdm_migration_enabled() ) {
            add_action( 'admin_post_wft_sdm_scan', array( __CLASS__, 'sdm_scan' ) );
            add_action( 'admin_post_wft_sdm_apply', array( __CLASS__, 'sdm_apply' ) );
            add_action( 'admin_post_wft_sdm_rollback', array( __CLASS__, 'sdm_rollback' ) );
            add_action( 'admin_post_wft_sdm_discard_rollback', array( __CLASS__, 'sdm_discard_rollback' ) );
        }
        add_filter( 'plugin_action_links_' . plugin_basename( WFT_FILE ), array( __CLASS__, 'plugin_action_links' ) );
        add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
    }

    public static function admin_menu(): void {
        add_menu_page(
            __( 'WP FileTrace', 'wp-filetrace' ),
            __( 'WP FileTrace', 'wp-filetrace' ),
            'manage_options',
            self::PAGE_SLUG,
            array( __CLASS__, 'render_page' ),
            WFT_URL . 'assets/images/icon--wp-filetrace.svg',
            58
        );
    }

    public static function admin_menu_icon_css(): void {
        ?>
        <style id="wft-admin-menu-icon-css">
            #adminmenu .toplevel_page_<?php echo esc_attr( self::PAGE_SLUG ); ?> .wp-menu-image img {
                width: 20px !important;
                height: 20px !important;
                max-width: 20px !important;
                max-height: 20px !important;
                object-fit: contain;
            }
        </style>
        <?php
    }

    public static function enqueue_assets( string $hook ): void {
        if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style( 'wft-admin', WFT_URL . 'assets/css/admin.css', array(), WFT_VERSION );
        wp_enqueue_script( 'wft-admin', WFT_URL . 'assets/js/admin.js', array( 'jquery' ), WFT_VERSION, true );
        wp_localize_script(
            'wft-admin',
            'WFTAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'pageUrl' => admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
                'nonce'   => wp_create_nonce( 'wft_admin' ),
                'strings' => array(
                    'selectFile'     => __( 'Select a file', 'wp-filetrace' ),
                    'useFile'        => __( 'Use this file', 'wp-filetrace' ),
                    'working'        => __( 'Generating…', 'wp-filetrace' ),
                    'generate'       => __( 'Generate Tracking Link', 'wp-filetrace' ),
                    'loading'        => __( 'Loading…', 'wp-filetrace' ),
                    'saving'         => __( 'Saving…', 'wp-filetrace' ),
                    'deleting'       => __( 'Deleting…', 'wp-filetrace' ),
                    'checking'       => __( 'Checking…', 'wp-filetrace' ),
                    'testing'        => __( 'Generating test rows…', 'wp-filetrace' ),
                    'copied'         => __( 'Copied!', 'wp-filetrace' ),
                    'copy'           => __( 'Copy', 'wp-filetrace' ),
                    'genericError'   => __( 'Something went wrong. Please try again.', 'wp-filetrace' ),
                    'created'        => __( 'Tracked file added.', 'wp-filetrace' ),
                    'confirmDelete'         => __( 'Are you sure? This will permanently delete this tracked file and all of its download history. This cannot be undone.', 'wp-filetrace' ),
                    'confirmDeleteSelected' => __( 'Are you sure? This will permanently delete the selected tracked files and all of their download history. This cannot be undone.', 'wp-filetrace' ),
                    'confirmDeleteAll'      => __( 'Are you sure? This will permanently delete ALL tracked files on every page and all download history. This cannot be undone.', 'wp-filetrace' ),
                    'confirmTest'           => __( 'Create 200 synthetic tracked-file rows for sorting and pagination testing?', 'wp-filetrace' ),
                    'scanningMigration'     => __( 'Scanning…', 'wp-filetrace' ),
                    'applyingMigration'     => __( 'Migrating…', 'wp-filetrace' ),
                    'rollingBackMigration'  => __( 'Rolling back…', 'wp-filetrace' ),
                    'discardingMigration'   => __( 'Discarding backup…', 'wp-filetrace' ),
                    'confirmMigrationApply' => __( 'Apply all migration rows marked Ready? WP FileTrace will create/reuse trackers, back up affected post content, and replace those SDM shortcodes.', 'wp-filetrace' ),
                    'confirmMigrationRollback' => __( 'Restore the backed-up post content from before the WP FileTrace migration? WP FileTrace tracker records will be left in place.', 'wp-filetrace' ),
                    'confirmMigrationDiscard' => __( 'Discard the migration rollback backup? This does not change current post content and cannot be undone.', 'wp-filetrace' ),
                ),
            )
        );
    }

    public static function plugin_action_links( array $links ): array {
        array_unshift(
            $links,
            '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'wp-filetrace' ) . '</a>'
        );
        return $links;
    }

    public static function plugin_row_meta( array $links, string $file ): array {
        if ( plugin_basename( WFT_FILE ) === $file ) {
            $links[] = '<a href="https://asenka.com/" target="_blank" rel="noopener noreferrer">Asenka.com</a>';
        }
        return $links;
    }

    private static function available_tabs(): array {
        $tabs = array( 'tracked', 'analytics', 'updates', 'settings' );
        if ( wft_sdm_migration_enabled() ) {
            $tabs[] = 'migration';
        }
        return $tabs;
    }

    private static function migration_enabled_or_error( bool $ajax = false ): void {
        if ( wft_sdm_migration_enabled() && class_exists( 'WFT_SDM_Migration' ) ) {
            return;
        }

        $message = __( 'The Simple Download Monitor migration beta is disabled. Enable it from WP FileTrace → Settings first.', 'wp-filetrace' );
        if ( $ajax ) {
            wp_send_json_error( array( 'message' => $message ), 403 );
        }
        wp_die( esc_html( $message ) );
    }

    public static function ajax_create_tracker(): void {
        self::ajax_guard();

        $attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
        $url           = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
        $title         = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $button_text   = isset( $_POST['button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['button_text'] ) ) : '';

        $tracker = WFT_Downloads::get_or_create( $attachment_id, $url, $title, $button_text );
        if ( is_wp_error( $tracker ) ) {
            wp_send_json_error( array( 'message' => $tracker->get_error_message() ), 400 );
        }

        $page = WFT_Downloads::get_created_desc_page_for_id( (int) $tracker->id, self::PER_PAGE );

        self::ajax_send_page(
            array(
                'tab'         => 'tracked',
                'orderby'     => 'created_at',
                'order'       => 'desc',
                'paged'       => $page,
                'wft_created' => (int) $tracker->id,
            ),
            array(
                'id'    => (int) $tracker->id,
                'title' => $tracker->title,
            )
        );
    }

    public static function ajax_render_admin_view(): void {
        self::ajax_guard();

        $tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'tracked';
        if ( ! in_array( $tab, self::available_tabs(), true ) ) {
            $tab = 'tracked';
        }

        $query = array( 'tab' => $tab );

        if ( 'tracked' === $tab ) {
            $state = self::list_state_from_request();
            $query = array_merge( $query, $state );
        } elseif ( 'migration' === $tab && ! empty( $_POST['wft_sdm_scan'] ) ) {
            $query['wft_sdm_scan'] = '1';
        }

        self::ajax_send_page( $query );
    }

    public static function ajax_update_tracker(): void {
        self::ajax_guard();

        $id            = isset( $_POST['tracker_id'] ) ? absint( $_POST['tracker_id'] ) : 0;
        $title         = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $url           = isset( $_POST['file_url'] ) ? esc_url_raw( wp_unslash( $_POST['file_url'] ) ) : '';
        $attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
        $button_text   = isset( $_POST['button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['button_text'] ) ) : '';
        $state         = self::list_state_from_request();

        $ok = $id > 0 && WFT_Downloads::update_tracker( $id, $title, $url, $attachment_id, $button_text );

        self::ajax_send_page(
            array_merge(
                array(
                    'tab'         => 'tracked',
                    'wft_updated' => $ok ? '1' : '0',
                ),
                $state
            )
        );
    }

    public static function ajax_delete_tracker(): void {
        self::ajax_guard();

        $id      = isset( $_POST['tracker_id'] ) ? absint( $_POST['tracker_id'] ) : 0;
        $state   = self::list_state_from_request();
        $tracker = $id > 0 ? WFT_Downloads::get_by_id( $id ) : null;

        $deleted_name = $tracker && ! empty( $tracker->title )
            ? $tracker->title
            : __( 'Untitled tracked file', 'wp-filetrace' );

        $deleted = $tracker && WFT_Downloads::delete_tracker( $id );

        self::ajax_send_page(
            array_merge(
                array(
                    'tab'              => 'tracked',
                    'wft_deleted'      => $deleted ? '1' : '0',
                    'wft_deleted_name' => $deleted ? $deleted_name : '',
                ),
                $state
            )
        );
    }

    public static function ajax_delete_selected_trackers(): void {
        self::ajax_guard();

        $raw_ids = isset( $_POST['tracker_ids'] ) && is_array( $_POST['tracker_ids'] )
            ? wp_unslash( $_POST['tracker_ids'] )
            : array();
        $ids   = array_values( array_unique( array_filter( array_map( 'absint', $raw_ids ) ) ) );
        $state = self::list_state_from_request();

        if ( empty( $ids ) ) {
            $result = 0;
            $status = 'none';
        } else {
            $result = WFT_Downloads::delete_trackers( $ids );
            $status = false === $result ? 'error' : 'success';
        }

        self::ajax_send_page(
            array_merge(
                array(
                    'tab'              => 'tracked',
                    'wft_bulk_deleted' => false === $result ? 0 : (int) $result,
                    'wft_bulk_status'  => $status,
                ),
                $state
            )
        );
    }

    public static function ajax_delete_all_trackers(): void {
        self::ajax_guard();

        $result = WFT_Downloads::delete_all_trackers();

        self::ajax_send_page(
            array(
                'tab'             => 'tracked',
                'orderby'         => 'created_at',
                'order'           => 'desc',
                'paged'           => 1,
                'wft_all_deleted' => false === $result ? 0 : (int) $result,
                'wft_all_status'  => false === $result ? 'error' : 'success',
            )
        );
    }

    public static function ajax_generate_test_rows(): void {
        self::ajax_guard();

        if ( ! wft_test_rows_enabled() ) {
            wp_send_json_error(
                array(
                    'message' => __( 'The test row generator is disabled.', 'wp-filetrace' ),
                ),
                403
            );
        }

        $created = WFT_Downloads::create_test_rows( 200 );

        self::ajax_send_page(
            array(
                'tab'          => 'tracked',
                'orderby'      => 'created_at',
                'order'        => 'desc',
                'paged'        => 1,
                'wft_test_rows'=> $created,
            )
        );
    }

    public static function ajax_save_analytics(): void {
        self::ajax_guard();

        $action = isset( $_POST['wft_analytics_action'] ) ? sanitize_key( wp_unslash( $_POST['wft_analytics_action'] ) ) : 'save';
        $notice = 'saved';

        if ( 'clear_global' === $action ) {
            WFT_Analytics::clear_global_snippet();
            $notice = 'global_cleared';
        } elseif ( 'clear_event' === $action ) {
            WFT_Analytics::clear_event_settings();
            $notice = 'event_cleared';
        } else {
            $global_snippet = isset( $_POST['wft_ga_global_snippet'] ) ? (string) wp_unslash( $_POST['wft_ga_global_snippet'] ) : '';
            $event_snippet        = isset( $_POST['wft_ga_event_snippet'] ) ? (string) wp_unslash( $_POST['wft_ga_event_snippet'] ) : '';
            $download_id_parameter = isset( $_POST['wft_ga_download_id_parameter'] ) ? sanitize_text_field( wp_unslash( $_POST['wft_ga_download_id_parameter'] ) ) : '';
            $file_parameter        = isset( $_POST['wft_ga_filename_parameter'] ) ? sanitize_text_field( wp_unslash( $_POST['wft_ga_filename_parameter'] ) ) : '';

            if ( ( '' !== trim( $global_snippet ) || '' !== trim( $event_snippet ) ) && ! current_user_can( 'unfiltered_html' ) ) {
                wp_send_json_error( array( 'message' => __( 'Your account is not allowed to save executable analytics snippets.', 'wp-filetrace' ) ), 403 );
            }

            WFT_Analytics::save_settings( $global_snippet, $event_snippet, $download_id_parameter, $file_parameter );
        }

        self::ajax_send_page(
            array(
                'tab'                  => 'analytics',
                'wft_analytics_notice' => $notice,
            )
        );
    }

    public static function ajax_save_settings(): void {
        self::ajax_guard();

        $migration_enabled = ! empty( $_POST['wft_enable_sdm_migration'] );
        $test_rows_enabled = ! empty( $_POST['wft_enable_test_rows'] );

        update_option( 'wft_enable_sdm_migration', $migration_enabled ? '1' : '0', false );
        update_option( 'wft_enable_test_rows', $test_rows_enabled ? '1' : '0', false );

        self::ajax_send_page(
            array(
                'tab'                 => 'settings',
                'wft_settings_notice' => 'saved',
            )
        );
    }

    public static function ajax_check_updates(): void {
        self::ajax_guard();

        $status = WFT_Updater::force_check();
        $notice = 'connected' === ( $status['connection'] ?? '' )
            ? ( ! empty( $status['update_available'] ) ? 'available' : 'current' )
            : 'error';

        self::ajax_send_page(
            array(
                'tab'                     => 'updates',
                'wft_update_check_notice' => $notice,
            ),
            array( 'diagnostics' => $status )
        );
    }

    public static function ajax_sdm_scan(): void {
        self::ajax_guard();
        self::migration_enabled_or_error( true );

        self::ajax_send_page(
            array(
                'tab'          => 'migration',
                'wft_sdm_scan' => '1',
            )
        );
    }

    public static function ajax_sdm_apply(): void {
        self::ajax_guard();
        self::migration_enabled_or_error( true );

        $stats = WFT_SDM_Migration::apply_safe_replacements();

        self::ajax_send_page(
            array(
                'tab'             => 'migration',
                'wft_sdm_scan'    => '1',
                'wft_sdm_applied' => '1',
            ),
            array( 'migration' => $stats )
        );
    }

    public static function ajax_sdm_rollback(): void {
        self::ajax_guard();
        self::migration_enabled_or_error( true );

        $stats = WFT_SDM_Migration::rollback();

        self::ajax_send_page(
            array(
                'tab'                => 'migration',
                'wft_sdm_scan'       => '1',
                'wft_sdm_rolledback' => '1',
            ),
            array( 'migration' => $stats )
        );
    }

    public static function ajax_sdm_discard_rollback(): void {
        self::ajax_guard();
        self::migration_enabled_or_error( true );

        $removed = WFT_SDM_Migration::discard_rollback();

        self::ajax_send_page(
            array(
                'tab'               => 'migration',
                'wft_sdm_scan'      => '1',
                'wft_sdm_discarded' => (string) $removed,
            ),
            array( 'migration' => array( 'discarded' => $removed ) )
        );
    }

    private static function ajax_guard(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-filetrace' ) ), 403 );
        }

        check_ajax_referer( 'wft_admin', 'nonce' );
    }

    private static function list_state_from_request(): array {
        $orderby = isset( $_POST['orderby'] )
            ? sanitize_key( wp_unslash( $_POST['orderby'] ) )
            : ( isset( $_POST['return_orderby'] ) ? sanitize_key( wp_unslash( $_POST['return_orderby'] ) ) : 'created_at' );
        $order = isset( $_POST['order'] )
            ? strtolower( sanitize_key( wp_unslash( $_POST['order'] ) ) )
            : ( isset( $_POST['return_order'] ) ? strtolower( sanitize_key( wp_unslash( $_POST['return_order'] ) ) ) : 'desc' );
        $paged = isset( $_POST['paged'] )
            ? max( 1, absint( $_POST['paged'] ) )
            : ( isset( $_POST['return_paged'] ) ? max( 1, absint( $_POST['return_paged'] ) ) : 1 );

        if ( ! in_array( $orderby, self::sortable_columns(), true ) ) {
            $orderby = 'created_at';
        }

        return array(
            'orderby' => $orderby,
            'order'   => 'asc' === $order ? 'asc' : 'desc',
            'paged'   => $paged,
        );
    }

    private static function ajax_send_page( array $query, array $extra = array() ): void {
        $old_get = $_GET;

        $_GET = array_merge(
            array( 'page' => self::PAGE_SLUG ),
            $query
        );

        ob_start();
        self::render_page();
        $html = (string) ob_get_clean();

        $_GET = $old_get;

        $tab = isset( $query['tab'] ) ? sanitize_key( (string) $query['tab'] ) : 'tracked';
        if ( ! in_array( $tab, self::available_tabs(), true ) ) {
            $tab = 'tracked';
        }

        $url_args = array(
            'page' => self::PAGE_SLUG,
            'tab'  => $tab,
        );

        if ( 'migration' === $tab && ! empty( $query['wft_sdm_scan'] ) ) {
            $url_args['wft_sdm_scan'] = '1';
        }

        if ( 'tracked' === $tab ) {
            $total_pages = max( 1, (int) ceil( WFT_Downloads::get_count() / self::PER_PAGE ) );
            $requested_page = isset( $query['paged'] ) ? max( 1, absint( $query['paged'] ) ) : 1;
            $state = array(
                'orderby' => isset( $query['orderby'] ) && in_array( sanitize_key( (string) $query['orderby'] ), self::sortable_columns(), true )
                    ? sanitize_key( (string) $query['orderby'] )
                    : 'created_at',
                'order'   => isset( $query['order'] ) && 'asc' === strtolower( (string) $query['order'] ) ? 'asc' : 'desc',
                'paged'   => min( $requested_page, $total_pages ),
            );
            $url_args = array_merge( $url_args, $state );
        }

        wp_send_json_success(
            array_merge(
                array(
                    'html' => $html,
                    'url'  => add_query_arg( $url_args, admin_url( 'admin.php' ) ),
                    'tab'  => $tab,
                ),
                $extra
            )
        );
    }

    public static function update_tracker(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wp-filetrace' ) );
        }

        $id = isset( $_POST['tracker_id'] ) ? absint( $_POST['tracker_id'] ) : 0;
        check_admin_referer( 'wft_update_tracker_' . $id );

        $title         = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $url           = isset( $_POST['file_url'] ) ? esc_url_raw( wp_unslash( $_POST['file_url'] ) ) : '';
        $attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
        $button_text   = isset( $_POST['button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['button_text'] ) ) : '';
        $state         = self::list_state_from_post();

        $ok = WFT_Downloads::update_tracker( $id, $title, $url, $attachment_id, $button_text );
        $redirect = add_query_arg(
            array_merge(
                array(
                    'page'        => self::PAGE_SLUG,
                    'wft_updated' => $ok ? '1' : '0',
                ),
                $state
            ),
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    public static function delete_tracker(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wp-filetrace' ) );
        }

        $id = isset( $_POST['tracker_id'] ) ? absint( $_POST['tracker_id'] ) : 0;
        check_admin_referer( 'wft_delete_tracker_' . $id );

        $state   = self::list_state_from_post();
        $tracker = $id > 0 ? WFT_Downloads::get_by_id( $id ) : null;

        $deleted_name = $tracker && ! empty( $tracker->title )
            ? $tracker->title
            : __( 'Untitled tracked file', 'wp-filetrace' );

        $deleted = $tracker && WFT_Downloads::delete_tracker( $id );
        $redirect = add_query_arg(
            array_merge(
                array(
                    'page'             => self::PAGE_SLUG,
                    'wft_deleted'      => $deleted ? '1' : '0',
                    'wft_deleted_name' => $deleted ? $deleted_name : '',
                ),
                $state
            ),
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    public static function delete_selected_trackers(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wp-filetrace' ) );
        }

        check_admin_referer( 'wft_delete_selected_trackers' );

        $raw_ids = isset( $_POST['tracker_ids'] ) && is_array( $_POST['tracker_ids'] )
            ? wp_unslash( $_POST['tracker_ids'] )
            : array();
        $ids   = array_values( array_filter( array_map( 'absint', $raw_ids ) ) );
        $state = self::list_state_from_post();

        if ( empty( $ids ) ) {
            $result = 0;
            $status = 'none';
        } else {
            $result = WFT_Downloads::delete_trackers( $ids );
            $status = false === $result ? 'error' : 'success';
        }

        $redirect = add_query_arg(
            array_merge(
                array(
                    'page'              => self::PAGE_SLUG,
                    'wft_bulk_deleted'  => false === $result ? 0 : (int) $result,
                    'wft_bulk_status'   => $status,
                ),
                $state
            ),
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    public static function delete_all_trackers(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wp-filetrace' ) );
        }

        check_admin_referer( 'wft_delete_all_trackers' );

        $result = WFT_Downloads::delete_all_trackers();
        $redirect = add_query_arg(
            array(
                'page'            => self::PAGE_SLUG,
                'orderby'         => 'created_at',
                'order'           => 'desc',
                'paged'           => 1,
                'wft_all_deleted' => false === $result ? 0 : (int) $result,
                'wft_all_status'  => false === $result ? 'error' : 'success',
            ),
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    public static function generate_test_rows(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wp-filetrace' ) );
        }

        check_admin_referer( 'wft_generate_test_rows' );

        if ( ! wft_test_rows_enabled() ) {
            wp_die( esc_html__( 'The test row generator is disabled.', 'wp-filetrace' ) );
        }

        $created = WFT_Downloads::create_test_rows( 200 );
        $redirect = add_query_arg(
            array(
                'page'          => self::PAGE_SLUG,
                'orderby'       => 'created_at',
                'order'         => 'desc',
                'paged'         => 1,
                'wft_test_rows' => $created,
            ),
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    public static function save_analytics(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wp-filetrace' ) );
        }

        check_admin_referer( 'wft_save_analytics' );

        $action = isset( $_POST['wft_analytics_action'] ) ? sanitize_key( wp_unslash( $_POST['wft_analytics_action'] ) ) : 'save';
        $notice = 'saved';

        if ( 'clear_global' === $action ) {
            WFT_Analytics::clear_global_snippet();
            $notice = 'global_cleared';
        } elseif ( 'clear_event' === $action ) {
            WFT_Analytics::clear_event_settings();
            $notice = 'event_cleared';
        } else {
            $global_snippet = isset( $_POST['wft_ga_global_snippet'] ) ? (string) wp_unslash( $_POST['wft_ga_global_snippet'] ) : '';
            $event_snippet        = isset( $_POST['wft_ga_event_snippet'] ) ? (string) wp_unslash( $_POST['wft_ga_event_snippet'] ) : '';
            $download_id_parameter = isset( $_POST['wft_ga_download_id_parameter'] ) ? sanitize_text_field( wp_unslash( $_POST['wft_ga_download_id_parameter'] ) ) : '';
            $file_parameter        = isset( $_POST['wft_ga_filename_parameter'] ) ? sanitize_text_field( wp_unslash( $_POST['wft_ga_filename_parameter'] ) ) : '';

            if ( ( '' !== trim( $global_snippet ) || '' !== trim( $event_snippet ) ) && ! current_user_can( 'unfiltered_html' ) ) {
                wp_die( esc_html__( 'Your account is not allowed to save executable analytics snippets.', 'wp-filetrace' ) );
            }

            WFT_Analytics::save_settings( $global_snippet, $event_snippet, $download_id_parameter, $file_parameter );
        }

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'                 => self::PAGE_SLUG,
                    'tab'                  => 'analytics',
                    'wft_analytics_notice' => $notice,
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public static function save_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wp-filetrace' ) );
        }

        check_admin_referer( 'wft_save_settings' );

        $migration_enabled = ! empty( $_POST['wft_enable_sdm_migration'] );
        $test_rows_enabled = ! empty( $_POST['wft_enable_test_rows'] );

        update_option( 'wft_enable_sdm_migration', $migration_enabled ? '1' : '0', false );
        update_option( 'wft_enable_test_rows', $test_rows_enabled ? '1' : '0', false );

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'                => self::PAGE_SLUG,
                    'tab'                 => 'settings',
                    'wft_settings_notice' => 'saved',
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public static function check_updates(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wp-filetrace' ) );
        }

        check_admin_referer( 'wft_check_updates' );

        $status = WFT_Updater::force_check();
        $notice = 'connected' === ( $status['connection'] ?? '' )
            ? ( ! empty( $status['update_available'] ) ? 'available' : 'current' )
            : 'error';

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'                    => self::PAGE_SLUG,
                    'tab'                     => 'updates',
                    'wft_update_check_notice' => $notice,
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public static function sdm_scan(): void {
        self::migration_enabled_or_error();

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wp-filetrace' ) );
        }

        check_admin_referer( 'wft_sdm_scan' );
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'          => self::PAGE_SLUG,
                    'tab'           => 'migration',
                    'wft_sdm_scan'  => '1',
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public static function sdm_apply(): void {
        self::migration_enabled_or_error();

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wp-filetrace' ) );
        }

        check_admin_referer( 'wft_sdm_apply' );
        WFT_SDM_Migration::apply_safe_replacements();

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'             => self::PAGE_SLUG,
                    'tab'              => 'migration',
                    'wft_sdm_scan'     => '1',
                    'wft_sdm_applied'  => '1',
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public static function sdm_rollback(): void {
        self::migration_enabled_or_error();

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wp-filetrace' ) );
        }

        check_admin_referer( 'wft_sdm_rollback' );
        WFT_SDM_Migration::rollback();

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'                 => self::PAGE_SLUG,
                    'tab'                  => 'migration',
                    'wft_sdm_scan'         => '1',
                    'wft_sdm_rolledback'   => '1',
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public static function sdm_discard_rollback(): void {
        self::migration_enabled_or_error();

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wp-filetrace' ) );
        }

        check_admin_referer( 'wft_sdm_discard_rollback' );
        $removed = WFT_SDM_Migration::discard_rollback();

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'               => self::PAGE_SLUG,
                    'tab'                => 'migration',
                    'wft_sdm_scan'       => '1',
                    'wft_sdm_discarded'  => $removed,
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    private static function render_tabs( string $active_tab ): void {
        $tabs = array(
            'tracked'   => __( 'Tracked Files', 'wp-filetrace' ),
            'analytics' => __( 'Analytics', 'wp-filetrace' ),
            'updates'   => __( 'Updates', 'wp-filetrace' ),
            'settings'  => __( 'Settings', 'wp-filetrace' ),
        );

        if ( wft_sdm_migration_enabled() ) {
            $tabs['migration'] = __( 'Migration', 'wp-filetrace' );
        }
        ?>
        <nav class="wft-tabs" aria-label="<?php esc_attr_e( 'WP FileTrace sections', 'wp-filetrace' ); ?>">
            <?php foreach ( $tabs as $tab => $label ) : ?>
                <a
                    class="wft-tab<?php echo $active_tab === $tab ? ' is-active' : ''; ?>"
                    href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => $tab ), admin_url( 'admin.php' ) ) ); ?>"
                    <?php echo $active_tab === $tab ? 'aria-current="page"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                ><?php echo esc_html( $label ); ?></a>
            <?php endforeach; ?>
        </nav>
        <?php
    }

    private static function render_analytics_page(): void {
        $global_snippet = WFT_Analytics::get_global_snippet();
        $event_snippet         = WFT_Analytics::get_event_snippet();
        $download_id_parameter = WFT_Analytics::get_download_id_parameter();
        $file_parameter        = WFT_Analytics::get_filename_parameter();
        $notice         = isset( $_GET['wft_analytics_notice'] ) ? sanitize_key( wp_unslash( $_GET['wft_analytics_notice'] ) ) : '';
        ?>
        <div class="wrap wft-wrap">
            <header class="wft-page-header">
                <div class="wft-brand">
                    <div class="wft-logo-shell">
                        <img src="<?php echo esc_url( WFT_URL . 'assets/images/logo--wp-filetrace.svg' ); ?>" alt="<?php esc_attr_e( 'WP FileTrace', 'wp-filetrace' ); ?>">
                    </div>
                    <div>
                        <h1><?php esc_html_e( 'WP FileTrace', 'wp-filetrace' ); ?></h1>
                        <p><?php esc_html_e( 'Create tracked download links, monitor usage, and export download data.', 'wp-filetrace' ); ?></p>
                    </div>
                </div>
                <a class="wft-asenka-link" href="https://asenka.com/" target="_blank" rel="noopener noreferrer">Asenka.com ↗</a>
            </header>

            <?php self::render_tabs( 'analytics' ); ?>

            <?php if ( $notice ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php
                        if ( 'global_cleared' === $notice ) {
                            esc_html_e( 'Global analytics snippet cleared.', 'wp-filetrace' );
                        } elseif ( 'event_cleared' === $notice ) {
                            esc_html_e( 'Download-event snippet and dynamic parameter mappings cleared.', 'wp-filetrace' );
                        } else {
                            esc_html_e( 'Analytics settings saved.', 'wp-filetrace' );
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <section class="wft-card wft-analytics-card">
                <div class="wft-card-heading">
                    <div>
                        <span class="wft-eyebrow"><?php esc_html_e( 'Optional Integration', 'wp-filetrace' ); ?></span>
                        <h2><?php esc_html_e( 'Google Analytics / gtag', 'wp-filetrace' ); ?></h2>
                    </div>
                </div>

                <p class="wft-analytics-intro">
                    <?php esc_html_e( 'Both snippets are optional and independent. Use the global snippet only if WP FileTrace should install the site tag; leave it blank if another theme or plugin already loads gtag.', 'wp-filetrace' ); ?>
                </p>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wft-analytics-form">
                    <input type="hidden" name="action" value="wft_save_analytics">
                    <?php wp_nonce_field( 'wft_save_analytics' ); ?>

                    <div class="wft-analytics-section">
                        <div class="wft-analytics-section-heading">
                            <div>
                                <h3><?php esc_html_e( 'Global Site Tag', 'wp-filetrace' ); ?></h3>
                                <p><?php esc_html_e( 'Paste the complete global/site tag exactly as provided, including its script tags. When saved, WP FileTrace prints it near the top of the frontend page head.', 'wp-filetrace' ); ?></p>
                            </div>
                            <span class="wft-config-status<?php echo '' !== trim( $global_snippet ) ? ' is-configured' : ''; ?>">
                                <?php echo '' !== trim( $global_snippet ) ? esc_html__( 'Configured', 'wp-filetrace' ) : esc_html__( 'Not configured', 'wp-filetrace' ); ?>
                            </span>
                        </div>
                        <label class="screen-reader-text" for="wft-ga-global-snippet"><?php esc_html_e( 'Global Site Tag code', 'wp-filetrace' ); ?></label>
                        <textarea id="wft-ga-global-snippet" class="wft-code-textarea" name="wft_ga_global_snippet" rows="11" spellcheck="false" placeholder="<!-- Google tag (gtag.js) -->&#10;<script async src=&quot;https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX&quot;></script>&#10;<script>...</script>"><?php echo esc_textarea( $global_snippet ); ?></textarea>
                    </div>

                    <div class="wft-analytics-section">
                        <div class="wft-analytics-section-heading">
                            <div>
                                <h3><?php esc_html_e( 'Download Event', 'wp-filetrace' ); ?></h3>
                                <p><?php esc_html_e( 'Paste JavaScript for the custom gtag event. It runs only after WP FileTrace successfully increments a tracked download, for both shortcode and external links.', 'wp-filetrace' ); ?></p>
                            </div>
                            <span class="wft-config-status<?php echo '' !== trim( $event_snippet ) ? ' is-configured' : ''; ?>">
                                <?php echo '' !== trim( $event_snippet ) ? esc_html__( 'Configured', 'wp-filetrace' ) : esc_html__( 'Not configured', 'wp-filetrace' ); ?>
                            </span>
                        </div>
                        <label class="screen-reader-text" for="wft-ga-event-snippet"><?php esc_html_e( 'Download Event code', 'wp-filetrace' ); ?></label>
                        <textarea id="wft-ga-event-snippet" class="wft-code-textarea" name="wft_ga_event_snippet" rows="10" spellcheck="false" placeholder="gtag('event', 'haver_download', {&#10;    'download_id': '&lt;&lt;INSERT ID HERE&gt;&gt;',&#10;    'download_name': '&lt;&lt;INSERT NAME HERE&gt;&gt;'&#10;});"><?php echo esc_textarea( $event_snippet ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'A surrounding <script> tag is optional for this field. WP FileTrace executes the event during a short browser handoff and then continues to the requested file.', 'wp-filetrace' ); ?></p>
                    </div>

                    <div class="wft-analytics-section wft-analytics-parameter-section">
                        <label for="wft-ga-download-id-parameter"><?php esc_html_e( 'Download ID Event Parameter', 'wp-filetrace' ); ?></label>
                        <input type="text" id="wft-ga-download-id-parameter" name="wft_ga_download_id_parameter" value="<?php echo esc_attr( $download_id_parameter ); ?>" placeholder="download_id">
                        <p class="description">
                            <?php esc_html_e( 'Optional. Enter the gtag event parameter that should receive the stable WP FileTrace tracker ID. If your event snippet already sets that parameter, WP FileTrace overwrites its value for the download event. Example: download_id.', 'wp-filetrace' ); ?>
                        </p>
                    </div>

                    <div class="wft-analytics-section wft-analytics-parameter-section">
                        <label for="wft-ga-filename-parameter"><?php esc_html_e( 'File Name Event Parameter', 'wp-filetrace' ); ?></label>
                        <input type="text" id="wft-ga-filename-parameter" name="wft_ga_filename_parameter" value="<?php echo esc_attr( $file_parameter ); ?>" placeholder="file_name">
                        <p class="description">
                            <?php esc_html_e( 'Optional. Enter the gtag event parameter that should receive the actual downloaded file name. If your event snippet already sets that parameter, WP FileTrace overwrites its value for the download event. Examples: download_name, file_name, or value.', 'wp-filetrace' ); ?>
                        </p>
                    </div>

                    <div class="wft-analytics-note">
                        <strong><?php esc_html_e( 'How downloads are handled:', 'wp-filetrace' ); ?></strong>
                        <?php esc_html_e( 'When Download Event code is configured, a valid tracked request is recorded first, the event runs in the visitor’s browser, and WP FileTrace then redirects to the file. If no event code is configured, downloads retain the normal direct redirect behavior.', 'wp-filetrace' ); ?>
                    </div>

                    <div class="wft-analytics-actions">
                        <button type="submit" name="wft_analytics_action" value="save" class="button button-primary"><?php esc_html_e( 'Save Analytics Settings', 'wp-filetrace' ); ?></button>
                        <button type="submit" name="wft_analytics_action" value="clear_global" class="button"><?php esc_html_e( 'Clear Global Tag', 'wp-filetrace' ); ?></button>
                        <button type="submit" name="wft_analytics_action" value="clear_event" class="button"><?php esc_html_e( 'Clear Event Settings', 'wp-filetrace' ); ?></button>
                    </div>
                </form>
            </section>

            <footer class="wft-footer">
                <span><?php echo esc_html( sprintf( __( 'WP FileTrace v%s', 'wp-filetrace' ), WFT_VERSION ) ); ?></span>
                <span>•</span>
                <span><?php esc_html_e( 'Primary Developer: Brian McLendon', 'wp-filetrace' ); ?></span>
                <span>•</span>
                <a href="https://asenka.com/" target="_blank" rel="noopener noreferrer">Asenka.com</a>
            </footer>
        </div>
        <?php
    }

    private static function render_updates_page(): void {
        $status = WFT_Updater::get_diagnostics();
        $notice = isset( $_GET['wft_update_check_notice'] ) ? sanitize_key( wp_unslash( $_GET['wft_update_check_notice'] ) ) : '';

        $connection = isset( $status['connection'] ) ? (string) $status['connection'] : 'not_checked';
        $connection_labels = array(
            'connected'      => __( 'Connected', 'wp-filetrace' ),
            'error'          => __( 'Connection Error', 'wp-filetrace' ),
            'not_checked'    => __( 'Not Checked Yet', 'wp-filetrace' ),
            'not_configured' => __( 'Not Configured', 'wp-filetrace' ),
        );
        $connection_label = $connection_labels[ $connection ] ?? __( 'Unknown', 'wp-filetrace' );

        $last_checked = ! empty( $status['last_checked'] )
            ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $status['last_checked'] )
            : __( 'Never', 'wp-filetrace' );

        $latest_version = ! empty( $status['latest_version'] ) ? 'v' . ltrim( (string) $status['latest_version'], 'vV' ) : '—';
        ?>
        <div class="wrap wft-wrap">
            <header class="wft-page-header">
                <div class="wft-brand">
                    <div class="wft-logo-shell">
                        <img src="<?php echo esc_url( WFT_URL . 'assets/images/logo--wp-filetrace.svg' ); ?>" alt="<?php esc_attr_e( 'WP FileTrace', 'wp-filetrace' ); ?>">
                    </div>
                    <div class="wft-info-shell">
                        <h1><?php esc_html_e( 'WP FileTrace', 'wp-filetrace' ); ?></h1>
                        <p class="quip"><?php esc_html_e( 'Create tracked download links, monitor usage, and export download data.', 'wp-filetrace' ); ?></p>
                    </div>
                </div>
                <a class="wft-asenka-link" href="https://asenka.com/" target="_blank" rel="noopener noreferrer">Asenka.com ↗</a>
            </header>

            <?php self::render_tabs( 'updates' ); ?>

            <?php if ( $notice ) : ?>
                <div class="notice <?php echo 'error' === $notice ? 'notice-error' : 'notice-success'; ?> is-dismissible">
                    <p>
                        <?php
                        if ( 'available' === $notice ) {
                            esc_html_e( 'Update check completed. A newer WP FileTrace release is available through WordPress Updates.', 'wp-filetrace' );
                        } elseif ( 'current' === $notice ) {
                            esc_html_e( 'Update check completed. No newer WP FileTrace release is available.', 'wp-filetrace' );
                        } else {
                            esc_html_e( 'WP FileTrace could not complete the GitHub update check. See the connection status below.', 'wp-filetrace' );
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <section class="wft-card wft-updates-card">
                <div class="wft-card-heading">
                    <div>
                        <span class="wft-eyebrow"><?php esc_html_e( 'GitHub Releases', 'wp-filetrace' ); ?></span>
                        <h2><?php esc_html_e( 'Update Status', 'wp-filetrace' ); ?></h2>
                    </div>
                </div>

                <div class="wft-update-status-grid">
                    <div class="wft-update-status-item">
                        <span><?php esc_html_e( 'Installed Version', 'wp-filetrace' ); ?></span>
                        <strong><?php echo esc_html( 'v' . WFT_VERSION ); ?></strong>
                    </div>
                    <div class="wft-update-status-item">
                        <span><?php esc_html_e( 'Latest Release', 'wp-filetrace' ); ?></span>
                        <strong><?php echo esc_html( $latest_version ); ?></strong>
                    </div>
                    <div class="wft-update-status-item">
                        <span><?php esc_html_e( 'Last Checked', 'wp-filetrace' ); ?></span>
                        <strong><?php echo esc_html( $last_checked ); ?></strong>
                    </div>
                    <div class="wft-update-status-item">
                        <span><?php esc_html_e( 'Connection Status', 'wp-filetrace' ); ?></span>
                        <strong class="wft-update-connection is-<?php echo esc_attr( $connection ); ?>"><?php echo esc_html( $connection_label ); ?></strong>
                    </div>
                </div>

                <?php if ( ! empty( $status['message'] ) ) : ?>
                    <p class="wft-update-message"><?php echo esc_html( (string) $status['message'] ); ?></p>
                <?php endif; ?>

                <?php if ( ! empty( $status['update_available'] ) ) : ?>
                    <div class="wft-update-available-note">
                        <strong><?php esc_html_e( 'Update available.', 'wp-filetrace' ); ?></strong>
                        <?php esc_html_e( 'WordPress has been refreshed with the latest WP FileTrace release information.', 'wp-filetrace' ); ?>
                        <a href="<?php echo esc_url( admin_url( 'update-core.php' ) ); ?>"><?php esc_html_e( 'Open WordPress Updates', 'wp-filetrace' ); ?></a>
                    </div>
                <?php endif; ?>

                <div class="wft-update-actions">
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wft-update-check-form">
                        <input type="hidden" name="action" value="wft_check_updates">
                        <?php wp_nonce_field( 'wft_check_updates' ); ?>
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-update" aria-hidden="true"></span>
                            <?php esc_html_e( 'Check for Updates', 'wp-filetrace' ); ?>
                        </button>
                    </form>
                    <a class="button" href="<?php echo esc_url( WFT_Updater::releases_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View GitHub Releases', 'wp-filetrace' ); ?> ↗</a>
                </div>

                <p class="description wft-update-cache-note">
                    <?php esc_html_e( 'Automatic GitHub release metadata is cached for up to one hour. Check for Updates bypasses that cache and immediately rebuilds WordPress plugin-update data.', 'wp-filetrace' ); ?>
                </p>
            </section>

            <footer class="wft-footer">
                <span><?php echo esc_html( sprintf( __( 'WP FileTrace v%s', 'wp-filetrace' ), WFT_VERSION ) ); ?></span>
                <span>•</span>
                <span><?php esc_html_e( 'Primary Developer: Brian McLendon', 'wp-filetrace' ); ?></span>
                <span>•</span>
                <a href="https://asenka.com/" target="_blank" rel="noopener noreferrer">Asenka.com</a>
            </footer>
        </div>
        <?php
    }

    private static function list_state_from_post(): array {
        $orderby = isset( $_POST['return_orderby'] ) ? sanitize_key( wp_unslash( $_POST['return_orderby'] ) ) : 'created_at';
        $order   = isset( $_POST['return_order'] ) ? strtolower( sanitize_key( wp_unslash( $_POST['return_order'] ) ) ) : 'desc';
        $paged   = isset( $_POST['return_paged'] ) ? max( 1, absint( $_POST['return_paged'] ) ) : 1;

        if ( ! in_array( $orderby, self::sortable_columns(), true ) ) {
            $orderby = 'created_at';
        }
        $order = 'asc' === $order ? 'asc' : 'desc';

        return array(
            'orderby' => $orderby,
            'order'   => $order,
            'paged'   => $paged,
        );
    }

    private static function sortable_columns(): array {
        return array(
            'title',
            'total_downloads',
            'shortcode_downloads',
            'external_downloads',
            'last_downloaded_at',
            'created_at',
        );
    }

    private static function sort_url( string $column, string $orderby, string $order ): string {
        $active = $column === $orderby;
        if ( $active ) {
            $next_order = 'asc' === $order ? 'desc' : 'asc';
        } else {
            $next_order = 'title' === $column ? 'asc' : 'desc';
        }

        return add_query_arg(
            array(
                'page'    => self::PAGE_SLUG,
                'orderby' => $column,
                'order'   => $next_order,
                'paged'   => 1,
            ),
            admin_url( 'admin.php' )
        );
    }

    private static function render_sortable_header( string $label, string $column, string $orderby, string $order ): void {
        $active       = $column === $orderby;
        $next_order   = $active && 'asc' === $order ? 'desc' : ( $active ? 'asc' : ( 'title' === $column ? 'asc' : 'desc' ) );
        $indicator    = $active ? ( 'asc' === $order ? '↑' : '↓' ) : '↕';
        $aria_sort    = $active ? ( 'asc' === $order ? 'ascending' : 'descending' ) : 'none';
        $button_label = sprintf(
            /* translators: 1: column label, 2: sort direction. */
            __( 'Sort %1$s %2$s', 'wp-filetrace' ),
            $label,
            'asc' === $next_order ? __( 'ascending', 'wp-filetrace' ) : __( 'descending', 'wp-filetrace' )
        );
        ?>
        <th scope="col" aria-sort="<?php echo esc_attr( $aria_sort ); ?>">
            <span class="wft-sort-heading">
                <span><?php echo esc_html( $label ); ?></span>
                <a class="wft-sort-button<?php echo $active ? ' is-active' : ''; ?>" href="<?php echo esc_url( self::sort_url( $column, $orderby, $order ) ); ?>" aria-label="<?php echo esc_attr( $button_label ); ?>" title="<?php echo esc_attr( $button_label ); ?>"><?php echo esc_html( $indicator ); ?></a>
            </span>
        </th>
        <?php
    }

    private static function render_settings_page(): void {
        $migration_enabled = wft_sdm_migration_enabled();
        $test_rows_enabled = wft_test_rows_enabled();
        $notice = isset( $_GET['wft_settings_notice'] ) ? sanitize_key( wp_unslash( $_GET['wft_settings_notice'] ) ) : '';
        ?>
        <div class="wrap wft-wrap">
            <header class="wft-page-header">
                <div class="wft-brand">
                    <div class="wft-logo-shell">
                        <img src="<?php echo esc_url( WFT_URL . 'assets/images/logo--wp-filetrace.svg' ); ?>" alt="<?php esc_attr_e( 'WP FileTrace', 'wp-filetrace' ); ?>">
                    </div>
                    <div class="wft-info-shell">
                        <h1><?php esc_html_e( 'WP FileTrace', 'wp-filetrace' ); ?></h1>
                        <p class="quip"><?php esc_html_e( 'Create tracked download links, monitor usage, and export download data.', 'wp-filetrace' ); ?></p>
                    </div>
                </div>
                <a class="wft-asenka-link" href="https://asenka.com/" target="_blank" rel="noopener noreferrer">Asenka.com ↗</a>
            </header>

            <?php self::render_tabs( 'settings' ); ?>

            <?php if ( 'saved' === $notice ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'WP FileTrace settings saved.', 'wp-filetrace' ); ?></p></div>
            <?php endif; ?>

            <section class="wft-card wft-settings-card">
                <div class="wft-card-heading">
                    <div>
                        <span class="wft-eyebrow"><?php esc_html_e( 'Settings', 'wp-filetrace' ); ?></span>
                        <h2><?php esc_html_e( 'Beta Features', 'wp-filetrace' ); ?></h2>
                    </div>
                </div>

                <div class="wft-beta-warning">
                    <span class="wft-beta-badge">BETA</span>
                    <div>
                        <strong><?php esc_html_e( 'Beta features are experimental and not fully fleshed out.', 'wp-filetrace' ); ?></strong>
                        <span><?php esc_html_e( 'Enable them only when you need the feature and test on a backed-up/staging copy of the site first.', 'wp-filetrace' ); ?></span>
                    </div>
                </div>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wft-settings-form">
                    <input type="hidden" name="action" value="wft_save_settings">
                    <?php wp_nonce_field( 'wft_save_settings' ); ?>

                    <div class="wft-beta-feature-row">
                        <label for="wft-enable-sdm-migration" class="wft-beta-feature-toggle">
                            <input type="checkbox" id="wft-enable-sdm-migration" name="wft_enable_sdm_migration" value="1" <?php checked( $migration_enabled ); ?>>
                            <span>
                                <strong><?php esc_html_e( 'Enable Simple Download Monitor Migration', 'wp-filetrace' ); ?></strong>
                                <span class="wft-beta-badge">BETA</span>
                            </span>
                        </label>
                        <p class="description"><?php esc_html_e( 'Currently supports migration from Simple Download Monitor (SDM) only. Enabling this setting adds the Migration tab, where SDM shortcodes can be scanned, reviewed, and migrated into WP FileTrace.', 'wp-filetrace' ); ?></p>
                    </div>

                    <div class="wft-settings-subsection">
                        <span class="wft-eyebrow"><?php esc_html_e( 'Developer / Testing Tools', 'wp-filetrace' ); ?></span>
                        <h3><?php esc_html_e( 'Testing Utilities', 'wp-filetrace' ); ?></h3>
                        <p class="description"><?php esc_html_e( 'These controls expose utilities intended for development and interface testing, not normal production use.', 'wp-filetrace' ); ?></p>
                    </div>

                    <div class="wft-beta-feature-row">
                        <label for="wft-enable-test-rows" class="wft-beta-feature-toggle">
                            <input type="checkbox" id="wft-enable-test-rows" name="wft_enable_test_rows" value="1" <?php checked( $test_rows_enabled ); ?>>
                            <span>
                                <strong><?php esc_html_e( 'Enable Test Row Generator', 'wp-filetrace' ); ?></strong>
                                <span class="wft-beta-badge">DEV</span>
                            </span>
                        </label>
                        <p class="description"><?php esc_html_e( 'Shows the Generate 200 Test Rows button on the Tracked Files tab for testing sorting, pagination, and bulk actions. Leave this disabled on normal production sites.', 'wp-filetrace' ); ?></p>
                    </div>

                    <p class="wft-settings-actions">
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'wp-filetrace' ); ?></button>
                        <?php if ( $migration_enabled ) : ?>
                            <a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => 'migration' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Open Migration', 'wp-filetrace' ); ?></a>
                        <?php endif; ?>
                    </p>
                </form>
            </section>

            <footer class="wft-footer">
                <span><?php echo esc_html( sprintf( __( 'WP FileTrace v%s', 'wp-filetrace' ), WFT_VERSION ) ); ?></span>
                <span>•</span>
                <span><?php esc_html_e( 'Primary Developer: Brian McLendon', 'wp-filetrace' ); ?></span>
                <span>•</span>
                <a href="https://asenka.com/" target="_blank" rel="noopener noreferrer">Asenka.com</a>
            </footer>
        </div>
        <?php
    }

    private static function render_migration_page(): void {
        if ( ! wft_sdm_migration_enabled() || ! class_exists( 'WFT_SDM_Migration' ) ) {
            self::render_settings_page();
            return;
        }

        $should_scan = isset( $_GET['wft_sdm_scan'] ) && '1' === sanitize_key( wp_unslash( $_GET['wft_sdm_scan'] ) );
        $scan        = $should_scan ? WFT_SDM_Migration::scan_site() : null;
        $rollback    = WFT_SDM_Migration::get_rollback_state();
        $last_run    = WFT_SDM_Migration::get_last_run();
        ?>
        <div class="wrap wft-wrap">
            <header class="wft-page-header">
                <div class="wft-brand">
                    <div class="wft-logo-shell">
                        <img src="<?php echo esc_url( WFT_URL . 'assets/images/logo--wp-filetrace.svg' ); ?>" alt="<?php esc_attr_e( 'WP FileTrace', 'wp-filetrace' ); ?>">
                    </div>
                    <div class="wft-info-shell">
                        <h1><?php esc_html_e( 'WP FileTrace', 'wp-filetrace' ); ?></h1>
                        <p class="quip"><?php esc_html_e( 'Create tracked download links, monitor usage, and export download data.', 'wp-filetrace' ); ?></p>
                    </div>
                </div>
                <a class="wft-asenka-link" href="https://asenka.com/" target="_blank" rel="noopener noreferrer">Asenka.com ↗</a>
            </header>

            <?php self::render_tabs( 'migration' ); ?>

            <?php if ( isset( $_GET['wft_sdm_applied'] ) && isset( $last_run['shortcodes_changed'] ) ) : ?>
                <div class="notice <?php echo ! empty( $last_run['failed'] ) ? 'notice-warning' : 'notice-success'; ?> is-dismissible">
                    <p>
                        <?php
                        printf(
                            esc_html__( 'Migration applied: %1$d SDM shortcode(s) replaced across %2$d content item(s). %3$d WP FileTrace tracker(s) created, %4$d reused, %5$d operation(s) failed.', 'wp-filetrace' ),
                            (int) $last_run['shortcodes_changed'],
                            (int) $last_run['posts_changed'],
                            (int) $last_run['trackers_created'],
                            (int) $last_run['trackers_reused'],
                            (int) $last_run['failed']
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ( isset( $_GET['wft_sdm_rolledback'] ) && isset( $last_run['rollback'] ) ) : ?>
                <div class="notice <?php echo ! empty( $last_run['rollback']['failed'] ) ? 'notice-warning' : 'notice-success'; ?> is-dismissible">
                    <p>
                        <?php
                        printf(
                            esc_html__( 'Migration rollback restored %1$d content item(s); %2$d restore operation(s) failed. WP FileTrace tracker records were intentionally left in place.', 'wp-filetrace' ),
                            (int) ( $last_run['rollback']['restored'] ?? 0 ),
                            (int) ( $last_run['rollback']['failed'] ?? 0 )
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ( isset( $_GET['wft_sdm_discarded'] ) ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html( sprintf( __( 'Migration rollback backup discarded for %d content item(s). Current content was not changed.', 'wp-filetrace' ), absint( $_GET['wft_sdm_discarded'] ) ) ); ?></p>
                </div>
            <?php endif; ?>

            <section class="wft-card wft-migration-card">
                <div class="wft-card-heading">
                    <div>
                        <span class="wft-eyebrow"><?php esc_html_e( 'Beta Feature', 'wp-filetrace' ); ?></span>
                        <h2><?php esc_html_e( 'Simple Download Monitor Migration', 'wp-filetrace' ); ?></h2>
                    </div>
                </div>

                <div class="wft-beta-warning wft-migration-beta-warning">
                    <span class="wft-beta-badge">BETA</span>
                    <div>
                        <strong><?php esc_html_e( 'This migration tool is a beta feature and currently supports Simple Download Monitor only.', 'wp-filetrace' ); ?></strong>
                        <span><?php esc_html_e( 'The workflow is intentionally conservative and may require manual review for unsupported SDM setups or page-builder data.', 'wp-filetrace' ); ?></span>
                    </div>
                </div>

                <div class="wft-migration-warning">
                    <strong><?php esc_html_e( 'Use a site/database backup before applying changes.', 'wp-filetrace' ); ?></strong>
                    <span><?php esc_html_e( 'Scan Site is a dry run: it does not create trackers or edit content. Apply Safe Replacements only changes rows marked Ready and stores an internal rollback copy of each affected post_content value.', 'wp-filetrace' ); ?></span>
                </div>

                <div class="wft-migration-scope">
                    <p><?php esc_html_e( 'This migration targets individual [sdm_download] and legacy [sdm-download] shortcodes. It resolves each SDM item through its stored file URL, maps Media Library files back to attachment IDs when possible, preserves button text, and creates or reuses the corresponding WP FileTrace tracker.', 'wp-filetrace' ); ?></p>
                    <p><?php esc_html_e( 'Post-meta/page-builder references are reported but never automatically modified. SDM counter/info/category shortcodes and direct SDM process URLs are not replaced by this pass.', 'wp-filetrace' ); ?></p>
                </div>

                <div class="wft-migration-actions">
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wft-sdm-scan-form">
                        <input type="hidden" name="action" value="wft_sdm_scan">
                        <?php wp_nonce_field( 'wft_sdm_scan' ); ?>
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-search" aria-hidden="true"></span>
                            <?php esc_html_e( 'Scan Site / Dry Run', 'wp-filetrace' ); ?>
                        </button>
                    </form>

                    <?php if ( $scan && ! empty( $scan['ready'] ) ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wft-sdm-apply-form">
                            <input type="hidden" name="action" value="wft_sdm_apply">
                            <?php wp_nonce_field( 'wft_sdm_apply' ); ?>
                            <button type="submit" class="button button-secondary">
                                <span class="dashicons dashicons-migrate" aria-hidden="true"></span>
                                <?php echo esc_html( sprintf( __( 'Apply %d Safe Replacement(s)', 'wp-filetrace' ), (int) $scan['ready'] ) ); ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ( ! empty( $rollback['post_ids'] ) ) : ?>
                <section class="wft-card wft-migration-rollback-card">
                    <div class="wft-card-heading">
                        <div>
                            <span class="wft-eyebrow"><?php esc_html_e( 'Safety Net', 'wp-filetrace' ); ?></span>
                            <h2><?php esc_html_e( 'Migration Rollback Available', 'wp-filetrace' ); ?></h2>
                        </div>
                    </div>
                    <p>
                        <?php
                        echo esc_html(
                            sprintf(
                                __( 'Original post content is currently backed up for %1$d item(s)%2$s.', 'wp-filetrace' ),
                                count( $rollback['post_ids'] ),
                                ! empty( $rollback['created_at'] ) ? ' (' . $rollback['created_at'] . ')' : ''
                            )
                        );
                        ?>
                    </p>
                    <p class="description"><?php esc_html_e( 'Rollback restores the original post content from before the first migration change and intentionally leaves WP FileTrace tracker records in place. Any later manual edits made to those same content items after migration would also be replaced by the rollback copy, so verify before using it.', 'wp-filetrace' ); ?></p>
                    <div class="wft-migration-actions">
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wft-sdm-rollback-form">
                            <input type="hidden" name="action" value="wft_sdm_rollback">
                            <?php wp_nonce_field( 'wft_sdm_rollback' ); ?>
                            <button type="submit" class="button wft-warning-button"><?php esc_html_e( 'Roll Back Content Changes', 'wp-filetrace' ); ?></button>
                        </form>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wft-sdm-discard-form">
                            <input type="hidden" name="action" value="wft_sdm_discard_rollback">
                            <?php wp_nonce_field( 'wft_sdm_discard_rollback' ); ?>
                            <button type="submit" class="button"><?php esc_html_e( 'Discard Rollback Backup', 'wp-filetrace' ); ?></button>
                        </form>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ( $scan && ! empty( $scan['audit'] ) && is_array( $scan['audit'] ) ) : ?>
                <?php $audit = $scan['audit']; ?>
                <section class="wft-card wft-sdm-audit-card">
                    <div class="wft-card-heading">
                        <div>
                            <span class="wft-eyebrow"><?php esc_html_e( 'Inventory', 'wp-filetrace' ); ?></span>
                            <h2><?php esc_html_e( 'SDM Usage Audit', 'wp-filetrace' ); ?></h2>
                        </div>
                    </div>

                    <p><?php esc_html_e( 'This audit inventories Simple Download Monitor records separately from shortcode occurrences. It helps explain why the number of SDM download items can be much larger than the number of individual shortcodes found by the migration pass.', 'wp-filetrace' ); ?></p>

                    <div class="wft-migration-metrics wft-audit-metrics">
                        <div class="wft-migration-metric"><span><?php esc_html_e( 'SDM Items', 'wp-filetrace' ); ?></span><strong><?php echo number_format_i18n( (int) $audit['total_items'] ); ?></strong></div>
                        <div class="wft-migration-metric"><span><?php esc_html_e( 'Referenced IDs', 'wp-filetrace' ); ?></span><strong><?php echo number_format_i18n( (int) $audit['referenced_ids'] ); ?></strong></div>
                        <div class="wft-migration-metric is-ready"><span><?php esc_html_e( 'Standard Shortcode IDs', 'wp-filetrace' ); ?></span><strong><?php echo number_format_i18n( (int) $audit['standard_shortcode_ids'] ); ?></strong></div>
                        <div class="wft-migration-metric"><span><?php esc_html_e( 'Direct URL IDs', 'wp-filetrace' ); ?></span><strong><?php echo number_format_i18n( (int) $audit['direct_url_ids'] ); ?></strong></div>
                        <div class="wft-migration-metric"><span><?php esc_html_e( 'Other SDM Reference IDs', 'wp-filetrace' ); ?></span><strong><?php echo number_format_i18n( (int) $audit['related_shortcode_ids'] + (int) $audit['hidden_shortcode_ids'] ); ?></strong></div>
                        <div class="wft-migration-metric is-review"><span><?php esc_html_e( 'No Direct Reference Found', 'wp-filetrace' ); ?></span><strong><?php echo number_format_i18n( (int) $audit['no_direct_reference'] ); ?></strong></div>
                    </div>

                    <div class="notice notice-info inline">
                        <p>
                            <?php
                            printf(
                                esc_html__( 'The migration found %1$d individual SDM shortcode occurrence(s), while those occurrences reference %2$d unique SDM item ID(s). WP FileTrace currently expects %3$d unique tracker destination(s) to be created or reused.', 'wp-filetrace' ),
                                (int) $scan['total'],
                                (int) $audit['standard_shortcode_ids'],
                                (int) $scan['create'] + (int) $scan['reuse']
                            );
                            ?>
                        </p>
                    </div>

                    <?php if ( ! empty( $audit['category_listing_occurrences'] ) ) : ?>
                        <div class="notice notice-warning inline">
                            <p>
                                <?php
                                printf(
                                    esc_html__( 'Found %1$d SDM category/listing shortcode occurrence(s) across %2$d content/meta location(s). A category listing can expose many download items dynamically, so “No Direct Reference Found” does not mean an SDM item is definitely unused.', 'wp-filetrace' ),
                                    (int) $audit['category_listing_occurrences'],
                                    (int) $audit['category_listing_locations']
                                );
                                ?>
                            </p>
                        </div>
                    <?php else : ?>
                        <p class="description"><?php esc_html_e( '“No Direct Reference Found” means this audit did not find the SDM item ID in a standard download shortcode, supported SDM info/counter/link shortcode, hidden-download shortcode, or direct SDM process URL. It is intentionally not labeled “unused” because references may still exist outside the content/meta patterns this beta scanner understands.', 'wp-filetrace' ); ?></p>
                    <?php endif; ?>

                    <?php if ( ! empty( $audit['missing_file_url'] ) ) : ?>
                        <div class="notice notice-warning inline">
                            <p><?php echo esc_html( sprintf( __( '%d SDM item(s) do not currently resolve to a valid HTTP/HTTPS file URL.', 'wp-filetrace' ), (int) $audit['missing_file_url'] ) ); ?></p>
                        </div>
                    <?php endif; ?>

                    <details class="wft-audit-details">
                        <summary><?php echo esc_html( sprintf( __( 'Review all %d SDM item(s)', 'wp-filetrace' ), (int) $audit['total_items'] ) ); ?></summary>
                        <div class="wft-table-wrap wft-audit-table-wrap">
                            <table class="widefat wft-table wft-audit-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'SDM Item', 'wp-filetrace' ); ?></th>
                                        <th><?php esc_html_e( 'Status', 'wp-filetrace' ); ?></th>
                                        <th><?php esc_html_e( 'File', 'wp-filetrace' ); ?></th>
                                        <th><?php esc_html_e( 'Standard Download', 'wp-filetrace' ); ?></th>
                                        <th><?php esc_html_e( 'Other SDM References', 'wp-filetrace' ); ?></th>
                                        <th><?php esc_html_e( 'Audit Result', 'wp-filetrace' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $audit['items'] as $audit_item ) : ?>
                                        <?php
                                        $sdm_edit_link = get_edit_post_link( (int) $audit_item['sdm_id'], '' );
                                        $audit_file_name = ! empty( $audit_item['file_url'] ) ? wp_basename( (string) wp_parse_url( $audit_item['file_url'], PHP_URL_PATH ) ) : '';
                                        $other_parts = array();
                                        $direct_count = (int) $audit_item['direct_content'] + (int) $audit_item['direct_meta'];
                                        $related_count = (int) $audit_item['related_content'] + (int) $audit_item['related_meta'];
                                        $hidden_count = (int) $audit_item['hidden_content'] + (int) $audit_item['hidden_meta'];
                                        if ( $direct_count > 0 ) {
                                            $other_parts[] = sprintf( __( 'Direct URL: %d', 'wp-filetrace' ), $direct_count );
                                        }
                                        if ( $related_count > 0 ) {
                                            $other_parts[] = sprintf( __( 'Counter/info/link: %d', 'wp-filetrace' ), $related_count );
                                        }
                                        if ( $hidden_count > 0 ) {
                                            $other_parts[] = sprintf( __( 'Hidden download: %d', 'wp-filetrace' ), $hidden_count );
                                        }
                                        ?>
                                        <tr class="<?php echo ! empty( $audit_item['has_direct_reference'] ) ? 'has-reference' : 'no-reference'; ?>">
                                            <td>
                                                <?php if ( $sdm_edit_link ) : ?>
                                                    <a class="wft-file-title" href="<?php echo esc_url( $sdm_edit_link ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $audit_item['title'] ); ?> ↗</a>
                                                <?php else : ?>
                                                    <strong><?php echo esc_html( $audit_item['title'] ); ?></strong>
                                                <?php endif; ?>
                                                <span class="wft-meta">#<?php echo (int) $audit_item['sdm_id']; ?></span>
                                            </td>
                                            <td><span class="wft-migration-badge"><?php echo esc_html( $audit_item['status'] ); ?></span></td>
                                            <td>
                                                <?php if ( ! empty( $audit_item['file_url'] ) ) : ?>
                                                    <a href="<?php echo esc_url( $audit_item['file_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $audit_file_name ?: $audit_item['file_url'] ); ?> ↗</a>
                                                <?php else : ?>
                                                    <span class="wft-meta"><?php esc_html_e( 'No valid file URL', 'wp-filetrace' ); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?php echo number_format_i18n( (int) $audit_item['standard_content'] + (int) $audit_item['standard_meta'] ); ?></strong>
                                                <span class="wft-meta"><?php echo esc_html( sprintf( __( 'content %1$d · meta %2$d', 'wp-filetrace' ), (int) $audit_item['standard_content'], (int) $audit_item['standard_meta'] ) ); ?></span>
                                            </td>
                                            <td>
                                                <?php if ( $other_parts ) : ?>
                                                    <?php foreach ( $other_parts as $part ) : ?><span class="wft-meta wft-audit-ref-line"><?php echo esc_html( $part ); ?></span><?php endforeach; ?>
                                                <?php else : ?>—<?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ( ! empty( $audit_item['has_direct_reference'] ) ) : ?>
                                                    <span class="wft-migration-badge is-ready"><?php esc_html_e( 'Reference Found', 'wp-filetrace' ); ?></span>
                                                <?php else : ?>
                                                    <span class="wft-migration-badge is-review"><?php esc_html_e( 'No Direct Reference Found', 'wp-filetrace' ); ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>
                </section>
            <?php endif; ?>

            <?php if ( $scan ) : ?>
                <section class="wft-card wft-migration-results-card">
                    <div class="wft-card-heading">
                        <div>
                            <span class="wft-eyebrow"><?php esc_html_e( 'Dry Run Results', 'wp-filetrace' ); ?></span>
                            <h2><?php esc_html_e( 'Proposed Replacements', 'wp-filetrace' ); ?></h2>
                        </div>
                    </div>

                    <div class="wft-migration-metrics">
                        <div class="wft-migration-metric"><span><?php esc_html_e( 'Found', 'wp-filetrace' ); ?></span><strong><?php echo number_format_i18n( (int) $scan['total'] ); ?></strong></div>
                        <div class="wft-migration-metric is-ready"><span><?php esc_html_e( 'Ready', 'wp-filetrace' ); ?></span><strong><?php echo number_format_i18n( (int) $scan['ready'] ); ?></strong></div>
                        <div class="wft-migration-metric is-review"><span><?php esc_html_e( 'Needs Review', 'wp-filetrace' ); ?></span><strong><?php echo number_format_i18n( (int) $scan['review'] ); ?></strong></div>
                        <div class="wft-migration-metric"><span><?php esc_html_e( 'Will Create', 'wp-filetrace' ); ?></span><strong><?php echo number_format_i18n( (int) $scan['create'] ); ?></strong></div>
                        <div class="wft-migration-metric"><span><?php esc_html_e( 'Will Reuse', 'wp-filetrace' ); ?></span><strong><?php echo number_format_i18n( (int) $scan['reuse'] ); ?></strong></div>
                    </div>

                    <?php if ( ! empty( $scan['related_count'] ) ) : ?>
                        <div class="notice notice-info inline">
                            <p><?php echo esc_html( sprintf( __( '%d content item(s) also contain related SDM counter/info/link shortcodes. Those usages are intentionally not changed by this migration.', 'wp-filetrace' ), (int) $scan['related_count'] ) ); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ( ! empty( $scan['meta_reference_count'] ) ) : ?>
                        <div class="notice notice-warning inline">
                            <p><?php echo esc_html( sprintf( __( '%d SDM shortcode reference(s) were found in post meta/page-builder data. They are listed below as Needs Review and will not be auto-edited.', 'wp-filetrace' ), (int) $scan['meta_reference_count'] ) ); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="wft-table-wrap wft-migration-table-wrap">
                        <table class="widefat wft-table wft-migration-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Content', 'wp-filetrace' ); ?></th>
                                    <th><?php esc_html_e( 'SDM Item', 'wp-filetrace' ); ?></th>
                                    <th><?php esc_html_e( 'File', 'wp-filetrace' ); ?></th>
                                    <th><?php esc_html_e( 'Current Shortcode', 'wp-filetrace' ); ?></th>
                                    <th><?php esc_html_e( 'WP FileTrace Replacement', 'wp-filetrace' ); ?></th>
                                    <th><?php esc_html_e( 'Tracker', 'wp-filetrace' ); ?></th>
                                    <th><?php esc_html_e( 'Status / Notes', 'wp-filetrace' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( empty( $scan['items'] ) ) : ?>
                                    <tr><td colspan="7" class="wft-empty-state"><?php esc_html_e( 'No individual Simple Download Monitor download shortcodes were found in post content or post meta.', 'wp-filetrace' ); ?></td></tr>
                                <?php else : ?>
                                    <?php foreach ( $scan['items'] as $item ) : ?>
                                        <?php
                                        $edit_link = get_edit_post_link( (int) $item['post_id'], '' );
                                        $file_name = ! empty( $item['file_url'] ) ? wp_basename( (string) wp_parse_url( $item['file_url'], PHP_URL_PATH ) ) : '—';
                                        ?>
                                        <tr>
                                            <td>
                                                <?php if ( $edit_link ) : ?>
                                                    <a class="wft-file-title" href="<?php echo esc_url( $edit_link ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $item['post_title'] ); ?> ↗</a>
                                                <?php else : ?>
                                                    <strong><?php echo esc_html( $item['post_title'] ); ?></strong>
                                                <?php endif; ?>
                                                <span class="wft-meta"><?php echo esc_html( $item['post_type'] . ' #' . (int) $item['post_id'] . ' · ' . $item['post_status'] ); ?></span>
                                                <?php if ( 'post_meta' === $item['source_type'] ) : ?>
                                                    <span class="wft-meta"><?php echo esc_html( 'meta: ' . $item['meta_key'] ); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ( ! empty( $item['sdm_id'] ) ) : ?><strong>#<?php echo (int) $item['sdm_id']; ?></strong><?php endif; ?>
                                                <?php if ( ! empty( $item['sdm_title'] ) ) : ?><span class="wft-meta"><?php echo esc_html( $item['sdm_title'] ); ?></span><?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ( ! empty( $item['file_url'] ) ) : ?>
                                                    <a href="<?php echo esc_url( $item['file_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $file_name ?: $item['file_url'] ); ?> ↗</a>
                                                    <?php if ( ! empty( $item['attachment_id'] ) ) : ?><span class="wft-meta">Media #<?php echo (int) $item['attachment_id']; ?></span><?php endif; ?>
                                                <?php else : ?>—<?php endif; ?>
                                            </td>
                                            <td><code class="wft-migration-code"><?php echo esc_html( $item['original'] ); ?></code></td>
                                            <td><?php echo ! empty( $item['proposed'] ) ? '<code class="wft-migration-code">' . esc_html( $item['proposed'] ) . '</code>' : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                                            <td>
                                                <?php if ( 'reuse' === $item['tracker_state'] ) : ?>
                                                    <span class="wft-migration-badge is-reuse"><?php echo esc_html( sprintf( __( 'Reuse #%d', 'wp-filetrace' ), (int) $item['tracker_id'] ) ); ?></span>
                                                <?php elseif ( 'create' === $item['tracker_state'] ) : ?>
                                                    <span class="wft-migration-badge is-create"><?php esc_html_e( 'Create', 'wp-filetrace' ); ?></span>
                                                <?php else : ?>—<?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="wft-migration-badge <?php echo ! empty( $item['ready'] ) ? 'is-ready' : 'is-review'; ?>">
                                                    <?php echo ! empty( $item['ready'] ) ? esc_html__( 'Ready', 'wp-filetrace' ) : esc_html__( 'Needs Review', 'wp-filetrace' ); ?>
                                                </span>
                                                <ul class="wft-migration-notes">
                                                    <?php foreach ( $item['notes'] as $note ) : ?><li><?php echo esc_html( $note ); ?></li><?php endforeach; ?>
                                                </ul>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <footer class="wft-footer">
                <span><?php echo esc_html( sprintf( __( 'WP FileTrace v%s', 'wp-filetrace' ), WFT_VERSION ) ); ?></span>
                <span>•</span>
                <span><?php esc_html_e( 'Primary Developer: Brian McLendon', 'wp-filetrace' ); ?></span>
                <span>•</span>
                <a href="https://asenka.com/" target="_blank" rel="noopener noreferrer">Asenka.com</a>
            </footer>
        </div>
        <?php
    }

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'tracked';
        if ( 'analytics' === $tab ) {
            self::render_analytics_page();
            return;
        }
        if ( 'updates' === $tab ) {
            self::render_updates_page();
            return;
        }
        if ( 'settings' === $tab ) {
            self::render_settings_page();
            return;
        }
        if ( 'migration' === $tab ) {
            if ( wft_sdm_migration_enabled() ) {
                self::render_migration_page();
            } else {
                self::render_settings_page();
            }
            return;
        }

        $orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at';
        if ( ! in_array( $orderby, self::sortable_columns(), true ) ) {
            $orderby = 'created_at';
        }

        $order = isset( $_GET['order'] ) ? strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) : 'desc';
        $order = 'asc' === $order ? 'asc' : 'desc';

        $total_items = WFT_Downloads::get_count();
        $total_pages = max( 1, (int) ceil( $total_items / self::PER_PAGE ) );
        $current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $current_page = min( $current_page, $total_pages );
        $rows         = WFT_Downloads::get_page( $current_page, self::PER_PAGE, $orderby, strtoupper( $order ) );
        $highlight_id = isset( $_GET['wft_created'] ) ? absint( $_GET['wft_created'] ) : 0;
        $export_url   = wp_nonce_url( admin_url( 'admin-post.php?action=wft_export_csv' ), 'wft_export_csv' );

        $pagination_base = add_query_arg(
            array(
                'page'    => self::PAGE_SLUG,
                'orderby' => $orderby,
                'order'   => $order,
                'paged'   => 999999999,
            ),
            admin_url( 'admin.php' )
        );
        $pagination_base = str_replace( '999999999', '%#%', $pagination_base );
        ?>
        <div class="wrap wft-wrap">
            <header class="wft-page-header">
                <div class="wft-brand">
                    <div class="wft-logo-shell">
                        <img src="<?php echo esc_url( WFT_URL . 'assets/images/logo--wp-filetrace.svg' ); ?>" alt="<?php esc_attr_e( 'WP FileTrace', 'wp-filetrace' ); ?>">
                    </div>
                    <div class="wft-info-shell">
                        <h1><?php esc_html_e( 'WP FileTrace', 'wp-filetrace' ); ?></h1>
                        <p class="quip"><?php esc_html_e( 'Create tracked download links, monitor usage, and export download data.', 'wp-filetrace' ); ?></p>
                    </div>
                </div>
                <a class="wft-asenka-link" href="https://asenka.com/" target="_blank" rel="noopener noreferrer">Asenka.com ↗</a>
            </header>

            <?php self::render_tabs( 'tracked' ); ?>

            <?php if ( $highlight_id ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Tracked file added. Its shortcode and external link are available from the row actions below.', 'wp-filetrace' ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( isset( $_GET['wft_updated'] ) ) : ?>
                <div class="notice <?php echo '1' === sanitize_key( wp_unslash( $_GET['wft_updated'] ) ) ? 'notice-success' : 'notice-error'; ?> is-dismissible">
                    <p><?php echo '1' === sanitize_key( wp_unslash( $_GET['wft_updated'] ) ) ? esc_html__( 'Tracked file updated.', 'wp-filetrace' ) : esc_html__( 'Tracked file could not be updated. Check the destination URL and try again.', 'wp-filetrace' ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( isset( $_GET['wft_deleted'] ) ) : ?>
                <?php
                $delete_success = '1' === sanitize_key( wp_unslash( $_GET['wft_deleted'] ) );
                $deleted_name   = isset( $_GET['wft_deleted_name'] )
                    ? sanitize_text_field( wp_unslash( $_GET['wft_deleted_name'] ) )
                    : '';
                ?>
                <div class="notice <?php echo $delete_success ? 'notice-success' : 'notice-error'; ?> is-dismissible">
                    <p>
                        <?php
                        if ( $delete_success ) {
                            printf(
                                esc_html__( 'Tracked file “%s” and its download history were permanently deleted.', 'wp-filetrace' ),
                                esc_html( $deleted_name )
                            );
                        } else {
                            esc_html_e( 'Tracked file could not be deleted. No data was intentionally removed.', 'wp-filetrace' );
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ( isset( $_GET['wft_bulk_status'] ) ) : ?>
                <?php
                $bulk_status = sanitize_key( wp_unslash( $_GET['wft_bulk_status'] ) );
                $bulk_count  = isset( $_GET['wft_bulk_deleted'] ) ? absint( $_GET['wft_bulk_deleted'] ) : 0;
                ?>
                <div class="notice <?php echo 'success' === $bulk_status ? 'notice-success' : ( 'none' === $bulk_status ? 'notice-warning' : 'notice-error' ); ?> is-dismissible">
                    <p>
                        <?php
                        if ( 'success' === $bulk_status ) {
                            echo esc_html(
                                sprintf(
                                    _n( '%d selected tracked file and its download history were permanently deleted.', '%d selected tracked files and their download histories were permanently deleted.', $bulk_count, 'wp-filetrace' ),
                                    $bulk_count
                                )
                            );
                        } elseif ( 'none' === $bulk_status ) {
                            esc_html_e( 'No tracked files were selected.', 'wp-filetrace' );
                        } else {
                            esc_html_e( 'The selected tracked files could not be deleted. No partial deletion was intentionally committed.', 'wp-filetrace' );
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ( isset( $_GET['wft_all_status'] ) ) : ?>
                <?php
                $all_status = sanitize_key( wp_unslash( $_GET['wft_all_status'] ) );
                $all_count  = isset( $_GET['wft_all_deleted'] ) ? absint( $_GET['wft_all_deleted'] ) : 0;
                ?>
                <div class="notice <?php echo 'success' === $all_status ? 'notice-success' : 'notice-error'; ?> is-dismissible">
                    <p>
                        <?php
                        if ( 'success' === $all_status ) {
                            echo esc_html(
                                sprintf(
                                    _n( '%d tracked file and its download history were permanently deleted.', '%d tracked files and all associated download history were permanently deleted.', $all_count, 'wp-filetrace' ),
                                    $all_count
                                )
                            );
                        } else {
                            esc_html_e( 'Tracked files could not be deleted. No partial deletion was intentionally committed.', 'wp-filetrace' );
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ( isset( $_GET['wft_test_rows'] ) ) : ?>
                <div class="notice notice-info is-dismissible">
                    <p><?php echo esc_html( sprintf( __( '%d synthetic test rows created.', 'wp-filetrace' ), absint( $_GET['wft_test_rows'] ) ) ); ?></p>
                </div>
            <?php endif; ?>

            <section class="wft-card wft-creator-card">
                <div class="wft-card-heading">
                    <div>
                        <span class="wft-eyebrow"><?php esc_html_e( 'Creator', 'wp-filetrace' ); ?></span>
                        <h2><?php esc_html_e( 'Build a tracked download', 'wp-filetrace' ); ?></h2>
                    </div>
                </div>

                <div class="wft-grid wft-grid-two">
                    <div class="wft-field-group">
                        <label><?php esc_html_e( 'WordPress Media File', 'wp-filetrace' ); ?></label>
                        <input type="hidden" id="wft-attachment-id" value="">
                        <div class="wft-media-control">
                            <button type="button" class="button button-secondary" id="wft-select-media"><?php esc_html_e( 'Select from Media Library', 'wp-filetrace' ); ?></button>
                            <button type="button" class="button-link wft-clear-media" id="wft-clear-media" hidden><?php esc_html_e( 'Clear', 'wp-filetrace' ); ?></button>
                        </div>
                        <div class="wft-media-preview" id="wft-media-preview" hidden>
                            <strong id="wft-media-name"></strong>
                            <span id="wft-media-url"></span>
                        </div>
                    </div>

                    <div class="wft-or-divider"><span><?php esc_html_e( 'or', 'wp-filetrace' ); ?></span></div>

                    <div class="wft-field-group">
                        <label for="wft-manual-url"><?php esc_html_e( 'Direct File URL', 'wp-filetrace' ); ?></label>
                        <input class="regular-text" type="url" id="wft-manual-url" placeholder="https://example.com/files/report.pdf">
                        <p class="description"><?php esc_html_e( 'Use this for files that are not in the WordPress Media Library.', 'wp-filetrace' ); ?></p>
                    </div>
                </div>

                <div class="wft-grid wft-grid-two wft-secondary-fields">
                    <div class="wft-field-group">
                        <label for="wft-title"><?php esc_html_e( 'Internal Title', 'wp-filetrace' ); ?></label>
                        <input class="regular-text" type="text" id="wft-title" placeholder="Annual Report 2026">
                    </div>
                    <div class="wft-field-group">
                        <label for="wft-button-text"><?php esc_html_e( 'Button Text', 'wp-filetrace' ); ?></label>
                        <input class="regular-text" type="text" id="wft-button-text" value="Download">
                        <p class="description"><?php esc_html_e( 'Used when you copy the shortcode from the tracked-file row.', 'wp-filetrace' ); ?></p>
                    </div>
                </div>

                <div class="wft-creator-actions">
                    <button type="button" class="button button-primary button-hero" id="wft-generate"><?php esc_html_e( 'Generate Tracking Link', 'wp-filetrace' ); ?></button>
                    <span class="wft-status" id="wft-status" aria-live="polite"></span>
                </div>
            </section>

            <section class="wft-card">
                <div class="wft-card-heading wft-table-heading">
                    <div>
                        <span class="wft-eyebrow"><?php esc_html_e( 'Reporting', 'wp-filetrace' ); ?></span>
                        <h2><?php esc_html_e( 'Tracked Files', 'wp-filetrace' ); ?></h2>
                    </div>
                    <div class="wft-table-tools">
                        <?php if ( wft_test_rows_enabled() ) : ?>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wft-test-form">
                                <input type="hidden" name="action" value="wft_generate_test_rows">
                                <?php wp_nonce_field( 'wft_generate_test_rows' ); ?>
                                <button type="submit" class="button wft-test-button"><?php esc_html_e( 'Generate 200 Test Rows', 'wp-filetrace' ); ?></button>
                            </form>
                        <?php endif; ?>

                        <form id="wft-bulk-delete-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wft-bulk-delete-form">
                            <input type="hidden" name="action" value="wft_delete_selected_trackers">
                            <input type="hidden" name="return_orderby" value="<?php echo esc_attr( $orderby ); ?>">
                            <input type="hidden" name="return_order" value="<?php echo esc_attr( $order ); ?>">
                            <input type="hidden" name="return_paged" value="<?php echo (int) $current_page; ?>">
                            <?php wp_nonce_field( 'wft_delete_selected_trackers' ); ?>
                            <button type="submit" class="button wft-bulk-delete-button" id="wft-delete-selected" disabled><?php esc_html_e( 'Delete Selected', 'wp-filetrace' ); ?></button>
                        </form>

                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wft-delete-all-form">
                            <input type="hidden" name="action" value="wft_delete_all_trackers">
                            <?php wp_nonce_field( 'wft_delete_all_trackers' ); ?>
                            <button type="submit" class="button wft-bulk-delete-button"<?php disabled( 0 === $total_items ); ?>><?php esc_html_e( 'Delete All', 'wp-filetrace' ); ?></button>
                        </form>

                        <a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'wp-filetrace' ); ?></a>
                    </div>
                </div>

                <div class="wft-table-summary">
                    <span><?php echo esc_html( sprintf( _n( '%s tracked file', '%s tracked files', $total_items, 'wp-filetrace' ), number_format_i18n( $total_items ) ) ); ?></span>
                    <span><?php echo esc_html( sprintf( __( '20 per page · Page %1$d of %2$d', 'wp-filetrace' ), $current_page, $total_pages ) ); ?></span>
                </div>

                <div class="wft-table-wrap">
                    <table class="widefat wft-table">
                        <thead>
                            <tr>
                                <th scope="col" class="wft-check-column">
                                    <input type="checkbox" id="wft-select-all" aria-label="<?php esc_attr_e( 'Select all tracked files on this page', 'wp-filetrace' ); ?>"<?php disabled( empty( $rows ) ); ?>>
                                </th>
                                <?php self::render_sortable_header( __( 'File', 'wp-filetrace' ), 'title', $orderby, $order ); ?>
                                <?php self::render_sortable_header( __( 'Total', 'wp-filetrace' ), 'total_downloads', $orderby, $order ); ?>
                                <?php self::render_sortable_header( __( 'Shortcode', 'wp-filetrace' ), 'shortcode_downloads', $orderby, $order ); ?>
                                <?php self::render_sortable_header( __( 'External', 'wp-filetrace' ), 'external_downloads', $orderby, $order ); ?>
                                <?php self::render_sortable_header( __( 'Created On', 'wp-filetrace' ), 'created_at', $orderby, $order ); ?>
                                <?php self::render_sortable_header( __( 'Last Download', 'wp-filetrace' ), 'last_downloaded_at', $orderby, $order ); ?>
                                <th scope="col"><?php esc_html_e( 'Actions', 'wp-filetrace' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ( empty( $rows ) ) : ?>
                            <tr><td colspan="8" class="wft-empty-state"><?php esc_html_e( 'No tracked files yet. Create your first one above.', 'wp-filetrace' ); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ( $rows as $row ) :
                                $shortcode = WFT_Downloads::shortcode_for( $row );
                                $external  = WFT_Downloads::build_tracked_url( $row, 'external' );
                                $created   = $row->created_at ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->created_at ) : '—';
                                $last      = $row->last_downloaded_at ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->last_downloaded_at ) : '—';
                                $dialog_id = 'wft-edit-' . (int) $row->id;
                                $row_class = $highlight_id === (int) $row->id ? ' class="wft-new-row"' : '';
                                ?>
                                <tr<?php echo $row_class; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed class string only. ?>>
                                    <td class="wft-check-column">
                                        <input
                                            type="checkbox"
                                            class="wft-row-checkbox"
                                            name="tracker_ids[]"
                                            value="<?php echo (int) $row->id; ?>"
                                            form="wft-bulk-delete-form"
                                            aria-label="<?php echo esc_attr( sprintf( __( 'Select %s', 'wp-filetrace' ), $row->title ?: wp_basename( $row->file_url ) ) ); ?>"
                                        >
                                    </td>
                                    <td>
                                        <a class="wft-file-title" href="<?php echo esc_url( $row->file_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $row->title ?: wp_basename( $row->file_url ) ); ?> ↗</a>
                                        <?php if ( $row->attachment_id ) : ?><span class="wft-meta">Media #<?php echo (int) $row->attachment_id; ?></span><?php endif; ?>
                                    </td>
                                    <td><strong><?php echo number_format_i18n( (int) $row->total_downloads ); ?></strong></td>
                                    <td><?php echo number_format_i18n( (int) $row->shortcode_downloads ); ?></td>
                                    <td><?php echo number_format_i18n( (int) $row->external_downloads ); ?></td>
                                    <td><?php echo esc_html( $created ); ?></td>
                                    <td><?php echo esc_html( $last ); ?></td>
                                    <td>
                                        <div class="wft-row-actions">
                                            <button type="button" class="button button-small wft-copy-value wft-icon-text-button" data-copy="<?php echo esc_attr( $shortcode ); ?>">
                                                <span class="dashicons dashicons-shortcode" aria-hidden="true"></span>
                                                <span><?php esc_html_e( 'Copy Shortcode', 'wp-filetrace' ); ?></span>
                                            </button>
                                            <button type="button" class="button button-small wft-copy-value wft-icon-text-button" data-copy="<?php echo esc_attr( $external ); ?>">
                                                <span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
                                                <span><?php esc_html_e( 'Copy Link', 'wp-filetrace' ); ?></span>
                                            </button>
                                            <button type="button" class="button button-small wft-edit-toggle wft-icon-only-button" data-target="<?php echo esc_attr( $dialog_id ); ?>" aria-expanded="false" title="<?php esc_attr_e( 'Edit tracked file', 'wp-filetrace' ); ?>">
                                                <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                                                <span class="screen-reader-text"><?php esc_html_e( 'Edit tracked file', 'wp-filetrace' ); ?></span>
                                            </button>
                                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wft-delete-form">
                                                <input type="hidden" name="action" value="wft_delete_tracker">
                                                <input type="hidden" name="tracker_id" value="<?php echo (int) $row->id; ?>">
                                                <input type="hidden" name="return_orderby" value="<?php echo esc_attr( $orderby ); ?>">
                                                <input type="hidden" name="return_order" value="<?php echo esc_attr( $order ); ?>">
                                                <input type="hidden" name="return_paged" value="<?php echo (int) $current_page; ?>">
                                                <?php wp_nonce_field( 'wft_delete_tracker_' . (int) $row->id ); ?>
                                                <button type="submit" class="button button-small wft-delete-button wft-icon-only-button" title="<?php esc_attr_e( 'Delete tracked file', 'wp-filetrace' ); ?>">
                                                    <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                                                    <span class="screen-reader-text"><?php esc_html_e( 'Delete tracked file', 'wp-filetrace' ); ?></span>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <tr id="<?php echo esc_attr( $dialog_id ); ?>" class="wft-edit-row" hidden>
                                    <td colspan="8">
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wft-edit-form">
                                            <input type="hidden" name="action" value="wft_update_tracker">
                                            <input type="hidden" name="tracker_id" value="<?php echo (int) $row->id; ?>">
                                            <input type="hidden" name="attachment_id" value="<?php echo (int) $row->attachment_id; ?>">
                                            <input type="hidden" name="return_orderby" value="<?php echo esc_attr( $orderby ); ?>">
                                            <input type="hidden" name="return_order" value="<?php echo esc_attr( $order ); ?>">
                                            <input type="hidden" name="return_paged" value="<?php echo (int) $current_page; ?>">
                                            <?php wp_nonce_field( 'wft_update_tracker_' . (int) $row->id ); ?>
                                            <label>
                                                <span><?php esc_html_e( 'Title', 'wp-filetrace' ); ?></span>
                                                <input type="text" name="title" value="<?php echo esc_attr( $row->title ); ?>">
                                            </label>
                                            <label>
                                                <span><?php esc_html_e( 'Button Text', 'wp-filetrace' ); ?></span>
                                                <input type="text" name="button_text" value="<?php echo esc_attr( isset( $row->button_text ) ? $row->button_text : __( 'Download', 'wp-filetrace' ) ); ?>">
                                            </label>
                                            <label class="wft-edit-url">
                                                <span><?php esc_html_e( 'Destination URL', 'wp-filetrace' ); ?></span>
                                                <input type="url" name="file_url" value="<?php echo esc_attr( $row->file_url ); ?>" required>
                                            </label>
                                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Changes', 'wp-filetrace' ); ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ( $total_pages > 1 ) : ?>
                    <div class="wft-pagination">
                        <?php
                        echo wp_kses_post(
                            paginate_links(
                                array(
                                    'base'      => $pagination_base,
                                    'format'    => '',
                                    'current'   => $current_page,
                                    'total'     => $total_pages,
                                    'mid_size'  => 2,
                                    'end_size'  => 1,
                                    'prev_text' => '‹',
                                    'next_text' => '›',
                                    'type'      => 'list',
                                )
                            )
                        );
                        ?>
                    </div>
                <?php endif; ?>
            </section>

            <footer class="wft-footer">
                <span><?php echo esc_html( sprintf( __( 'WP FileTrace v%s', 'wp-filetrace' ), WFT_VERSION ) ); ?></span>
                <span>•</span>
                <span><?php esc_html_e( 'Primary Developer: Brian McLendon', 'wp-filetrace' ); ?></span>
                <span>•</span>
                <a href="https://asenka.com/" target="_blank" rel="noopener noreferrer">Asenka.com</a>
            </footer>
        </div>
        <?php
    }
}
