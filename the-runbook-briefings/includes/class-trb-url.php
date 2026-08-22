<?php
/**
 * URL validation and canonicalization.
 */

defined( 'ABSPATH' ) || exit;

final class TRB_URL {
    /**
     * Tracking query parameters that do not identify an article.
     *
     * @var string[]
     */
    private const TRACKING_PARAMETERS = array(
        'fbclid',
        'gclid',
        'dclid',
        'msclkid',
        'mc_cid',
        'mc_eid',
        '_ga',
        '_gl',
        'ref',
        'source',
    );

    /**
     * Normalize an article URL for duplicate detection.
     */
    public static function normalize( string $url ): string {
        $url = trim( html_entity_decode( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
        if ( '' === $url ) {
            return '';
        }

        $parts = parse_url( $url );
        if ( false === $parts || empty( $parts['host'] ) ) {
            return '';
        }

        $scheme = strtolower( (string) ( $parts['scheme'] ?? 'https' ) );
        if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
            return '';
        }

        $host = strtolower( rtrim( (string) $parts['host'], '.' ) );
        if ( function_exists( 'idn_to_ascii' ) ) {
            $ascii_host = idn_to_ascii( $host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 );
            if ( false !== $ascii_host ) {
                $host = strtolower( $ascii_host );
            }
        }
        $host = preg_replace( '/^www\./i', '', $host ) ?? $host;

        // HTTP and HTTPS versions of a story are equivalent for duplicate detection.
        $normalized = 'https://' . $host;
        $port       = isset( $parts['port'] ) ? (int) $parts['port'] : 0;
        if ( $port && 80 !== $port && 443 !== $port ) {
            $normalized .= ':' . $port;
        }

        $path = self::normalize_path( (string) ( $parts['path'] ?? '/' ) );
        $normalized .= $path;

        $query = self::normalize_query( (string) ( $parts['query'] ?? '' ) );
        if ( '' !== $query ) {
            $normalized .= '?' . $query;
        }

        return $normalized;
    }

    /**
     * Hash a normalized URL for indexed comparisons.
     */
    public static function hash( string $url ): string {
        $normalized = self::normalize( $url );
        return '' === $normalized ? '' : hash( 'sha256', $normalized );
    }

    /**
     * Validate a feed URL using WordPress's SSRF-aware validator.
     *
     * @return true|WP_Error
     */
    public static function validate_feed_url( string $url ) {
        $url = esc_url_raw( trim( $url ), array( 'http', 'https' ) );
        if ( '' === $url || ! wp_http_validate_url( $url ) ) {
            return new WP_Error( 'trb_invalid_feed_url', __( 'Enter a valid public HTTP or HTTPS feed URL.', 'the-runbook-briefings' ) );
        }

        $host = (string) wp_parse_url( $url, PHP_URL_HOST );
        if ( '' === $host || 'localhost' === strtolower( $host ) || str_ends_with( strtolower( $host ), '.local' ) ) {
            return new WP_Error( 'trb_unsafe_feed_url', __( 'Local and private feed addresses are not allowed.', 'the-runbook-briefings' ) );
        }

        return true;
    }

    private static function normalize_path( string $path ): string {
        $path = preg_replace( '#/+#', '/', '/' . ltrim( $path, '/' ) ) ?? '/';
        $segments = array();
        foreach ( explode( '/', $path ) as $segment ) {
            if ( '' === $segment || '.' === $segment ) {
                continue;
            }
            if ( '..' === $segment ) {
                array_pop( $segments );
                continue;
            }
            $segment    = preg_replace_callback(
                '/%[0-9A-Fa-f]{2}/',
                static function ( array $match ): string {
                    $character = chr( hexdec( substr( $match[0], 1 ) ) );
                    return preg_match( '/[A-Za-z0-9\-._~]/', $character ) ? $character : strtoupper( $match[0] );
                },
                $segment
            ) ?? $segment;
            $segments[] = $segment;
        }

        $normalized = '/' . implode( '/', $segments );
        return '/' === $normalized ? '/' : rtrim( $normalized, '/' );
    }

    private static function normalize_query( string $query ): string {
        if ( '' === $query ) {
            return '';
        }

        parse_str( $query, $parameters );
        foreach ( array_keys( $parameters ) as $key ) {
            $lower_key = strtolower( (string) $key );
            if ( str_starts_with( $lower_key, 'utm_' ) || in_array( $lower_key, self::TRACKING_PARAMETERS, true ) ) {
                unset( $parameters[ $key ] );
            }
        }
        ksort( $parameters, SORT_STRING );

        return http_build_query( $parameters, '', '&', PHP_QUERY_RFC3986 );
    }
}
