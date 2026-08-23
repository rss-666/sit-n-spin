<?php
/**
 * Minimal WordPress compatibility layer for pure unit tests.
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'TRB_VERSION', 'test' );

$GLOBALS['trb_test_options'] = array();
$GLOBALS['trb_test_caps'] = array();
$GLOBALS['trb_test_nonce_valid'] = true;
$GLOBALS['trb_test_posts'] = array();
$GLOBALS['trb_test_meta'] = array();

class WP_Error {
    private string $code;
    private string $message;

    public function __construct( string $code = '', string $message = '' ) {
        $this->code = $code;
        $this->message = $message;
    }

    public function get_error_code(): string { return $this->code; }
    public function get_error_message(): string { return $this->message; }
}

function __( string $text, string $domain = '' ): string { return $text; }
function esc_html__( string $text, string $domain = '' ): string { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
function sanitize_text_field( $value ): string {
    $value = strip_tags( (string) $value );
    return trim( preg_replace( '/[\r\n\t ]+/', ' ', $value ) ?? $value );
}
function sanitize_textarea_field( $value ): string { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ) ?? ''; }
function absint( $value ): int { return abs( (int) $value ); }
function esc_url_raw( $url, $protocols = null ): string {
    $url = filter_var( (string) $url, FILTER_SANITIZE_URL );
    return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : '';
}
function esc_url( $url ): string { return htmlspecialchars( esc_url_raw( $url ), ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_textarea( $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function wp_strip_all_tags( $value, $remove_breaks = false ): string { return trim( strip_tags( (string) $value ) ); }
function wp_kses_post( $value ): string { return strip_tags( (string) $value, '<p><a><strong><em><ul><ol><li><code>' ); }
function wpautop( $value ): string { return '<p>' . str_replace( "\n\n", '</p><p>', (string) $value ) . '</p>'; }
function wp_json_encode( $value, int $flags = 0 ): string { return (string) json_encode( $value, $flags ); }
function wp_parse_args( $args, $defaults = array() ): array { return array_merge( $defaults, is_array( $args ) ? $args : array() ); }
function wp_parse_url( string $url, int $component = -1 ) { return parse_url( $url, $component ); }
function wp_http_validate_url( string $url ) { return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : false; }
function home_url( string $path = '' ): string { return 'https://runbook.example' . $path; }
function wp_salt( string $scheme = 'auth' ): string { return 'unit-test-salt-that-is-never-used-in-production'; }
function get_option( string $name, $default = false ) { return $GLOBALS['trb_test_options'][ $name ] ?? $default; }
function update_option( string $name, $value, $autoload = null ): bool { $GLOBALS['trb_test_options'][ $name ] = $value; return true; }
function current_user_can( string $capability, ...$args ): bool { return ! empty( $GLOBALS['trb_test_caps'][ $capability ] ); }
function check_admin_referer( string $action ): bool {
    if ( empty( $GLOBALS['trb_test_nonce_valid'] ) ) {
        throw new RuntimeException( 'Invalid nonce' );
    }
    return true;
}
function wp_die( $message = '', $title = '', $args = array() ): void { throw new RuntimeException( strip_tags( (string) $message ) ); }
function wp_slash( $value ) { return $value; }
function get_post( int $id ) { return $GLOBALS['trb_test_posts'][ $id ] ?? null; }
function wp_insert_post( array $post, bool $wp_error = false ) {
    $id = count( $GLOBALS['trb_test_posts'] ) + 100;
    $post['ID'] = $id;
    $GLOBALS['trb_test_posts'][ $id ] = (object) $post;
    return $id;
}
function wp_update_post( array $post, bool $wp_error = false ) {
    $id = (int) $post['ID'];
    $GLOBALS['trb_test_posts'][ $id ] = (object) $post;
    return $id;
}
function update_post_meta( int $id, string $key, $value ): bool { $GLOBALS['trb_test_meta'][ $id ][ $key ] = $value; return true; }

require_once dirname( __DIR__ ) . '/the-runbook-briefings/includes/class-trb-url.php';
require_once dirname( __DIR__ ) . '/the-runbook-briefings/includes/class-trb-filter.php';
require_once dirname( __DIR__ ) . '/the-runbook-briefings/includes/class-trb-duplicate-detector.php';
require_once dirname( __DIR__ ) . '/the-runbook-briefings/includes/class-trb-settings.php';
require_once dirname( __DIR__ ) . '/the-runbook-briefings/includes/class-trb-credentials.php';
require_once dirname( __DIR__ ) . '/the-runbook-briefings/includes/interface-trb-ai-provider.php';
require_once dirname( __DIR__ ) . '/the-runbook-briefings/includes/class-trb-prompt-builder.php';
require_once dirname( __DIR__ ) . '/the-runbook-briefings/includes/class-trb-openai-provider.php';
require_once dirname( __DIR__ ) . '/the-runbook-briefings/includes/class-trb-briefing.php';
require_once dirname( __DIR__ ) . '/the-runbook-briefings/includes/class-trb-draft.php';
require_once dirname( __DIR__ ) . '/the-runbook-briefings/includes/class-trb-security.php';
