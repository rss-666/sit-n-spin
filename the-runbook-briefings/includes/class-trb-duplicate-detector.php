<?php
/**
 * Duplicate confidence calculations.
 */

defined( 'ABSPATH' ) || exit;

final class TRB_Duplicate_Detector {
    /**
     * Compare titles using token overlap and normalized edit distance.
     */
    public static function title_similarity( string $first, string $second ): float {
        $first_normalized  = self::normalize_title( $first );
        $second_normalized = self::normalize_title( $second );
        if ( '' === $first_normalized || '' === $second_normalized ) {
            return 0.0;
        }
        if ( $first_normalized === $second_normalized ) {
            return 1.0;
        }

        $first_tokens  = array_values( array_unique( explode( ' ', $first_normalized ) ) );
        $second_tokens = array_values( array_unique( explode( ' ', $second_normalized ) ) );
        $intersection  = count( array_intersect( $first_tokens, $second_tokens ) );
        $union         = count( array_unique( array_merge( $first_tokens, $second_tokens ) ) );
        $jaccard       = $union > 0 ? $intersection / $union : 0.0;

        $maximum_length = max( strlen( $first_normalized ), strlen( $second_normalized ) );
        $edit_score     = $maximum_length > 0
            ? 1 - ( levenshtein( $first_normalized, $second_normalized ) / $maximum_length )
            : 0.0;

        return round( max( $jaccard, $edit_score ), 4 );
    }

    /**
     * Normalize titles while retaining meaningful technical terms.
     */
    public static function normalize_title( string $title ): string {
        $title = html_entity_decode( strip_tags( $title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $title = function_exists( 'mb_strtolower' ) ? mb_strtolower( $title, 'UTF-8' ) : strtolower( $title );
        $title = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $title ) ?? $title;
        $words = preg_split( '/\s+/u', trim( $title ) ) ?: array();
        $stop  = array( 'a', 'an', 'and', 'are', 'at', 'for', 'from', 'in', 'is', 'of', 'on', 'the', 'to', 'with' );
        $words = array_values(
            array_filter(
                $words,
                static fn( string $word ): bool => '' !== $word && ! in_array( $word, $stop, true )
            )
        );
        sort( $words, SORT_STRING );
        return implode( ' ', $words );
    }
}
