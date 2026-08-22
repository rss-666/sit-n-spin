<?php

use PHPUnit\Framework\TestCase;

final class BriefingDraftSecurityTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['trb_test_options'] = array();
        $GLOBALS['trb_test_caps'] = array();
        $GLOBALS['trb_test_nonce_valid'] = true;
        $GLOBALS['trb_test_posts'] = array();
        $GLOBALS['trb_test_meta'] = array();
    }

    public function test_attribution_contains_required_source_title_date_and_link(): void {
        $html = TRB_Briefing::attribution_html( $this->item() );
        self::assertStringContainsString( 'Original source:', $html );
        self::assertStringContainsString( 'Security Desk', $html );
        self::assertStringContainsString( 'Plugin flaw patched', $html );
        self::assertStringContainsString( 'https://source.example/story', $html );
        self::assertStringContainsString( 'January 15, 2026', $html );
        self::assertStringContainsString( 'original Runbook analysis', $html );
    }

    public function test_provider_internal_links_are_limited_to_supplied_candidates(): void {
        $valid = TRB_Briefing::validate_internal_links(
            array(
                array( 'title' => 'Changed', 'url' => 'https://runbook.example/guide', 'reason' => 'Useful' ),
                array( 'title' => 'Invented', 'url' => 'https://runbook.example/invented', 'reason' => 'No' ),
            ),
            array( array( 'title' => 'Approved guide', 'url' => 'https://runbook.example/guide' ) )
        );
        self::assertCount( 1, $valid );
        self::assertSame( 'Approved guide', $valid[0]['title'] );
    }

    public function test_manual_internal_links_reject_external_hosts(): void {
        $links = TRB_Briefing::parse_internal_link_lines(
            "Local | https://runbook.example/guide | Relevant\nExternal | https://attacker.example/page | No"
        );
        self::assertCount( 1, $links );
        self::assertSame( 'https://runbook.example/guide', $links[0]['url'] );
    }

    public function test_editor_action_creates_draft_with_attribution_and_source_meta(): void {
        $GLOBALS['trb_test_options'][ TRB_Settings::OPTION ] = TRB_Settings::defaults();
        $post_id = TRB_Draft::save( $this->item() );
        self::assertIsInt( $post_id );
        self::assertSame( 'draft', $GLOBALS['trb_test_posts'][ $post_id ]->post_status );
        self::assertStringContainsString( 'Original source:', $GLOBALS['trb_test_posts'][ $post_id ]->post_content );
        self::assertSame( 'https://source.example/story', $GLOBALS['trb_test_meta'][ $post_id ]['_trb_source_url'] );
        self::assertSame( 42, $GLOBALS['trb_test_meta'][ $post_id ]['_trb_item_id'] );
    }

    public function test_draft_requires_a_factual_summary(): void {
        $item = $this->item();
        $item->factual_summary = '';
        self::assertInstanceOf( WP_Error::class, TRB_Draft::save( $item ) );
    }

    public function test_capability_helpers_distinguish_managers_and_reviewers(): void {
        $GLOBALS['trb_test_caps'] = array( 'review_runbook_briefings' => true, 'manage_options' => false );
        self::assertTrue( TRB_Security::can_review() );
        self::assertFalse( TRB_Security::can_manage() );
    }

    public function test_review_action_rejects_missing_capability_before_nonce(): void {
        $this->expectException( RuntimeException::class );
        TRB_Security::require_review( 'action_1' );
    }

    public function test_review_action_rejects_invalid_nonce(): void {
        $GLOBALS['trb_test_caps']['review_runbook_briefings'] = true;
        $GLOBALS['trb_test_nonce_valid'] = false;
        $this->expectException( RuntimeException::class );
        TRB_Security::require_review( 'action_1' );
    }

    private function item(): object {
        return (object) array(
            'id' => 42,
            'source_name' => 'Security Desk',
            'title' => 'Plugin flaw patched',
            'source_url' => 'https://source.example/story',
            'canonical_url' => 'https://source.example/story',
            'published_at' => '2026-01-15 12:00:00',
            'suggested_headline' => 'What site owners should know about the plugin patch',
            'factual_summary' => 'The vendor released a security update.',
            'why_matters' => 'Unpatched installations may require prompt attention.',
            'practical_implications' => 'Test and deploy the supported update.',
            'internal_links' => json_encode( array( array( 'title' => 'Update guide', 'url' => 'https://runbook.example/guide', 'reason' => 'Deployment steps' ) ) ),
            'wp_post_id' => 0,
        );
    }
}
