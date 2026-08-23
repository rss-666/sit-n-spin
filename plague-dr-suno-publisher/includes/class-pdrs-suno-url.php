<?php
/**
 * Strict Suno URL parsing and short-link resolution.
 */

defined( 'ABSPATH' ) || exit;

final class PDRS_Suno_URL {
    private const UUID_PATTERN = '[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}';

    /**
     * Parse a direct public Suno song or embed URL without making a request.
     *
     * @return array{song_id:string,source_url:string,embed_url:string}|WP_Error
     */
    public static function parse_direct( string $url ) {
        $url = self::prepare( $url );
        if ( is_wp_error( $url ) ) {
            return $url;
        }

        $path = (string) wp_parse_url( $url, PHP_URL_PATH );
        if ( preg_match( '#^/(?:song|embed)/(' . self::UUID_PATTERN . ')/?$#i', $path, $matches ) ) {
            return self::details( strtolower( $matches[1] ) );
        }

        return new WP_Error(
            'pdrs_unsupported_suno_url',
            __( 'Paste a full public Suno song link. Short share links are resolved when the form is submitted.', 'plague-dr-suno-publisher' )
        );
    }

    /**
     * Parse a direct URL or safely resolve a Suno /s/ short share link.
     *
     * @return array{song_id:string,source_url:string,embed_url:string}|WP_Error
     */
    public static function resolve( string $url ) {
        $prepared = self::prepare( $url );
        if ( is_wp_error( $prepared ) ) {
            return $prepared;
        }

        $direct = self::parse_direct( $prepared );
        if ( ! is_wp_error( $direct ) ) {
            return $direct;
        }

        $path = (string) wp_parse_url( $prepared, PHP_URL_PATH );
        if ( ! preg_match( '#^/s/[A-Za-z0-9_-]+/?$#', $path ) ) {
            return $direct;
        }

        $current = $prepared;
        for ( $request = 0; $request < 4; ++$request ) {
            $response = wp_safe_remote_get(
                $current,
                array(
                    'timeout'             => 10,
                    'redirection'         => 0,
                    'reject_unsafe_urls'  => true,
                    'limit_response_size' => 1024 * 1024,
                    'user-agent'          => 'Plague Dr Suno Publisher/' . PDRS_VERSION . '; ' . home_url( '/' ),
                )
            );
            if ( is_wp_error( $response ) ) {
                return new WP_Error( 'pdrs_suno_unreachable', __( 'The Suno share link could not be reached. Open it in your browser and paste the full /song/ URL.', 'plague-dr-suno-publisher' ) );
            }

            $status   = (int) wp_remote_retrieve_response_code( $response );
            $location = (string) wp_remote_retrieve_header( $response, 'location' );
            if ( $status >= 300 && $status < 400 && '' !== $location ) {
                $current = self::absolute_location( $current, $location );
                $next    = self::prepare( $current );
                if ( is_wp_error( $next ) ) {
                    return $next;
                }
                $current = $next;
                $direct  = self::parse_direct( $current );
                if ( ! is_wp_error( $direct ) ) {
                    return $direct;
                }
                continue;
            }

            $body = (string) wp_remote_retrieve_body( $response );
            if ( preg_match( '#https?://(?:www\.)?suno\.(?:com|ai)/(?:song|embed)/(' . self::UUID_PATTERN . ')#i', html_entity_decode( $body, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), $matches ) ) {
                return self::details( strtolower( $matches[1] ) );
            }
            break;
        }

        return new WP_Error(
            'pdrs_short_link_unresolved',
            __( 'Suno did not reveal the song behind that short link. Open the link and copy the full suno.com/song/… address instead.', 'plague-dr-suno-publisher' )
        );
    }

    /**
     * Validate a song identifier read from protected post meta.
     */
    public static function valid_song_id( string $song_id ): bool {
        return 1 === preg_match( '/^' . self::UUID_PATTERN . '$/i', $song_id );
    }

    /**
     * @return array{song_id:string,source_url:string,embed_url:string}
     */
    public static function details( string $song_id ): array {
        return array(
            'song_id'    => $song_id,
            'source_url' => 'https://suno.com/song/' . $song_id,
            'embed_url'  => 'https://suno.com/embed/' . $song_id,
        );
    }

    /**
     * @return string|WP_Error
     */
    private static function prepare( string $url ) {
        $url = trim( html_entity_decode( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
        if ( '' === $url ) {
            return new WP_Error( 'pdrs_empty_url', __( 'Enter a public Suno song URL.', 'plague-dr-suno-publisher' ) );
        }
        if ( ! preg_match( '#^https?://#i', $url ) ) {
            $url = 'https://' . ltrim( $url, '/' );
        }
        $url   = esc_url_raw( $url, array( 'http', 'https' ) );
        $host  = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
        $port  = wp_parse_url( $url, PHP_URL_PORT );
        $hosts = array( 'suno.com', 'www.suno.com', 'suno.ai', 'www.suno.ai' );

        if ( '' === $url || ! in_array( $host, $hosts, true ) || null !== $port ) {
            return new WP_Error( 'pdrs_invalid_host', __( 'Only public links from suno.com are accepted.', 'plague-dr-suno-publisher' ) );
        }
        return $url;
    }

    private static function absolute_location( string $current, string $location ): string {
        if ( preg_match( '#^https?://#i', $location ) ) {
            return $location;
        }
        $scheme = (string) wp_parse_url( $current, PHP_URL_SCHEME );
        $host   = (string) wp_parse_url( $current, PHP_URL_HOST );
        if ( str_starts_with( $location, '//' ) ) {
            return $scheme . ':' . $location;
        }
        if ( str_starts_with( $location, '/' ) ) {
            return $scheme . '://' . $host . $location;
        }
        $path      = (string) wp_parse_url( $current, PHP_URL_PATH );
        $directory = rtrim( str_replace( '\\', '/', dirname( $path ) ), '/' );
        return $scheme . '://' . $host . $directory . '/' . ltrim( $location, '/' );
    }
}
