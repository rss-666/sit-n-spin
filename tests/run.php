<?php
/**
 * Dependency-free focused test runner for environments without PHPUnit.
 */

require __DIR__ . '/bootstrap.php';

$passed = 0;
$failed = 0;

/**
 * @param callable():void $callback Test callback.
 */
function trb_test( string $name, callable $callback ): void {
    global $passed, $failed;
    try {
        $callback();
        ++$passed;
        echo "PASS {$name}\n";
    } catch ( Throwable $exception ) {
        ++$failed;
        echo "FAIL {$name}: {$exception->getMessage()}\n";
    }
}

function trb_assert( bool $condition, string $message = 'Assertion failed.' ): void {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
}

function trb_same( $expected, $actual ): void {
    trb_assert( $expected === $actual, 'Expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . '.' );
}

trb_test( 'URL normalization removes tracking and canonicalizes components', static function (): void {
    trb_same( 'https://example.com/news/story?a=one&b=2', TRB_URL::normalize( 'http://WWW.Example.com:80/news//story/?utm_source=email&b=2&a=one#comments' ) );
} );
trb_test( 'Equivalent URL variants hash identically', static function (): void {
    trb_same( TRB_URL::hash( 'http://example.com/story/' ), TRB_URL::hash( 'https://www.example.com/story?fbclid=abc' ) );
} );
trb_test( 'Unsafe URL schemes do not normalize', static function (): void {
    trb_same( '', TRB_URL::normalize( 'file:///etc/passwd' ) );
} );
trb_test( 'Keyword filtering is case-insensitive', static function (): void {
    trb_assert( TRB_Filter::matches_keywords( 'WordPress security update', '', array( 'wordpress' ) ) );
    trb_assert( ! TRB_Filter::matches_keywords( 'Company picnic', '', array( 'vulnerability' ) ) );
} );
trb_test( 'Category filtering handles partial labels', static function (): void {
    trb_assert( TRB_Filter::matches_categories( array( 'WordPress Security' ), array( 'security' ) ) );
    trb_assert( ! TRB_Filter::matches_categories( array( 'Business' ), array( 'security' ) ) );
} );
trb_test( 'Date filtering rejects old and far-future stories', static function (): void {
    $now = 2_000_000_000;
    trb_assert( TRB_Filter::is_within_date_range( $now - DAY_IN_SECONDS, 30, $now ) );
    trb_assert( ! TRB_Filter::is_within_date_range( $now - 31 * DAY_IN_SECONDS, 30, $now ) );
    trb_assert( ! TRB_Filter::is_within_date_range( $now + 2 * DAY_IN_SECONDS, 30, $now ) );
} );
trb_test( 'Excerpt is plain text with an exact ceiling', static function (): void {
    $excerpt = TRB_Filter::excerpt( '<p>' . str_repeat( 'a', 200 ) . '</p>', 100 );
    trb_same( 100, mb_strlen( $excerpt ) );
    trb_assert( str_ends_with( $excerpt, '…' ) && ! str_contains( $excerpt, '<p>' ) );
} );
trb_test( 'Reordered technical titles are probable duplicates', static function (): void {
    $score = TRB_Duplicate_Detector::title_similarity( 'Patch released for Acme WordPress plugin vulnerability', 'Acme vulnerability: WordPress plugin patch released' );
    trb_assert( $score >= 0.86, 'Similarity was ' . $score );
} );
trb_test( 'Unrelated titles have low duplicate confidence', static function (): void {
    $score = TRB_Duplicate_Detector::title_similarity( 'Managed WordPress host launches backup feature', 'Linux kernel networking performance benchmark' );
    trb_assert( $score < 0.5, 'Similarity was ' . $score );
} );
trb_test( 'Prompt contains quality and structured-output constraints', static function (): void {
    $prompt = TRB_Prompt_Builder::system() . TRB_Prompt_Builder::user( array( 'source_name' => 'Security Desk', 'title' => 'Patch', 'excerpt' => 'Version 2.1 released.' ) );
    trb_assert( str_contains( $prompt, 'Never invent' ) );
    trb_assert( str_contains( $prompt, 'Security Desk' ) );
    trb_assert( str_contains( $prompt, 'factual_summary' ) );
} );
trb_test( 'Provider parser accepts a complete structured result', static function (): void {
    $provider = new TRB_OpenAI_Provider();
    $content = json_encode( array( 'suggested_headline' => 'Headline', 'factual_summary' => 'Facts', 'why_matters' => 'Analysis', 'practical_implications' => 'Act', 'internal_links' => array() ) );
    $result = $provider->parse( json_encode( array( 'choices' => array( array( 'message' => array( 'content' => $content ) ) ) ) ) );
    trb_assert( is_array( $result ) );
    trb_same( 'Facts', $result['factual_summary'] );
} );
trb_test( 'Provider parser safely rejects malformed output', static function (): void {
    $provider = new TRB_OpenAI_Provider();
    $result = $provider->parse( '{"choices":[{"message":{"content":"not json"}}]}' );
    trb_assert( $result instanceof WP_Error );
} );
trb_test( 'Settings do not accept publish status', static function (): void {
    $settings = TRB_Settings::sanitize( array( 'ai_provider' => 'openai', 'model' => 'model', 'default_tone' => 'technical', 'cron_frequency' => 'hourly', 'default_post_status' => 'publish' ) );
    trb_same( 'draft', $settings['default_post_status'] );
} );
trb_test( 'Credential encryption round-trips without plaintext storage', static function (): void {
    $encrypted = TRB_Credentials::encrypt( 'sk-unit-test-value' );
    trb_assert( is_string( $encrypted ) );
    trb_assert( ! str_contains( $encrypted, 'sk-unit-test-value' ) );
    trb_same( 'sk-unit-test-value', TRB_Credentials::decrypt( $encrypted ) );
} );
trb_test( 'Generated internal links are limited to offered candidates', static function (): void {
    $links = TRB_Briefing::validate_internal_links(
        array( array( 'title' => 'Changed', 'url' => 'https://runbook.example/guide', 'reason' => 'Useful' ), array( 'title' => 'Invented', 'url' => 'https://runbook.example/nope' ) ),
        array( array( 'title' => 'Approved', 'url' => 'https://runbook.example/guide' ) )
    );
    trb_same( 1, count( $links ) );
    trb_same( 'Approved', $links[0]['title'] );
} );
trb_test( 'Manual internal links reject external hosts', static function (): void {
    $links = TRB_Briefing::parse_internal_link_lines( "Local | https://runbook.example/guide | Yes\nExternal | https://attacker.example/ | No" );
    trb_same( 1, count( $links ) );
} );
trb_test( 'Attribution contains source title date and URL', static function (): void {
    $item = (object) array( 'source_name' => 'Security Desk', 'title' => 'Plugin flaw patched', 'source_url' => 'https://source.example/story', 'published_at' => '2026-01-15 12:00:00' );
    $html = TRB_Briefing::attribution_html( $item );
    foreach ( array( 'Original source:', 'Security Desk', 'Plugin flaw patched', 'https://source.example/story', 'January 15, 2026' ) as $expected ) {
        trb_assert( str_contains( $html, $expected ), 'Missing attribution value: ' . $expected );
    }
} );
trb_test( 'Draft creation includes attribution and protected source metadata', static function (): void {
    $GLOBALS['trb_test_options'][ TRB_Settings::OPTION ] = TRB_Settings::defaults();
    $GLOBALS['trb_test_posts'] = array();
    $item = (object) array(
        'id' => 42, 'source_name' => 'Security Desk', 'title' => 'Plugin flaw patched', 'source_url' => 'https://source.example/story',
        'canonical_url' => 'https://source.example/story', 'published_at' => '2026-01-15 12:00:00', 'suggested_headline' => 'Patch briefing',
        'factual_summary' => 'The vendor released a patch.', 'why_matters' => 'Updates reduce exposure.', 'practical_implications' => 'Test and update.',
        'internal_links' => '[]', 'wp_post_id' => 0,
    );
    $post_id = TRB_Draft::save( $item );
    trb_assert( is_int( $post_id ) );
    trb_same( 'draft', $GLOBALS['trb_test_posts'][ $post_id ]->post_status );
    trb_assert( str_contains( $GLOBALS['trb_test_posts'][ $post_id ]->post_content, 'Original source:' ) );
    trb_same( 'https://source.example/story', $GLOBALS['trb_test_meta'][ $post_id ]['_trb_source_url'] );
} );
trb_test( 'Draft creation refuses an empty factual summary', static function (): void {
    $item = (object) array( 'suggested_headline' => 'Title', 'title' => 'Source title', 'factual_summary' => '', 'wp_post_id' => 0 );
    trb_assert( TRB_Draft::save( $item ) instanceof WP_Error );
} );
trb_test( 'Capability helpers separate review and management', static function (): void {
    $GLOBALS['trb_test_caps'] = array( 'review_runbook_briefings' => true, 'manage_options' => false );
    trb_assert( TRB_Security::can_review() );
    trb_assert( ! TRB_Security::can_manage() );
} );
trb_test( 'Review action rejects invalid nonce', static function (): void {
    $GLOBALS['trb_test_caps']['review_runbook_briefings'] = true;
    $GLOBALS['trb_test_nonce_valid'] = false;
    try {
        TRB_Security::require_review( 'action' );
    } catch ( RuntimeException $exception ) {
        $GLOBALS['trb_test_nonce_valid'] = true;
        return;
    }
    throw new RuntimeException( 'Invalid nonce was accepted.' );
} );

require_once dirname( __DIR__ ) . '/the-runbook-briefings/includes/class-trb-admin.php';
trb_test( 'Brand-new item renders empty briefing textareas without a fatal', static function (): void {
    $reflection = new ReflectionClass( TRB_Admin::class );
    $admin      = $reflection->newInstanceWithoutConstructor();
    $method     = $reflection->getMethod( 'editor_textarea' );
    $method->setAccessible( true );
    ob_start();
    $method->invoke( $admin, 'factual_summary', 'Factual summary', null, 'Source-supported facts.' );
    $html = (string) ob_get_clean();
    trb_assert( str_contains( $html, 'name="briefing[factual_summary]"' ) );
    trb_assert( str_contains( $html, '<textarea' ) );
} );

// Minimal collaborators for focused single-source importer tests.
$GLOBALS['trb_test_transients'] = array();
function get_transient( string $key ) { return $GLOBALS['trb_test_transients'][ $key ] ?? false; }
function set_transient( string $key, $value, int $expiration ): bool { $GLOBALS['trb_test_transients'][ $key ] = $value; return true; }
function delete_transient( string $key ): bool { unset( $GLOBALS['trb_test_transients'][ $key ] ); return true; }
function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool { return true; }
function remove_filter( string $hook, $callback, int $priority = 10 ): bool { return true; }
function fetch_feed( string $url ) { return new TRB_Test_Empty_Feed(); }

final class TRB_Test_Empty_Feed {
    public function get_items( int $start = 0, int $end = 0 ): array { return array(); }
}
final class TRB_Source_Repository {
    public int $successful_source_id = 0;
    public function all( bool $enabled_only = false ): array { return array(); }
    public function record_success( int $id ): void { $this->successful_source_id = $id; }
    public function record_error( int $id, string $message ): void {}
}
final class TRB_Item_Repository {}
final class TRB_Logger {
    public static array $events = array();
    public static function log( string $level, string $event, string $message, array $context = array(), ?int $source_id = null, ?int $item_id = null ): void { self::$events[] = $event; }
    public static function prune(): void {}
}

require_once dirname( __DIR__ ) . '/the-runbook-briefings/includes/class-trb-importer.php';

trb_test( 'Fetch Now runs one selected source and releases its overlap lock', static function (): void {
    $sources = new TRB_Source_Repository();
    $items   = new TRB_Item_Repository();
    $source  = (object) array(
        'id' => 77,
        'name' => 'Disabled test feed',
        'feed_url' => 'https://example.com/feed/',
        'enabled' => 0,
        'keywords' => '',
        'categories' => '',
    );
    $result = ( new TRB_Importer( $sources, $items ) )->run_source( $source );
    trb_assert( is_array( $result ) );
    trb_same( 1, $result['sources'] );
    trb_same( 77, $sources->successful_source_id );
    trb_assert( ! get_transient( 'trb_import_lock' ) );
    trb_assert( in_array( 'manual_source_import_complete', TRB_Logger::$events, true ) );
} );

trb_test( 'Fetch Now refuses to overlap an active importer run', static function (): void {
    set_transient( 'trb_import_lock', 1, MINUTE_IN_SECONDS );
    $result = ( new TRB_Importer( new TRB_Source_Repository(), new TRB_Item_Repository() ) )->run_source( (object) array( 'id' => 88 ) );
    trb_assert( $result instanceof WP_Error );
    trb_same( 'trb_import_locked', $result->get_error_code() );
    delete_transient( 'trb_import_lock' );
} );

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
