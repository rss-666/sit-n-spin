<?php
/**
 * Uninstall handler. Data is retained unless explicit removal was enabled.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$settings = get_option( 'trb_settings', array() );
if ( empty( $settings['delete_data_on_uninstall'] ) ) {
    return;
}

global $wpdb;
foreach ( array( 'trb_history', 'trb_logs', 'trb_items', 'trb_sources' ) as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
delete_option( 'trb_settings' );
delete_option( 'trb_db_version' );
wp_clear_scheduled_hook( 'trb_import_feeds' );

foreach ( array( 'administrator', 'editor' ) as $role_name ) {
    $role = get_role( $role_name );
    if ( $role ) {
        $role->remove_cap( 'review_runbook_briefings' );
    }
}
