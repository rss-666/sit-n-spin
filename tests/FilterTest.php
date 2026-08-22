<?php

use PHPUnit\Framework\TestCase;

final class FilterTest extends TestCase {
    public function test_parses_and_deduplicates_filter_terms(): void {
        self::assertSame( array( 'WordPress', 'hosting', 'security' ), TRB_Filter::terms( "WordPress, hosting\nsecurity,hosting" ) );
    }

    public function test_keyword_filter_allows_empty_filters_and_matches_case_insensitively(): void {
        self::assertTrue( TRB_Filter::matches_keywords( 'Any story', '', array() ) );
        self::assertTrue( TRB_Filter::matches_keywords( 'WordPress security release', '', array( 'wordpress' ) ) );
        self::assertFalse( TRB_Filter::matches_keywords( 'Generic company news', '', array( 'vulnerability' ) ) );
    }

    public function test_category_filter_matches_labels_and_rejects_missing_categories(): void {
        self::assertTrue( TRB_Filter::matches_categories( array( 'WordPress Security' ), array( 'security' ) ) );
        self::assertFalse( TRB_Filter::matches_categories( array( 'Business' ), array( 'security' ) ) );
    }

    public function test_date_filter_handles_old_future_and_missing_dates(): void {
        $now = 2_000_000_000;
        self::assertTrue( TRB_Filter::is_within_date_range( 0, 30, $now ) );
        self::assertTrue( TRB_Filter::is_within_date_range( $now - DAY_IN_SECONDS, 30, $now ) );
        self::assertFalse( TRB_Filter::is_within_date_range( $now - ( 31 * DAY_IN_SECONDS ), 30, $now ) );
        self::assertFalse( TRB_Filter::is_within_date_range( $now + ( 2 * DAY_IN_SECONDS ), 30, $now ) );
    }

    public function test_excerpt_is_plain_text_and_honors_character_limit(): void {
        $excerpt = TRB_Filter::excerpt( '<p>' . str_repeat( 'a', 200 ) . '</p>', 100 );
        self::assertSame( 100, mb_strlen( $excerpt ) );
        self::assertStringEndsWith( '…', $excerpt );
        self::assertStringNotContainsString( '<p>', $excerpt );
    }
}
