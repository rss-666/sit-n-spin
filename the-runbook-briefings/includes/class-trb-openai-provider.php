<?php
/**
 * OpenAI-compatible chat-completions provider.
 */

defined( 'ABSPATH' ) || exit;

final class TRB_OpenAI_Provider implements TRB_AI_Provider {
    private string $api_key;
    private string $model;
    private int $timeout;
    private int $retry_limit;

    public function __construct() {
        $this->api_key    = TRB_Credentials::decrypt( (string) TRB_Settings::get( 'api_key_encrypted' ) );
        $this->model      = (string) TRB_Settings::get( 'model' );
        $this->timeout    = (int) TRB_Settings::get( 'request_timeout' );
        $this->retry_limit = (int) TRB_Settings::get( 'retry_limit' );
    }

    public function is_configured(): bool {
        return '' !== $this->api_key && '' !== $this->model;
    }

    public function generate( array $input ) {
        if ( ! $this->is_configured() ) {
            return new WP_Error(
                'trb_ai_not_configured',
                __( 'No AI API key is configured. You can still write and save the briefing manually.', 'the-runbook-briefings' )
            );
        }
        if ( ! $this->within_rate_limit() ) {
            return new WP_Error( 'trb_ai_rate_limit', __( 'The local generation limit was reached. Wait one minute and try again.', 'the-runbook-briefings' ) );
        }

        $endpoint = (string) apply_filters( 'trb_openai_endpoint', 'https://api.openai.com/v1/chat/completions' );
        if ( ! wp_http_validate_url( $endpoint ) || 'https' !== wp_parse_url( $endpoint, PHP_URL_SCHEME ) ) {
            return new WP_Error( 'trb_ai_endpoint', __( 'The configured provider endpoint is not a safe HTTPS URL.', 'the-runbook-briefings' ) );
        }

        $body = array(
            'model'           => $this->model,
            'temperature'     => 0.2,
            'response_format' => array( 'type' => 'json_object' ),
            'messages'        => array(
                array( 'role' => 'system', 'content' => TRB_Prompt_Builder::system() ),
                array( 'role' => 'user', 'content' => TRB_Prompt_Builder::user( $input ) ),
            ),
        );

        for ( $attempt = 0; $attempt <= $this->retry_limit; ++$attempt ) {
            $response = wp_safe_remote_post(
                $endpoint,
                array(
                    'timeout'     => $this->timeout,
                    'redirection' => 0,
                    'headers'     => array(
                        'Authorization' => 'Bearer ' . $this->api_key,
                        'Content-Type'  => 'application/json',
                    ),
                    'body'        => wp_json_encode( $body ),
                    'data_format' => 'body',
                )
            );

            if ( is_wp_error( $response ) ) {
                if ( $attempt < $this->retry_limit ) {
                    usleep( 250000 * ( $attempt + 1 ) );
                    continue;
                }
                return new WP_Error( 'trb_ai_transport', __( 'The AI provider could not be reached. Check connectivity and try again.', 'the-runbook-briefings' ) );
            }

            $status = (int) wp_remote_retrieve_response_code( $response );
            if ( ( 429 === $status || $status >= 500 ) && $attempt < $this->retry_limit ) {
                usleep( 250000 * ( $attempt + 1 ) );
                continue;
            }
            if ( $status < 200 || $status >= 300 ) {
                return $this->http_error( $status );
            }

            return $this->parse( (string) wp_remote_retrieve_body( $response ) );
        }

        return new WP_Error( 'trb_ai_failed', __( 'The AI request failed after the configured retries.', 'the-runbook-briefings' ) );
    }

    /**
     * Parse a chat-completions response without exposing raw provider data.
     *
     * @return array<string,mixed>|WP_Error
     */
    public function parse( string $body ) {
        $payload = json_decode( $body, true );
        $content = $payload['choices'][0]['message']['content'] ?? null;
        if ( ! is_string( $content ) || '' === trim( $content ) ) {
            return new WP_Error( 'trb_ai_response', __( 'The AI provider returned an empty or unsupported response.', 'the-runbook-briefings' ) );
        }
        $content = trim( $content );
        if ( str_starts_with( $content, '```' ) ) {
            $content = preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', $content ) ?? $content;
        }
        $briefing = json_decode( $content, true );
        if ( ! is_array( $briefing ) ) {
            return new WP_Error( 'trb_ai_json', __( 'The AI provider did not return valid structured JSON.', 'the-runbook-briefings' ) );
        }

        foreach ( array( 'suggested_headline', 'factual_summary', 'why_matters', 'practical_implications' ) as $required ) {
            if ( ! isset( $briefing[ $required ] ) || ! is_string( $briefing[ $required ] ) ) {
                return new WP_Error( 'trb_ai_fields', __( 'The AI response was missing a required briefing field.', 'the-runbook-briefings' ) );
            }
        }
        $briefing['internal_links'] = is_array( $briefing['internal_links'] ?? null ) ? $briefing['internal_links'] : array();
        return $briefing;
    }

    private function http_error( int $status ): WP_Error {
        if ( 401 === $status || 403 === $status ) {
            return new WP_Error( 'trb_ai_auth', __( 'The AI provider rejected the credentials. Update the API key in Settings.', 'the-runbook-briefings' ) );
        }
        if ( 429 === $status ) {
            return new WP_Error( 'trb_ai_remote_rate', __( 'The AI provider rate limit was reached. Try again later.', 'the-runbook-briefings' ) );
        }
        return new WP_Error(
            'trb_ai_http',
            sprintf( /* translators: %d: HTTP status code. */ __( 'The AI provider returned HTTP status %d.', 'the-runbook-briefings' ), $status )
        );
    }

    private function within_rate_limit(): bool {
        $user_id = get_current_user_id();
        $key     = 'trb_ai_rate_' . $user_id;
        $count   = (int) get_transient( $key );
        if ( $count >= 10 ) {
            return false;
        }
        set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
        return true;
    }
}
