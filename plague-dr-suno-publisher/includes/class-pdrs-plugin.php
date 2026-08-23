<?php
/**
 * Plugin composition root and song post type.
 */

defined( 'ABSPATH' ) || exit;

final class PDRS_Plugin {
    public const POST_TYPE = 'pdr_suno_song';

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

        load_plugin_textdomain( 'plague-dr-suno-publisher', false, dirname( plugin_basename( PDRS_PLUGIN_FILE ) ) . '/languages' );
        add_action( 'init', array( self::class, 'register_post_type' ) );
        add_filter( 'post_updated_messages', array( $this, 'updated_messages' ) );
        ( new PDRS_Renderer() )->hooks();
        ( new PDRS_PDU_Integration() )->hooks();

        if ( is_admin() ) {
            ( new PDRS_Admin() )->hooks();
        }
    }

    public static function register_post_type(): void {
        register_post_type(
            self::POST_TYPE,
            array(
                'labels' => array(
                    'name'                  => __( 'Plague Dr Songs', 'plague-dr-suno-publisher' ),
                    'singular_name'         => __( 'Plague Dr Song', 'plague-dr-suno-publisher' ),
                    'menu_name'             => __( 'All Songs', 'plague-dr-suno-publisher' ),
                    'edit_item'             => __( 'Edit Plague Dr Song', 'plague-dr-suno-publisher' ),
                    'view_item'             => __( 'Preview Song Placement', 'plague-dr-suno-publisher' ),
                    'search_items'           => __( 'Search Songs', 'plague-dr-suno-publisher' ),
                    'not_found'              => __( 'No songs found. Use Add from Suno to create one.', 'plague-dr-suno-publisher' ),
                    'item_published'         => __( 'Song activated on its destination.', 'plague-dr-suno-publisher' ),
                    'item_reverted_to_draft' => __( 'Song moved to draft and hidden from its automatic destination.', 'plague-dr-suno-publisher' ),
                    'item_updated'           => __( 'Song updated.', 'plague-dr-suno-publisher' ),
                ),
                'description'         => __( 'Suno song placements managed by Plague Dr.', 'plague-dr-suno-publisher' ),
                'public'              => false,
                'publicly_queryable'  => false,
                'show_ui'             => true,
                'show_in_menu'        => 'plague-dr-music',
                'show_in_rest'        => false,
                'exclude_from_search' => true,
                'has_archive'         => false,
                'rewrite'             => false,
                'query_var'           => false,
                'supports'            => array( 'title', 'editor', 'page-attributes' ),
                'capability_type'     => 'page',
                'map_meta_cap'        => true,
                'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
                'menu_icon'           => 'dashicons-format-audio',
            )
        );

        register_post_meta( self::POST_TYPE, '_pdrs_song_id', array( 'type' => 'string', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => static fn(): bool => current_user_can( 'edit_pages' ) ) );
        register_post_meta( self::POST_TYPE, '_pdrs_source_url', array( 'type' => 'string', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => static fn( $value ): string => esc_url_raw( (string) $value ), 'auth_callback' => static fn(): bool => current_user_can( 'edit_pages' ) ) );
        register_post_meta( self::POST_TYPE, '_pdrs_destination_id', array( 'type' => 'integer', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => 'absint', 'auth_callback' => static fn(): bool => current_user_can( 'edit_pages' ) ) );
        register_post_meta( self::POST_TYPE, '_pdrs_position', array( 'type' => 'string', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => 'sanitize_key', 'auth_callback' => static fn(): bool => current_user_can( 'edit_pages' ) ) );
        register_post_meta( self::POST_TYPE, '_pdrs_artwork_url', array( 'type' => 'string', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => static fn( $value ): string => PDRS_Metadata::image_url( (string) $value ), 'auth_callback' => static fn(): bool => current_user_can( 'edit_pages' ) ) );
        register_post_meta( self::POST_TYPE, '_pdrs_placement_mode', array( 'type' => 'string', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => static fn( $value ): string => in_array( $value, array( PDRS_PDU_Integration::MODE, PDRS_PDU_Integration::MODE_VIDEO, PDRS_PDU_Integration::MODE_BOTH ), true ) ? $value : 'destination', 'auth_callback' => static fn(): bool => current_user_can( 'edit_pages' ) ) );
        register_post_meta( self::POST_TYPE, '_pdrs_lyrics', array( 'type' => 'string', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => static fn( $value ): string => wp_kses_post( (string) $value ), 'auth_callback' => static fn(): bool => current_user_can( 'edit_pages' ) ) );
        register_post_meta( self::POST_TYPE, '_pdrs_album', array( 'type' => 'string', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => static fn(): bool => current_user_can( 'edit_pages' ) ) );
        register_post_meta( self::POST_TYPE, '_pdrs_duration', array( 'type' => 'string', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => static fn( $value ): string => PDRS_PDU_Integration::duration( (string) $value ), 'auth_callback' => static fn(): bool => current_user_can( 'edit_pages' ) ) );
        register_post_meta( self::POST_TYPE, '_pdrs_genre', array( 'type' => 'string', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => static fn(): bool => current_user_can( 'edit_pages' ) ) );
        register_post_meta( self::POST_TYPE, '_pdrs_buy_url', array( 'type' => 'string', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => static fn( $value ): string => esc_url_raw( (string) $value ), 'auth_callback' => static fn(): bool => current_user_can( 'edit_pages' ) ) );
        register_post_meta( self::POST_TYPE, '_pdrs_audio_url', array( 'type' => 'string', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => static fn( $value ): string => PDRS_PDU_Integration::audio_url( (string) $value ), 'auth_callback' => static fn(): bool => current_user_can( 'edit_pages' ) ) );
        register_post_meta( self::POST_TYPE, '_pdrs_video_url', array( 'type' => 'string', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => static fn( $value ): string => PDRS_PDU_Integration::video_url( (string) $value ), 'auth_callback' => static fn(): bool => current_user_can( 'edit_pages' ) ) );
        register_post_meta( self::POST_TYPE, '_pdrs_video_note', array( 'type' => 'string', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => static fn(): bool => current_user_can( 'edit_pages' ) ) );
        register_post_meta( self::POST_TYPE, '_pdrs_import_artwork', array( 'type' => 'boolean', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => static fn( $value ): bool => (bool) $value, 'auth_callback' => static fn(): bool => current_user_can( 'edit_pages' ) ) );
        register_post_meta( self::POST_TYPE, '_pdrs_pdu_track_id', array( 'type' => 'integer', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => 'absint', 'auth_callback' => static fn(): bool => current_user_can( 'edit_pages' ) ) );
        register_post_meta( self::POST_TYPE, '_pdrs_pdu_video_id', array( 'type' => 'integer', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => 'absint', 'auth_callback' => static fn(): bool => current_user_can( 'edit_pages' ) ) );
    }

    /**
     * @param array<string,array<int,string>> $messages Messages.
     * @return array<string,array<int,string>>
     */
    public function updated_messages( array $messages ): array {
        $messages[ self::POST_TYPE ] = array(
            0  => '',
            1  => __( 'Plague Dr Song updated.', 'plague-dr-suno-publisher' ),
            2  => __( 'Custom field updated.', 'plague-dr-suno-publisher' ),
            3  => __( 'Custom field deleted.', 'plague-dr-suno-publisher' ),
            4  => __( 'Plague Dr Song updated.', 'plague-dr-suno-publisher' ),
            5  => __( 'Plague Dr Song restored from revision.', 'plague-dr-suno-publisher' ),
            6  => __( 'Plague Dr Song is active on its selected destination.', 'plague-dr-suno-publisher' ),
            7  => __( 'Plague Dr Song saved.', 'plague-dr-suno-publisher' ),
            8  => __( 'Plague Dr Song submitted.', 'plague-dr-suno-publisher' ),
            9  => __( 'Plague Dr Song scheduled.', 'plague-dr-suno-publisher' ),
            10 => __( 'Plague Dr Song draft updated.', 'plague-dr-suno-publisher' ),
        );
        return $messages;
    }

    public static function activate(): void {
        if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
            deactivate_plugins( plugin_basename( PDRS_PLUGIN_FILE ) );
            wp_die( esc_html__( 'Plague Dr Suno Publisher requires PHP 8.1 or newer.', 'plague-dr-suno-publisher' ) );
        }
        self::register_post_type();
        update_option( 'pdrs_version', PDRS_VERSION, false );
        flush_rewrite_rules( false );
    }

    public static function deactivate(): void {
        flush_rewrite_rules( false );
    }
}
