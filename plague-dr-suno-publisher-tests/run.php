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
        '_pdrs_position' => 'after',
        '_pdrs_destination_id' => 10,
    );
    $html = PDRS_Renderer::song_html( 101 );
    pdrs_assert( str_contains( $html, 'https://suno.com/embed/' . $uuid ) );
    pdrs_assert( str_contains( $html, 'Plague &lt;script&gt;alert(1)&lt;/script&gt; Song' ) );
    pdrs_assert( ! str_contains( $html, '<script>' ) );
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
    pdrs_same( '<p>Track body</p>', $integration->single_track_player( '<p>Track body</p>' ) );

    delete_post_meta( 300, '_pdrs_audio_url' );
    PDRS_PDU_Integration::sync_song( 300 );
    $fallback = $integration->single_track_player( '<p>Track body</p>' );
    pdrs_assert( str_contains( $fallback, 'https://suno.com/embed/' ) );
    pdrs_assert( str_contains( $fallback, '<p>Track body</p>' ) );
} );

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed ? 1 : 0 );
