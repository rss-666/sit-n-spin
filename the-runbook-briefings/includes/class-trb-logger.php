<?php
/**
 * Structured, secret-safe operational logging.
 */

defined( 'ABSPATH' ) || exit;

final class TRB_Logger {
    /**
     * Write a log record. Context is sanitized and common secrets are redacted.
     *
     * @param array<string,mixed> $context Context.
     */
    public static function log(
        string $level,
        string $event,
        string $message,
        array $context = array(),
        ?int $source_id = null,
        ?int $item_id = null
    ): void {
        global $wpdb;
        $levels = array( 'debug', 'info', 'warning', 'error' );
        $level  = in_array( $level, $levels, true ) ? $level : 'info';

        $wpdb->insert(
            TRB_Database::table( 'logs' ),
            array(
                'level'     => $level,
                'event'     => sanitize_key( $event ),
                'message'   => self::redact( sanitize_textarea_field( $message ) ),
                'context'   => empty( $context ) ? null : wp_json_encode( self::sanitize_context( $context ) ),
                'source_id' => $source_id,
                'item_id'   => $item_id,
                'created_at' => TRB_Database::now(),
            ),
            array( '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
        );
    }

    /**
     * Delete old records to keep the table bounded.
     */
    public static function prune(): void {
        global $wpdb;
        $table  = TRB_Database::table( 'logs' );
        $before = gmdate( 'Y-m-d H:i:s', time() - ( 90 * DAY_IN_SECONDS ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $before ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    /**
     * @param array<string,mixed> $context Context.
     * @return array<string,mixed>
     */
    private static function sanitize_context( array $context ): array {
        $clean = array();
        foreach ( $context as $key => $value ) {
            $key = sanitize_key( (string) $key );
            if ( preg_match( '/key|token|secret|authorization|password/i', $key ) ) {
                $clean[ $key ] = '[redacted]';
                continue;
            }
            if ( is_scalar( $value ) || null === $value ) {
                $clean[ $key ] = self::redact( sanitize_text_field( (string) $value ) );
            }
        }
        return $clean;
    }

    private static function redact( string $value ): string {
        $value = preg_replace( '/Bearer\s+[A-Za-z0-9._~+\/-]+=*/i', 'Bearer [redacted]', $value ) ?? $value;
        $value = preg_replace( '/\bsk-[A-Za-z0-9_-]{8,}\b/', '[redacted]', $value ) ?? $value;
        return substr( $value, 0, 2000 );
    }
}
