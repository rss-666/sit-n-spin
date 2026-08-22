<?php
/**
 * RSS source persistence.
 */

defined( 'ABSPATH' ) || exit;

final class TRB_Source_Repository {
    /**
     * @return array<int,object>
     */
    public function all( bool $enabled_only = false ): array {
        global $wpdb;
        $table = TRB_Database::table( 'sources' );
        $where = $enabled_only ? ' WHERE enabled = 1' : '';
        return $wpdb->get_results( "SELECT * FROM {$table}{$where} ORDER BY name ASC" ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    public function find( int $id ): ?object {
        global $wpdb;
        $table = TRB_Database::table( 'sources' );
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $row ?: null;
    }

    /**
     * Validate and save a source.
     *
     * @param array<string,mixed> $input Source fields.
     * @return int|WP_Error
     */
    public function save( array $input ) {
        global $wpdb;

        $name = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
        $url  = esc_url_raw( (string) ( $input['feed_url'] ?? '' ), array( 'http', 'https' ) );
        if ( '' === $name ) {
            return new WP_Error( 'trb_source_name', __( 'A source name is required.', 'the-runbook-briefings' ) );
        }
        $valid_url = TRB_URL::validate_feed_url( $url );
        if ( is_wp_error( $valid_url ) ) {
            return $valid_url;
        }

        $frequency = (string) ( $input['import_frequency'] ?? 'inherit' );
        if ( ! in_array( $frequency, array( 'inherit', 'hourly', 'twicedaily', 'daily' ), true ) ) {
            $frequency = 'inherit';
        }

        $id   = absint( $input['id'] ?? 0 );
        $data = array(
            'name'                => $name,
            'feed_url'            => $url,
            'url_hash'            => TRB_URL::hash( $url ),
            'enabled'             => empty( $input['enabled'] ) ? 0 : 1,
            'keywords'            => sanitize_textarea_field( (string) ( $input['keywords'] ?? '' ) ),
            'categories'          => sanitize_textarea_field( (string) ( $input['categories'] ?? '' ) ),
            'reliability_notes'   => sanitize_textarea_field( (string) ( $input['reliability_notes'] ?? '' ) ),
            'import_frequency'    => $frequency,
            'updated_at'          => TRB_Database::now(),
        );
        $formats = array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' );

        if ( $id > 0 ) {
            if ( ! $this->find( $id ) ) {
                return new WP_Error( 'trb_source_missing', __( 'The source no longer exists.', 'the-runbook-briefings' ) );
            }
            $result = $wpdb->update( TRB_Database::table( 'sources' ), $data, array( 'id' => $id ), $formats, array( '%d' ) );
        } else {
            $data['created_at'] = TRB_Database::now();
            $formats[]          = '%s';
            $result             = $wpdb->insert( TRB_Database::table( 'sources' ), $data, $formats );
            $id                 = (int) $wpdb->insert_id;
        }

        if ( false === $result ) {
            $message = str_contains( strtolower( $wpdb->last_error ), 'duplicate' )
                ? __( 'That feed URL is already configured.', 'the-runbook-briefings' )
                : __( 'The source could not be saved.', 'the-runbook-briefings' );
            return new WP_Error( 'trb_source_save', $message );
        }
        return $id;
    }

    public function delete( int $id ): bool {
        global $wpdb;
        return false !== $wpdb->delete( TRB_Database::table( 'sources' ), array( 'id' => $id ), array( '%d' ) );
    }

    public function set_enabled( int $id, bool $enabled ): bool {
        global $wpdb;
        return false !== $wpdb->update(
            TRB_Database::table( 'sources' ),
            array(
                'enabled'    => $enabled ? 1 : 0,
                'updated_at' => TRB_Database::now(),
            ),
            array( 'id' => $id ),
            array( '%d', '%s' ),
            array( '%d' )
        );
    }

    public function record_success( int $id ): void {
        global $wpdb;
        $now = TRB_Database::now();
        $wpdb->update(
            TRB_Database::table( 'sources' ),
            array(
                'last_fetched_at' => $now,
                'last_success_at' => $now,
                'last_error'      => null,
                'error_count'     => 0,
                'updated_at'      => $now,
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%s', '%d', '%s' ),
            array( '%d' )
        );
    }

    public function record_error( int $id, string $message ): void {
        global $wpdb;
        $table = TRB_Database::table( 'sources' );
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET last_fetched_at = %s, last_error = %s, error_count = error_count + 1, updated_at = %s WHERE id = %d",
                TRB_Database::now(),
                sanitize_text_field( $message ),
                TRB_Database::now(),
                $id
            )
        ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }
}
