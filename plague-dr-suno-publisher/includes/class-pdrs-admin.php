<?php
/**
 * Plague Dr Music admin workflow.
 */

defined( 'ABSPATH' ) || exit;

final class PDRS_Admin {
    public function hooks(): void {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_post_pdrs_add_song', array( $this, 'add_song' ) );
        add_action( 'add_meta_boxes_' . PDRS_Plugin::POST_TYPE, array( $this, 'meta_box' ) );
        add_action( 'save_post_' . PDRS_Plugin::POST_TYPE, array( $this, 'save_song' ), 10, 2 );
        add_action( 'admin_notices', array( $this, 'notice' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
        add_filter( 'manage_' . PDRS_Plugin::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
        add_action( 'manage_' . PDRS_Plugin::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
        add_filter( 'plugin_action_links_' . plugin_basename( PDRS_PLUGIN_FILE ), array( $this, 'plugin_links' ) );
    }

    public function menu(): void {
        add_menu_page(
            __( 'Plague Dr Music', 'plague-dr-suno-publisher' ),
            __( 'Plague Dr Music', 'plague-dr-suno-publisher' ),
            'edit_pages',
            'plague-dr-music',
            array( $this, 'quick_add_page' ),
            'dashicons-format-audio',
            27
        );
        add_submenu_page(
            'plague-dr-music',
            __( 'Add from Suno', 'plague-dr-suno-publisher' ),
            __( 'Add from Suno', 'plague-dr-suno-publisher' ),
            'edit_pages',
            'plague-dr-music',
            array( $this, 'quick_add_page' )
        );
    }

    public function assets( string $hook ): void {
        $screen = get_current_screen();
        if ( 'toplevel_page_plague-dr-music' !== $hook && ( ! $screen || PDRS_Plugin::POST_TYPE !== $screen->post_type ) ) {
            return;
        }
        wp_enqueue_style( 'pdrs-admin', PDRS_PLUGIN_URL . 'assets/admin.css', array(), PDRS_VERSION );
        wp_enqueue_media();
        wp_enqueue_script( 'pdrs-admin', PDRS_PLUGIN_URL . 'assets/admin.js', array(), PDRS_VERSION, true );
    }

    public function quick_add_page(): void {
        if ( ! current_user_can( 'edit_pages' ) ) {
            wp_die( esc_html__( 'You are not allowed to add Plague Dr songs.', 'plague-dr-suno-publisher' ), '', array( 'response' => 403 ) );
        }
        $pdu_available = PDRS_PDU_Integration::available();
        ?>
        <div class="wrap pdrs-admin">
            <h1><?php esc_html_e( 'Plague Dr Suno Publisher', 'plague-dr-suno-publisher' ); ?></h1>
            <p class="pdrs-lede"><?php esc_html_e( 'Create a native Soundtrack, Music Video, both, or a reversible page placement from one managed music release.', 'plague-dr-suno-publisher' ); ?></p>
            <?php $this->created_summary(); ?>
            <div class="pdrs-layout">
                <form class="pdrs-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="pdrs_add_song">
                    <?php wp_nonce_field( 'pdrs_add_song' ); ?>
                    <h2><?php esc_html_e( 'Add a music release', 'plague-dr-suno-publisher' ); ?></h2>
                    <p><label for="pdrs-url"><strong><?php esc_html_e( 'Public Suno URL (optional)', 'plague-dr-suno-publisher' ); ?></strong></label><br>
                        <input id="pdrs-url" class="large-text" type="url" name="suno_url" placeholder="https://suno.com/song/…"></p>
                    <p class="description"><?php esc_html_e( 'Required for hosted Suno playback, but optional when you provide local audio, YouTube, or Vimeo.', 'plague-dr-suno-publisher' ); ?></p>
                    <p><label for="pdrs-title"><strong><?php esc_html_e( 'Song title', 'plague-dr-suno-publisher' ); ?></strong></label><br>
                        <input id="pdrs-title" class="large-text" type="text" name="song_title" placeholder="<?php esc_attr_e( 'Leave blank to fetch the public Suno title', 'plague-dr-suno-publisher' ); ?>"></p>
                    <p><label for="pdrs-artist"><strong><?php esc_html_e( 'Artist / featured artist name', 'plague-dr-suno-publisher' ); ?></strong></label><br>
                        <input id="pdrs-artist" class="large-text" type="text" name="artist_name" placeholder="Plague Dr General"><br><span class="description"><?php esc_html_e( 'Displayed as a separate highlighted credit; do not repeat “By…” in the description.', 'plague-dr-suno-publisher' ); ?></span></p>
                    <p><label for="pdrs-description"><strong><?php esc_html_e( 'Description and credits', 'plague-dr-suno-publisher' ); ?></strong></label><br>
                        <textarea id="pdrs-description" class="large-text" name="song_description" rows="5" placeholder="<?php esc_attr_e( 'Short description, production notes, performers, or credits', 'plague-dr-suno-publisher' ); ?>"></textarea></p>
                    <p><label for="pdrs-lyrics"><strong><?php esc_html_e( 'Lyrics', 'plague-dr-suno-publisher' ); ?></strong></label><br>
                        <textarea id="pdrs-lyrics" class="large-text" name="lyrics" rows="10" placeholder="<?php esc_attr_e( 'Verse 1…', 'plague-dr-suno-publisher' ); ?>"></textarea><br><span class="description"><?php esc_html_e( 'Optional. Line breaks are preserved and lyrics remain separate from archive excerpts.', 'plague-dr-suno-publisher' ); ?></span></p>
                    <p><label for="pdrs-mode"><strong><?php esc_html_e( 'Publishing mode', 'plague-dr-suno-publisher' ); ?></strong></label><br>
                        <select id="pdrs-mode" name="placement_mode" data-pdrs-mode><option value="pdu_track" <?php disabled( ! $pdu_available ); ?>><?php esc_html_e( 'Theme Soundtrack only', 'plague-dr-suno-publisher' ); ?></option><option value="pdu_video" <?php disabled( ! PDRS_PDU_Integration::video_available() ); ?>><?php esc_html_e( 'Theme Music Video only', 'plague-dr-suno-publisher' ); ?></option><option value="pdu_both" <?php disabled( ! $pdu_available || ! PDRS_PDU_Integration::video_available() ); ?>><?php esc_html_e( 'Theme Soundtrack + Music Video', 'plague-dr-suno-publisher' ); ?></option><option value="destination" <?php selected( ! $pdu_available ); ?>><?php esc_html_e( 'Place Suno player on an existing page or post', 'plague-dr-suno-publisher' ); ?></option></select></p>
                    <?php if ( ! $pdu_available ) : ?><p class="notice notice-warning inline"><?php esc_html_e( 'The active theme does not currently expose the pdu_track Soundtrack type, so page placement will be used.', 'plague-dr-suno-publisher' ); ?></p><?php endif; ?>
                    <p data-pdrs-mode-panel="pdu_track,pdu_video,pdu_both"><label for="pdrs-duration"><strong><?php esc_html_e( 'Duration', 'plague-dr-suno-publisher' ); ?></strong></label><br><input id="pdrs-duration" type="text" name="duration" placeholder="4:12" pattern="[0-9]{1,3}:[0-5][0-9]"></p>
                    <div class="pdrs-theme-fields" data-pdrs-mode-panel="pdu_track,pdu_both">
                        <h3><?php esc_html_e( 'Theme Soundtrack details', 'plague-dr-suno-publisher' ); ?></h3>
                        <p><label for="pdrs-album"><strong><?php esc_html_e( 'Album / release', 'plague-dr-suno-publisher' ); ?></strong></label><br><input id="pdrs-album" class="large-text" type="text" name="album"></p>
                        <div class="pdrs-two-column">
                            <p><label for="pdrs-genre"><strong><?php esc_html_e( 'Genre', 'plague-dr-suno-publisher' ); ?></strong></label><br><input id="pdrs-genre" class="large-text" type="text" name="genre" placeholder="Doom, ambient, metal…"></p>
                            <p><label for="pdrs-buy-url"><strong><?php esc_html_e( 'Buy / stream URL', 'plague-dr-suno-publisher' ); ?></strong></label><br><input id="pdrs-buy-url" class="large-text" type="url" name="buy_url"></p>
                        </div>
                        <p><label for="pdrs-audio-url"><strong><?php esc_html_e( 'Local audio file (optional)', 'plague-dr-suno-publisher' ); ?></strong></label><br><span class="pdrs-media-field"><input id="pdrs-audio-url" class="large-text" type="url" name="audio_url" placeholder="Choose an MP3/OGG from the Media Library"><button class="button" type="button" data-pdrs-select-audio data-target="pdrs-audio-url"><?php esc_html_e( 'Choose audio', 'plague-dr-suno-publisher' ); ?></button></span><br><span class="description"><?php esc_html_e( 'With a direct file, the theme’s native player works everywhere. Without one, Suno opens in a hosted-player modal and on the track page.', 'plague-dr-suno-publisher' ); ?></span></p>
                    </div>
                    <div class="pdrs-theme-fields" data-pdrs-mode-panel="pdu_video,pdu_both">
                        <h3><?php esc_html_e( 'Theme Music Video details', 'plague-dr-suno-publisher' ); ?></h3>
                        <p><label for="pdrs-video-url"><strong><?php esc_html_e( 'YouTube, Vimeo, or direct video URL', 'plague-dr-suno-publisher' ); ?></strong></label><br><input id="pdrs-video-url" class="large-text" type="url" name="video_url" placeholder="https://www.youtube.com/watch?v=…"></p>
                        <p><label for="pdrs-video-note"><strong><?php esc_html_e( 'Runtime / release note', 'plague-dr-suno-publisher' ); ?></strong></label><br><input id="pdrs-video-note" class="large-text" type="text" name="video_note" placeholder="Official music video"></p>
                    </div>
                    <p><label for="pdrs-artwork"><strong><?php esc_html_e( 'Artwork URL', 'plague-dr-suno-publisher' ); ?></strong></label><br>
                        <input id="pdrs-artwork" class="large-text" type="url" name="artwork_url" placeholder="<?php esc_attr_e( 'Optional; Suno artwork is fetched when available', 'plague-dr-suno-publisher' ); ?>"></p>
                    <p data-pdrs-mode-panel="pdu_track,pdu_video,pdu_both"><label><input type="checkbox" name="import_artwork" value="1" checked> <?php esc_html_e( 'Import this artwork into the Media Library as the theme cover image', 'plague-dr-suno-publisher' ); ?></label></p>
                    <div class="pdrs-two-column" data-pdrs-mode-panel="destination">
                        <p><label for="pdrs-destination"><strong><?php esc_html_e( 'Destination', 'plague-dr-suno-publisher' ); ?></strong></label><br>
                            <?php $this->destination_select( 'destination_id', 0, 'pdrs-destination' ); ?></p>
                        <p><label for="pdrs-position"><strong><?php esc_html_e( 'Placement', 'plague-dr-suno-publisher' ); ?></strong></label><br>
                            <select id="pdrs-position" name="position"><option value="after"><?php esc_html_e( 'After page content', 'plague-dr-suno-publisher' ); ?></option><option value="before"><?php esc_html_e( 'Before page content', 'plague-dr-suno-publisher' ); ?></option></select></p>
                    </div>
                    <p><label for="pdrs-status"><strong><?php esc_html_e( 'Visibility', 'plague-dr-suno-publisher' ); ?></strong></label><br>
                        <select id="pdrs-status" name="song_status"><option value="draft"><?php esc_html_e( 'Draft — do not show yet', 'plague-dr-suno-publisher' ); ?></option><option value="publish"><?php esc_html_e( 'Active — show on the destination', 'plague-dr-suno-publisher' ); ?></option></select></p>
                    <?php submit_button( __( 'Add song', 'plague-dr-suno-publisher' ), 'primary', 'submit', false ); ?>
                </form>
                <aside>
                    <section class="pdrs-card"><h2><?php esc_html_e( 'Native theme integration', 'plague-dr-suno-publisher' ); ?></h2><p><?php esc_html_e( 'Create a Soundtrack, Music Video, or both from one managed release. Shared title, description, lyrics, duration, artwork, and status stay synchronized without duplicate native posts.', 'plague-dr-suno-publisher' ); ?></p></section>
                    <section class="pdrs-card"><h2><?php esc_html_e( 'Manual placement', 'plague-dr-suno-publisher' ); ?></h2><p><?php esc_html_e( 'Every song also has a shortcode:', 'plague-dr-suno-publisher' ); ?></p><code>[plague_dr_song id="123"]</code><p><?php esc_html_e( 'Use it in a Shortcode block when you want a second placement or precise position inside a layout.', 'plague-dr-suno-publisher' ); ?></p></section>
                    <section class="pdrs-card pdrs-safety"><h2><?php esc_html_e( 'How media is handled', 'plague-dr-suno-publisher' ); ?></h2><p><?php esc_html_e( 'By default, audio remains hosted by Suno. The plugin only uses a local audio file when you explicitly choose one from WordPress. Artwork is imported only when the cover-image option is checked.', 'plague-dr-suno-publisher' ); ?></p></section>
                </aside>
            </div>
        </div>
        <?php
    }

    public function add_song(): void {
        if ( ! current_user_can( 'edit_pages' ) ) {
            wp_die( esc_html__( 'You are not allowed to add Plague Dr songs.', 'plague-dr-suno-publisher' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'pdrs_add_song' );

        $requested_mode = sanitize_key( (string) ( $_POST['placement_mode'] ?? '' ) );
        $placement_mode = 'destination';
        if ( PDRS_PDU_Integration::MODE === $requested_mode && PDRS_PDU_Integration::available() ) {
            $placement_mode = PDRS_PDU_Integration::MODE;
        } elseif ( PDRS_PDU_Integration::MODE_VIDEO === $requested_mode && PDRS_PDU_Integration::video_available() ) {
            $placement_mode = PDRS_PDU_Integration::MODE_VIDEO;
        } elseif ( PDRS_PDU_Integration::MODE_BOTH === $requested_mode && PDRS_PDU_Integration::available() && PDRS_PDU_Integration::video_available() ) {
            $placement_mode = PDRS_PDU_Integration::MODE_BOTH;
        }

        $suno_url  = sanitize_text_field( wp_unslash( (string) ( $_POST['suno_url'] ?? '' ) ) );
        $audio_url = PDRS_PDU_Integration::audio_url( wp_unslash( (string) ( $_POST['audio_url'] ?? '' ) ) );
        $video_url = PDRS_PDU_Integration::video_url( wp_unslash( (string) ( $_POST['video_url'] ?? '' ) ) );
        $resolved  = array( 'song_id' => '', 'source_url' => '', 'embed_url' => '' );
        if ( '' !== $suno_url ) {
            $resolved = PDRS_Suno_URL::resolve( $suno_url );
            if ( is_wp_error( $resolved ) ) {
                $this->set_notice( 'error', $resolved->get_error_message() );
                $this->redirect_add();
            }
        }

        $needs_track = PDRS_PDU_Integration::mode_has_track( $placement_mode );
        $needs_video = PDRS_PDU_Integration::mode_has_video( $placement_mode );
        if ( 'destination' === $placement_mode && empty( $resolved['song_id'] ) ) {
            $this->set_notice( 'error', __( 'Page/post placement requires a public Suno song URL.', 'plague-dr-suno-publisher' ) );
            $this->redirect_add();
        }
        if ( $needs_track && empty( $resolved['song_id'] ) && '' === $audio_url ) {
            $this->set_notice( 'error', __( 'A Soundtrack needs either a Suno URL or a local audio file.', 'plague-dr-suno-publisher' ) );
            $this->redirect_add();
        }
        if ( $needs_video && '' === $video_url ) {
            $this->set_notice( 'error', __( 'A Music Video needs a YouTube, Vimeo, or direct video URL.', 'plague-dr-suno-publisher' ) );
            $this->redirect_add();
        }

        $duplicate = empty( $resolved['song_id'] ) ? 0 : $this->find_song( $resolved['song_id'] );
        if ( ! $duplicate && '' !== $video_url ) {
            $duplicate = $this->find_video( $video_url );
        }
        if ( $duplicate ) {
            $this->set_notice( 'warning', __( 'That media source is already managed. Its edit screen has been opened.', 'plague-dr-suno-publisher' ) );
            $this->redirect_edit( $duplicate );
        }

        $destination_id = 'destination' === $placement_mode ? absint( $_POST['destination_id'] ?? 0 ) : 0;
        if ( $destination_id && ! $this->valid_destination( $destination_id ) ) {
            $this->set_notice( 'error', __( 'You cannot edit the selected destination.', 'plague-dr-suno-publisher' ) );
            $this->redirect_add();
        }

        $metadata    = empty( $resolved['song_id'] ) ? array( 'title' => '', 'description' => '', 'image' => '' ) : PDRS_Metadata::fetch( $resolved['song_id'] );
        $title       = sanitize_text_field( wp_unslash( (string) ( $_POST['song_title'] ?? '' ) ) );
        $artist      = sanitize_text_field( wp_unslash( (string) ( $_POST['artist_name'] ?? '' ) ) );
        $description = wp_kses_post( wp_unslash( (string) ( $_POST['song_description'] ?? '' ) ) );
        $lyrics      = wp_kses_post( wp_unslash( (string) ( $_POST['lyrics'] ?? '' ) ) );
        $artwork     = PDRS_Metadata::image_url( wp_unslash( (string) ( $_POST['artwork_url'] ?? '' ) ) );
        if ( '' === $title ) {
            $title = $metadata['title'] ?: ( $resolved['song_id']
                ? sprintf( /* translators: %s: shortened song ID. */ __( 'Suno Song %s', 'plague-dr-suno-publisher' ), substr( $resolved['song_id'], 0, 8 ) )
                : __( 'Untitled Music Release', 'plague-dr-suno-publisher' ) );
        }
        if ( '' === trim( $description ) ) {
            $description = $metadata['description'];
        }
        if ( '' === $artwork ) {
            $artwork = $metadata['image'];
        }

        $status = 'publish' === ( $_POST['song_status'] ?? '' ) && current_user_can( 'publish_pages' ) ? 'publish' : 'draft';
        $post_id = wp_insert_post(
            wp_slash(
                array(
                    'post_type'    => PDRS_Plugin::POST_TYPE,
                    'post_status'  => $status,
                    'post_title'   => $title,
                    'post_content' => $description,
                    'post_author'  => get_current_user_id(),
                )
            ),
            true
        );
        if ( is_wp_error( $post_id ) ) {
            $this->set_notice( 'error', __( 'WordPress could not create the song record.', 'plague-dr-suno-publisher' ) );
            $this->redirect_add();
        }

        if ( $resolved['song_id'] ) {
            update_post_meta( $post_id, '_pdrs_song_id', $resolved['song_id'] );
            update_post_meta( $post_id, '_pdrs_source_url', $resolved['source_url'] );
        }
        update_post_meta( $post_id, '_pdrs_video_url', $video_url );
        update_post_meta( $post_id, '_pdrs_video_note', sanitize_text_field( wp_unslash( (string) ( $_POST['video_note'] ?? '' ) ) ) );
        update_post_meta( $post_id, '_pdrs_placement_mode', $placement_mode );
        update_post_meta( $post_id, '_pdrs_destination_id', $destination_id );
        update_post_meta( $post_id, '_pdrs_position', 'before' === ( $_POST['position'] ?? '' ) ? 'before' : 'after' );
        update_post_meta( $post_id, '_pdrs_artwork_url', $artwork );
        update_post_meta( $post_id, '_pdrs_artist_name', $artist );
        update_post_meta( $post_id, '_pdrs_lyrics', $lyrics );
        update_post_meta( $post_id, '_pdrs_album', sanitize_text_field( wp_unslash( (string) ( $_POST['album'] ?? '' ) ) ) );
        update_post_meta( $post_id, '_pdrs_duration', PDRS_PDU_Integration::duration( wp_unslash( (string) ( $_POST['duration'] ?? '' ) ) ) );
        update_post_meta( $post_id, '_pdrs_genre', sanitize_text_field( wp_unslash( (string) ( $_POST['genre'] ?? '' ) ) ) );
        update_post_meta( $post_id, '_pdrs_buy_url', esc_url_raw( wp_unslash( (string) ( $_POST['buy_url'] ?? '' ) ), array( 'http', 'https' ) ) );
        update_post_meta( $post_id, '_pdrs_audio_url', $audio_url );
        update_post_meta( $post_id, '_pdrs_import_artwork', empty( $_POST['import_artwork'] ) ? 0 : 1 );

        if ( 'destination' !== $placement_mode ) {
            $synced = PDRS_PDU_Integration::sync_song( (int) $post_id );
            if ( is_wp_error( $synced ) ) {
                $this->set_notice( 'error', $synced->get_error_message() );
                $this->redirect_edit( (int) $post_id );
            }
        }

        $message = 'destination' !== $placement_mode
            ? __( 'Music release saved and synchronized with the selected native theme content.', 'plague-dr-suno-publisher' )
            : ( 'publish' === $status
                ? __( 'Song added and activated on its selected destination.', 'plague-dr-suno-publisher' )
                : __( 'Song saved as a draft. Review it and publish when ready.', 'plague-dr-suno-publisher' ) );
        $this->set_notice( 'success', $message );
        $this->redirect_created( (int) $post_id );
    }

    public function meta_box( WP_Post $post ): void {
        add_meta_box(
            'pdrs-placement',
            __( 'Suno Placement', 'plague-dr-suno-publisher' ),
            array( $this, 'meta_box_html' ),
            PDRS_Plugin::POST_TYPE,
            'side',
            'high'
        );
        add_meta_box(
            'pdrs-lyrics',
            __( 'Lyrics', 'plague-dr-suno-publisher' ),
            array( $this, 'lyrics_meta_box_html' ),
            PDRS_Plugin::POST_TYPE,
            'normal',
            'default'
        );
    }

    public function meta_box_html( WP_Post $post ): void {
        $source      = (string) get_post_meta( $post->ID, '_pdrs_source_url', true );
        $destination = (int) get_post_meta( $post->ID, '_pdrs_destination_id', true );
        $position    = (string) get_post_meta( $post->ID, '_pdrs_position', true );
        $artwork     = (string) get_post_meta( $post->ID, '_pdrs_artwork_url', true );
        $artist      = (string) get_post_meta( $post->ID, '_pdrs_artist_name', true );
        $mode        = PDRS_PDU_Integration::mode_for( (int) $post->ID );
        $track_id    = PDRS_PDU_Integration::linked_track_id( (int) $post->ID );
        $video_id    = PDRS_PDU_Integration::linked_video_id( (int) $post->ID );
        $video_url   = (string) get_post_meta( $post->ID, '_pdrs_video_url', true );
        $video_note  = (string) get_post_meta( $post->ID, '_pdrs_video_note', true );
        $album       = (string) get_post_meta( $post->ID, '_pdrs_album', true );
        $duration    = (string) get_post_meta( $post->ID, '_pdrs_duration', true );
        $genre       = (string) get_post_meta( $post->ID, '_pdrs_genre', true );
        $buy_url     = (string) get_post_meta( $post->ID, '_pdrs_buy_url', true );
        $audio_url   = (string) get_post_meta( $post->ID, '_pdrs_audio_url', true );
        $import_art  = (bool) get_post_meta( $post->ID, '_pdrs_import_artwork', true );
        wp_nonce_field( 'pdrs_save_song_' . $post->ID, 'pdrs_song_nonce' );
        ?>
        <p><label for="pdrs-song-url"><strong><?php esc_html_e( 'Suno song URL (optional)', 'plague-dr-suno-publisher' ); ?></strong></label><input id="pdrs-song-url" class="widefat" type="url" name="pdrs_song_url" value="<?php echo esc_attr( $source ); ?>"></p>
        <p><label for="pdrs-song-artist"><strong><?php esc_html_e( 'Artist / featured artist', 'plague-dr-suno-publisher' ); ?></strong></label><input id="pdrs-song-artist" class="widefat" type="text" name="pdrs_artist_name" value="<?php echo esc_attr( $artist ); ?>"></p>
        <p><label for="pdrs-song-mode"><strong><?php esc_html_e( 'Publishing mode', 'plague-dr-suno-publisher' ); ?></strong></label><select class="widefat" id="pdrs-song-mode" name="pdrs_placement_mode" data-pdrs-mode><option value="pdu_track" <?php selected( $mode, 'pdu_track' ); disabled( ! PDRS_PDU_Integration::available() ); ?>><?php esc_html_e( 'Theme Soundtrack only', 'plague-dr-suno-publisher' ); ?></option><option value="pdu_video" <?php selected( $mode, 'pdu_video' ); disabled( ! PDRS_PDU_Integration::video_available() ); ?>><?php esc_html_e( 'Theme Music Video only', 'plague-dr-suno-publisher' ); ?></option><option value="pdu_both" <?php selected( $mode, 'pdu_both' ); disabled( ! PDRS_PDU_Integration::available() || ! PDRS_PDU_Integration::video_available() ); ?>><?php esc_html_e( 'Theme Soundtrack + Music Video', 'plague-dr-suno-publisher' ); ?></option><option value="destination" <?php selected( $mode, 'destination' ); ?>><?php esc_html_e( 'Page/post destination', 'plague-dr-suno-publisher' ); ?></option></select></p>
        <p data-pdrs-mode-panel="pdu_track,pdu_video,pdu_both"><label for="pdrs-song-duration"><strong><?php esc_html_e( 'Duration', 'plague-dr-suno-publisher' ); ?></strong></label><input id="pdrs-song-duration" class="widefat" type="text" name="pdrs_duration" value="<?php echo esc_attr( $duration ); ?>" placeholder="4:12"></p>
        <div data-pdrs-mode-panel="pdu_track,pdu_both">
            <p><label for="pdrs-song-album"><strong><?php esc_html_e( 'Album / release', 'plague-dr-suno-publisher' ); ?></strong></label><input id="pdrs-song-album" class="widefat" type="text" name="pdrs_album" value="<?php echo esc_attr( $album ); ?>"></p>
            <p><label for="pdrs-song-genre"><strong><?php esc_html_e( 'Genre', 'plague-dr-suno-publisher' ); ?></strong></label><input id="pdrs-song-genre" class="widefat" type="text" name="pdrs_genre" value="<?php echo esc_attr( $genre ); ?>"></p>
            <p><label for="pdrs-song-buy"><strong><?php esc_html_e( 'Buy / stream URL', 'plague-dr-suno-publisher' ); ?></strong></label><input id="pdrs-song-buy" class="widefat" type="url" name="pdrs_buy_url" value="<?php echo esc_attr( $buy_url ); ?>"></p>
            <p><label for="pdrs-song-audio"><strong><?php esc_html_e( 'Local audio (optional)', 'plague-dr-suno-publisher' ); ?></strong></label><input id="pdrs-song-audio" class="widefat" type="url" name="pdrs_audio_url" value="<?php echo esc_attr( $audio_url ); ?>"><button class="button" type="button" data-pdrs-select-audio data-target="pdrs-song-audio"><?php esc_html_e( 'Choose audio', 'plague-dr-suno-publisher' ); ?></button></p>
            <?php if ( $track_id ) : ?><p><a href="<?php echo esc_url( get_edit_post_link( $track_id ) ); ?>"><?php esc_html_e( 'Edit linked theme Soundtrack', 'plague-dr-suno-publisher' ); ?></a> · <a href="<?php echo esc_url( get_permalink( $track_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'plague-dr-suno-publisher' ); ?></a></p><?php endif; ?>
        </div>
        <div data-pdrs-mode-panel="pdu_video,pdu_both">
            <p><label for="pdrs-song-video"><strong><?php esc_html_e( 'YouTube, Vimeo, or direct video URL', 'plague-dr-suno-publisher' ); ?></strong></label><input id="pdrs-song-video" class="widefat" type="url" name="pdrs_video_url" value="<?php echo esc_attr( $video_url ); ?>"></p>
            <p><label for="pdrs-song-video-note"><strong><?php esc_html_e( 'Runtime / release note', 'plague-dr-suno-publisher' ); ?></strong></label><input id="pdrs-song-video-note" class="widefat" type="text" name="pdrs_video_note" value="<?php echo esc_attr( $video_note ); ?>"></p>
            <?php if ( $video_id ) : ?><p><a href="<?php echo esc_url( get_edit_post_link( $video_id ) ); ?>"><?php esc_html_e( 'Edit linked theme Video Release', 'plague-dr-suno-publisher' ); ?></a> · <a href="<?php echo esc_url( get_permalink( $video_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'plague-dr-suno-publisher' ); ?></a></p><?php endif; ?>
        </div>
        <p data-pdrs-mode-panel="pdu_track,pdu_video,pdu_both"><label><input type="checkbox" name="pdrs_import_artwork" value="1" <?php checked( $import_art ); ?>> <?php esc_html_e( 'Import artwork as cover image', 'plague-dr-suno-publisher' ); ?></label></p>
        <div data-pdrs-mode-panel="destination">
            <p><label for="pdrs-song-destination"><strong><?php esc_html_e( 'Destination', 'plague-dr-suno-publisher' ); ?></strong></label><?php $this->destination_select( 'pdrs_destination_id', $destination, 'pdrs-song-destination' ); ?></p>
            <p><label for="pdrs-song-position"><strong><?php esc_html_e( 'Placement', 'plague-dr-suno-publisher' ); ?></strong></label><select class="widefat" id="pdrs-song-position" name="pdrs_position"><option value="after" <?php selected( $position, 'after' ); ?>><?php esc_html_e( 'After page content', 'plague-dr-suno-publisher' ); ?></option><option value="before" <?php selected( $position, 'before' ); ?>><?php esc_html_e( 'Before page content', 'plague-dr-suno-publisher' ); ?></option></select></p>
        </div>
        <p><label for="pdrs-song-artwork"><strong><?php esc_html_e( 'Artwork URL', 'plague-dr-suno-publisher' ); ?></strong></label><input id="pdrs-song-artwork" class="widefat" type="url" name="pdrs_artwork_url" value="<?php echo esc_attr( $artwork ); ?>"></p>
        <p><strong><?php esc_html_e( 'Shortcode', 'plague-dr-suno-publisher' ); ?></strong><br><code>[plague_dr_song id="<?php echo esc_attr( (string) $post->ID ); ?>"]</code></p>
        <?php if ( $source ) : ?><p><a href="<?php echo esc_url( $source ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open song on Suno', 'plague-dr-suno-publisher' ); ?></a></p><?php endif; ?>
        <?php
    }

    public function lyrics_meta_box_html( WP_Post $post ): void {
        $lyrics = (string) get_post_meta( $post->ID, '_pdrs_lyrics', true );
        ?>
        <p class="description"><?php esc_html_e( 'Lyrics are stored once, excluded from archive excerpts, and shown on linked Soundtrack and Music Video pages.', 'plague-dr-suno-publisher' ); ?></p>
        <textarea class="widefat pdrs-lyrics-editor" name="pdrs_lyrics" rows="16" placeholder="<?php esc_attr_e( 'Verse 1…', 'plague-dr-suno-publisher' ); ?>"><?php echo esc_textarea( $lyrics ); ?></textarea>
        <?php
    }

    public function save_song( int $post_id, WP_Post $post ): void {
        if ( ! isset( $_POST['pdrs_song_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pdrs_song_nonce'] ) ), 'pdrs_save_song_' . $post_id ) ) {
            return;
        }
        if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $requested_mode = sanitize_key( (string) ( $_POST['pdrs_placement_mode'] ?? '' ) );
        $mode = 'destination';
        if ( PDRS_PDU_Integration::MODE === $requested_mode && PDRS_PDU_Integration::available() ) {
            $mode = PDRS_PDU_Integration::MODE;
        } elseif ( PDRS_PDU_Integration::MODE_VIDEO === $requested_mode && PDRS_PDU_Integration::video_available() ) {
            $mode = PDRS_PDU_Integration::MODE_VIDEO;
        } elseif ( PDRS_PDU_Integration::MODE_BOTH === $requested_mode && PDRS_PDU_Integration::available() && PDRS_PDU_Integration::video_available() ) {
            $mode = PDRS_PDU_Integration::MODE_BOTH;
        }
        update_post_meta( $post_id, '_pdrs_placement_mode', $mode );

        $url = sanitize_text_field( wp_unslash( (string) ( $_POST['pdrs_song_url'] ?? '' ) ) );
        if ( '' !== $url ) {
            $resolved = PDRS_Suno_URL::resolve( $url );
            if ( is_wp_error( $resolved ) ) {
                $this->set_notice( 'error', $resolved->get_error_message() );
            } else {
                $duplicate = $this->find_song( $resolved['song_id'], $post_id );
                if ( $duplicate ) {
                    $this->set_notice( 'error', __( 'Another Plague Dr Song already uses that Suno URL.', 'plague-dr-suno-publisher' ) );
                } else {
                    update_post_meta( $post_id, '_pdrs_song_id', $resolved['song_id'] );
                    update_post_meta( $post_id, '_pdrs_source_url', $resolved['source_url'] );
                }
            }
        } elseif ( PDRS_PDU_Integration::MODE_VIDEO === $mode ) {
            delete_post_meta( $post_id, '_pdrs_song_id' );
            delete_post_meta( $post_id, '_pdrs_source_url' );
        }

        $video_url = PDRS_PDU_Integration::video_url( wp_unslash( (string) ( $_POST['pdrs_video_url'] ?? '' ) ) );
        $video_duplicate = $this->find_video( $video_url, $post_id );
        if ( $video_duplicate ) {
            $this->set_notice( 'error', __( 'Another managed release already uses that video URL.', 'plague-dr-suno-publisher' ) );
        } else {
            update_post_meta( $post_id, '_pdrs_video_url', $video_url );
        }
        update_post_meta( $post_id, '_pdrs_video_note', sanitize_text_field( wp_unslash( (string) ( $_POST['pdrs_video_note'] ?? '' ) ) ) );

        $destination_id = 'destination' === $mode ? absint( $_POST['pdrs_destination_id'] ?? 0 ) : 0;
        if ( 0 === $destination_id || $this->valid_destination( $destination_id ) ) {
            update_post_meta( $post_id, '_pdrs_destination_id', $destination_id );
        } else {
            $this->set_notice( 'error', __( 'The selected destination was not saved because you cannot edit it.', 'plague-dr-suno-publisher' ) );
        }
        update_post_meta( $post_id, '_pdrs_position', 'before' === ( $_POST['pdrs_position'] ?? '' ) ? 'before' : 'after' );
        update_post_meta( $post_id, '_pdrs_artwork_url', PDRS_Metadata::image_url( wp_unslash( (string) ( $_POST['pdrs_artwork_url'] ?? '' ) ) ) );
        update_post_meta( $post_id, '_pdrs_artist_name', sanitize_text_field( wp_unslash( (string) ( $_POST['pdrs_artist_name'] ?? '' ) ) ) );
        update_post_meta( $post_id, '_pdrs_lyrics', wp_kses_post( wp_unslash( (string) ( $_POST['pdrs_lyrics'] ?? '' ) ) ) );
        update_post_meta( $post_id, '_pdrs_album', sanitize_text_field( wp_unslash( (string) ( $_POST['pdrs_album'] ?? '' ) ) ) );
        update_post_meta( $post_id, '_pdrs_duration', PDRS_PDU_Integration::duration( wp_unslash( (string) ( $_POST['pdrs_duration'] ?? '' ) ) ) );
        update_post_meta( $post_id, '_pdrs_genre', sanitize_text_field( wp_unslash( (string) ( $_POST['pdrs_genre'] ?? '' ) ) ) );
        update_post_meta( $post_id, '_pdrs_buy_url', esc_url_raw( wp_unslash( (string) ( $_POST['pdrs_buy_url'] ?? '' ) ), array( 'http', 'https' ) ) );
        update_post_meta( $post_id, '_pdrs_audio_url', PDRS_PDU_Integration::audio_url( wp_unslash( (string) ( $_POST['pdrs_audio_url'] ?? '' ) ) ) );
        update_post_meta( $post_id, '_pdrs_import_artwork', empty( $_POST['pdrs_import_artwork'] ) ? 0 : 1 );
    }

    /**
     * @param array<string,string> $columns Columns.
     * @return array<string,string>
     */
    public function columns( array $columns ): array {
        $columns['pdrs_destination'] = __( 'Destination', 'plague-dr-suno-publisher' );
        $columns['pdrs_shortcode']   = __( 'Shortcode', 'plague-dr-suno-publisher' );
        return $columns;
    }

    public function column( string $column, int $post_id ): void {
        if ( 'pdrs_destination' === $column ) {
            $mode = PDRS_PDU_Integration::mode_for( $post_id );
            if ( PDRS_PDU_Integration::mode_has_track( $mode ) || PDRS_PDU_Integration::mode_has_video( $mode ) ) {
                $links = array();
                $track_id = PDRS_PDU_Integration::linked_track_id( $post_id );
                $video_id = PDRS_PDU_Integration::linked_video_id( $post_id );
                if ( PDRS_PDU_Integration::mode_has_track( $mode ) ) {
                    $links[] = $track_id ? '<a href="' . esc_url( get_edit_post_link( $track_id ) ) . '">' . esc_html__( 'Soundtrack', 'plague-dr-suno-publisher' ) . ' #' . esc_html( (string) $track_id ) . '</a>' : esc_html__( 'Soundtrack pending', 'plague-dr-suno-publisher' );
                }
                if ( PDRS_PDU_Integration::mode_has_video( $mode ) ) {
                    $links[] = $video_id ? '<a href="' . esc_url( get_edit_post_link( $video_id ) ) . '">' . esc_html__( 'Video', 'plague-dr-suno-publisher' ) . ' #' . esc_html( (string) $video_id ) . '</a>' : esc_html__( 'Video pending', 'plague-dr-suno-publisher' );
                }
                echo wp_kses_post( implode( '<br>', $links ) );
                return;
            }
            $destination_id = (int) get_post_meta( $post_id, '_pdrs_destination_id', true );
            if ( ! $destination_id ) {
                echo '—';
                return;
            }
            $title = get_the_title( $destination_id );
            $link  = get_edit_post_link( $destination_id );
            echo $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title );
        }
        if ( 'pdrs_shortcode' === $column ) {
            echo '<code>[plague_dr_song id=&quot;' . esc_attr( (string) $post_id ) . '&quot;]</code>';
        }
    }

    public function notice(): void {
        $key    = 'pdrs_notice_' . get_current_user_id();
        $notice = get_transient( $key );
        if ( ! is_array( $notice ) ) {
            return;
        }
        delete_transient( $key );
        $type = in_array( $notice['type'] ?? '', array( 'error', 'warning', 'success' ), true ) ? $notice['type'] : 'info';
        echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( (string) ( $notice['message'] ?? '' ) ) . '</p></div>';
    }

    /**
     * @param string[] $links Links.
     * @return string[]
     */
    public function plugin_links( array $links ): array {
        array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=plague-dr-music' ) ) . '">' . esc_html__( 'Add Song', 'plague-dr-suno-publisher' ) . '</a>' );
        return $links;
    }

    private function created_summary(): void {
        $created_id = absint( $_GET['pdrs_created'] ?? 0 );
        $release    = $created_id ? get_post( $created_id ) : null;
        if ( ! $release || PDRS_Plugin::POST_TYPE !== $release->post_type || ! current_user_can( 'edit_post', $created_id ) ) {
            return;
        }

        $mode           = PDRS_PDU_Integration::mode_for( $created_id );
        $track_id       = PDRS_PDU_Integration::linked_track_id( $created_id );
        $video_id       = PDRS_PDU_Integration::linked_video_id( $created_id );
        $destination_id = absint( get_post_meta( $created_id, '_pdrs_destination_id', true ) );
        ?>
        <section class="pdrs-result" aria-labelledby="pdrs-result-title">
            <div><span class="pdrs-result__check" aria-hidden="true">✓</span></div>
            <div>
                <h2 id="pdrs-result-title"><?php esc_html_e( 'Release created and synchronized', 'plague-dr-suno-publisher' ); ?></h2>
                <p><?php echo esc_html( get_the_title( $created_id ) ); ?> · <strong><?php echo esc_html( ucfirst( get_post_status( $created_id ) ) ); ?></strong></p>
                <?php if ( 'destination' !== $mode ) : ?>
                    <p><?php esc_html_e( 'The theme entries and public placement are already connected. No shortcode or second URL entry is required.', 'plague-dr-suno-publisher' ); ?></p>
                <?php elseif ( $destination_id ) : ?>
                    <p><?php esc_html_e( 'The selected page/post placement is automatic. No shortcode is required.', 'plague-dr-suno-publisher' ); ?></p>
                <?php else : ?>
                    <p><?php esc_html_e( 'No automatic destination was selected. Use the shortcode only when you want manual block-level placement.', 'plague-dr-suno-publisher' ); ?></p>
                <?php endif; ?>
                <div class="pdrs-result__actions">
                    <a class="button" href="<?php echo esc_url( get_edit_post_link( $created_id ) ); ?>"><?php esc_html_e( 'Edit managed release', 'plague-dr-suno-publisher' ); ?></a>
                    <?php $this->native_result_link( $track_id, __( 'Soundtrack', 'plague-dr-suno-publisher' ) ); ?>
                    <?php $this->native_result_link( $video_id, __( 'Music Video', 'plague-dr-suno-publisher' ) ); ?>
                    <?php if ( $destination_id ) : ?><a class="button" href="<?php echo esc_url( get_permalink( $destination_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View destination', 'plague-dr-suno-publisher' ); ?></a><?php endif; ?>
                    <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=plague-dr-music' ) ); ?>"><?php esc_html_e( 'Add another release', 'plague-dr-suno-publisher' ); ?></a>
                </div>
            </div>
        </section>
        <?php
    }

    private function native_result_link( int $post_id, string $label ): void {
        if ( ! $post_id ) {
            return;
        }
        $status   = get_post_status( $post_id );
        $view_url = 'publish' === $status ? get_permalink( $post_id ) : get_preview_post_link( $post_id );
        ?>
        <span class="pdrs-result__native"><a class="button" href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>"><?php echo esc_html( sprintf( /* translators: 1: content label, 2: post ID. */ __( 'Edit %1$s #%2$d', 'plague-dr-suno-publisher' ), $label, $post_id ) ); ?></a><?php if ( $view_url ) : ?><a class="button" href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( sprintf( /* translators: %s: content label. */ __( 'View %s', 'plague-dr-suno-publisher' ), $label ) ); ?></a><?php endif; ?></span>
        <?php
    }

    private function destination_select( string $name, int $selected_id, string $id ): void {
        echo '<select class="widefat" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
        echo '<option value="0">' . esc_html__( 'No automatic destination', 'plague-dr-suno-publisher' ) . '</option>';
        foreach ( $this->destinations() as $post ) {
            $type_object   = get_post_type_object( $post->post_type );
            $status_object = get_post_status_object( $post->post_status );
            $type_label    = $type_object ? $type_object->labels->singular_name : $post->post_type;
            $status        = 'publish' === $post->post_status ? '' : ' — ' . ( $status_object ? $status_object->label : ucfirst( $post->post_status ) );
            $label         = $type_label . ': ' . get_the_title( $post ) . $status;
            echo '<option value="' . esc_attr( (string) $post->ID ) . '" ' . selected( $selected_id, $post->ID, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
    }

    /**
     * @return WP_Post[]
     */
    private function destinations(): array {
        $post_types = get_post_types( array( 'public' => true ), 'names' );
        unset( $post_types['attachment'], $post_types[ PDRS_Plugin::POST_TYPE ] );
        $posts = get_posts(
            array(
                'post_type'        => array_values( $post_types ),
                'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page'   => -1,
                'orderby'          => array( 'post_type' => 'ASC', 'title' => 'ASC' ),
                'order'            => 'ASC',
                'suppress_filters' => false,
            )
        );
        return array_values( array_filter( $posts, static fn( WP_Post $post ): bool => current_user_can( 'edit_post', $post->ID ) ) );
    }

    private function valid_destination( int $post_id ): bool {
        $post = get_post( $post_id );
        if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) {
            return false;
        }
        $type = get_post_type_object( $post->post_type );
        return $type && $type->public && 'attachment' !== $post->post_type;
    }

    private function find_song( string $song_id, int $exclude = 0 ): int {
        $posts = get_posts(
            array(
                'post_type'      => PDRS_Plugin::POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'trash' ),
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'post__not_in'   => $exclude ? array( $exclude ) : array(),
                'meta_key'       => '_pdrs_song_id',
                'meta_value'     => $song_id,
                'no_found_rows'  => true,
            )
        );
        return empty( $posts ) ? 0 : (int) $posts[0];
    }

    private function find_video( string $video_url, int $exclude = 0 ): int {
        if ( '' === $video_url ) {
            return 0;
        }
        $posts = get_posts(
            array(
                'post_type'      => PDRS_Plugin::POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'trash' ),
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'post__not_in'   => $exclude ? array( $exclude ) : array(),
                'meta_key'       => '_pdrs_video_url',
                'meta_value'     => $video_url,
                'no_found_rows'  => true,
            )
        );
        return empty( $posts ) ? 0 : (int) $posts[0];
    }

    private function set_notice( string $type, string $message ): void {
        set_transient( 'pdrs_notice_' . get_current_user_id(), array( 'type' => $type, 'message' => sanitize_text_field( $message ) ), MINUTE_IN_SECONDS );
    }

    private function redirect_add(): void {
        wp_safe_redirect( admin_url( 'admin.php?page=plague-dr-music' ) );
        exit;
    }

    private function redirect_created( int $post_id ): void {
        wp_safe_redirect( admin_url( 'admin.php?page=plague-dr-music&pdrs_created=' . $post_id ) );
        exit;
    }

    private function redirect_edit( int $post_id ): void {
        wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $post_id ) );
        exit;
    }
}
