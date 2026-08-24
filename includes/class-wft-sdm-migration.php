<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Temporary Simple Download Monitor -> WP FileTrace migration helper.
 *
 * This utility intentionally migrates only individual SDM download shortcodes
 * found in post_content. Post-meta references are reported for review but are
 * never modified automatically.
 */
final class WFT_SDM_Migration {
    private const ROLLBACK_OPTION = 'wft_sdm_migration_rollback';
    private const LAST_RUN_OPTION = 'wft_sdm_migration_last_run';
    private const BACKUP_META     = '_wft_sdm_migration_original_content';

    public static function scan_site(): array {
        global $wpdb;

        $content_like_a = '%' . $wpdb->esc_like( '[sdm_download' ) . '%';
        $content_like_b = '%' . $wpdb->esc_like( '[sdm-download' ) . '%';

        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_type, post_status, post_title, post_content
                 FROM {$wpdb->posts}
                 WHERE (post_content LIKE %s OR post_content LIKE %s)
                   AND post_status NOT IN ('trash', 'auto-draft', 'inherit')
                   AND post_type NOT IN ('revision', 'attachment', 'sdm_downloads', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache')
                 ORDER BY post_type ASC, post_title ASC, ID ASC",
                $content_like_a,
                $content_like_b
            )
        ) ?: array();

        $items = array();
        foreach ( $posts as $post ) {
            foreach ( self::find_download_shortcodes( (string) $post->post_content ) as $occurrence ) {
                $items[] = self::analyze_occurrence( $post, $occurrence, 'post_content' );
            }
        }

        // Detect SDM shortcodes stored in post meta, but never auto-edit meta.
        $meta_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm.post_id, pm.meta_key, pm.meta_value, p.post_type, p.post_status, p.post_title
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE (pm.meta_value LIKE %s OR pm.meta_value LIKE %s)
                   AND pm.meta_key <> %s
                   AND p.post_status NOT IN ('trash', 'auto-draft', 'inherit')
                   AND p.post_type NOT IN ('revision', 'attachment', 'sdm_downloads')
                 ORDER BY p.post_type ASC, p.post_title ASC, p.ID ASC
                 LIMIT 1000",
                $content_like_a,
                $content_like_b,
                self::BACKUP_META
            )
        ) ?: array();

        foreach ( $meta_rows as $meta_row ) {
            foreach ( self::find_download_shortcodes( (string) $meta_row->meta_value ) as $occurrence ) {
                $item = self::analyze_occurrence( $meta_row, $occurrence, 'post_meta' );
                $item['meta_key'] = (string) $meta_row->meta_key;
                $item['ready']    = false;
                $item['status']   = 'review';
                $item['notes'][]  = __( 'Stored in post meta. WP FileTrace reports this reference but will not automatically modify post meta or serialized/page-builder data.', 'wp-filetrace' );
                $items[] = $item;
            }
        }

        $ready         = 0;
        $review        = 0;
        $reuse_ids     = array();
        $create_keys   = array();
        $content_items = array();
        $meta_items    = 0;

        foreach ( $items as $item ) {
            if ( ! empty( $item['ready'] ) ) {
                ++$ready;
            } else {
                ++$review;
            }

            if ( ! empty( $item['ready'] ) && 'post_content' === $item['source_type'] ) {
                if ( 'reuse' === $item['tracker_state'] && ! empty( $item['tracker_id'] ) ) {
                    $reuse_ids[ (int) $item['tracker_id'] ] = true;
                } elseif ( 'create' === $item['tracker_state'] && ! empty( $item['file_url'] ) ) {
                    $create_keys[ WFT_Downloads::destination_hash( (int) $item['attachment_id'], (string) $item['file_url'] ) ] = true;
                }
            }

            if ( 'post_content' === $item['source_type'] ) {
                $content_items[ (int) $item['post_id'] ] = true;
            } else {
                ++$meta_items;
            }
        }

        return array(
            'items'                => $items,
            'total'                => count( $items ),
            'ready'                => $ready,
            'review'               => $review,
            'reuse'                => count( $reuse_ids ),
            'create'               => count( $create_keys ),
            'content_post_count'   => count( $content_items ),
            'meta_reference_count' => $meta_items,
            'related_count'        => self::count_related_sdm_shortcodes(),
            'audit'                => self::build_usage_audit( $items ),
        );
    }

    /**
     * Inventory every SDM download record and compare it with known reference
     * types. "No direct reference" intentionally does not mean "unused"; SDM
     * category/listing shortcodes can expose items dynamically without placing
     * the individual download ID in content.
     */
    private static function build_usage_audit( array $scan_items ): array {
        global $wpdb;

        $sdm_posts = $wpdb->get_results(
            "SELECT ID, post_title, post_status
             FROM {$wpdb->posts}
             WHERE post_type = 'sdm_downloads'
               AND post_status NOT IN ('trash', 'auto-draft')
             ORDER BY post_title ASC, ID ASC"
        ) ?: array();

        $usage = array();
        foreach ( $sdm_posts as $sdm_post ) {
            $usage[ (int) $sdm_post->ID ] = array(
                'standard_content' => 0,
                'standard_meta'    => 0,
                'direct_content'   => 0,
                'direct_meta'      => 0,
                'related_content'  => 0,
                'related_meta'     => 0,
                'hidden_content'   => 0,
                'hidden_meta'      => 0,
            );
        }

        foreach ( $scan_items as $item ) {
            $id = absint( $item['sdm_id'] ?? 0 );
            if ( $id <= 0 || ! isset( $usage[ $id ] ) ) {
                continue;
            }

            if ( 'post_meta' === ( $item['source_type'] ?? '' ) ) {
                ++$usage[ $id ]['standard_meta'];
            } else {
                ++$usage[ $id ]['standard_content'];
            }
        }

        self::collect_id_shortcode_usage( $usage );
        self::collect_direct_url_usage( $usage );

        $category_listing = self::count_category_listing_shortcodes();
        $status_counts    = array();
        $items            = array();
        $standard_ids     = array();
        $direct_ids       = array();
        $related_ids      = array();
        $hidden_ids       = array();
        $referenced_ids   = array();
        $missing_file_url = 0;

        $occurrences = array(
            'standard_content' => 0,
            'standard_meta'    => 0,
            'direct_content'   => 0,
            'direct_meta'      => 0,
            'related_content'  => 0,
            'related_meta'     => 0,
            'hidden_content'   => 0,
            'hidden_meta'      => 0,
        );

        foreach ( $sdm_posts as $sdm_post ) {
            $id     = (int) $sdm_post->ID;
            $counts = $usage[ $id ];

            foreach ( $counts as $key => $count ) {
                $occurrences[ $key ] += (int) $count;
            }

            $standard_total = (int) $counts['standard_content'] + (int) $counts['standard_meta'];
            $direct_total   = (int) $counts['direct_content'] + (int) $counts['direct_meta'];
            $related_total  = (int) $counts['related_content'] + (int) $counts['related_meta'];
            $hidden_total   = (int) $counts['hidden_content'] + (int) $counts['hidden_meta'];
            $reference_total = $standard_total + $direct_total + $related_total + $hidden_total;

            if ( $standard_total > 0 ) {
                $standard_ids[ $id ] = true;
            }
            if ( $direct_total > 0 ) {
                $direct_ids[ $id ] = true;
            }
            if ( $related_total > 0 ) {
                $related_ids[ $id ] = true;
            }
            if ( $hidden_total > 0 ) {
                $hidden_ids[ $id ] = true;
            }
            if ( $reference_total > 0 ) {
                $referenced_ids[ $id ] = true;
            }

            $status = sanitize_key( (string) $sdm_post->post_status );
            if ( '' === $status ) {
                $status = 'unknown';
            }
            $status_counts[ $status ] = ( $status_counts[ $status ] ?? 0 ) + 1;

            $raw_url  = (string) get_post_meta( $id, 'sdm_upload', true );
            $file_url = WFT_Downloads::normalize_url( $raw_url );
            if ( '' === $file_url ) {
                ++$missing_file_url;
            }

            $items[] = array(
                'sdm_id'             => $id,
                'title'              => '' !== trim( (string) $sdm_post->post_title ) ? (string) $sdm_post->post_title : sprintf( __( 'SDM item #%d', 'wp-filetrace' ), $id ),
                'status'             => $status,
                'file_url'           => $file_url,
                'standard_content'   => (int) $counts['standard_content'],
                'standard_meta'      => (int) $counts['standard_meta'],
                'direct_content'     => (int) $counts['direct_content'],
                'direct_meta'        => (int) $counts['direct_meta'],
                'related_content'    => (int) $counts['related_content'],
                'related_meta'       => (int) $counts['related_meta'],
                'hidden_content'     => (int) $counts['hidden_content'],
                'hidden_meta'        => (int) $counts['hidden_meta'],
                'reference_total'    => $reference_total,
                'has_direct_reference' => $reference_total > 0,
            );
        }

        usort(
            $items,
            static function ( array $a, array $b ): int {
                if ( $a['has_direct_reference'] !== $b['has_direct_reference'] ) {
                    return $a['has_direct_reference'] ? 1 : -1;
                }

                $title_compare = strcasecmp( (string) $a['title'], (string) $b['title'] );
                return 0 !== $title_compare ? $title_compare : ( (int) $a['sdm_id'] <=> (int) $b['sdm_id'] );
            }
        );

        return array(
            'total_items'                  => count( $sdm_posts ),
            'status_counts'                => $status_counts,
            'standard_shortcode_ids'       => count( $standard_ids ),
            'direct_url_ids'               => count( $direct_ids ),
            'related_shortcode_ids'        => count( $related_ids ),
            'hidden_shortcode_ids'         => count( $hidden_ids ),
            'referenced_ids'               => count( $referenced_ids ),
            'no_direct_reference'          => max( 0, count( $sdm_posts ) - count( $referenced_ids ) ),
            'missing_file_url'             => $missing_file_url,
            'category_listing_occurrences' => (int) $category_listing['occurrences'],
            'category_listing_locations'   => (int) $category_listing['locations'],
            'occurrences'                  => $occurrences,
            'items'                        => $items,
        );
    }

    private static function collect_id_shortcode_usage( array &$usage ): void {
        global $wpdb;

        $tags = array( 'sdm_download_counter', 'sdm_show_download_info', 'sdm_download_link', 'sdm_hidden_download' );
        $clauses = array();
        $values  = array();
        foreach ( $tags as $tag ) {
            $clauses[] = 'post_content LIKE %s';
            $values[]  = '%' . $wpdb->esc_like( '[' . $tag ) . '%';
        }

        $post_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_content AS ref_value
                 FROM {$wpdb->posts}
                 WHERE (" . implode( ' OR ', $clauses ) . ")
                   AND post_status NOT IN ('trash', 'auto-draft', 'inherit')
                   AND post_type NOT IN ('revision', 'attachment', 'sdm_downloads')",
                $values
            )
        ) ?: array();

        foreach ( $post_rows as $row ) {
            self::apply_id_shortcode_counts( $usage, (string) $row->ref_value, 'content', $tags );
        }

        $meta_clauses = array();
        $meta_values  = array();
        foreach ( $tags as $tag ) {
            $meta_clauses[] = 'pm.meta_value LIKE %s';
            $meta_values[]  = '%' . $wpdb->esc_like( '[' . $tag ) . '%';
        }

        $meta_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm.meta_value AS ref_value
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE (" . implode( ' OR ', $meta_clauses ) . ")
                   AND pm.meta_key <> %s
                   AND p.post_status NOT IN ('trash', 'auto-draft', 'inherit')
                   AND p.post_type NOT IN ('revision', 'attachment', 'sdm_downloads')",
                array_merge( $meta_values, array( self::BACKUP_META ) )
            )
        ) ?: array();

        foreach ( $meta_rows as $row ) {
            self::apply_id_shortcode_counts( $usage, (string) $row->ref_value, 'meta', $tags );
        }
    }

    private static function apply_id_shortcode_counts( array &$usage, string $content, string $source, array $tags ): void {
        foreach ( self::find_id_shortcodes( $content, $tags ) as $reference ) {
            $id = absint( $reference['id'] ?? 0 );
            if ( $id <= 0 || ! isset( $usage[ $id ] ) ) {
                continue;
            }

            $tag = (string) ( $reference['tag'] ?? '' );
            $bucket = 'sdm_hidden_download' === $tag ? 'hidden_' . $source : 'related_' . $source;
            ++$usage[ $id ][ $bucket ];
        }
    }

    private static function find_id_shortcodes( string $content, array $tags ): array {
        if ( '' === $content ) {
            return array();
        }

        $pattern = get_shortcode_regex( $tags );
        if ( ! preg_match_all( '/' . $pattern . '/s', $content, $matches, PREG_SET_ORDER ) ) {
            return array();
        }

        $references = array();
        foreach ( $matches as $match ) {
            if ( '[' === ( $match[1] ?? '' ) && ']' === ( $match[6] ?? '' ) ) {
                continue;
            }

            $attrs = shortcode_parse_atts( (string) ( $match[3] ?? '' ) );
            $attrs = is_array( $attrs ) ? array_change_key_case( $attrs, CASE_LOWER ) : array();
            $id    = isset( $attrs['id'] ) && is_numeric( $attrs['id'] ) ? absint( $attrs['id'] ) : 0;
            if ( $id <= 0 ) {
                continue;
            }

            $references[] = array(
                'tag' => (string) ( $match[2] ?? '' ),
                'id'  => $id,
            );
        }

        return $references;
    }

    private static function collect_direct_url_usage( array &$usage ): void {
        global $wpdb;

        $like_a = '%' . $wpdb->esc_like( 'sdm_process_download' ) . '%';
        $like_b = '%' . $wpdb->esc_like( 'smd_process_download' ) . '%';

        $post_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_content AS ref_value
                 FROM {$wpdb->posts}
                 WHERE (post_content LIKE %s OR post_content LIKE %s)
                   AND post_status NOT IN ('trash', 'auto-draft', 'inherit')
                   AND post_type NOT IN ('revision', 'attachment', 'sdm_downloads')",
                $like_a,
                $like_b
            )
        ) ?: array();

        foreach ( $post_rows as $row ) {
            foreach ( self::find_direct_process_ids( (string) $row->ref_value ) as $id ) {
                if ( isset( $usage[ $id ] ) ) {
                    ++$usage[ $id ]['direct_content'];
                }
            }
        }

        $meta_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm.meta_value AS ref_value
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE (pm.meta_value LIKE %s OR pm.meta_value LIKE %s)
                   AND pm.meta_key <> %s
                   AND p.post_status NOT IN ('trash', 'auto-draft', 'inherit')
                   AND p.post_type NOT IN ('revision', 'attachment', 'sdm_downloads')",
                $like_a,
                $like_b,
                self::BACKUP_META
            )
        ) ?: array();

        foreach ( $meta_rows as $row ) {
            foreach ( self::find_direct_process_ids( (string) $row->ref_value ) as $id ) {
                if ( isset( $usage[ $id ] ) ) {
                    ++$usage[ $id ]['direct_meta'];
                }
            }
        }
    }

    private static function find_direct_process_ids( string $content ): array {
        if ( '' === $content || ( false === stripos( $content, 'sdm_process_download' ) && false === stripos( $content, 'smd_process_download' ) ) ) {
            return array();
        }

        $ids = array();
        $patterns = array(
            '~(?:sdm|smd)_process_download(?:=|%3D)1[^\s"\'<>]{0,500}?download_id(?:=|%3D)(\d+)~i',
            '~download_id(?:=|%3D)(\d+)[^\s"\'<>]{0,500}?(?:sdm|smd)_process_download(?:=|%3D)1~i',
        );

        foreach ( $patterns as $pattern ) {
            if ( preg_match_all( $pattern, $content, $matches ) ) {
                foreach ( $matches[1] as $id ) {
                    $id = absint( $id );
                    if ( $id > 0 ) {
                        $ids[] = $id;
                    }
                }
            }
        }

        return $ids;
    }

    private static function count_category_listing_shortcodes(): array {
        global $wpdb;

        $needle = '[sdm_show_dl_from_category';
        $like   = '%' . $wpdb->esc_like( $needle ) . '%';
        $occurrences = 0;
        $locations   = 0;

        $post_rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT post_content
                 FROM {$wpdb->posts}
                 WHERE post_content LIKE %s
                   AND post_status NOT IN ('trash', 'auto-draft', 'inherit')
                   AND post_type NOT IN ('revision', 'attachment', 'sdm_downloads')",
                $like
            )
        ) ?: array();

        foreach ( $post_rows as $value ) {
            $count = substr_count( strtolower( (string) $value ), $needle );
            if ( $count > 0 ) {
                $occurrences += $count;
                ++$locations;
            }
        }

        $meta_rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT pm.meta_value
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_value LIKE %s
                   AND pm.meta_key <> %s
                   AND p.post_status NOT IN ('trash', 'auto-draft', 'inherit')
                   AND p.post_type NOT IN ('revision', 'attachment', 'sdm_downloads')",
                $like,
                self::BACKUP_META
            )
        ) ?: array();

        foreach ( $meta_rows as $value ) {
            $count = substr_count( strtolower( (string) $value ), $needle );
            if ( $count > 0 ) {
                $occurrences += $count;
                ++$locations;
            }
        }

        return array(
            'occurrences' => $occurrences,
            'locations'   => $locations,
        );
    }

    private static function find_download_shortcodes( string $content ): array {
        if ( '' === $content || ( false === stripos( $content, '[sdm_download' ) && false === stripos( $content, '[sdm-download' ) ) ) {
            return array();
        }

        $pattern = get_shortcode_regex( array( 'sdm_download', 'sdm-download' ) );
        if ( ! preg_match_all( '/' . $pattern . '/s', $content, $matches, PREG_SET_ORDER ) ) {
            return array();
        }

        $result = array();
        foreach ( $matches as $match ) {
            // Ignore escaped shortcodes such as [[sdm_download ...]].
            if ( '[' === ( $match[1] ?? '' ) && ']' === ( $match[6] ?? '' ) ) {
                continue;
            }

            $result[] = array(
                'full'       => (string) ( $match[0] ?? '' ),
                'tag'        => (string) ( $match[2] ?? 'sdm_download' ),
                'attr_text'  => (string) ( $match[3] ?? '' ),
                'inner'      => (string) ( $match[5] ?? '' ),
            );
        }

        return $result;
    }

    private static function analyze_occurrence( $post, array $occurrence, string $source_type ): array {
        $attrs = shortcode_parse_atts( (string) $occurrence['attr_text'] );
        $attrs = is_array( $attrs ) ? array_change_key_case( $attrs, CASE_LOWER ) : array();
        $id    = isset( $attrs['id'] ) ? absint( $attrs['id'] ) : 0;

        $item = array(
            'post_id'        => (int) $post->ID,
            'post_title'     => '' !== trim( (string) $post->post_title ) ? (string) $post->post_title : sprintf( __( '(no title) #%d', 'wp-filetrace' ), (int) $post->ID ),
            'post_type'      => (string) $post->post_type,
            'post_status'    => (string) $post->post_status,
            'source_type'    => $source_type,
            'meta_key'       => '',
            'original'       => (string) $occurrence['full'],
            'attrs'          => $attrs,
            'sdm_id'         => $id,
            'sdm_title'      => '',
            'file_url'       => '',
            'attachment_id'  => 0,
            'button_text'    => '',
            'proposed'       => '',
            'tracker_state'  => 'unknown',
            'tracker_id'     => 0,
            'ready'          => false,
            'status'         => 'review',
            'notes'          => array(),
        );

        if ( $id <= 0 ) {
            $item['notes'][] = __( 'Missing or invalid SDM download ID.', 'wp-filetrace' );
            return $item;
        }

        $sdm_post = get_post( $id );
        if ( ! $sdm_post || 'sdm_downloads' !== $sdm_post->post_type ) {
            $item['notes'][] = __( 'The referenced SDM download item could not be found.', 'wp-filetrace' );
            return $item;
        }

        $item['sdm_title'] = '' !== trim( (string) $sdm_post->post_title ) ? (string) $sdm_post->post_title : sprintf( __( 'SDM item #%d', 'wp-filetrace' ), $id );

        if ( ! empty( $sdm_post->post_password ) ) {
            $item['notes'][] = __( 'Password-protected SDM downloads require manual review because WP FileTrace does not reproduce SDM password protection.', 'wp-filetrace' );
        }

        if ( 'publish' !== $sdm_post->post_status ) {
            $item['notes'][] = sprintf( __( 'SDM item status is “%s”; unpublished/private behavior should be reviewed.', 'wp-filetrace' ), $sdm_post->post_status );
        }

        $url = WFT_Downloads::normalize_url( (string) get_post_meta( $id, 'sdm_upload', true ) );
        if ( '' === $url ) {
            $item['notes'][] = __( 'The SDM item does not contain a valid HTTP/HTTPS file URL.', 'wp-filetrace' );
            return $item;
        }

        $item['file_url']      = $url;
        $item['attachment_id'] = self::attachment_id_for_url( $url );

        $button_text = isset( $attrs['button_text'] ) ? sanitize_text_field( (string) $attrs['button_text'] ) : '';
        if ( '' === trim( $button_text ) ) {
            $button_text = sanitize_text_field( (string) get_post_meta( $id, 'sdm_download_button_text', true ) );
        }
        if ( '' === trim( $button_text ) ) {
            $button_text = __( 'Download Now!', 'wp-filetrace' );
        }
        $item['button_text'] = $button_text;

        $existing = WFT_Downloads::get_by_destination( $item['attachment_id'], $url );
        if ( $existing ) {
            $item['tracker_state'] = 'reuse';
            $item['tracker_id']    = (int) $existing->id;
            $item['proposed']      = WFT_Downloads::shortcode_for( $existing, $button_text );
        } else {
            $item['tracker_state'] = 'create';
            $item['proposed']      = self::proposed_shortcode( $item['attachment_id'], $url, $button_text );
        }

        $blocking = false;

        if ( ! empty( $sdm_post->post_password ) || 'publish' !== $sdm_post->post_status ) {
            $blocking = true;
        }

        if ( false !== strpos( (string) $occurrence['attr_text'], '\"' ) || false !== strpos( (string) $occurrence['attr_text'], "\\'" ) ) {
            $blocking = true;
            $item['notes'][] = __( 'The shortcode attributes appear escaped inside structured/builder content. Automatic replacement is disabled to avoid corrupting that structure.', 'wp-filetrace' );
        }

        if ( '' !== trim( (string) $occurrence['inner'] ) ) {
            $blocking = true;
            $item['notes'][] = __( 'This SDM shortcode contains enclosed content instead of the normal self-contained form; review it manually before replacement.', 'wp-filetrace' );
        }

        $allowed_attrs = array( 'id', 'button_text', 'fancy' );
        foreach ( $attrs as $key => $value ) {
            if ( in_array( $key, $allowed_attrs, true ) ) {
                continue;
            }
            if ( '' === trim( (string) $value ) || in_array( strtolower( trim( (string) $value ) ), array( '0', 'false', 'off', 'no' ), true ) ) {
                continue;
            }

            $blocking = true;
            $item['notes'][] = sprintf(
                __( 'Unsupported SDM shortcode attribute “%1$s=%2$s” would change behavior and will not be auto-migrated.', 'wp-filetrace' ),
                $key,
                (string) $value
            );
        }

        $fancy = isset( $attrs['fancy'] ) ? trim( (string) $attrs['fancy'] ) : '0';
        if ( '' !== $fancy && '0' !== $fancy ) {
            $item['notes'][] = sprintf( __( 'SDM fancy template %s will be replaced by the standard WP FileTrace download button.', 'wp-filetrace' ), $fancy );
        }

        $advanced = get_option( 'sdm_advanced_options', array() );
        if ( is_array( $advanced ) ) {
            if ( ! empty( $advanced['termscond_enable'] ) ) {
                $blocking = true;
                $item['notes'][] = __( 'Simple Download Monitor Terms & Conditions are enabled globally; auto-migration is blocked so that flow is not bypassed.', 'wp-filetrace' );
            }
            if ( ! empty( $advanced['recaptcha_enable'] ) || ! empty( $advanced['recaptcha_v3_enable'] ) ) {
                $blocking = true;
                $item['notes'][] = __( 'Simple Download Monitor reCAPTCHA is enabled globally; auto-migration is blocked so that protection is not bypassed.', 'wp-filetrace' );
            }
        }

        $item['ready']  = ! $blocking;
        $item['status'] = $blocking ? 'review' : 'ready';

        if ( empty( $item['notes'] ) ) {
            $item['notes'][] = __( 'Direct one-to-one shortcode replacement.', 'wp-filetrace' );
        }

        return $item;
    }

    private static function attachment_id_for_url( string $url ): int {
        $attachment_id = attachment_url_to_postid( $url );
        if ( $attachment_id > 0 ) {
            return (int) $attachment_id;
        }

        $clean = preg_replace( '/[?#].*$/', '', $url );
        if ( $clean && $clean !== $url ) {
            $attachment_id = attachment_url_to_postid( $clean );
        }

        return max( 0, (int) $attachment_id );
    }

    private static function proposed_shortcode( int $attachment_id, string $url, string $button_text ): string {
        if ( $attachment_id > 0 ) {
            $shortcode = '[wft media="' . $attachment_id . '"';
        } else {
            $shortcode = '[wft url="' . esc_url_raw( $url ) . '"';
        }

        if ( '' !== trim( $button_text ) && __( 'Download', 'wp-filetrace' ) !== $button_text ) {
            $shortcode .= ' text="' . esc_attr( $button_text ) . '"';
        }

        return $shortcode . ']';
    }

    public static function apply_safe_replacements(): array {
        $scan = self::scan_site();
        $ready_items = array_values(
            array_filter(
                $scan['items'],
                static fn( array $item ): bool => ! empty( $item['ready'] ) && 'post_content' === $item['source_type']
            )
        );

        $stats = array(
            'shortcodes_changed' => 0,
            'posts_changed'      => 0,
            'trackers_created'   => 0,
            'trackers_reused'    => 0,
            'failed'             => 0,
            'changed_posts'      => array(),
            'run_at'             => current_time( 'mysql' ),
        );

        if ( empty( $ready_items ) ) {
            update_option( self::LAST_RUN_OPTION, $stats, false );
            return $stats;
        }

        $replacement_map    = array();
        $created_tracker_ids = array();
        $reused_tracker_ids  = array();

        foreach ( $ready_items as $item ) {
            $tracker = null;
            if ( ! empty( $item['tracker_id'] ) ) {
                $tracker = WFT_Downloads::get_by_id( (int) $item['tracker_id'] );
            }

            if ( $tracker ) {
                $reused_tracker_ids[ (int) $tracker->id ] = true;
            } else {
                $existing = WFT_Downloads::get_by_destination( (int) $item['attachment_id'], (string) $item['file_url'] );
                if ( $existing ) {
                    $tracker = $existing;
                    $reused_tracker_ids[ (int) $tracker->id ] = true;
                } else {
                    $tracker = WFT_Downloads::get_or_create(
                        (int) $item['attachment_id'],
                        (string) $item['file_url'],
                        (string) $item['sdm_title'],
                        (string) $item['button_text']
                    );
                    if ( is_wp_error( $tracker ) ) {
                        ++$stats['failed'];
                        continue;
                    }
                    $created_tracker_ids[ (int) $tracker->id ] = true;
                }
            }

            $replacement_map[ (int) $item['post_id'] ][ (string) $item['original'] ] = WFT_Downloads::shortcode_for( $tracker, (string) $item['button_text'] );
        }

        foreach ( $created_tracker_ids as $tracker_id => $_created ) {
            unset( $reused_tracker_ids[ $tracker_id ] );
        }
        $stats['trackers_created'] = count( $created_tracker_ids );
        $stats['trackers_reused']  = count( $reused_tracker_ids );

        foreach ( $replacement_map as $post_id => $replacements ) {
            $post = get_post( $post_id );
            if ( ! $post ) {
                ++$stats['failed'];
                continue;
            }

            $original_content = (string) $post->post_content;
            $new_content      = $original_content;
            $changed_count    = 0;

            foreach ( $replacements as $old => $new ) {
                $occurrences = substr_count( $new_content, $old );
                if ( $occurrences <= 0 ) {
                    continue;
                }
                $new_content   = str_replace( $old, $new, $new_content );
                $changed_count += $occurrences;
            }

            if ( $new_content === $original_content || $changed_count <= 0 ) {
                continue;
            }

            self::backup_post_content( $post_id, $original_content );

            $updated = wp_update_post(
                wp_slash(
                    array(
                        'ID'           => $post_id,
                        'post_content' => $new_content,
                    )
                ),
                true
            );

            if ( is_wp_error( $updated ) ) {
                ++$stats['failed'];
                continue;
            }

            $stats['shortcodes_changed'] += $changed_count;
            ++$stats['posts_changed'];
            $stats['changed_posts'][] = array(
                'id'    => $post_id,
                'title' => '' !== trim( (string) $post->post_title ) ? (string) $post->post_title : sprintf( __( '(no title) #%d', 'wp-filetrace' ), $post_id ),
                'count' => $changed_count,
            );
        }

        update_option( self::LAST_RUN_OPTION, $stats, false );

        return $stats;
    }

    private static function backup_post_content( int $post_id, string $content ): void {
        $rollback = self::get_rollback_state();

        if ( ! metadata_exists( 'post', $post_id, self::BACKUP_META ) ) {
            add_post_meta( $post_id, self::BACKUP_META, base64_encode( $content ), true );
        }

        if ( ! in_array( $post_id, $rollback['post_ids'], true ) ) {
            $rollback['post_ids'][] = $post_id;
        }
        if ( empty( $rollback['created_at'] ) ) {
            $rollback['created_at'] = current_time( 'mysql' );
        }

        update_option( self::ROLLBACK_OPTION, $rollback, false );
    }

    public static function rollback(): array {
        $rollback = self::get_rollback_state();
        $result = array(
            'restored' => 0,
            'failed'   => 0,
            'run_at'   => current_time( 'mysql' ),
        );

        foreach ( $rollback['post_ids'] as $post_id ) {
            if ( ! metadata_exists( 'post', $post_id, self::BACKUP_META ) ) {
                continue;
            }

            $encoded = (string) get_post_meta( $post_id, self::BACKUP_META, true );
            $content = base64_decode( $encoded, true );
            if ( false === $content ) {
                ++$result['failed'];
                continue;
            }

            $updated = wp_update_post(
                wp_slash(
                    array(
                        'ID'           => $post_id,
                        'post_content' => $content,
                    )
                ),
                true
            );

            if ( is_wp_error( $updated ) ) {
                ++$result['failed'];
                continue;
            }

            delete_post_meta( $post_id, self::BACKUP_META );
            ++$result['restored'];
        }

        if ( 0 === $result['failed'] ) {
            delete_option( self::ROLLBACK_OPTION );
        } else {
            $remaining = array();
            foreach ( $rollback['post_ids'] as $post_id ) {
                if ( metadata_exists( 'post', $post_id, self::BACKUP_META ) ) {
                    $remaining[] = $post_id;
                }
            }
            $rollback['post_ids'] = $remaining;
            update_option( self::ROLLBACK_OPTION, $rollback, false );
        }

        update_option( self::LAST_RUN_OPTION, array( 'rollback' => $result ), false );
        return $result;
    }

    public static function discard_rollback(): int {
        $rollback = self::get_rollback_state();
        $removed  = 0;

        foreach ( $rollback['post_ids'] as $post_id ) {
            if ( delete_post_meta( $post_id, self::BACKUP_META ) ) {
                ++$removed;
            }
        }

        delete_option( self::ROLLBACK_OPTION );
        return $removed;
    }

    public static function has_rollback(): bool {
        $rollback = self::get_rollback_state();
        return ! empty( $rollback['post_ids'] );
    }

    public static function get_rollback_state(): array {
        $state = get_option( self::ROLLBACK_OPTION, array() );
        if ( ! is_array( $state ) ) {
            $state = array();
        }

        return array(
            'post_ids'   => array_values( array_unique( array_filter( array_map( 'absint', $state['post_ids'] ?? array() ) ) ) ),
            'created_at' => isset( $state['created_at'] ) ? sanitize_text_field( (string) $state['created_at'] ) : '',
        );
    }

    public static function get_last_run(): array {
        $run = get_option( self::LAST_RUN_OPTION, array() );
        return is_array( $run ) ? $run : array();
    }

    private static function count_related_sdm_shortcodes(): int {
        global $wpdb;

        $patterns = array(
            '[sdm_download_counter',
            '[sdm-download-counter',
            '[sdm_show_download_info',
            '[sdm_download_link',
        );

        $clauses = array();
        $values  = array();
        foreach ( $patterns as $pattern ) {
            $clauses[] = 'post_content LIKE %s';
            $values[]  = '%' . $wpdb->esc_like( $pattern ) . '%';
        }

        $sql = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE (" . implode( ' OR ', $clauses ) . ") AND post_status NOT IN ('trash', 'auto-draft', 'inherit') AND post_type NOT IN ('revision', 'attachment', 'sdm_downloads')";
        return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
    }
}
