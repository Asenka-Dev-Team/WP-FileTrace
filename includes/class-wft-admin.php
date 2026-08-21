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
        add_action( 'admin_post_wft_update_tracker', array( __CLASS__, 'update_tracker' ) );
        add_action( 'admin_post_wft_delete_tracker', array( __CLASS__, 'delete_tracker' ) );
        add_action( 'admin_post_wft_delete_selected_trackers', array( __CLASS__, 'delete_selected_trackers' ) );
        add_action( 'admin_post_wft_delete_all_trackers', array( __CLASS__, 'delete_all_trackers' ) );
        add_action( 'admin_post_wft_generate_test_rows', array( __CLASS__, 'generate_test_rows' ) );
        add_action( 'admin_post_wft_save_analytics', array( __CLASS__, 'save_analytics' ) );
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
                    'copied'         => __( 'Copied!', 'wp-filetrace' ),
                    'copy'           => __( 'Copy', 'wp-filetrace' ),
                    'genericError'   => __( 'Something went wrong. Please try again.', 'wp-filetrace' ),
                    'created'        => __( 'Tracked file added. Opening it below…', 'wp-filetrace' ),
                    'confirmDelete'         => __( 'Are you sure? This will permanently delete this tracked file and all of its download history. This cannot be undone.', 'wp-filetrace' ),
                    'confirmDeleteSelected' => __( 'Are you sure? This will permanently delete the selected tracked files and all of their download history. This cannot be undone.', 'wp-filetrace' ),
                    'confirmDeleteAll'      => __( 'Are you sure? This will permanently delete ALL tracked files on every page and all download history. This cannot be undone.', 'wp-filetrace' ),
                    'confirmTest'           => __( 'Create 200 synthetic tracked-file rows for sorting and pagination testing?', 'wp-filetrace' ),
                ),
            )
        );
    }

    public static function plugin_action_links( array $links ): array {
        array_unshift(
            $links,
            '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '">' . esc_html__( 'Settings', 'wp-filetrace' ) . '</a>'
        );
        return $links;
    }

    public static function plugin_row_meta( array $links, string $file ): array {
        if ( plugin_basename( WFT_FILE ) === $file ) {
            $links[] = '<a href="https://asenka.com/" target="_blank" rel="noopener noreferrer">Asenka.com</a>';
        }
        return $links;
    }

    public static function ajax_create_tracker(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-filetrace' ) ), 403 );
        }

        check_ajax_referer( 'wft_admin', 'nonce' );

        $attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
        $url           = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
        $title         = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $button_text   = isset( $_POST['button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['button_text'] ) ) : '';

        $tracker = WFT_Downloads::get_or_create( $attachment_id, $url, $title, $button_text );
        if ( is_wp_error( $tracker ) ) {
            wp_send_json_error( array( 'message' => $tracker->get_error_message() ), 400 );
        }

        wp_send_json_success(
            array(
                'id'    => (int) $tracker->id,
                'title' => $tracker->title,
                'page'  => WFT_Downloads::get_created_desc_page_for_id( (int) $tracker->id, self::PER_PAGE ),
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
            $event_snippet  = isset( $_POST['wft_ga_event_snippet'] ) ? (string) wp_unslash( $_POST['wft_ga_event_snippet'] ) : '';
            $file_parameter = isset( $_POST['wft_ga_filename_parameter'] ) ? sanitize_text_field( wp_unslash( $_POST['wft_ga_filename_parameter'] ) ) : '';

            if ( ( '' !== trim( $global_snippet ) || '' !== trim( $event_snippet ) ) && ! current_user_can( 'unfiltered_html' ) ) {
                wp_die( esc_html__( 'Your account is not allowed to save executable analytics snippets.', 'wp-filetrace' ) );
            }

            WFT_Analytics::save_settings( $global_snippet, $event_snippet, $file_parameter );
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

    private static function render_tabs( string $active_tab ): void {
        $tabs = array(
            'tracked'   => __( 'Tracked Files', 'wp-filetrace' ),
            'analytics' => __( 'Analytics', 'wp-filetrace' ),
        );
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
        $event_snippet  = WFT_Analytics::get_event_snippet();
        $file_parameter = WFT_Analytics::get_filename_parameter();
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
                            esc_html_e( 'Download-event snippet and file-name parameter cleared.', 'wp-filetrace' );
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
                        <textarea id="wft-ga-event-snippet" class="wft-code-textarea" name="wft_ga_event_snippet" rows="10" spellcheck="false" placeholder="gtag('event', 'file_download', {&#10;    'file_source': 'WP FileTrace'&#10;});"><?php echo esc_textarea( $event_snippet ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'A surrounding <script> tag is optional for this field. WP FileTrace executes the event during a short browser handoff and then continues to the requested file.', 'wp-filetrace' ); ?></p>
                    </div>

                    <div class="wft-analytics-section wft-analytics-parameter-section">
                        <label for="wft-ga-filename-parameter"><?php esc_html_e( 'File Name Event Parameter', 'wp-filetrace' ); ?></label>
                        <input type="text" id="wft-ga-filename-parameter" name="wft_ga_filename_parameter" value="<?php echo esc_attr( $file_parameter ); ?>" placeholder="file_name">
                        <p class="description">
                            <?php esc_html_e( 'Optional. Enter the gtag event parameter that should receive the actual downloaded file name. If your event snippet already sets that parameter, WP FileTrace overwrites its value for the download event. Examples: file_name or value.', 'wp-filetrace' ); ?>
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

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'tracked';
        if ( 'analytics' === $tab ) {
            self::render_analytics_page();
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
                    <div>
                        <h1><?php esc_html_e( 'WP FileTrace', 'wp-filetrace' ); ?></h1>
                        <p><?php esc_html_e( 'Create tracked download links, monitor usage, and export download data.', 'wp-filetrace' ); ?></p>
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
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wft-test-form">
                            <input type="hidden" name="action" value="wft_generate_test_rows">
                            <?php wp_nonce_field( 'wft_generate_test_rows' ); ?>
                            <button type="submit" class="button wft-test-button"><?php esc_html_e( 'Generate 200 Test Rows', 'wp-filetrace' ); ?></button>
                        </form>

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
