<?php

use PHPUnit\Framework\TestCase;

final class DuplicateDetectorTest extends TestCase {
    public function test_identical_titles_score_one_despite_stop_words_and_punctuation(): void {
        self::assertSame(
            1.0,
            TRB_Duplicate_Detector::title_similarity(
                'The Critical WordPress Plugin Vulnerability',
                'Critical WordPress plugin vulnerability!'
            )
        );
    }

    public function test_reordered_technical_title_is_a_strong_match(): void {
        self::assertGreaterThanOrEqual(
            0.86,
            TRB_Duplicate_Detector::title_similarity(
                'Patch released for Acme WordPress plugin vulnerability',
                'Acme vulnerability: WordPress plugin patch released'
            )
        );
    }

    public function test_unrelated_titles_have_low_similarity(): void {
        self::assertLessThan(
            0.5,
            TRB_Duplicate_Detector::title_similarity(
                'Managed WordPress host launches backup feature',
                'Linux kernel networking performance benchmark'
            )
        );
    }
}
