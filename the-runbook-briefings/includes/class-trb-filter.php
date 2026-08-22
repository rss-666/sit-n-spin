<?php
/**
 * Testable item filtering and excerpt helpers.
 */

defined( 'ABSPATH' ) || exit;

final class TRB_Filter {
    /**
     * Turn a comma/newline-delimited setting into unique terms.
     *
     * @return string[]
     */
    public static function terms( string $value ): array {
        $terms = preg_split( '/[,\r\n]+/', $value ) ?: array();
        $terms = array_map( 'trim', $terms );
        $terms = array_filter( $terms, static fn( string $term ): bool => '' !== $term );
        return array_values( array_unique( $terms ) );
    }

    /**
     * Match at least one configured keyword. An empty list allows all items.
     *
     * @param string[] $keywords Keywords.
     */
    public static function matches_keywords( string $title, string $excerpt, array $keywords ): bool {
        if ( empty( $keywords ) ) {
            return true;
        }

        $haystack = self::lower( $title . ' ' . $excerpt );
        foreach ( $keywords as $keyword ) {
            $keyword = trim( (string) $keyword );
            if ( '' !== $keyword && false !== strpos( $haystack, self::lower( $keyword ) ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Match at least one category, case-insensitively. An empty list allows all.
     *
     * @param string[] $item_categories   Feed item categories.
     * @param string[] $wanted_categories Source filters.
     */
    public static function matches_categories( array $item_categories, array $wanted_categories ): bool {
        if ( empty( $wanted_categories ) ) {
            return true;
        }
        $item_categories = array_map( array( self::class, 'lower' ), array_map( 'strval', $item_categories ) );
        foreach ( $wanted_categories as $wanted ) {
            $wanted = self::lower( trim( (string) $wanted ) );
            foreach ( $item_categories as $category ) {
                if ( '' !== $wanted && ( $category === $wanted || false !== strpos( $category, $wanted ) ) ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Check an item timestamp against an age window and modest future skew.
     */
    public static function is_within_date_range( int $timestamp, int $max_age_days, ?int $now = null ): bool {
        if ( $timestamp <= 0 ) {
            return true; // Feeds frequently omit dates; keep the item for editorial review.
        }
        $now = $now ?? time();
        return $timestamp >= ( $now - ( max( 1, $max_age_days ) * DAY_IN_SECONDS ) )
            && $timestamp <= ( $now + DAY_IN_SECONDS );
    }

    /**
     * Store plain text only and enforce an exact character ceiling.
     */
    public static function excerpt( string $content, int $maximum ): string {
        $content = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $content, true ) : strip_tags( $content );
        $content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $content = preg_replace( '/\s+/u', ' ', $content ) ?? $content;
        $content = trim( $content );
        $maximum = max( 100, min( 5000, $maximum ) );

        if ( self::length( $content ) <= $maximum ) {
            return $content;
        }

        $trimmed = rtrim( self::substring( $content, 0, $maximum - 1 ) );
        return $trimmed . '…';
    }

    private static function lower( string $value ): string {
        return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
    }

    private static function length( string $value ): int {
        return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
    }

    private static function substring( string $value, int $offset, int $length ): string {
        return function_exists( 'mb_substr' ) ? mb_substr( $value, $offset, $length, 'UTF-8' ) : substr( $value, $offset, $length );
    }
}
