<?php

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'PDRS_VERSION', 'test' );
define( 'PDRS_PLUGIN_URL', 'https://example.test/wp-content/plugins/plague-dr-suno-publisher/' );
define( 'DAY_IN_SECONDS', 86400 );

class WP_Error {
    public function __construct( private string $code = '', private string $message = '' ) {}
    public function get_error_code(): string { return $this->code; }
    public function get_error_message(): string { return $this->message; }
}

class PDRS_Plugin {
    public const POST_TYPE = 'pdr_suno_song';
}

$GLOBALS['pdrs_posts'] = array();
$GLOBALS['pdrs_meta'] = array();
$GLOBALS['pdrs_queried_id'] = 0;
$GLOBALS['pdrs_can_edit'] = false;

function __( string $text, string $domain = '' ): string { return $text; }
function esc_attr__( string $text, string $domain = '' ): string { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( string $text, string $domain = '' ): string { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_html_e( string $text, string $domain = '' ): void { echo esc_html__( $text, $domain ); }
function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
function sanitize_text_field( $value ): string { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ): string { return trim( strip_tags( (string) $value ) ); }
function esc_url_raw( $url, $protocols = null ): string {
    $url = filter_var( (string) $url, FILTER_SANITIZE_URL );
    return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : '';
}
function esc_url( $url ): string { return htmlspecialchars( esc_url_raw( $url ), ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function wp_parse_url( string $url, int $component = -1 ) { return parse_url( $url, $component ); }
function wp_http_validate_url( string $url ) { return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : false; }
function wp_parse_args( $args, $defaults = array() ): array { return array_merge( $defaults, is_array( $args ) ? $args : array() ); }
function wp_kses_post( $value ): string { return strip_tags( (string) $value, '<p><strong><em><ul><ol><li><a>' ); }
function wpautop( $value ): string { return '<p>' . str_replace( "\n\n", '</p><p>', (string) $value ) . '</p>'; }
function get_transient( string $key ) { return false; }
function set_transient( string $key, $value, int $expiration ): bool { return true; }
function home_url( string $path = '' ): string { return 'https://plaguedr.test' . $path; }
function get_post( int $id ) { return $GLOBALS['pdrs_posts'][ $id ] ?? null; }
function get_post_meta( int $id, string $key, bool $single = false ) { return $GLOBALS['pdrs_meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( int $id, string $key, $value ): bool { $GLOBALS['pdrs_meta'][ $id ][ $key ] = $value; return true; }
function delete_post_meta( int $id, string $key ): bool { unset( $GLOBALS['pdrs_meta'][ $id ][ $key ] ); return true; }
function get_post_type( $post = null ): string { $object = is_object( $post ) ? $post : get_post( (int) $post ); return $object ? (string) $object->post_type : ''; }
function get_post_status( $post = null ): string { $object = is_object( $post ) ? $post : get_post( (int) $post ); return $object ? (string) $object->post_status : ''; }
function wp_insert_post( array $post, bool $wp_error = false ) {
    $id = empty( $GLOBALS['pdrs_posts'] ) ? 100 : max( array_keys( $GLOBALS['pdrs_posts'] ) ) + 1;
    $post['ID'] = $id;
    $post += array( 'post_content' => '', 'post_author' => 1, 'post_excerpt' => '' );
    $GLOBALS['pdrs_posts'][ $id ] = (object) $post;
    return $id;
}
function wp_update_post( array $post, bool $wp_error = false ) {
    $id = (int) $post['ID'];
    $current = (array) ( $GLOBALS['pdrs_posts'][ $id ] ?? (object) array( 'ID' => $id ) );
    $GLOBALS['pdrs_posts'][ $id ] = (object) array_merge( $current, $post );
    return $id;
}
function wp_slash( $value ) { return $value; }
function wp_strip_all_tags( $value ): string { return strip_tags( (string) $value ); }
function wp_trim_words( string $text, int $number = 55, string $more = null ): string { return implode( ' ', array_slice( preg_split( '/\s+/', trim( $text ) ) ?: array(), 0, $number ) ); }
function taxonomy_exists( string $taxonomy ): bool { return false; }
function wp_set_object_terms( int $object_id, $terms, string $taxonomy, bool $append = false ): array { return array(); }
function wp_is_post_revision( int $id ): bool { return false; }
function get_permalink( $post = 0 ): string { $id = is_object( $post ) ? $post->ID : (int) $post; return 'https://plaguedr.test/soundtracks/' . $id . '/'; }
function get_the_title( $post ): string {
    $object = is_object( $post ) ? $post : get_post( (int) $post );
    return $object ? (string) $object->post_title : '';
}
function get_posts( array $args ): array {
    $result = array();
    foreach ( $GLOBALS['pdrs_posts'] as $post ) {
        if ( ( $args['post_type'] ?? '' ) !== $post->post_type || 'publish' !== $post->post_status ) {
            continue;
        }
        if ( isset( $args['meta_value'] ) && (int) get_post_meta( $post->ID, (string) $args['meta_key'], true ) !== (int) $args['meta_value'] ) {
            continue;
        }
        $result[] = $post;
    }
    return $result;
}
function is_admin(): bool { return false; }
function is_feed(): bool { return false; }
function post_type_exists( string $post_type ): bool { return in_array( $post_type, array( PDRS_Plugin::POST_TYPE, 'pdu_track' ), true ); }
function is_singular(): bool { return true; }
function get_queried_object_id(): int { return (int) $GLOBALS['pdrs_queried_id']; }
function get_the_ID(): int { return (int) $GLOBALS['pdrs_queried_id']; }
function current_user_can( string $capability, ...$args ): bool { return (bool) $GLOBALS['pdrs_can_edit']; }
function shortcode_atts( array $defaults, array $attributes, string $shortcode = '' ): array { return array_merge( $defaults, $attributes ); }
function absint( $value ): int { return abs( (int) $value ); }

require_once dirname( __DIR__ ) . '/plague-dr-suno-publisher/includes/class-pdrs-suno-url.php';
require_once dirname( __DIR__ ) . '/plague-dr-suno-publisher/includes/class-pdrs-metadata.php';
require_once dirname( __DIR__ ) . '/plague-dr-suno-publisher/includes/class-pdrs-renderer.php';
require_once dirname( __DIR__ ) . '/plague-dr-suno-publisher/includes/class-pdrs-pdu-integration.php';
