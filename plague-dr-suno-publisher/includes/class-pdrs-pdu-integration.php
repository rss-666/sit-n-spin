<?php
/**
 * Adapter for The Plague Dr Universe theme's native Soundtrack content type.
 */

defined( 'ABSPATH' ) || exit;

final class PDRS_PDU_Integration {
    public const THEME_POST_TYPE = 'pdu_track';
    public const VIDEO_POST_TYPE = 'pdu_video';
    public const MODE            = 'pdu_track'; // Backward-compatible track-only mode.
    public const MODE_VIDEO      = 'pdu_video';
    public const MODE_BOTH       = 'pdu_both';

    private bool $has_hosted_tracks = false;

    public function hooks(): void {
        add_action( 'save_post_' . PDRS_Plugin::POST_TYPE, array( $this, 'sync_after_save' ), 30, 2 );
        add_action( 'trashed_post', array( $this, 'trash_linked_track' ) );
        add_action( 'untrashed_post', array( $this, 'restore_linked_track' ) );
        add_filter( 'the_content', array( $this, 'single_track_player' ), 15 );
        add_action( 'wp_enqueue_scripts', array( $this, 'frontend_assets' ), 20 );
        add_action( 'wp_footer', array( $this, 'modal' ) );
    }

    public static function available(): bool {
        return post_type_exists( self::THEME_POST_TYPE );
    }

    public static function video_available(): bool {
        return post_type_exists( self::VIDEO_POST_TYPE );
    }

    public static function mode_for( int $song_post_id ): string {
        $mode = (string) get_post_meta( $song_post_id, '_pdrs_placement_mode', true );
        return in_array( $mode, array( self::MODE, self::MODE_VIDEO, self::MODE_BOTH ), true ) ? $mode : 'destination';
    }

    public static function mode_has_track( string $mode ): bool {
        return in_array( $mode, array( self::MODE, self::MODE_BOTH ), true );
    }

    public static function mode_has_video( string $mode ): bool {
        return in_array( $mode, array( self::MODE_VIDEO, self::MODE_BOTH ), true );
    }

    /**
     * Create or update one native theme Soundtrack entry.
     *
     * @return int|WP_Error Linked pdu_track ID or error.
     */
    public static function sync_song( int $song_post_id ) {
        $song = get_post( $song_post_id );
        if ( ! $song || PDRS_Plugin::POST_TYPE !== $song->post_type ) {
            return new WP_Error( 'pdrs_song_missing', __( 'The managed Suno song no longer exists.', 'plague-dr-suno-publisher' ) );
        }

        $mode      = self::mode_for( $song_post_id );
        $has_track = self::mode_has_track( $mode );
        $has_video = self::mode_has_video( $mode );
        if ( ! $has_track && ! $has_video ) {
            self::deactivate_linked_track( $song_post_id );
            self::deactivate_linked_video( $song_post_id );
            return 0;
        }
        if ( $has_track && ! self::available() ) {
            return new WP_Error( 'pdrs_theme_unavailable', __( 'The active theme does not provide the Plague Dr Soundtrack content type.', 'plague-dr-suno-publisher' ) );
        }
        if ( $has_video && ! self::video_available() ) {
            return new WP_Error( 'pdrs_video_type_unavailable', __( 'The active theme does not provide the Plague Dr Video Release content type.', 'plague-dr-suno-publisher' ) );
        }

        $suno_id  = (string) get_post_meta( $song_post_id, '_pdrs_song_id', true );
        $audio_url = self::audio_url( (string) get_post_meta( $song_post_id, '_pdrs_audio_url', true ) );
        $video_url = self::video_url( (string) get_post_meta( $song_post_id, '_pdrs_video_url', true ) );
        if ( $has_track && ! PDRS_Suno_URL::valid_song_id( $suno_id ) && '' === $audio_url ) {
            return new WP_Error( 'pdrs_track_source_missing', __( 'A theme Soundtrack needs either a valid Suno URL or a local audio file.', 'plague-dr-suno-publisher' ) );
        }
        if ( $has_video && '' === $video_url ) {
            return new WP_Error( 'pdrs_video_source_missing', __( 'A theme Video Release needs a valid YouTube, Vimeo, or direct video URL.', 'plague-dr-suno-publisher' ) );
        }

        if ( ! $has_track ) {
            self::deactivate_linked_track( $song_post_id );
            return self::sync_video( $song_post_id, $song, $video_url );
        }

        $linked_id = self::linked_track_id( $song_post_id );
        if ( $linked_id && 'trash' === get_post_status( $linked_id ) ) {
            wp_untrash_post( $linked_id );
        }

        $status = in_array( $song->post_status, array( 'publish', 'pending', 'draft', 'private' ), true ) ? $song->post_status : 'draft';
        $data   = array(
            'post_type'    => self::THEME_POST_TYPE,
            'post_status'  => $status,
            'post_title'   => get_the_title( $song_post_id ),
            'post_content' => (string) $song->post_content,
            'post_excerpt' => wp_trim_words( wp_strip_all_tags( (string) $song->post_content ), 28 ),
            'post_author'  => (int) $song->post_author,
        );

        if ( $linked_id ) {
            $data['ID'] = $linked_id;
            $result     = wp_update_post( wp_slash( $data ), true );
        } else {
            $result = wp_insert_post( wp_slash( $data ), true );
        }
        if ( is_wp_error( $result ) ) {
            return new WP_Error( 'pdrs_theme_sync_failed', __( 'WordPress could not save the linked theme Soundtrack.', 'plague-dr-suno-publisher' ) );
        }

        $track_id = (int) $result;
        update_post_meta( $song_post_id, '_pdrs_pdu_track_id', $track_id );
        update_post_meta( $track_id, '_pdrs_managed_song_id', $song_post_id );
        PDRS_Suno_URL::valid_song_id( $suno_id ) ? update_post_meta( $track_id, '_pdrs_suno_id', $suno_id ) : delete_post_meta( $track_id, '_pdrs_suno_id' );

        self::sync_text_meta( $track_id, 'pdu_album', (string) get_post_meta( $song_post_id, '_pdrs_album', true ) );
        self::sync_text_meta( $track_id, 'pdu_duration', self::duration( (string) get_post_meta( $song_post_id, '_pdrs_duration', true ) ) );
        self::sync_url_meta( $track_id, 'pdu_buy_url', (string) get_post_meta( $song_post_id, '_pdrs_buy_url', true ) );
        self::sync_url_meta( $track_id, 'pdu_audio_url', $audio_url );

        if ( taxonomy_exists( 'pdu_genre' ) ) {
            $genre = sanitize_text_field( (string) get_post_meta( $song_post_id, '_pdrs_genre', true ) );
            wp_set_object_terms( $track_id, '' === $genre ? array() : array( $genre ), 'pdu_genre', false );
        }

        self::maybe_import_artwork( $song_post_id, $track_id );
        if ( $has_video ) {
            $video_result = self::sync_video( $song_post_id, $song, $video_url, $track_id );
            if ( is_wp_error( $video_result ) ) {
                return $video_result;
            }
        } else {
            self::deactivate_linked_video( $song_post_id );
        }
        return $track_id;
    }

    public function sync_after_save( int $post_id, WP_Post $post ): void {
        if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
            return;
        }
        if ( ! get_post_meta( $post_id, '_pdrs_song_id', true ) && ! get_post_meta( $post_id, '_pdrs_video_url', true ) && ! get_post_meta( $post_id, '_pdrs_audio_url', true ) ) {
            return; // Quick Add writes media metadata immediately after wp_insert_post().
        }
        $result = self::sync_song( $post_id );
        if ( is_wp_error( $result ) && is_admin() ) {
            self::notice( 'error', $result->get_error_message() );
        }
    }

    public function trash_linked_track( int $post_id ): void {
        if ( PDRS_Plugin::POST_TYPE !== get_post_type( $post_id ) ) {
            return;
        }
        foreach ( array( self::linked_track_id( $post_id ), self::linked_video_id( $post_id ) ) as $linked_id ) {
            if ( $linked_id && 'trash' !== get_post_status( $linked_id ) ) {
                wp_trash_post( $linked_id );
            }
        }
    }

    public function restore_linked_track( int $post_id ): void {
        if ( PDRS_Plugin::POST_TYPE !== get_post_type( $post_id ) ) {
            return;
        }
        foreach ( array( self::linked_track_id( $post_id ), self::linked_video_id( $post_id ) ) as $linked_id ) {
            if ( $linked_id && 'trash' === get_post_status( $linked_id ) ) {
                wp_untrash_post( $linked_id );
                wp_update_post( array( 'ID' => $linked_id, 'post_status' => 'draft' ) );
            }
        }
    }

    /**
     * Add the Suno fallback player to a native Soundtrack page only when no
     * direct audio file is available for the theme player.
     */
    public function single_track_player( string $content ): string {
        if ( is_admin() || is_feed() || ! is_singular( array( self::THEME_POST_TYPE, self::VIDEO_POST_TYPE ) ) || get_the_ID() !== get_queried_object_id() ) {
            return $content;
        }
        $native_id = get_queried_object_id();
        $song_id   = absint( get_post_meta( $native_id, '_pdrs_managed_song_id', true ) );
        if ( ! $song_id ) {
            return $content;
        }
        $managed_is_public = 'publish' === get_post_status( $song_id );
        $native_is_public  = 'publish' === get_post_status( $native_id );
        if ( ! $managed_is_public && ! $native_is_public && ! current_user_can( 'edit_post', $song_id ) ) {
            return $content;
        }
        $lyrics = PDRS_Renderer::lyrics_html( $song_id, true );
        if ( self::VIDEO_POST_TYPE === get_post_type( $native_id ) ) {
            return $content . $lyrics;
        }
        $player = get_post_meta( $native_id, 'pdu_audio_url', true ) ? '' : self::hosted_player_html( $song_id );
        return $player . $content . $lyrics;
    }

    /**
     * Enable hosted-player modal fallbacks for theme track lists that have no
     * native direct audio URL.
     */
    public function frontend_assets(): void {
        if ( ! self::available() ) {
            return;
        }
        $songs = get_posts(
            array(
                'post_type'      => PDRS_Plugin::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 100,
                'meta_query'     => array(
                    array(
                        'key'     => '_pdrs_placement_mode',
                        'value'   => array( self::MODE, self::MODE_BOTH ),
                        'compare' => 'IN',
                    ),
                ),
                'no_found_rows'  => true,
            )
        );
        $tracks = array();
        foreach ( $songs as $song ) {
            $track_id = self::linked_track_id( (int) $song->ID );
            $suno_id  = (string) get_post_meta( $song->ID, '_pdrs_song_id', true );
            if ( ! $track_id || 'publish' !== get_post_status( $track_id ) || get_post_meta( $track_id, 'pdu_audio_url', true ) || ! PDRS_Suno_URL::valid_song_id( $suno_id ) ) {
                continue;
            }
            $details  = PDRS_Suno_URL::details( $suno_id );
            $tracks[] = array(
                'title'    => get_the_title( $track_id ),
                'album'    => (string) get_post_meta( $track_id, 'pdu_album', true ),
                'embedUrl' => $details['embed_url'],
                'trackUrl' => get_permalink( $track_id ),
            );
        }
        if ( empty( $tracks ) ) {
            return;
        }
        $this->has_hosted_tracks = true;
        wp_enqueue_script( 'pdrs-pdu', PDRS_PLUGIN_URL . 'assets/pdu-integration.js', array(), PDRS_VERSION, true );
        wp_localize_script(
            'pdrs-pdu',
            'pdrsPduTracks',
            array(
                'tracks' => $tracks,
                'close'  => __( 'Close player', 'plague-dr-suno-publisher' ),
            )
        );
    }

    public function modal(): void {
        if ( ! $this->has_hosted_tracks ) {
            return;
        }
        ?>
        <dialog class="pdrs-pdu-dialog" data-pdrs-dialog aria-labelledby="pdrs-dialog-title">
            <div class="pdrs-pdu-dialog__bar"><h2 id="pdrs-dialog-title" data-pdrs-dialog-title><?php esc_html_e( 'Now streaming', 'plague-dr-suno-publisher' ); ?></h2><button type="button" data-pdrs-dialog-close><?php esc_html_e( 'Close', 'plague-dr-suno-publisher' ); ?></button></div>
            <iframe data-pdrs-dialog-frame src="about:blank" title="<?php esc_attr_e( 'Suno music player', 'plague-dr-suno-publisher' ); ?>" allow="autoplay; encrypted-media; fullscreen; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin"></iframe>
        </dialog>
        <?php
    }

    public static function audio_url( string $url ): string {
        $url = esc_url_raw( trim( $url ), array( 'http', 'https' ) );
        if ( '' === $url || ! wp_http_validate_url( $url ) ) {
            return '';
        }
        $path      = (string) wp_parse_url( $url, PHP_URL_PATH );
        $extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
        return in_array( $extension, array( 'mp3', 'ogg', 'oga', 'wav', 'm4a', 'aac' ), true ) ? $url : '';
    }

    public static function video_url( string $url ): string {
        $url = esc_url_raw( trim( $url ), array( 'http', 'https' ) );
        if ( '' === $url || ! wp_http_validate_url( $url ) ) {
            return '';
        }
        $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
        if ( in_array( $host, array( 'youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com', 'youtu.be', 'vimeo.com', 'www.vimeo.com', 'player.vimeo.com' ), true ) ) {
            return $url;
        }
        $path = (string) wp_parse_url( $url, PHP_URL_PATH );
        return in_array( strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ), array( 'mp4', 'webm', 'mov' ), true ) ? $url : '';
    }

    public static function duration( string $duration ): string {
        $duration = trim( sanitize_text_field( $duration ) );
        return preg_match( '/^\d{1,3}:[0-5]\d$/', $duration ) ? $duration : '';
    }

    public static function linked_track_id( int $song_post_id ): int {
        $linked_id = absint( get_post_meta( $song_post_id, '_pdrs_pdu_track_id', true ) );
        if ( $linked_id && self::THEME_POST_TYPE === get_post_type( $linked_id ) ) {
            return $linked_id;
        }
        $matches = get_posts(
            array(
                'post_type'      => self::THEME_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'trash' ),
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_key'       => '_pdrs_managed_song_id',
                'meta_value'     => $song_post_id,
                'no_found_rows'  => true,
            )
        );
        return empty( $matches ) ? 0 : (int) $matches[0];
    }

    public static function linked_video_id( int $song_post_id ): int {
        $linked_id = absint( get_post_meta( $song_post_id, '_pdrs_pdu_video_id', true ) );
        if ( $linked_id && self::VIDEO_POST_TYPE === get_post_type( $linked_id ) ) {
            return $linked_id;
        }
        $matches = get_posts(
            array(
                'post_type'      => self::VIDEO_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'trash' ),
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_key'       => '_pdrs_managed_song_id',
                'meta_value'     => $song_post_id,
                'no_found_rows'  => true,
            )
        );
        return empty( $matches ) ? 0 : (int) $matches[0];
    }

    private static function sync_video( int $song_post_id, object $song, string $video_url, int $cover_source_id = 0 ) {
        $video_id = self::linked_video_id( $song_post_id );
        if ( $video_id && 'trash' === get_post_status( $video_id ) ) {
            wp_untrash_post( $video_id );
        }
        $status = in_array( $song->post_status, array( 'publish', 'pending', 'draft', 'private' ), true ) ? $song->post_status : 'draft';
        $data   = array(
            'post_type'    => self::VIDEO_POST_TYPE,
            'post_status'  => $status,
            'post_title'   => get_the_title( $song_post_id ),
            'post_content' => (string) $song->post_content,
            'post_excerpt' => wp_trim_words( wp_strip_all_tags( (string) $song->post_content ), 28 ),
            'post_author'  => (int) $song->post_author,
        );
        if ( $video_id ) {
            $data['ID'] = $video_id;
            $result     = wp_update_post( wp_slash( $data ), true );
        } else {
            $result = wp_insert_post( wp_slash( $data ), true );
        }
        if ( is_wp_error( $result ) ) {
            return new WP_Error( 'pdrs_video_sync_failed', __( 'WordPress could not save the linked theme Video Release.', 'plague-dr-suno-publisher' ) );
        }
        $video_id = (int) $result;
        update_post_meta( $song_post_id, '_pdrs_pdu_video_id', $video_id );
        update_post_meta( $video_id, '_pdrs_managed_song_id', $song_post_id );
        self::sync_url_meta( $video_id, 'pdu_video_url', $video_url );
        self::sync_text_meta( $video_id, 'pdu_duration', self::duration( (string) get_post_meta( $song_post_id, '_pdrs_duration', true ) ) );
        self::sync_text_meta( $video_id, 'pdu_runtime_note', (string) get_post_meta( $song_post_id, '_pdrs_video_note', true ) );
        if ( taxonomy_exists( 'pdu_video_type' ) ) {
            wp_set_object_terms( $video_id, array( __( 'Music Video', 'plague-dr-suno-publisher' ) ), 'pdu_video_type', false );
        }
        if ( $cover_source_id && has_post_thumbnail( $cover_source_id ) ) {
            set_post_thumbnail( $video_id, get_post_thumbnail_id( $cover_source_id ) );
        } else {
            self::maybe_import_artwork( $song_post_id, $video_id );
        }
        return $video_id;
    }

    private static function deactivate_linked_track( int $song_post_id ): void {
        $linked_id = self::linked_track_id( $song_post_id );
        if ( $linked_id && ! in_array( get_post_status( $linked_id ), array( 'draft', 'trash' ), true ) ) {
            wp_update_post( array( 'ID' => $linked_id, 'post_status' => 'draft' ) );
        }
    }

    private static function deactivate_linked_video( int $song_post_id ): void {
        $linked_id = self::linked_video_id( $song_post_id );
        if ( $linked_id && ! in_array( get_post_status( $linked_id ), array( 'draft', 'trash' ), true ) ) {
            wp_update_post( array( 'ID' => $linked_id, 'post_status' => 'draft' ) );
        }
    }

    private static function hosted_player_html( int $song_post_id ): string {
        $suno_id = (string) get_post_meta( $song_post_id, '_pdrs_song_id', true );
        if ( ! PDRS_Suno_URL::valid_song_id( $suno_id ) ) {
            return '';
        }
        $details = PDRS_Suno_URL::details( $suno_id );
        $title   = get_the_title( $song_post_id );
        return '<section class="pdrs-pdu-embed" aria-label="' . esc_attr__( 'Hosted music player', 'plague-dr-suno-publisher' ) . '"><p class="pdrs-pdu-embed__eyebrow">' . esc_html__( 'Now streaming', 'plague-dr-suno-publisher' ) . '</p><iframe src="' . esc_url( $details['embed_url'] ) . '" title="' . esc_attr( sprintf( /* translators: %s: song title. */ __( 'Play %s on Suno', 'plague-dr-suno-publisher' ), $title ) ) . '" loading="lazy" allow="autoplay; encrypted-media; fullscreen; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin"></iframe><p><a href="' . esc_url( $details['source_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open this track on Suno', 'plague-dr-suno-publisher' ) . '</a></p></section>';
    }

    private static function sync_text_meta( int $post_id, string $key, string $value ): void {
        $value = sanitize_text_field( $value );
        '' === $value ? delete_post_meta( $post_id, $key ) : update_post_meta( $post_id, $key, $value );
    }

    private static function sync_url_meta( int $post_id, string $key, string $value ): void {
        $value = esc_url_raw( $value );
        '' === $value ? delete_post_meta( $post_id, $key ) : update_post_meta( $post_id, $key, $value );
    }

    private static function maybe_import_artwork( int $song_post_id, int $track_id ): void {
        if ( ! get_post_meta( $song_post_id, '_pdrs_import_artwork', true ) ) {
            return;
        }
        $artwork = PDRS_Metadata::image_url( (string) get_post_meta( $song_post_id, '_pdrs_artwork_url', true ) );
        if ( '' === $artwork || $artwork === get_post_meta( $track_id, '_pdrs_imported_artwork_url', true ) ) {
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_id = media_sideload_image( $artwork, $track_id, get_the_title( $track_id ), 'id' );
        if ( is_wp_error( $attachment_id ) ) {
            self::notice( 'warning', __( 'The Soundtrack was synced, but its external artwork could not be imported. You can set a cover image manually.', 'plague-dr-suno-publisher' ) );
            return;
        }
        set_post_thumbnail( $track_id, (int) $attachment_id );
        update_post_meta( $track_id, '_pdrs_imported_artwork_url', $artwork );
    }

    private static function notice( string $type, string $message ): void {
        set_transient( 'pdrs_notice_' . get_current_user_id(), array( 'type' => $type, 'message' => sanitize_text_field( $message ) ), MINUTE_IN_SECONDS );
    }
}
