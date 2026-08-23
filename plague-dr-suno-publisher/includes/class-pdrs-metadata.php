<?php
/**
 * Optional public metadata lookup for a Suno embed page.
 */

defined( 'ABSPATH' ) || exit;

final class PDRS_Metadata {
    /**
     * Fetch public Open Graph metadata. Failure never blocks manual publishing.
     *
     * @return array{title:string,description:string,image:string}
     */
    public static function fetch( string $song_id ): array {
        $empty = array( 'title' => '', 'description' => '', 'image' => '' );
        if ( ! PDRS_Suno_URL::valid_song_id( $song_id ) ) {
            return $empty;
        }

        $cache_key = 'pdrs_meta_' . str_replace( '-', '', $song_id );
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return wp_parse_args( $cached, $empty );
        }

        $response = wp_safe_remote_get(
            'https://suno.com/embed/' . rawurlencode( $song_id ),
            array(
                'timeout'             => 12,
                'redirection'         => 2,
                'reject_unsafe_urls'  => true,
                'limit_response_size' => 1024 * 1024,
                'user-agent'          => 'Plague Dr Suno Publisher/' . PDRS_VERSION . '; ' . home_url( '/' ),
            )
        );
        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return $empty;
        }

        $body = (string) wp_remote_retrieve_body( $response );
        $data = array(
            'title'       => self::meta( $body, 'og:title' ),
            'description' => self::meta( $body, 'og:description' ),
            'image'       => self::meta( $body, 'og:image' ),
        );
        $data['title']       = preg_replace( '/\s*[|–—-]\s*Suno\s*$/i', '', sanitize_text_field( $data['title'] ) ) ?? '';
        $data['description'] = sanitize_textarea_field( $data['description'] );
        $data['image']       = self::image_url( $data['image'] );
        set_transient( $cache_key, $data, DAY_IN_SECONDS );
        return $data;
    }

    /**
     * Sanitize an HTTPS artwork URL.
     */
    public static function image_url( string $url ): string {
        $url  = esc_url_raw( trim( $url ), array( 'https' ) );
        $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
        if ( '' === $url || 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) || '' === $host || 'localhost' === $host || str_ends_with( $host, '.local' ) ) {
            return '';
        }
        if ( filter_var( $host, FILTER_VALIDATE_IP ) && ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
            return '';
        }
        return $url;
    }

    private static function meta( string $html, string $property ): string {
        if ( class_exists( 'DOMDocument' ) ) {
            $previous = libxml_use_internal_errors( true );
            $document = new DOMDocument();
            if ( $document->loadHTML( $html ) ) {
                foreach ( $document->getElementsByTagName( 'meta' ) as $node ) {
                    $name = $node->getAttribute( 'property' ) ?: $node->getAttribute( 'name' );
                    if ( strtolower( $name ) === strtolower( $property ) ) {
                        $value = $node->getAttribute( 'content' );
                        libxml_clear_errors();
                        libxml_use_internal_errors( $previous );
                        return html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
                    }
                }
            }
            libxml_clear_errors();
            libxml_use_internal_errors( $previous );
        }

        if ( preg_match_all( '/<meta\s+[^>]*>/i', $html, $tags ) ) {
            foreach ( $tags[0] as $tag ) {
                if ( ! preg_match( "/(?:property|name)=[\"']" . preg_quote( $property, '/' ) . "[\"']/i", $tag ) ) {
                    continue;
                }
                if ( preg_match( "/content=[\"']([^\"']*)[\"']/i", $tag, $content ) ) {
                    return html_entity_decode( $content[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
                }
            }
        }
        return '';
    }
}
