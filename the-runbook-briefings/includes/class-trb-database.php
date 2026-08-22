<?php
/**
 * Database schema and shared helpers.
 */

defined( 'ABSPATH' ) || exit;

final class TRB_Database {
    /**
     * Return a prefixed plugin table name.
     */
    public static function table( string $name ): string {
        global $wpdb;
        $allowed = array( 'sources', 'items', 'logs', 'history' );
        if ( ! in_array( $name, $allowed, true ) ) {
            throw new InvalidArgumentException( 'Unknown Runbook Briefings table.' );
        }
        return $wpdb->prefix . 'trb_' . $name;
    }

    /**
     * Current UTC database timestamp.
     */
    public static function now(): string {
        return gmdate( 'Y-m-d H:i:s' );
    }

    /**
     * Install or upgrade all custom tables.
     */
    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $sources = self::table( 'sources' );
        $items   = self::table( 'items' );
        $logs    = self::table( 'logs' );
        $history = self::table( 'history' );

        $sql_sources = "CREATE TABLE {$sources} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            feed_url text NOT NULL,
            url_hash char(64) NOT NULL,
            enabled tinyint(1) NOT NULL DEFAULT 1,
            keywords text NULL,
            categories text NULL,
            reliability_notes text NULL,
            import_frequency varchar(30) NOT NULL DEFAULT 'inherit',
            last_fetched_at datetime NULL,
            last_success_at datetime NULL,
            last_error text NULL,
            error_count int(10) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY url_hash (url_hash),
            KEY enabled (enabled),
            KEY last_fetched_at (last_fetched_at)
        ) {$charset};";

        $sql_items = "CREATE TABLE {$items} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_id bigint(20) unsigned NOT NULL,
            source_name varchar(190) NOT NULL,
            guid varchar(255) NULL,
            source_url text NOT NULL,
            canonical_url text NULL,
            normalized_url text NOT NULL,
            url_hash char(64) NOT NULL,
            title text NOT NULL,
            title_hash char(64) NOT NULL,
            author varchar(190) NULL,
            published_at datetime NULL,
            categories text NULL,
            excerpt text NULL,
            status varchar(20) NOT NULL DEFAULT 'new',
            duplicate_of bigint(20) unsigned NULL,
            duplicate_confidence decimal(5,4) NOT NULL DEFAULT 0,
            suggested_headline text NULL,
            factual_summary longtext NULL,
            why_matters longtext NULL,
            practical_implications longtext NULL,
            internal_links longtext NULL,
            editorial_notes longtext NULL,
            wp_post_id bigint(20) unsigned NULL,
            error_message text NULL,
            retry_count tinyint(3) unsigned NOT NULL DEFAULT 0,
            discovered_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            processed_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY url_hash (url_hash),
            KEY source_id (source_id),
            KEY status (status),
            KEY title_hash (title_hash),
            KEY published_at (published_at),
            KEY duplicate_of (duplicate_of),
            KEY wp_post_id (wp_post_id)
        ) {$charset};";

        $sql_logs = "CREATE TABLE {$logs} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL,
            event varchar(80) NOT NULL,
            message text NOT NULL,
            context longtext NULL,
            source_id bigint(20) unsigned NULL,
            item_id bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY level (level),
            KEY event (event),
            KEY source_id (source_id),
            KEY item_id (item_id),
            KEY created_at (created_at)
        ) {$charset};";

        $sql_history = "CREATE TABLE {$history} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            item_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NULL,
            action varchar(80) NOT NULL,
            from_status varchar(20) NULL,
            to_status varchar(20) NULL,
            message text NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY item_id (item_id),
            KEY action (action),
            KEY created_at (created_at)
        ) {$charset};";

        dbDelta( $sql_sources );
        dbDelta( $sql_items );
        dbDelta( $sql_logs );
        dbDelta( $sql_history );
        update_option( 'trb_db_version', TRB_DB_VERSION, false );
    }
}
