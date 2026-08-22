<?php
/**
 * Imported item and processing-history persistence.
 */

defined( 'ABSPATH' ) || exit;

final class TRB_Item_Repository {
    public const STATUSES = array( 'new', 'processing', 'drafted', 'published', 'dismissed', 'error' );

    public function find( int $id ): ?object {
        global $wpdb;
        $table = TRB_Database::table( 'items' );
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $row ?: null;
    }

    public function find_by_url_hash( string $hash ): ?object {
        global $wpdb;
        $table = TRB_Database::table( 'items' );
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE url_hash = %s LIMIT 1", $hash ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $row ?: null;
    }

    /**
     * Find the closest recent title match.
     *
     * @return array{item:?object,confidence:float}
     */
    public function closest_title( string $title, int $days = 90 ): array {
        global $wpdb;
        $table = TRB_Database::table( 'items' );
        $since = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, $days ) * DAY_IN_SECONDS ) );
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, source_url FROM {$table} WHERE discovered_at >= %s ORDER BY discovered_at DESC LIMIT 250",
                $since
            )
        ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $best       = null;
        $confidence = 0.0;
        foreach ( $rows as $row ) {
            $candidate = TRB_Duplicate_Detector::title_similarity( $title, (string) $row->title );
            if ( $candidate > $confidence ) {
                $confidence = $candidate;
                $best       = $row;
            }
        }
        return array( 'item' => $best, 'confidence' => $confidence );
    }

    /**
     * Insert a normalized feed item.
     *
     * @param array<string,mixed> $data Item fields.
     * @return int|WP_Error
     */
    public function insert( array $data ) {
        global $wpdb;
        $now = TRB_Database::now();
        $row = array(
            'source_id'             => absint( $data['source_id'] ?? 0 ),
            'source_name'           => sanitize_text_field( (string) ( $data['source_name'] ?? '' ) ),
            'guid'                  => sanitize_text_field( (string) ( $data['guid'] ?? '' ) ),
            'source_url'            => esc_url_raw( (string) ( $data['source_url'] ?? '' ) ),
            'canonical_url'         => esc_url_raw( (string) ( $data['canonical_url'] ?? '' ) ),
            'normalized_url'        => (string) ( $data['normalized_url'] ?? '' ),
            'url_hash'              => (string) ( $data['url_hash'] ?? '' ),
            'title'                 => sanitize_text_field( (string) ( $data['title'] ?? '' ) ),
            'title_hash'            => hash( 'sha256', TRB_Duplicate_Detector::normalize_title( (string) ( $data['title'] ?? '' ) ) ),
            'author'                => sanitize_text_field( (string) ( $data['author'] ?? '' ) ),
            'published_at'          => $data['published_at'] ?? null,
            'categories'            => wp_json_encode( array_values( $data['categories'] ?? array() ) ),
            'excerpt'               => sanitize_textarea_field( (string) ( $data['excerpt'] ?? '' ) ),
            'status'                => in_array( $data['status'] ?? 'new', self::STATUSES, true ) ? $data['status'] : 'new',
            'duplicate_of'          => absint( $data['duplicate_of'] ?? 0 ) ?: null,
            'duplicate_confidence'  => min( 1, max( 0, (float) ( $data['duplicate_confidence'] ?? 0 ) ) ),
            'discovered_at'         => $now,
            'updated_at'            => $now,
        );

        $result = $wpdb->insert( TRB_Database::table( 'items' ), $row );
        if ( false === $result ) {
            return new WP_Error( 'trb_item_insert', __( 'The feed item could not be stored.', 'the-runbook-briefings' ) );
        }
        $id = (int) $wpdb->insert_id;
        $this->history( $id, 'imported', '', (string) $row['status'], __( 'Item discovered from RSS.', 'the-runbook-briefings' ), 0 );
        if ( $row['duplicate_of'] ) {
            $this->history( $id, 'duplicate_detected', 'new', 'dismissed', __( 'Dismissed automatically as a probable title duplicate.', 'the-runbook-briefings' ), 0 );
        }
        return $id;
    }

    /**
     * Queue query with whitelisted filters.
     *
     * @param array<string,mixed> $filters Filters.
     * @return array<int,object>
     */
    public function query( array $filters = array() ): array {
        global $wpdb;
        $table  = TRB_Database::table( 'items' );
        $where  = array( '1=1' );
        $values = array();

        $status = (string) ( $filters['status'] ?? '' );
        if ( in_array( $status, self::STATUSES, true ) ) {
            $where[]  = 'status = %s';
            $values[] = $status;
        }
        $source_id = absint( $filters['source_id'] ?? 0 );
        if ( $source_id ) {
            $where[]  = 'source_id = %d';
            $values[] = $source_id;
        }
        $search = sanitize_text_field( (string) ( $filters['search'] ?? '' ) );
        if ( '' !== $search ) {
            $where[]  = '(title LIKE %s OR source_name LIKE %s)';
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $values[] = $like;
            $values[] = $like;
        }

        $limit  = min( 100, max( 1, absint( $filters['limit'] ?? 30 ) ) );
        $offset = max( 0, absint( $filters['offset'] ?? 0 ) );
        $sql    = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY discovered_at DESC LIMIT %d OFFSET %d';
        $values[] = $limit;
        $values[] = $offset;
        return $wpdb->get_results( $wpdb->prepare( $sql, $values ) ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    /**
     * @return array<string,int>
     */
    public function counts(): array {
        global $wpdb;
        $table = TRB_Database::table( 'items' );
        $rows  = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status" ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $counts = array_fill_keys( self::STATUSES, 0 );
        foreach ( $rows as $row ) {
            if ( isset( $counts[ $row->status ] ) ) {
                $counts[ $row->status ] = (int) $row->total;
            }
        }
        return $counts;
    }

    /**
     * Save editable briefing fields without changing workflow status.
     *
     * @param array<string,mixed> $fields Fields.
     */
    public function save_briefing( int $id, array $fields ): bool {
        global $wpdb;
        $links = $fields['internal_links'] ?? array();
        if ( is_string( $links ) ) {
            $links = TRB_Briefing::parse_internal_link_lines( $links );
        }
        $data = array(
            'suggested_headline'    => sanitize_text_field( (string) ( $fields['suggested_headline'] ?? '' ) ),
            'factual_summary'       => wp_kses_post( (string) ( $fields['factual_summary'] ?? '' ) ),
            'why_matters'           => wp_kses_post( (string) ( $fields['why_matters'] ?? '' ) ),
            'practical_implications'=> wp_kses_post( (string) ( $fields['practical_implications'] ?? '' ) ),
            'internal_links'        => wp_json_encode( array_values( is_array( $links ) ? $links : array() ) ),
            'editorial_notes'       => sanitize_textarea_field( (string) ( $fields['editorial_notes'] ?? '' ) ),
            'updated_at'            => TRB_Database::now(),
        );
        return false !== $wpdb->update( TRB_Database::table( 'items' ), $data, array( 'id' => $id ) );
    }

    public function transition( int $id, string $status, string $action, string $message = '', int $user_id = 0, array $extra = array() ): bool {
        global $wpdb;
        if ( ! in_array( $status, self::STATUSES, true ) ) {
            return false;
        }
        $item = $this->find( $id );
        if ( ! $item ) {
            return false;
        }
        $data = array_merge(
            $extra,
            array(
                'status'     => $status,
                'updated_at' => TRB_Database::now(),
            )
        );
        if ( in_array( $status, array( 'drafted', 'published', 'dismissed' ), true ) ) {
            $data['processed_at'] = TRB_Database::now();
        }
        $result = $wpdb->update( TRB_Database::table( 'items' ), $data, array( 'id' => $id ) );
        if ( false !== $result ) {
            $this->history( $id, $action, (string) $item->status, $status, $message, $user_id );
            return true;
        }
        return false;
    }

    public function update_by_post( int $post_id, string $status ): void {
        global $wpdb;
        if ( ! in_array( $status, array( 'drafted', 'published' ), true ) ) {
            return;
        }
        $wpdb->update(
            TRB_Database::table( 'items' ),
            array( 'status' => $status, 'updated_at' => TRB_Database::now() ),
            array( 'wp_post_id' => $post_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
    }

    /**
     * @return array<int,object>
     */
    public function history_for( int $item_id ): array {
        global $wpdb;
        $table = TRB_Database::table( 'history' );
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE item_id = %d ORDER BY created_at DESC, id DESC LIMIT 50", $item_id ) ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private function history( int $item_id, string $action, string $from, string $to, string $message, int $user_id ): void {
        global $wpdb;
        $wpdb->insert(
            TRB_Database::table( 'history' ),
            array(
                'item_id'     => $item_id,
                'user_id'     => $user_id ?: null,
                'action'      => sanitize_key( $action ),
                'from_status' => sanitize_key( $from ),
                'to_status'   => sanitize_key( $to ),
                'message'     => sanitize_text_field( $message ),
                'created_at'  => TRB_Database::now(),
            )
        );
    }
}
