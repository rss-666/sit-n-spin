<?php
/**
 * Briefing sanitization, links, and rendered content.
 */

defined( 'ABSPATH' ) || exit;

final class TRB_Briefing {
    /**
     * Sanitize generated fields and retain only offered internal links.
     *
     * @param array<string,mixed> $generated  Provider result.
     * @param array<int,array<string,string>> $candidates Offered links.
     * @return array<string,mixed>
     */
    public static function sanitize_generated( array $generated, array $candidates ): array {
        return array(
            'suggested_headline'     => sanitize_text_field( (string) ( $generated['suggested_headline'] ?? '' ) ),
            'factual_summary'        => wp_kses_post( (string) ( $generated['factual_summary'] ?? '' ) ),
            'why_matters'            => wp_kses_post( (string) ( $generated['why_matters'] ?? '' ) ),
            'practical_implications' => wp_kses_post( (string) ( $generated['practical_implications'] ?? '' ) ),
            'internal_links'         => self::validate_internal_links( (array) ( $generated['internal_links'] ?? array() ), $candidates ),
        );
    }

    /**
     * Accept provider links only when the exact URL was supplied as a candidate.
     *
     * @param array<int,mixed> $links Links.
     * @param array<int,array<string,string>> $candidates Candidates.
     * @return array<int,array<string,string>>
     */
    public static function validate_internal_links( array $links, array $candidates ): array {
        $allowed = array();
        foreach ( $candidates as $candidate ) {
            if ( ! empty( $candidate['url'] ) ) {
                $allowed[ (string) $candidate['url'] ] = (string) ( $candidate['title'] ?? '' );
            }
        }

        $valid = array();
        foreach ( array_slice( $links, 0, 3 ) as $link ) {
            if ( ! is_array( $link ) ) {
                continue;
            }
            $url = esc_url_raw( (string) ( $link['url'] ?? '' ) );
            if ( '' === $url || ! isset( $allowed[ $url ] ) ) {
                continue;
            }
            $valid[] = array(
                'title'  => $allowed[ $url ],
                'url'    => $url,
                'reason' => sanitize_text_field( (string) ( $link['reason'] ?? '' ) ),
            );
        }
        return $valid;
    }

    /**
     * Parse editable lines in "Title | URL | Reason" form, accepting local URLs only.
     *
     * @return array<int,array<string,string>>
     */
    public static function parse_internal_link_lines( string $lines ): array {
        $links     = array();
        $home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
        foreach ( preg_split( '/\r\n|\r|\n/', $lines ) ?: array() as $line ) {
            $parts = array_map( 'trim', explode( '|', $line, 3 ) );
            if ( count( $parts ) < 2 ) {
                continue;
            }
            $url  = esc_url_raw( $parts[1], array( 'http', 'https' ) );
            $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
            if ( '' === $url || '' === $host || $host !== $home_host ) {
                continue;
            }
            $links[] = array(
                'title'  => sanitize_text_field( $parts[0] ),
                'url'    => $url,
                'reason' => sanitize_text_field( $parts[2] ?? '' ),
            );
            if ( count( $links ) >= 3 ) {
                break;
            }
        }
        return $links;
    }

    /**
     * Convert stored JSON links to editable lines.
     */
    public static function internal_link_lines( string $json ): string {
        $links = json_decode( $json, true );
        if ( ! is_array( $links ) ) {
            return '';
        }
        $lines = array();
        foreach ( $links as $link ) {
            if ( is_array( $link ) && ! empty( $link['url'] ) ) {
                $lines[] = (string) ( $link['title'] ?? '' ) . ' | ' . (string) $link['url'] . ' | ' . (string) ( $link['reason'] ?? '' );
            }
        }
        return implode( "\n", $lines );
    }

    /**
     * Visible, source-controlled attribution block.
     */
    public static function attribution_html( object $item ): string {
        $source_name = esc_html( (string) $item->source_name );
        $title       = esc_html( (string) $item->title );
        $url         = esc_url( (string) $item->source_url );
        $date        = '';
        if ( ! empty( $item->published_at ) ) {
            $timestamp = strtotime( (string) $item->published_at . ' UTC' );
            if ( $timestamp ) {
                $date = gmdate( 'F j, Y', $timestamp );
            }
        }

        $date_html = '' !== $date ? ' ' . sprintf( /* translators: %s: publication date. */ esc_html__( 'Published %s.', 'the-runbook-briefings' ), esc_html( $date ) ) : '';
        return '<aside class="runbook-source-attribution"><p><strong>'
            . esc_html__( 'Original source:', 'the-runbook-briefings' )
            . '</strong> ' . $source_name . ' — <a href="' . $url . '" rel="noopener noreferrer">' . $title . '</a>.'
            . $date_html . ' ' . esc_html__( 'This briefing summarizes source facts and adds original Runbook analysis.', 'the-runbook-briefings' )
            . '</p></aside>';
    }

    /**
     * Build final post HTML with facts, analysis, implications, links, and attribution.
     */
    public static function content_html( object $item ): string {
        $sections = array();
        if ( '' !== trim( (string) $item->factual_summary ) ) {
            $sections[] = '<h2>' . esc_html__( 'What happened', 'the-runbook-briefings' ) . '</h2>' . wpautop( wp_kses_post( (string) $item->factual_summary ) );
        }
        if ( '' !== trim( (string) $item->why_matters ) ) {
            $sections[] = '<h2>' . esc_html__( 'Why this matters', 'the-runbook-briefings' ) . '</h2>' . wpautop( wp_kses_post( (string) $item->why_matters ) );
        }
        if ( '' !== trim( (string) $item->practical_implications ) ) {
            $sections[] = '<h2>' . esc_html__( 'Practical implications for site owners', 'the-runbook-briefings' ) . '</h2>' . wpautop( wp_kses_post( (string) $item->practical_implications ) );
        }

        $links = json_decode( (string) $item->internal_links, true );
        if ( is_array( $links ) && ! empty( $links ) ) {
            $list = '';
            foreach ( $links as $link ) {
                if ( ! is_array( $link ) || empty( $link['url'] ) ) {
                    continue;
                }
                $reason = empty( $link['reason'] ) ? '' : ' — ' . esc_html( (string) $link['reason'] );
                $list  .= '<li><a href="' . esc_url( (string) $link['url'] ) . '">' . esc_html( (string) ( $link['title'] ?? $link['url'] ) ) . '</a>' . $reason . '</li>';
            }
            if ( '' !== $list ) {
                $sections[] = '<h2>' . esc_html__( 'Related reading', 'the-runbook-briefings' ) . '</h2><ul>' . $list . '</ul>';
            }
        }
        $sections[] = self::attribution_html( $item );
        return implode( "\n\n", $sections );
    }
}
