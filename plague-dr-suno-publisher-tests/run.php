<?php
/**
 * Dependency-free tests for Plague Dr Suno Publisher.
 */

require __DIR__ . '/bootstrap.php';

$passed = 0;
$failed = 0;

function pdrs_test( string $name, callable $test ): void {
    global $passed, $failed;
    try {
        $test();
        ++$passed;
        echo "PASS {$name}\n";
    } catch ( Throwable $error ) {
        ++$failed;
        echo "FAIL {$name}: {$error->getMessage()}\n";
    }
}

function pdrs_assert( bool $condition, string $message = 'Assertion failed.' ): void {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
}

function pdrs_same( $expected, $actual ): void {
    pdrs_assert( $expected === $actual, 'Expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
}

$uuid = '0b2647f2-07bc-4e51-8822-10d5cca2c495';

pdrs_test( 'Full Suno song URL becomes canonical source and embed URLs', static function () use ( $uuid ): void {
    $result = PDRS_Suno_URL::parse_direct( 'https://suno.com/song/' . $uuid . '?share=1' );
    pdrs_assert( is_array( $result ) );
    pdrs_same( $uuid, $result['song_id'] );
    pdrs_same( 'https://suno.com/song/' . $uuid, $result['source_url'] );
    pdrs_same( 'https://suno.com/embed/' . $uuid, $result['embed_url'] );
} );

pdrs_test( 'Suno embed and legacy host URLs are accepted', static function () use ( $uuid ): void {
    pdrs_assert( is_array( PDRS_Suno_URL::parse_direct( 'suno.com/embed/' . $uuid ) ) );
    pdrs_assert( is_array( PDRS_Suno_URL::parse_direct( 'https://suno.ai/song/' . $uuid ) ) );
} );

pdrs_test( 'Non-Suno and deceptive hosts are rejected', static function () use ( $uuid ): void {
    pdrs_assert( PDRS_Suno_URL::parse_direct( 'https://attacker.example/song/' . $uuid ) instanceof WP_Error );
    pdrs_assert( PDRS_Suno_URL::parse_direct( 'https://suno.com.attacker.example/song/' . $uuid ) instanceof WP_Error );
} );

pdrs_test( 'Malformed song identifiers are rejected', static function (): void {
    pdrs_assert( ! PDRS_Suno_URL::valid_song_id( '../../bad' ) );
    pdrs_assert( PDRS_Suno_URL::parse_direct( 'https://suno.com/song/not-a-song-id' ) instanceof WP_Error );
} );

pdrs_test( 'Artwork sanitizer accepts HTTPS and rejects local or insecure URLs', static function (): void {
    pdrs_same( 'https://cdn2.suno.ai/cover.jpeg', PDRS_Metadata::image_url( 'https://cdn2.suno.ai/cover.jpeg' ) );
    pdrs_same( '', PDRS_Metadata::image_url( 'http://cdn2.suno.ai/cover.jpeg' ) );
    pdrs_same( '', PDRS_Metadata::image_url( 'https://localhost/cover.jpeg' ) );
    pdrs_same( '', PDRS_Metadata::image_url( 'https://127.0.0.1/cover.jpeg' ) );
} );

pdrs_test( 'Player HTML uses a generated Suno embed and escapes editable content', static function () use ( $uuid ): void {
    $GLOBALS['pdrs_posts'][101] = (object) array(
        'ID' => 101,
        'post_type' => PDRS_Plugin::POST_TYPE,
        'post_status' => 'publish',
        'post_title' => 'Plague <script>alert(1)</script> Song',
        'post_content' => 'Credits <script>alert(2)</script>',
    );
    $GLOBALS['pdrs_meta'][101] = array(
        '_pdrs_song_id' => $uuid,
        '_pdrs_artwork_url' => 'https://cdn2.suno.ai/cover.jpeg',
        '_pdrs_artist_name' => 'Featured Artist',
        '_pdrs_lyrics' => "Verse one\n\nSafe refrain <script>alert(3)</script>",
        '_pdrs_position' => 'after',
        '_pdrs_destination_id' => 10,
    );
    $html = PDRS_Renderer::song_html( 101 );
    pdrs_assert( str_contains( $html, 'https://suno.com/embed/' . $uuid ) );
    pdrs_assert( str_contains( $html, 'Plague &lt;script&gt;alert(1)&lt;/script&gt; Song' ) );
    pdrs_assert( ! str_contains( $html, '<script>' ) );
    pdrs_assert( str_contains( $html, '<summary>Lyrics</summary>' ) );
    pdrs_assert( str_contains( $html, '<span>By</span> Featured Artist' ) );
    pdrs_assert( str_contains( $html, 'Safe refrain alert(3)' ) );
    pdrs_assert( str_contains( $html, 'loading="lazy"' ) );
} );

pdrs_test( 'Changing destination moves automatic placement without stale markup', static function (): void {
    $renderer = new PDRS_Renderer();
    $GLOBALS['pdrs_queried_id'] = 10;
    $GLOBALS['pdrs_meta'][101]['_pdrs_destination_id'] = 10;
    $at_first = $renderer->destination_content( '<p>Page ten</p>' );
    pdrs_assert( str_contains( $at_first, 'data-suno-song-id' ) );

    $GLOBALS['pdrs_meta'][101]['_pdrs_destination_id'] = 20;
    $old_destination = $renderer->destination_content( '<p>Page ten</p>' );
    pdrs_same( '<p>Page ten</p>', $old_destination );

    $GLOBALS['pdrs_queried_id'] = 20;
    $new_destination = $renderer->destination_content( '<p>Page twenty</p>' );
    pdrs_assert( str_contains( $new_destination, 'data-suno-song-id' ) );
} );

pdrs_test( 'Draft shortcode stays hidden from visitors but remains previewable to editors', static function (): void {
    $GLOBALS['pdrs_posts'][101]->post_status = 'draft';
    $renderer = new PDRS_Renderer();
    $GLOBALS['pdrs_can_edit'] = false;
    pdrs_same( '', $renderer->shortcode( array( 'id' => 101 ) ) );
    $GLOBALS['pdrs_can_edit'] = true;
    pdrs_assert( str_contains( $renderer->shortcode( array( 'id' => 101 ) ), 'data-suno-song-id' ) );
} );

pdrs_test( 'Theme adapter accepts direct audio files but rejects Suno webpages as audio', static function () use ( $uuid ): void {
    pdrs_same( 'https://plaguedr.test/uploads/track.mp3', PDRS_PDU_Integration::audio_url( 'https://plaguedr.test/uploads/track.mp3' ) );
    pdrs_same( '', PDRS_PDU_Integration::audio_url( 'https://suno.com/song/' . $uuid ) );
    pdrs_same( '4:12', PDRS_PDU_Integration::duration( '4:12' ) );
    pdrs_same( '', PDRS_PDU_Integration::duration( 'four minutes' ) );
} );

pdrs_test( 'Theme Soundtrack sync creates one native entry and updates it without duplicates', static function () use ( $uuid ): void {
    $GLOBALS['pdrs_posts'][300] = (object) array(
        'ID' => 300,
        'post_type' => PDRS_Plugin::POST_TYPE,
        'post_status' => 'publish',
        'post_title' => 'Rain Over First Avenue',
        'post_content' => 'A soundtrack written for the rain sequence.',
        'post_author' => 1,
    );
    $GLOBALS['pdrs_meta'][300] = array(
        '_pdrs_song_id' => $uuid,
        '_pdrs_placement_mode' => PDRS_PDU_Integration::MODE,
        '_pdrs_album' => 'Emerald Rot',
        '_pdrs_duration' => '4:12',
        '_pdrs_artist_name' => 'Plague Dr General',
        '_pdrs_lyrics' => "Rain keeps falling\n\nThe street remembers",
        '_pdrs_audio_url' => 'https://plaguedr.test/uploads/rain.mp3',
        '_pdrs_import_artwork' => 0,
    );
    $first = PDRS_PDU_Integration::sync_song( 300 );
    pdrs_assert( is_int( $first ) && $first > 0 );
    pdrs_same( 'pdu_track', get_post_type( $first ) );
    pdrs_same( 'Emerald Rot', get_post_meta( $first, 'pdu_album', true ) );
    pdrs_same( 'https://plaguedr.test/uploads/rain.mp3', get_post_meta( $first, 'pdu_audio_url', true ) );

    $GLOBALS['pdrs_posts'][300]->post_title = 'Rain Over First Avenue — Remastered';
    $second = PDRS_PDU_Integration::sync_song( 300 );
    pdrs_same( $first, $second );
    pdrs_same( 'Rain Over First Avenue — Remastered', get_the_title( $second ) );
    $native = array_filter( $GLOBALS['pdrs_posts'], static fn( $post ): bool => 'pdu_track' === $post->post_type );
    pdrs_same( 1, count( $native ) );
} );

pdrs_test( 'Theme track uses native audio when present and Suno fallback when absent', static function (): void {
    $track_id = PDRS_PDU_Integration::linked_track_id( 300 );
    $GLOBALS['pdrs_queried_id'] = $track_id;
    $integration = new PDRS_PDU_Integration();
    $native = $integration->single_track_player( '<p>Track body</p>' );
    pdrs_assert( ! str_contains( $native, 'https://suno.com/embed/' ) );
    pdrs_assert( str_contains( $native, '<span>Artist</span> Plague Dr General' ) );
    pdrs_assert( str_contains( $native, '<h2 id="pdrs-lyrics-300">Lyrics</h2>' ) );

    delete_post_meta( 300, '_pdrs_audio_url' );
    PDRS_PDU_Integration::sync_song( 300 );
    $fallback = $integration->single_track_player( '<p>Track body</p>' );
    pdrs_assert( str_contains( $fallback, 'https://suno.com/embed/' ) );
    pdrs_assert( str_contains( $fallback, '<p>Track body</p>' ) );
    pdrs_assert( str_contains( $fallback, 'The street remembers' ) );
} );

pdrs_test( 'YouTube and Vimeo are accepted as video sources but ordinary webpages are rejected', static function (): void {
    pdrs_same( 'https://www.youtube.com/watch?v=abc123', PDRS_PDU_Integration::video_url( 'https://www.youtube.com/watch?v=abc123' ) );
    pdrs_same( 'https://vimeo.com/123456', PDRS_PDU_Integration::video_url( 'https://vimeo.com/123456' ) );
    pdrs_same( '', PDRS_PDU_Integration::video_url( 'https://example.com/watch/123' ) );
} );

pdrs_test( 'Both mode creates one linked Soundtrack and one linked Video with shared lyrics', static function (): void {
    update_post_meta( 300, '_pdrs_placement_mode', PDRS_PDU_Integration::MODE_BOTH );
    update_post_meta( 300, '_pdrs_video_url', 'https://www.youtube.com/watch?v=abc123' );
    update_post_meta( 300, '_pdrs_video_note', 'Official music video' );
    $track_id = PDRS_PDU_Integration::sync_song( 300 );
    $video_id = PDRS_PDU_Integration::linked_video_id( 300 );
    pdrs_assert( is_int( $track_id ) && $track_id > 0 );
    pdrs_assert( $video_id > 0 && 'pdu_video' === get_post_type( $video_id ) );
    pdrs_same( 'https://www.youtube.com/watch?v=abc123', get_post_meta( $video_id, 'pdu_video_url', true ) );
    pdrs_same( 'Official music video', get_post_meta( $video_id, 'pdu_runtime_note', true ) );

    $again = PDRS_PDU_Integration::sync_song( 300 );
    pdrs_same( $track_id, $again );
    pdrs_same( $video_id, PDRS_PDU_Integration::linked_video_id( 300 ) );
    $videos = array_filter( $GLOBALS['pdrs_posts'], static fn( $post ): bool => 'pdu_video' === $post->post_type );
    pdrs_same( 1, count( $videos ) );

    $GLOBALS['pdrs_queried_id'] = $video_id;
    $video_content = ( new PDRS_PDU_Integration() )->single_track_player( '<p>Video description</p>' );
    pdrs_assert( str_contains( $video_content, 'Video description' ) );
    pdrs_assert( str_contains( $video_content, 'The street remembers' ) );
} );

pdrs_test( 'Video-only mode works without a Suno URL and does not create a Soundtrack', static function (): void {
    $GLOBALS['pdrs_posts'][400] = (object) array(
        'ID' => 400,
        'post_type' => PDRS_Plugin::POST_TYPE,
        'post_status' => 'draft',
        'post_title' => 'YouTube Only Release',
        'post_content' => 'Video release notes.',
        'post_author' => 1,
    );
    $GLOBALS['pdrs_meta'][400] = array(
        '_pdrs_placement_mode' => PDRS_PDU_Integration::MODE_VIDEO,
        '_pdrs_video_url' => 'https://youtu.be/abc123',
        '_pdrs_lyrics' => 'One shared lyric record',
        '_pdrs_import_artwork' => 0,
    );
    $result = PDRS_PDU_Integration::sync_song( 400 );
    pdrs_assert( is_int( $result ) && 'pdu_video' === get_post_type( $result ) );
    pdrs_same( 0, PDRS_PDU_Integration::linked_track_id( 400 ) );
    pdrs_same( 'draft', get_post_status( $result ) );
} );

pdrs_test( 'Published native page still renders player and lyrics if managed status is mismatched', static function (): void {
    $track_id = PDRS_PDU_Integration::linked_track_id( 300 );
    $GLOBALS['pdrs_posts'][300]->post_status = 'draft';
    $GLOBALS['pdrs_posts'][ $track_id ]->post_status = 'publish';
    delete_post_meta( $track_id, 'pdu_audio_url' );
    $GLOBALS['pdrs_queried_id'] = $track_id;
    $GLOBALS['pdrs_can_edit'] = false;
    $html = ( new PDRS_PDU_Integration() )->single_track_player( '<p>Public native body</p>' );
    pdrs_assert( str_contains( $html, 'https://suno.com/embed/' ) );
    pdrs_assert( str_contains( $html, 'The street remembers' ) );
} );

pdrs_test( 'Managed native pages receive scoped centered-header and lyrics cue classes', static function (): void {
    $track_id = PDRS_PDU_Integration::linked_track_id( 300 );
    $GLOBALS['pdrs_queried_id'] = $track_id;
    $classes = ( new PDRS_PDU_Integration() )->body_classes( array( 'existing-class' ) );
    pdrs_assert( in_array( 'existing-class', $classes, true ) );
    pdrs_assert( in_array( 'pdrs-managed-native', $classes, true ) );
    pdrs_assert( in_array( 'pdrs-managed-track', $classes, true ) );
    pdrs_assert( in_array( 'pdrs-has-lyrics', $classes, true ) );
    $css = (string) file_get_contents( dirname( __DIR__ ) . '/plague-dr-suno-publisher/assets/player.css' );
    pdrs_assert( str_contains( $css, '.pdrs-pdu-lyrics { border-top: 1px solid #343442; margin-top: 2.5rem; padding-top: 2rem; text-align: center;' ) );
    pdrs_assert( str_contains( $css, '.pdrs-pdu-artist { color: var(--pdu-amber, #c8912a);' ) );
} );

pdrs_test( 'Hosted popout includes keyboard-scrollable lyrics and safe text rendering', static function (): void {
    $integration = new PDRS_PDU_Integration();
    $reflection = new ReflectionClass( $integration );
    $property = $reflection->getProperty( 'has_hosted_tracks' );
    $property->setAccessible( true );
    $property->setValue( $integration, true );
    ob_start();
    $integration->modal();
    $html = (string) ob_get_clean();
    pdrs_assert( str_contains( $html, 'data-pdrs-dialog-lyrics' ) );
    pdrs_assert( str_contains( $html, 'tabindex="0"' ) );
    pdrs_assert( str_contains( $html, 'data-pdrs-dialog-artist' ) );
    pdrs_assert( str_contains( $html, 'data-pdrs-dialog-link' ) );

    $script = (string) file_get_contents( dirname( __DIR__ ) . '/plague-dr-suno-publisher/assets/pdu-integration.js' );
    pdrs_assert( str_contains( $script, "lyrics.textContent = track.lyrics || ''" ) );
    pdrs_assert( ! str_contains( $script, 'lyrics.innerHTML' ) );
} );

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed ? 1 : 0 );
