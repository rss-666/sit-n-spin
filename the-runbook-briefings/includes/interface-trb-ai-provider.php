<?php
/**
 * AI provider contract.
 */

defined( 'ABSPATH' ) || exit;

interface TRB_AI_Provider {
    /**
     * Whether the provider has all required credentials.
     */
    public function is_configured(): bool;

    /**
     * Generate structured briefing fields.
     *
     * @param array<string,mixed> $input Source and internal-link context.
     * @return array<string,mixed>|WP_Error
     */
    public function generate( array $input );
}
