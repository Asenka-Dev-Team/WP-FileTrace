<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ADT_Admin {
    private const PAGE_SLUG = 'asenka-download-tracker';
    private const PER_PAGE  = 20;

    public static function init(): void {
        add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'wp_ajax_adt_create_tracker', array( __CLASS__, 'ajax_create_tracker' ) );
        add_action( 'admin_post_adt_update_tracker', array( __CLASS__, 'update_tracker' ) );
        add_action( 'admin_post_adt_delete_tracker', array( __CLASS__, 'delete_tracker' ) );
        add_action( 'admin_post_adt_generate_test_rows', array( __CLASS__, 'generate_test_rows' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( ADT_FILE ), array( __CLASS__, 'plugin_action_links' ) );
        add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
    }

    public static function admin_menu(): void {
        add_menu_page(
            __( 'Asenka Download Tracker', 'asenka-download-tracker' ),
            __( 'Download Tracker', 'asenka-download-tracker' ),
            'manage_options',
            self::PAGE_SLUG,
            array( __CLASS__, 'render_page' ),
            ADT_URL . 'assets/images/admin-menu-icon.svg',
            58
        );
    }

    public static function enqueue_assets( string $hook ): void {
        if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style( 'adt-admin', ADT_URL . 'assets/css/admin.css', array(), ADT_VERSION );
        wp_enqueue_script( 'adt-admin', ADT_URL . 'assets/js/admin.js', array( 'jquery' ), ADT_VERSION, true );
        wp_localize_script(
            'adt-admin',
            'ADTAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'pageUrl' => admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
                'nonce'   => wp_create_nonce( 'adt_admin' ),
                'strings' => array(
                    'selectFile'     => __( 'Select a file', 'asenka-download-tracker' ),
                    'useFile'        => __( 'Use this file', 'asenka-download-tracker' ),
                    'working'        => __( 'Generating…', 'asenka-download-tracker' ),
                    'generate'       => __( 'Generate Tracking Link', 'asenka-download-tracker' ),
                    'copied'         => __( 'Copied!', 'asenka-download-tracker' ),
                    'copy'           => __( 'Copy', 'asenka-download-tracker' ),
                    'genericError'   => __( 'Something went wrong. Please try again.', 'asenka-download-tracker' ),
                    'created'        => __( 'Tracked file added. Opening it below…', 'asenka-download-tracker' ),
                    'confirmDelete'  => __( 'Are you sure? This will permanently delete this tracked file and all of its download history. This cannot be undone.', 'asenka-download-tracker' ),
                    'confirmTest'    => __( 'Create 200 synthetic tracked-file rows for sorting and pagination testing?', 'asenka-download-tracker' ),
                ),
            )
        );
    }

    public static function plugin_action_links( array $links ): array {
        array_unshift(
            $links,
            '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '">' . esc_html__( 'Settings', 'asenka-download-tracker' ) . '</a>'
        );
        return $links;
    }

    public static function plugin_row_meta( array $links, string $file ): array {
        if ( plugin_basename( ADT_FILE ) === $file ) {
            $links[] = '<a href="https://asenka.com/" target="_blank" rel="noopener noreferrer">Asenka.com</a>';
        }
        return $links;
    }

    public static function ajax_create_tracker(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'asenka-download-tracker' ) ), 403 );
        }

        check_ajax_referer( 'adt_admin', 'nonce' );

        $attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
        $url           = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
        $title         = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $button_text   = isset( $_POST['button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['button_text'] ) ) : '';

        $tracker = ADT_Downloads::get_or_create( $attachment_id, $url, $title, $button_text );
        if ( is_wp_error( $tracker ) ) {
            wp_send_json_error( array( 'message' => $tracker->get_error_message() ), 400 );
        }

        wp_send_json_success(
            array(
                'id'    => (int) $tracker->id,
                'title' => $tracker->title,
                'page'  => ADT_Downloads::get_created_desc_page_for_id( (int) $tracker->id, self::PER_PAGE ),
            )
        );
    }

    public static function update_tracker(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'asenka-download-tracker' ) );
        }

        $id = isset( $_POST['tracker_id'] ) ? absint( $_POST['tracker_id'] ) : 0;
        check_admin_referer( 'adt_update_tracker_' . $id );

        $title         = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $url           = isset( $_POST['file_url'] ) ? esc_url_raw( wp_unslash( $_POST['file_url'] ) ) : '';
        $attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
        $button_text   = isset( $_POST['button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['button_text'] ) ) : '';
        $state         = self::list_state_from_post();

        $ok = ADT_Downloads::update_tracker( $id, $title, $url, $attachment_id, $button_text );
        $redirect = add_query_arg(
            array_merge(
                array(
                    'page'        => self::PAGE_SLUG,
                    'adt_updated' => $ok ? '1' : '0',
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
            wp_die( esc_html__( 'Permission denied.', 'asenka-download-tracker' ) );
        }

        $id = isset( $_POST['tracker_id'] ) ? absint( $_POST['tracker_id'] ) : 0;
        check_admin_referer( 'adt_delete_tracker_' . $id );

        $state   = self::list_state_from_post();
        $tracker = $id > 0 ? ADT_Downloads::get_by_id( $id ) : null;

        $deleted_name = $tracker && ! empty( $tracker->title )
            ? $tracker->title
            : __( 'Untitled tracked file', 'asenka-download-tracker' );

        $deleted = $tracker && ADT_Downloads::delete_tracker( $id );
        $redirect = add_query_arg(
            array_merge(
                array(
                    'page'        => self::PAGE_SLUG,
                    'adt_deleted'      => $deleted ? '1' : '0',
                    'adt_deleted_name' => $deleted ? $deleted_name : '',
                ),
                $state
            ),
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    public static function generate_test_rows(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'asenka-download-tracker' ) );
        }

        check_admin_referer( 'adt_generate_test_rows' );

        $created = ADT_Downloads::create_test_rows( 200 );
        $redirect = add_query_arg(
            array(
                'page'          => self::PAGE_SLUG,
                'orderby'       => 'created_at',
                'order'         => 'desc',
                'paged'         => 1,
                'adt_test_rows' => $created,
            ),
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect );
        exit;
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
            __( 'Sort %1$s %2$s', 'asenka-download-tracker' ),
            $label,
            'asc' === $next_order ? __( 'ascending', 'asenka-download-tracker' ) : __( 'descending', 'asenka-download-tracker' )
        );
        ?>
        <th scope="col" aria-sort="<?php echo esc_attr( $aria_sort ); ?>">
            <span class="adt-sort-heading">
                <span><?php echo esc_html( $label ); ?></span>
                <a class="adt-sort-button<?php echo $active ? ' is-active' : ''; ?>" href="<?php echo esc_url( self::sort_url( $column, $orderby, $order ) ); ?>" aria-label="<?php echo esc_attr( $button_label ); ?>" title="<?php echo esc_attr( $button_label ); ?>"><?php echo esc_html( $indicator ); ?></a>
            </span>
        </th>
        <?php
    }

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at';
        if ( ! in_array( $orderby, self::sortable_columns(), true ) ) {
            $orderby = 'created_at';
        }

        $order = isset( $_GET['order'] ) ? strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) : 'desc';
        $order = 'asc' === $order ? 'asc' : 'desc';

        $total_items = ADT_Downloads::get_count();
        $total_pages = max( 1, (int) ceil( $total_items / self::PER_PAGE ) );
        $current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $current_page = min( $current_page, $total_pages );
        $rows         = ADT_Downloads::get_page( $current_page, self::PER_PAGE, $orderby, strtoupper( $order ) );
        $highlight_id = isset( $_GET['adt_created'] ) ? absint( $_GET['adt_created'] ) : 0;
        $export_url   = wp_nonce_url( admin_url( 'admin-post.php?action=adt_export_csv' ), 'adt_export_csv' );

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
        <div class="wrap adt-wrap">
            <header class="adt-page-header">
                <div class="adt-brand">
                    <div class="adt-logo-shell">
                        <img src="<?php echo esc_url( ADT_URL . 'assets/images/asenka-download-tracker-logo.svg' ); ?>" alt="<?php esc_attr_e( 'Asenka Download Tracker', 'asenka-download-tracker' ); ?>">
                    </div>
                    <div>
                        <h1><?php esc_html_e( 'Asenka Download Tracker', 'asenka-download-tracker' ); ?></h1>
                        <p><?php esc_html_e( 'Create tracked download links, monitor usage, and export download data.', 'asenka-download-tracker' ); ?></p>
                    </div>
                </div>
                <a class="adt-asenka-link" href="https://asenka.com/" target="_blank" rel="noopener noreferrer">Asenka.com ↗</a>
            </header>

            <?php if ( $highlight_id ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Tracked file added. Its shortcode and external link are available from the row actions below.', 'asenka-download-tracker' ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( isset( $_GET['adt_updated'] ) ) : ?>
                <div class="notice <?php echo '1' === sanitize_key( wp_unslash( $_GET['adt_updated'] ) ) ? 'notice-success' : 'notice-error'; ?> is-dismissible">
                    <p><?php echo '1' === sanitize_key( wp_unslash( $_GET['adt_updated'] ) ) ? esc_html__( 'Tracked file updated.', 'asenka-download-tracker' ) : esc_html__( 'Tracked file could not be updated. Check the destination URL and try again.', 'asenka-download-tracker' ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( isset( $_GET['adt_deleted'] ) ) : ?>
                <?php
                $delete_success = '1' === sanitize_key( wp_unslash( $_GET['adt_deleted'] ) );
                $deleted_name   = isset( $_GET['adt_deleted_name'] )
                    ? sanitize_text_field( wp_unslash( $_GET['adt_deleted_name'] ) )
                    : '';
                ?>

                <div class="notice <?php echo $delete_success ? 'notice-success' : 'notice-error'; ?> is-dismissible">
                    <p>
                        <?php
                        if ( $delete_success ) {
                            printf(
                                esc_html__( 'Tracked file “%s” and its download history were permanently deleted.', 'asenka-download-tracker' ),
                                esc_html( $deleted_name )
                            );
                        } else {
                            esc_html_e( 'Tracked file could not be deleted. No data was intentionally removed.', 'asenka-download-tracker' );
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ( isset( $_GET['adt_test_rows'] ) ) : ?>
                <div class="notice notice-info is-dismissible">
                    <p><?php echo esc_html( sprintf( __( '%d synthetic test rows created.', 'asenka-download-tracker' ), absint( $_GET['adt_test_rows'] ) ) ); ?></p>
                </div>
            <?php endif; ?>

            <section class="adt-card adt-creator-card">
                <div class="adt-card-heading">
                    <div>
                        <span class="adt-eyebrow"><?php esc_html_e( 'Creator', 'asenka-download-tracker' ); ?></span>
                        <h2><?php esc_html_e( 'Build a tracked download', 'asenka-download-tracker' ); ?></h2>
                    </div>
                </div>

                <div class="adt-grid adt-grid-two">
                    <div class="adt-field-group">
                        <label><?php esc_html_e( 'WordPress Media File', 'asenka-download-tracker' ); ?></label>
                        <input type="hidden" id="adt-attachment-id" value="">
                        <div class="adt-media-control">
                            <button type="button" class="button button-secondary" id="adt-select-media"><?php esc_html_e( 'Select from Media Library', 'asenka-download-tracker' ); ?></button>
                            <button type="button" class="button-link adt-clear-media" id="adt-clear-media" hidden><?php esc_html_e( 'Clear', 'asenka-download-tracker' ); ?></button>
                        </div>
                        <div class="adt-media-preview" id="adt-media-preview" hidden>
                            <strong id="adt-media-name"></strong>
                            <span id="adt-media-url"></span>
                        </div>
                    </div>

                    <div class="adt-or-divider"><span><?php esc_html_e( 'or', 'asenka-download-tracker' ); ?></span></div>

                    <div class="adt-field-group">
                        <label for="adt-manual-url"><?php esc_html_e( 'Direct File URL', 'asenka-download-tracker' ); ?></label>
                        <input class="regular-text" type="url" id="adt-manual-url" placeholder="https://example.com/files/report.pdf">
                        <p class="description"><?php esc_html_e( 'Use this for files that are not in the WordPress Media Library.', 'asenka-download-tracker' ); ?></p>
                    </div>
                </div>

                <div class="adt-grid adt-grid-two adt-secondary-fields">
                    <div class="adt-field-group">
                        <label for="adt-title"><?php esc_html_e( 'Internal Title', 'asenka-download-tracker' ); ?></label>
                        <input class="regular-text" type="text" id="adt-title" placeholder="Annual Report 2026">
                    </div>
                    <div class="adt-field-group">
                        <label for="adt-button-text"><?php esc_html_e( 'Button Text', 'asenka-download-tracker' ); ?></label>
                        <input class="regular-text" type="text" id="adt-button-text" value="Download">
                        <p class="description"><?php esc_html_e( 'Used when you copy the shortcode from the tracked-file row.', 'asenka-download-tracker' ); ?></p>
                    </div>
                </div>

                <div class="adt-creator-actions">
                    <button type="button" class="button button-primary button-hero" id="adt-generate"><?php esc_html_e( 'Generate Tracking Link', 'asenka-download-tracker' ); ?></button>
                    <span class="adt-status" id="adt-status" aria-live="polite"></span>
                </div>
            </section>

            <section class="adt-card">
                <div class="adt-card-heading adt-table-heading">
                    <div>
                        <span class="adt-eyebrow"><?php esc_html_e( 'Reporting', 'asenka-download-tracker' ); ?></span>
                        <h2><?php esc_html_e( 'Tracked Files', 'asenka-download-tracker' ); ?></h2>
                    </div>
                    <div class="adt-table-tools">
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="adt-test-form">
                            <input type="hidden" name="action" value="adt_generate_test_rows">
                            <?php wp_nonce_field( 'adt_generate_test_rows' ); ?>
                            <button type="submit" class="button adt-test-button"><?php esc_html_e( 'Generate 200 Test Rows', 'asenka-download-tracker' ); ?></button>
                        </form>
                        <a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'asenka-download-tracker' ); ?></a>
                    </div>
                </div>

                <div class="adt-table-summary">
                    <span><?php echo esc_html( sprintf( _n( '%s tracked file', '%s tracked files', $total_items, 'asenka-download-tracker' ), number_format_i18n( $total_items ) ) ); ?></span>
                    <span><?php echo esc_html( sprintf( __( '20 per page · Page %1$d of %2$d', 'asenka-download-tracker' ), $current_page, $total_pages ) ); ?></span>
                </div>

                <div class="adt-table-wrap">
                    <table class="widefat striped adt-table">
                        <thead>
                            <tr>
                                <?php self::render_sortable_header( __( 'File', 'asenka-download-tracker' ), 'title', $orderby, $order ); ?>
                                <?php self::render_sortable_header( __( 'Total', 'asenka-download-tracker' ), 'total_downloads', $orderby, $order ); ?>
                                <?php self::render_sortable_header( __( 'Shortcode', 'asenka-download-tracker' ), 'shortcode_downloads', $orderby, $order ); ?>
                                <?php self::render_sortable_header( __( 'External', 'asenka-download-tracker' ), 'external_downloads', $orderby, $order ); ?>
                                <?php self::render_sortable_header( __( 'Last Download', 'asenka-download-tracker' ), 'last_downloaded_at', $orderby, $order ); ?>
                                <?php self::render_sortable_header( __( 'Date Created', 'asenka-download-tracker' ), 'created_at', $orderby, $order ); ?>
                                <th scope="col"><?php esc_html_e( 'Actions', 'asenka-download-tracker' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ( empty( $rows ) ) : ?>
                            <tr><td colspan="7" class="adt-empty-state"><?php esc_html_e( 'No tracked files yet. Create your first one above.', 'asenka-download-tracker' ); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ( $rows as $row ) :
                                $shortcode = ADT_Downloads::shortcode_for( $row );
                                $external  = ADT_Downloads::build_tracked_url( $row, 'external' );
                                $last      = $row->last_downloaded_at ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->last_downloaded_at ) : '—';
                                $created   = $row->created_at ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->created_at ) : '—';
                                $dialog_id = 'adt-edit-' . (int) $row->id;
                                $row_class = $highlight_id === (int) $row->id ? ' class="adt-new-row"' : '';
                                ?>
                                <tr<?php echo $row_class; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed class string only. ?>>
                                    <td>
                                        <a class="adt-file-title" href="<?php echo esc_url( $row->file_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $row->title ?: wp_basename( $row->file_url ) ); ?> ↗</a>
                                        <?php if ( $row->attachment_id ) : ?><span class="adt-meta">Media #<?php echo (int) $row->attachment_id; ?></span><?php endif; ?>
                                    </td>
                                    <td><strong><?php echo number_format_i18n( (int) $row->total_downloads ); ?></strong></td>
                                    <td><?php echo number_format_i18n( (int) $row->shortcode_downloads ); ?></td>
                                    <td><?php echo number_format_i18n( (int) $row->external_downloads ); ?></td>
                                    <td><?php echo esc_html( $last ); ?></td>
                                    <td><?php echo esc_html( $created ); ?></td>
                                    <td>
                                        <div class="adt-row-actions">
                                            <button type="button" class="button button-small adt-copy-value" data-copy="<?php echo esc_attr( $shortcode ); ?>"><?php esc_html_e( 'Copy Shortcode', 'asenka-download-tracker' ); ?></button>
                                            <button type="button" class="button button-small adt-copy-value" data-copy="<?php echo esc_attr( $external ); ?>"><?php esc_html_e( 'Copy Link', 'asenka-download-tracker' ); ?></button>
                                            <button type="button" class="button button-small adt-edit-toggle" data-target="<?php echo esc_attr( $dialog_id ); ?>"><?php esc_html_e( 'Edit', 'asenka-download-tracker' ); ?></button>
                                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="adt-delete-form">
                                                <input type="hidden" name="action" value="adt_delete_tracker">
                                                <input type="hidden" name="tracker_id" value="<?php echo (int) $row->id; ?>">
                                                <input type="hidden" name="return_orderby" value="<?php echo esc_attr( $orderby ); ?>">
                                                <input type="hidden" name="return_order" value="<?php echo esc_attr( $order ); ?>">
                                                <input type="hidden" name="return_paged" value="<?php echo (int) $current_page; ?>">
                                                <?php wp_nonce_field( 'adt_delete_tracker_' . (int) $row->id ); ?>
                                                <button type="submit" class="button button-small adt-delete-button"><?php esc_html_e( 'Delete', 'asenka-download-tracker' ); ?></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <tr id="<?php echo esc_attr( $dialog_id ); ?>" class="adt-edit-row" hidden>
                                    <td colspan="7">
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="adt-edit-form">
                                            <input type="hidden" name="action" value="adt_update_tracker">
                                            <input type="hidden" name="tracker_id" value="<?php echo (int) $row->id; ?>">
                                            <input type="hidden" name="attachment_id" value="<?php echo (int) $row->attachment_id; ?>">
                                            <input type="hidden" name="return_orderby" value="<?php echo esc_attr( $orderby ); ?>">
                                            <input type="hidden" name="return_order" value="<?php echo esc_attr( $order ); ?>">
                                            <input type="hidden" name="return_paged" value="<?php echo (int) $current_page; ?>">
                                            <?php wp_nonce_field( 'adt_update_tracker_' . (int) $row->id ); ?>
                                            <label>
                                                <span><?php esc_html_e( 'Title', 'asenka-download-tracker' ); ?></span>
                                                <input type="text" name="title" value="<?php echo esc_attr( $row->title ); ?>">
                                            </label>
                                            <label>
                                                <span><?php esc_html_e( 'Button Text', 'asenka-download-tracker' ); ?></span>
                                                <input type="text" name="button_text" value="<?php echo esc_attr( isset( $row->button_text ) ? $row->button_text : __( 'Download', 'asenka-download-tracker' ) ); ?>">
                                            </label>
                                            <label class="adt-edit-url">
                                                <span><?php esc_html_e( 'Destination URL', 'asenka-download-tracker' ); ?></span>
                                                <input type="url" name="file_url" value="<?php echo esc_attr( $row->file_url ); ?>" required>
                                            </label>
                                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Changes', 'asenka-download-tracker' ); ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ( $total_pages > 1 ) : ?>
                    <div class="adt-pagination">
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

            <footer class="adt-footer">
                <span><?php echo esc_html( sprintf( __( 'Asenka Download Tracker v%s', 'asenka-download-tracker' ), ADT_VERSION ) ); ?></span>
                <span>•</span>
                <span><?php esc_html_e( 'Primary Developer: Brian McLendon', 'asenka-download-tracker' ); ?></span>
                <span>•</span>
                <a href="https://asenka.com/" target="_blank" rel="noopener noreferrer">Asenka.com</a>
            </footer>
        </div>
        <?php
    }
}
