<?php
/**
 * Plugin lifecycle.
 */

defined( 'ABSPATH' ) || exit;

final class TRB_Activator {
    public static function activate(): void {
        if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
            deactivate_plugins( plugin_basename( TRB_PLUGIN_FILE ) );
            wp_die( esc_html__( 'The Runbook Briefings requires PHP 8.1 or newer.', 'the-runbook-briefings' ) );
        }

        TRB_Database::install();
        if ( false === get_option( TRB_Settings::OPTION, false ) ) {
            add_option( TRB_Settings::OPTION, TRB_Settings::defaults(), '', false );
        }

        $administrator = get_role( 'administrator' );
        if ( $administrator ) {
            $administrator->add_cap( 'review_runbook_briefings' );
        }
        $editor = get_role( 'editor' );
        if ( $editor ) {
            $editor->add_cap( 'review_runbook_briefings' );
        }

        self::schedule();
        flush_rewrite_rules( false );
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook( 'trb_import_feeds' );
        delete_transient( 'trb_import_lock' );
        flush_rewrite_rules( false );
    }

    /**
     * Ensure exactly one importer event uses the configured recurrence.
     */
    public static function schedule( bool $force = false ): void {
        $frequency = (string) TRB_Settings::get( 'cron_frequency' );
        $event     = wp_get_scheduled_event( 'trb_import_feeds' );
        if ( $force || ( $event && $event->schedule !== $frequency ) ) {
            wp_clear_scheduled_hook( 'trb_import_feeds' );
            $event = false;
        }
        if ( ! $event ) {
            wp_schedule_event( time() + MINUTE_IN_SECONDS, $frequency, 'trb_import_feeds' );
        }
    }
}
