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
    }

    public function quick_add_page(): void {
        if ( ! current_user_can( 'edit_pages' ) ) {
            wp_die( esc_html__( 'You are not allowed to add Plague Dr songs.', 'plague-dr-suno-publisher' ), '', array( 'response' => 403 ) );
        }
        ?>
        <div class="wrap pdrs-admin">
            <h1><?php esc_html_e( 'Plague Dr Suno Publisher', 'plague-dr-suno-publisher' ); ?></h1>
            <p class="pdrs-lede"><?php esc_html_e( 'Paste one of your public Suno song links, choose any destination, and place the hosted player without rewriting that page’s saved content.', 'plague-dr-suno-publisher' ); ?></p>
            <div class="pdrs-layout">
                <form class="pdrs-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="pdrs_add_song">
                    <?php wp_nonce_field( 'pdrs_add_song' ); ?>
                    <h2><?php esc_html_e( 'Add a Suno song', 'plague-dr-suno-publisher' ); ?></h2>
                    <p><label for="pdrs-url"><strong><?php esc_html_e( 'Public Suno URL', 'plague-dr-suno-publisher' ); ?></strong></label><br>
                        <input id="pdrs-url" class="large-text" type="url" name="suno_url" required placeholder="https://suno.com/song/…"></p>
                    <p class="description"><?php esc_html_e( 'Full /song/ and /embed/ links work best. The plugin also attempts to resolve Suno /s/ share links.', 'plague-dr-suno-publisher' ); ?></p>
                    <p><label for="pdrs-title"><strong><?php esc_html_e( 'Song title', 'plague-dr-suno-publisher' ); ?></strong></label><br>
                        <input id="pdrs-title" class="large-text" type="text" name="song_title" placeholder="<?php esc_attr_e( 'Leave blank to fetch the public Suno title', 'plague-dr-suno-publisher' ); ?>"></p>
                    <p><label for="pdrs-description"><strong><?php esc_html_e( 'Description, credits, or lyrics', 'plague-dr-suno-publisher' ); ?></strong></label><br>
                        <textarea id="pdrs-description" class="large-text" name="song_description" rows="6" placeholder="<?php esc_attr_e( 'Optional; you can edit this later', 'plague-dr-suno-publisher' ); ?>"></textarea></p>
                    <p><label for="pdrs-artwork"><strong><?php esc_html_e( 'Artwork URL', 'plague-dr-suno-publisher' ); ?></strong></label><br>
                        <input id="pdrs-artwork" class="large-text" type="url" name="artwork_url" placeholder="<?php esc_attr_e( 'Optional; Suno artwork is fetched when available', 'plague-dr-suno-publisher' ); ?>"></p>
                    <div class="pdrs-two-column">
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
                    <section class="pdrs-card"><h2><?php esc_html_e( 'Change the destination anytime', 'plague-dr-suno-publisher' ); ?></h2><p><?php esc_html_e( 'Open a song under Plague Dr Music → All Songs and choose a different page or post in the Suno Placement box. The player moves dynamically; the plugin does not leave stale embed code behind.', 'plague-dr-suno-publisher' ); ?></p></section>
                    <section class="pdrs-card"><h2><?php esc_html_e( 'Manual placement', 'plague-dr-suno-publisher' ); ?></h2><p><?php esc_html_e( 'Every song also has a shortcode:', 'plague-dr-suno-publisher' ); ?></p><code>[plague_dr_song id="123"]</code><p><?php esc_html_e( 'Use it in a Shortcode block when you want a second placement or precise position inside a layout.', 'plague-dr-suno-publisher' ); ?></p></section>
                    <section class="pdrs-card pdrs-safety"><h2><?php esc_html_e( 'How media is handled', 'plague-dr-suno-publisher' ); ?></h2><p><?php esc_html_e( 'Audio remains hosted by Suno. This plugin stores the song ID, public link, editable text, optional artwork URL, and destination. It does not download or rehost the song.', 'plague-dr-suno-publisher' ); ?></p></section>
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

        $resolved = PDRS_Suno_URL::resolve( sanitize_text_field( wp_unslash( (string) ( $_POST['suno_url'] ?? '' ) ) ) );
        if ( is_wp_error( $resolved ) ) {
            $this->set_notice( 'error', $resolved->get_error_message() );
            $this->redirect_add();
        }
        $duplicate = $this->find_song( $resolved['song_id'] );
        if ( $duplicate ) {
            $this->set_notice( 'warning', __( 'That Suno song is already managed. Its edit screen has been opened.', 'plague-dr-suno-publisher' ) );
            $this->redirect_edit( $duplicate );
        }

        $destination_id = absint( $_POST['destination_id'] ?? 0 );
        if ( $destination_id && ! $this->valid_destination( $destination_id ) ) {
            $this->set_notice( 'error', __( 'You cannot edit the selected destination.', 'plague-dr-suno-publisher' ) );
            $this->redirect_add();
        }

        $metadata    = PDRS_Metadata::fetch( $resolved['song_id'] );
        $title       = sanitize_text_field( wp_unslash( (string) ( $_POST['song_title'] ?? '' ) ) );
        $description = wp_kses_post( wp_unslash( (string) ( $_POST['song_description'] ?? '' ) ) );
        $artwork     = PDRS_Metadata::image_url( wp_unslash( (string) ( $_POST['artwork_url'] ?? '' ) ) );
        if ( '' === $title ) {
            $title = $metadata['title'] ?: sprintf( /* translators: %s: shortened song ID. */ __( 'Suno Song %s', 'plague-dr-suno-publisher' ), substr( $resolved['song_id'], 0, 8 ) );
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

        update_post_meta( $post_id, '_pdrs_song_id', $resolved['song_id'] );
        update_post_meta( $post_id, '_pdrs_source_url', $resolved['source_url'] );
        update_post_meta( $post_id, '_pdrs_destination_id', $destination_id );
        update_post_meta( $post_id, '_pdrs_position', 'before' === ( $_POST['position'] ?? '' ) ? 'before' : 'after' );
        update_post_meta( $post_id, '_pdrs_artwork_url', $artwork );

        $message = 'publish' === $status
            ? __( 'Song added and activated on its selected destination.', 'plague-dr-suno-publisher' )
            : __( 'Song saved as a draft. Review it and publish when ready.', 'plague-dr-suno-publisher' );
        $this->set_notice( 'success', $message );
        $this->redirect_edit( (int) $post_id );
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
    }

    public function meta_box_html( WP_Post $post ): void {
        $source      = (string) get_post_meta( $post->ID, '_pdrs_source_url', true );
        $destination = (int) get_post_meta( $post->ID, '_pdrs_destination_id', true );
        $position    = (string) get_post_meta( $post->ID, '_pdrs_position', true );
        $artwork     = (string) get_post_meta( $post->ID, '_pdrs_artwork_url', true );
        wp_nonce_field( 'pdrs_save_song_' . $post->ID, 'pdrs_song_nonce' );
        ?>
        <p><label for="pdrs-song-url"><strong><?php esc_html_e( 'Suno song URL', 'plague-dr-suno-publisher' ); ?></strong></label><input id="pdrs-song-url" class="widefat" type="url" name="pdrs_song_url" value="<?php echo esc_attr( $source ); ?>"></p>
        <p><label for="pdrs-song-destination"><strong><?php esc_html_e( 'Destination', 'plague-dr-suno-publisher' ); ?></strong></label><?php $this->destination_select( 'pdrs_destination_id', $destination, 'pdrs-song-destination' ); ?></p>
        <p><label for="pdrs-song-position"><strong><?php esc_html_e( 'Placement', 'plague-dr-suno-publisher' ); ?></strong></label><select class="widefat" id="pdrs-song-position" name="pdrs_position"><option value="after" <?php selected( $position, 'after' ); ?>><?php esc_html_e( 'After page content', 'plague-dr-suno-publisher' ); ?></option><option value="before" <?php selected( $position, 'before' ); ?>><?php esc_html_e( 'Before page content', 'plague-dr-suno-publisher' ); ?></option></select></p>
        <p><label for="pdrs-song-artwork"><strong><?php esc_html_e( 'Artwork URL', 'plague-dr-suno-publisher' ); ?></strong></label><input id="pdrs-song-artwork" class="widefat" type="url" name="pdrs_artwork_url" value="<?php echo esc_attr( $artwork ); ?>"></p>
        <p><strong><?php esc_html_e( 'Shortcode', 'plague-dr-suno-publisher' ); ?></strong><br><code>[plague_dr_song id="<?php echo esc_attr( (string) $post->ID ); ?>"]</code></p>
        <?php if ( $source ) : ?><p><a href="<?php echo esc_url( $source ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open song on Suno', 'plague-dr-suno-publisher' ); ?></a></p><?php endif; ?>
        <?php
    }

    public function save_song( int $post_id, WP_Post $post ): void {
        if ( ! isset( $_POST['pdrs_song_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pdrs_song_nonce'] ) ), 'pdrs_save_song_' . $post_id ) ) {
            return;
        }
        if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

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
        }

        $destination_id = absint( $_POST['pdrs_destination_id'] ?? 0 );
        if ( 0 === $destination_id || $this->valid_destination( $destination_id ) ) {
            update_post_meta( $post_id, '_pdrs_destination_id', $destination_id );
        } else {
            $this->set_notice( 'error', __( 'The selected destination was not saved because you cannot edit it.', 'plague-dr-suno-publisher' ) );
        }
        update_post_meta( $post_id, '_pdrs_position', 'before' === ( $_POST['pdrs_position'] ?? '' ) ? 'before' : 'after' );
        update_post_meta( $post_id, '_pdrs_artwork_url', PDRS_Metadata::image_url( wp_unslash( (string) ( $_POST['pdrs_artwork_url'] ?? '' ) ) ) );
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

    private function set_notice( string $type, string $message ): void {
        set_transient( 'pdrs_notice_' . get_current_user_id(), array( 'type' => $type, 'message' => sanitize_text_field( $message ) ), MINUTE_IN_SECONDS );
    }

    private function redirect_add(): void {
        wp_safe_redirect( admin_url( 'admin.php?page=plague-dr-music' ) );
        exit;
    }

    private function redirect_edit( int $post_id ): void {
        wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $post_id ) );
        exit;
    }
}
