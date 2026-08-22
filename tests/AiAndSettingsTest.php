<?php

use PHPUnit\Framework\TestCase;

final class AiAndSettingsTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['trb_test_options'] = array();
    }

    public function test_prompt_contains_source_context_quality_rules_and_json_contract(): void {
        $prompt = TRB_Prompt_Builder::system() . "\n" . TRB_Prompt_Builder::user(
            array(
                'source_name' => 'Security Desk',
                'title' => 'Plugin flaw patched',
                'source_url' => 'https://source.example/story',
                'excerpt' => 'The vendor released version 2.1.',
                'tone' => 'technical',
                'internal_link_candidates' => array( array( 'title' => 'Patch guide', 'url' => 'https://runbook.example/patch-guide' ) ),
            )
        );
        self::assertStringContainsString( 'Never invent', $prompt );
        self::assertStringContainsString( 'Security Desk', $prompt );
        self::assertStringContainsString( 'factual_summary', $prompt );
        self::assertStringContainsString( 'https://runbook.example/patch-guide', $prompt );
        self::assertStringContainsString( 'Do not include source attribution', $prompt );
    }

    public function test_openai_response_parser_accepts_structured_result(): void {
        $provider = new TRB_OpenAI_Provider();
        $result = $provider->parse(
            json_encode(
                array(
                    'choices' => array(
                        array(
                            'message' => array(
                                'content' => json_encode(
                                    array(
                                        'suggested_headline' => 'A useful headline',
                                        'factual_summary' => 'Supported facts.',
                                        'why_matters' => 'Original analysis.',
                                        'practical_implications' => 'Update after testing.',
                                        'internal_links' => array(),
                                    )
                                ),
                            ),
                        ),
                    ),
                )
            )
        );
        self::assertIsArray( $result );
        self::assertSame( 'Supported facts.', $result['factual_summary'] );
    }

    public function test_openai_response_parser_rejects_bad_json_and_missing_fields(): void {
        $provider = new TRB_OpenAI_Provider();
        self::assertInstanceOf( WP_Error::class, $provider->parse( '{"choices":[{"message":{"content":"not json"}}]}' ) );
        self::assertInstanceOf(
            WP_Error::class,
            $provider->parse( '{"choices":[{"message":{"content":"{\\"suggested_headline\\":\\"Only one\\"}"}}]}' )
        );
    }

    public function test_settings_never_allow_publish_status_and_preserve_encrypted_key(): void {
        $GLOBALS['trb_test_options'][ TRB_Settings::OPTION ] = array( 'api_key_encrypted' => 'encrypted-value' );
        $settings = TRB_Settings::sanitize(
            array(
                'ai_provider' => 'openai',
                'model' => 'test-model',
                'default_tone' => 'technical',
                'cron_frequency' => 'hourly',
                'default_post_status' => 'publish',
            )
        );
        self::assertSame( 'draft', $settings['default_post_status'] );
        self::assertSame( 'encrypted-value', $settings['api_key_encrypted'] );
    }

    public function test_credentials_round_trip_without_plaintext_storage(): void {
        $encrypted = TRB_Credentials::encrypt( 'sk-unit-test-value' );
        self::assertNotInstanceOf( WP_Error::class, $encrypted );
        self::assertNotSame( 'sk-unit-test-value', $encrypted );
        self::assertStringNotContainsString( 'sk-unit-test-value', $encrypted );
        self::assertSame( 'sk-unit-test-value', TRB_Credentials::decrypt( $encrypted ) );
    }
}
