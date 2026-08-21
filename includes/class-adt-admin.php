<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ADT_Admin {
    private const PAGE_SLUG = 'asenka-download-tracker';

    public static function init(): void {
        add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'wp_ajax_adt_create_tracker', array( __CLASS__, 'ajax_create_tracker' ) );
        add_action( 'admin_post_adt_update_tracker', array( __CLASS__, 'update_tracker' ) );
        add_action( 'admin_post_adt_delete_tracker', array( __CLASS__, 'delete_tracker' ) );
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
                'nonce'   => wp_create_nonce( 'adt_admin' ),
                'strings' => array(
                    'selectFile'   => __( 'Select a file', 'asenka-download-tracker' ),
                    'useFile'      => __( 'Use this file', 'asenka-download-tracker' ),
                    'working'      => __( 'Generating…', 'asenka-download-tracker' ),
                    'generate'     => __( 'Generate Tracking Links', 'asenka-download-tracker' ),
                    'copied'       => __( 'Copied!', 'asenka-download-tracker' ),
                    'copy'         => __( 'Copy', 'asenka-download-tracker' ),
                    'genericError'   => __( 'Something went wrong. Please try again.', 'asenka-download-tracker' ),
                    'confirmDelete'  => __( 'Are you sure? This will permanently delete this tracked download and all of its download history. This cannot be undone.', 'asenka-download-tracker' ),
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

        $tracker = ADT_Downloads::get_or_create( $attachment_id, $url, $title );
        if ( is_wp_error( $tracker ) ) {
            wp_send_json_error( array( 'message' => $tracker->get_error_message() ), 400 );
        }

        wp_send_json_success(
            array(
                'id'          => (int) $tracker->id,
                'shortcode'   => ADT_Downloads::shortcode_for( $tracker, $button_text ),
                'externalUrl' => ADT_Downloads::build_tracked_url( $tracker, 'external' ),
                'title'       => $tracker->title,
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

        $ok = ADT_Downloads::update_tracker( $id, $title, $url, $attachment_id );
        $redirect = add_query_arg(
            array(
                'page'        => self::PAGE_SLUG,
                'adt_updated' => $ok ? '1' : '0',
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

        $deleted = $id > 0 && ADT_Downloads::delete_tracker( $id );
        $redirect = add_query_arg(
            array(
                'page'        => self::PAGE_SLUG,
                'adt_deleted' => $deleted ? '1' : '0',
            ),
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $rows       = ADT_Downloads::get_all();
        $export_url = wp_nonce_url( admin_url( 'admin-post.php?action=adt_export_csv' ), 'adt_export_csv' );
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

            <?php if ( isset( $_GET['adt_updated'] ) ) : ?>
                <div class="notice <?php echo '1' === sanitize_key( wp_unslash( $_GET['adt_updated'] ) ) ? 'notice-success' : 'notice-error'; ?> is-dismissible">
                    <p><?php echo '1' === sanitize_key( wp_unslash( $_GET['adt_updated'] ) ) ? esc_html__( 'Tracker updated.', 'asenka-download-tracker' ) : esc_html__( 'Tracker could not be updated. Check the destination URL and try again.', 'asenka-download-tracker' ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( isset( $_GET['adt_deleted'] ) ) : ?>
                <div class="notice <?php echo '1' === sanitize_key( wp_unslash( $_GET['adt_deleted'] ) ) ? 'notice-success' : 'notice-error'; ?> is-dismissible">
                    <p><?php echo '1' === sanitize_key( wp_unslash( $_GET['adt_deleted'] ) ) ? esc_html__( 'Tracked download and its download history were permanently deleted.', 'asenka-download-tracker' ) : esc_html__( 'Tracked download could not be deleted. No data was intentionally removed.', 'asenka-download-tracker' ); ?></p>
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
                    </div>
                </div>

                <div class="adt-creator-actions">
                    <button type="button" class="button button-primary button-hero" id="adt-generate"><?php esc_html_e( 'Generate Tracking Links', 'asenka-download-tracker' ); ?></button>
                    <span class="adt-status" id="adt-status" aria-live="polite"></span>
                </div>

                <div class="adt-results" id="adt-results" hidden>
                    <div class="adt-result-row">
                        <div>
                            <span class="adt-result-label"><?php esc_html_e( 'Shortcode', 'asenka-download-tracker' ); ?></span>
                            <code id="adt-shortcode-output"></code>
                        </div>
                        <button type="button" class="button adt-copy" data-target="adt-shortcode-output"><?php esc_html_e( 'Copy Shortcode', 'asenka-download-tracker' ); ?></button>
                    </div>
                    <div class="adt-result-row">
                        <div>
                            <span class="adt-result-label"><?php esc_html_e( 'External / Email Link', 'asenka-download-tracker' ); ?></span>
                            <code id="adt-external-output"></code>
                        </div>
                        <button type="button" class="button adt-copy" data-target="adt-external-output"><?php esc_html_e( 'Copy Link', 'asenka-download-tracker' ); ?></button>
                    </div>
                </div>
            </section>

            <section class="adt-card">
                <div class="adt-card-heading adt-table-heading">
                    <div>
                        <span class="adt-eyebrow"><?php esc_html_e( 'Reporting', 'asenka-download-tracker' ); ?></span>
                        <h2><?php esc_html_e( 'Tracked Downloads', 'asenka-download-tracker' ); ?></h2>
                    </div>
                    <a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'asenka-download-tracker' ); ?></a>
                </div>

                <div class="adt-table-wrap">
                    <table class="widefat striped adt-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'File', 'asenka-download-tracker' ); ?></th>
                                <th><?php esc_html_e( 'Total', 'asenka-download-tracker' ); ?></th>
                                <th><?php esc_html_e( 'Shortcode', 'asenka-download-tracker' ); ?></th>
                                <th><?php esc_html_e( 'External', 'asenka-download-tracker' ); ?></th>
                                <th><?php esc_html_e( 'Last Download', 'asenka-download-tracker' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'asenka-download-tracker' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ( empty( $rows ) ) : ?>
                            <tr><td colspan="6" class="adt-empty-state"><?php esc_html_e( 'No tracked downloads yet. Create your first one above.', 'asenka-download-tracker' ); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ( $rows as $row ) :
                                $shortcode   = ADT_Downloads::shortcode_for( $row );
                                $external    = ADT_Downloads::build_tracked_url( $row, 'external' );
                                $last        = $row->last_downloaded_at ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->last_downloaded_at ) : '—';
                                $dialog_id   = 'adt-edit-' . (int) $row->id;
                                ?>
                                <tr>
                                    <td>
                                        <a class="adt-file-title" href="<?php echo esc_url( $row->file_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $row->title ?: wp_basename( $row->file_url ) ); ?> ↗</a>
                                        <?php if ( $row->attachment_id ) : ?><span class="adt-meta">Media #<?php echo (int) $row->attachment_id; ?></span><?php endif; ?>
                                    </td>
                                    <td><strong><?php echo number_format_i18n( (int) $row->total_downloads ); ?></strong></td>
                                    <td><?php echo number_format_i18n( (int) $row->shortcode_downloads ); ?></td>
                                    <td><?php echo number_format_i18n( (int) $row->external_downloads ); ?></td>
                                    <td><?php echo esc_html( $last ); ?></td>
                                    <td>
                                        <div class="adt-row-actions">
                                            <button type="button" class="button button-small adt-copy-value" data-copy="<?php echo esc_attr( $shortcode ); ?>"><?php esc_html_e( 'Copy Shortcode', 'asenka-download-tracker' ); ?></button>
                                            <button type="button" class="button button-small adt-copy-value" data-copy="<?php echo esc_attr( $external ); ?>"><?php esc_html_e( 'Copy Link', 'asenka-download-tracker' ); ?></button>
                                            <button type="button" class="button button-small adt-edit-toggle" data-target="<?php echo esc_attr( $dialog_id ); ?>"><?php esc_html_e( 'Edit', 'asenka-download-tracker' ); ?></button>
                                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="adt-delete-form">
                                                <input type="hidden" name="action" value="adt_delete_tracker">
                                                <input type="hidden" name="tracker_id" value="<?php echo (int) $row->id; ?>">
                                                <?php wp_nonce_field( 'adt_delete_tracker_' . (int) $row->id ); ?>
                                                <button type="submit" class="button button-small adt-delete-button"><?php esc_html_e( 'Delete', 'asenka-download-tracker' ); ?></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <tr id="<?php echo esc_attr( $dialog_id ); ?>" class="adt-edit-row" hidden>
                                    <td colspan="6">
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="adt-edit-form">
                                            <input type="hidden" name="action" value="adt_update_tracker">
                                            <input type="hidden" name="tracker_id" value="<?php echo (int) $row->id; ?>">
                                            <input type="hidden" name="attachment_id" value="<?php echo (int) $row->attachment_id; ?>">
                                            <?php wp_nonce_field( 'adt_update_tracker_' . (int) $row->id ); ?>
                                            <label>
                                                <span><?php esc_html_e( 'Title', 'asenka-download-tracker' ); ?></span>
                                                <input type="text" name="title" value="<?php echo esc_attr( $row->title ); ?>">
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
