<?php
/**
 * Front-end player cards, destination injection, and shortcodes.
 */

defined( 'ABSPATH' ) || exit;

final class PDRS_Renderer {
    private bool $injecting = false;

    public function hooks(): void {
        add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
        add_filter( 'the_content', array( $this, 'destination_content' ), 20 );
        add_shortcode( 'plague_dr_song', array( $this, 'shortcode' ) );
        add_shortcode( 'pdr_suno_song', array( $this, 'shortcode' ) );
    }

    public function assets(): void {
        wp_enqueue_style( 'pdrs-player', PDRS_PLUGIN_URL . 'assets/player.css', array(), PDRS_VERSION );
    }

    /**
     * Place active songs before or after the currently selected destination.
     */
    public function destination_content( string $content ): string {
        if ( $this->injecting || is_admin() || is_feed() || ! is_singular() ) {
            return $content;
        }
        $destination_id = get_queried_object_id();
        if ( $destination_id <= 0 || get_the_ID() !== $destination_id ) {
            return $content;
        }

        $songs = get_posts(
            array(
                'post_type'              => PDRS_Plugin::POST_TYPE,
                'post_status'            => 'publish',
                'posts_per_page'         => -1,
                'meta_key'               => '_pdrs_destination_id',
                'meta_value'             => $destination_id,
                'orderby'                => array( 'menu_order' => 'ASC', 'date' => 'ASC' ),
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_term_cache' => false,
            )
        );
        if ( empty( $songs ) ) {
            return $content;
        }

        $this->injecting = true;
        $before          = '';
        $after           = '';
        foreach ( $songs as $song ) {
            $card = self::song_html( (int) $song->ID );
            if ( 'before' === get_post_meta( $song->ID, '_pdrs_position', true ) ) {
                $before .= $card;
            } else {
                $after .= $card;
            }
        }
        $this->injecting = false;

        return self::group( $before ) . $content . self::group( $after );
    }

    /**
     * Shortcode: [plague_dr_song id="123"].
     *
     * @param array<string,mixed> $attributes Attributes.
     */
    public function shortcode( array $attributes = array() ): string {
        $attributes = shortcode_atts( array( 'id' => 0 ), $attributes, 'plague_dr_song' );
        $song_id    = absint( $attributes['id'] );
        $song       = get_post( $song_id );
        if ( ! $song || PDRS_Plugin::POST_TYPE !== $song->post_type ) {
            return '';
        }
        if ( 'publish' !== $song->post_status && ! current_user_can( 'edit_post', $song_id ) ) {
            return '';
        }
        return self::group( self::song_html( $song_id ) );
    }

    /**
     * Render a song using only trusted post fields and validated protected meta.
     */
    public static function song_html( int $post_id ): string {
        $song = get_post( $post_id );
        if ( ! $song || PDRS_Plugin::POST_TYPE !== $song->post_type ) {
            return '';
        }
        $song_id = (string) get_post_meta( $post_id, '_pdrs_song_id', true );
        if ( ! PDRS_Suno_URL::valid_song_id( $song_id ) ) {
            return '';
        }

        $details     = PDRS_Suno_URL::details( strtolower( $song_id ) );
        $artwork     = PDRS_Metadata::image_url( (string) get_post_meta( $post_id, '_pdrs_artwork_url', true ) );
        $title       = get_the_title( $post_id );
        $player_title = sprintf( /* translators: %s: song title. */ __( 'Play %s on Suno', 'plague-dr-suno-publisher' ), $title );

        ob_start();
        ?>
        <article class="pdrs-song" data-suno-song-id="<?php echo esc_attr( $song_id ); ?>">
            <?php if ( $artwork ) : ?>
                <figure class="pdrs-song__artwork"><img src="<?php echo esc_url( $artwork ); ?>" alt="" loading="lazy" decoding="async"></figure>
            <?php endif; ?>
            <div class="pdrs-song__body">
                <h2 class="pdrs-song__title"><?php echo esc_html( $title ); ?></h2>
                <?php echo self::artist_html( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php if ( '' !== trim( (string) $song->post_content ) ) : ?>
                    <div class="pdrs-song__description"><?php echo wpautop( wp_kses_post( $song->post_content ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php endif; ?>
                <div class="pdrs-song__player">
                    <iframe src="<?php echo esc_url( $details['embed_url'] ); ?>" title="<?php echo esc_attr( $player_title ); ?>" loading="lazy" allow="autoplay; encrypted-media; fullscreen; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin"></iframe>
                </div>
                <?php echo self::lyrics_html( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <p class="pdrs-song__source"><a href="<?php echo esc_url( $details['source_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Listen on Suno', 'plague-dr-suno-publisher' ); ?></a></p>
            </div>
        </article>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render a dedicated artist credit instead of mixing it into body copy.
     */
    public static function artist_html( int $post_id, bool $theme_layout = false ): string {
        $artist = trim( sanitize_text_field( (string) get_post_meta( $post_id, '_pdrs_artist_name', true ) ) );
        if ( '' === $artist ) {
            return '';
        }
        if ( $theme_layout ) {
            return '<p class="pdrs-pdu-artist"><span>' . esc_html__( 'Artist', 'plague-dr-suno-publisher' ) . '</span> ' . esc_html( $artist ) . '</p>';
        }
        return '<p class="pdrs-song__artist"><span>' . esc_html__( 'By', 'plague-dr-suno-publisher' ) . '</span> ' . esc_html( $artist ) . '</p>';
    }

    /**
     * Render editor-controlled lyrics separately from descriptions/excerpts.
     */
    public static function lyrics_html( int $post_id, bool $theme_layout = false ): string {
        $lyrics = trim( (string) get_post_meta( $post_id, '_pdrs_lyrics', true ) );
        if ( '' === $lyrics ) {
            return '';
        }
        $body = wpautop( wp_kses_post( $lyrics ) );
        if ( $theme_layout ) {
            return '<section class="pdrs-pdu-lyrics" aria-labelledby="pdrs-lyrics-' . esc_attr( (string) $post_id ) . '"><p class="pdrs-pdu-embed__eyebrow">' . esc_html__( 'Words from the record', 'plague-dr-suno-publisher' ) . '</p><h2 id="pdrs-lyrics-' . esc_attr( (string) $post_id ) . '">' . esc_html__( 'Lyrics', 'plague-dr-suno-publisher' ) . '</h2><div class="pdrs-pdu-lyrics__body">' . $body . '</div></section>';
        }
        return '<details class="pdrs-song__lyrics"><summary>' . esc_html__( 'Lyrics', 'plague-dr-suno-publisher' ) . '</summary><div class="pdrs-song__lyrics-body">' . $body . '</div></details>';
    }

    private static function group( string $songs ): string {
        if ( '' === $songs ) {
            return '';
        }
        return '<section class="pdrs-song-group" aria-label="' . esc_attr__( 'Plague Dr music', 'plague-dr-suno-publisher' ) . '">' . $songs . '</section>';
    }
}
