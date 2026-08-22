<?php
/**
 * Factual, source-aware AI prompt construction.
 */

defined( 'ABSPATH' ) || exit;

final class TRB_Prompt_Builder {
    public static function system(): string {
        return implode(
            "\n",
            array(
                'You are an experienced WordPress and hosting security briefing editor.',
                'Use only the supplied source metadata and short excerpt for source facts.',
                'Never invent or infer unsupported prices, dates, statistics, quotes, vulnerabilities, features, or claims.',
                'Do not reconstruct, spin, or reproduce the source article. Write a compact original briefing.',
                'Clearly separate the factual summary from original Runbook analysis.',
                'If evidence is insufficient, say so plainly rather than filling gaps.',
                'Return valid JSON only, with no markdown fence or commentary outside the JSON object.',
            )
        );
    }

    /**
     * @param array<string,mixed> $input Context.
     */
    public static function user( array $input ): string {
        $payload = array(
            'source' => array(
                'name'             => (string) ( $input['source_name'] ?? '' ),
                'article_title'    => (string) ( $input['title'] ?? '' ),
                'publication_date' => (string) ( $input['published_at'] ?? '' ),
                'url'              => (string) ( $input['source_url'] ?? '' ),
                'short_excerpt'    => (string) ( $input['excerpt'] ?? '' ),
            ),
            'editorial_preferences' => array(
                'tone' => (string) ( $input['tone'] ?? 'technical' ),
                'audience' => 'WordPress site owners, hosting customers, and technical decision makers',
            ),
            'allowed_internal_links' => array_values( (array) ( $input['internal_link_candidates'] ?? array() ) ),
        );

        return "Create an original source-attributed briefing from this input:\n"
            . self::encode( $payload )
            . "\n\nReturn exactly this JSON shape:\n"
            . '{"suggested_headline":"...","factual_summary":"...","why_matters":"...","practical_implications":"...","internal_links":[{"title":"exact allowed title","url":"exact allowed URL","reason":"brief relevance"}]}'
            . "\nUse zero to three internal links and only select exact URLs from allowed_internal_links. Do not include source attribution in these fields; the application adds it independently.";
    }

    /**
     * @param mixed $value Value.
     */
    private static function encode( $value ): string {
        if ( function_exists( 'wp_json_encode' ) ) {
            return (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        }
        return (string) json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    }
}
