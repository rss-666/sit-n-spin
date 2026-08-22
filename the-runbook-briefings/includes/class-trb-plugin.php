<?php
/**
 * Plugin composition root.
 */

defined( 'ABSPATH' ) || exit;

final class TRB_Plugin {
    private static ?self $instance = null;
    private bool $running = false;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function run(): void {
        if ( $this->running ) {
            return;
        }
        $this->running = true;

        load_plugin_textdomain( 'the-runbook-briefings', false, dirname( plugin_basename( TRB_PLUGIN_FILE ) ) . '/languages' );
        add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
        add_action( 'trb_import_feeds', array( $this, 'cron_import' ) );
        add_action( 'transition_post_status', array( $this, 'post_status_changed' ), 10, 3 );
        add_filter( 'plugin_action_links_' . plugin_basename( TRB_PLUGIN_FILE ), array( $this, 'plugin_links' ) );

        if ( get_option( 'trb_db_version' ) !== TRB_DB_VERSION ) {
            TRB_Database::install();
        }
        if ( is_admin() ) {
            ( new TRB_Admin() )->hooks();
            ( new TRB_Actions() )->hooks();
            add_action( 'admin_init', array( 'TRB_Activator', 'schedule' ) );
        }
    }

    /**
     * @param array<string,array<string,mixed>> $schedules Cron schedules.
     * @return array<string,array<string,mixed>>
     */
    public function cron_schedules( array $schedules ): array {
        $schedules['trb_fifteen_minutes'] = array(
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display'  => __( 'Every 15 minutes (Runbook Briefings)', 'the-runbook-briefings' ),
        );
        return $schedules;
    }

    public function cron_import(): void {
        ( new TRB_Importer() )->run();
    }

    public function post_status_changed( string $new_status, string $old_status, WP_Post $post ): void {
        if ( 'post' !== $post->post_type || $new_status === $old_status ) {
            return;
        }
        if ( 'publish' === $new_status ) {
            ( new TRB_Item_Repository() )->update_by_post( (int) $post->ID, 'published' );
        } elseif ( in_array( $new_status, array( 'draft', 'pending' ), true ) ) {
            ( new TRB_Item_Repository() )->update_by_post( (int) $post->ID, 'drafted' );
        }
    }

    /**
     * @param string[] $links Action links.
     * @return string[]
     */
    public function plugin_links( array $links ): array {
        array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=trb-settings' ) ) . '">' . esc_html__( 'Settings', 'the-runbook-briefings' ) . '</a>' );
        return $links;
    }
}
