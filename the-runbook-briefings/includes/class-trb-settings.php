<?php
/**
 * Plugin settings access and validation.
 */

defined( 'ABSPATH' ) || exit;

final class TRB_Settings {
    public const OPTION = 'trb_settings';

    /**
     * Default settings. Credentials are stored as an encrypted value.
     *
     * @return array<string,mixed>
     */
    public static function defaults(): array {
        return array(
            'ai_provider'             => 'openai',
            'api_key_encrypted'       => '',
            'model'                   => 'gpt-4o-mini',
            'default_tone'            => 'technical',
            'max_excerpt_length'      => 1200,
            'cron_frequency'          => 'hourly',
            'default_post_status'     => 'draft',
            'max_items_per_run'       => 20,
            'request_timeout'         => 20,
            'retry_limit'             => 2,
            'max_item_age_days'       => 30,
            'delete_data_on_uninstall'=> 0,
        );
    }

    /**
     * Get all settings or one setting.
     *
     * @return mixed
     */
    public static function get( ?string $key = null ) {
        $settings = wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
        if ( null === $key ) {
            return $settings;
        }
        return $settings[ $key ] ?? null;
    }

    /**
     * Sanitize non-secret settings submitted by an administrator.
     *
     * @param array<string,mixed> $input Input.
     * @return array<string,mixed>
     */
    public static function sanitize( array $input ): array {
        $current = self::get();

        $providers = array( 'openai', 'none' );
        $tones     = array( 'technical', 'beginner-friendly', 'concise', 'editorial' );
        $schedules = array( 'trb_fifteen_minutes', 'hourly', 'twicedaily', 'daily' );
        $statuses  = array( 'draft', 'pending' );

        return array(
            'ai_provider'              => in_array( $input['ai_provider'] ?? '', $providers, true ) ? $input['ai_provider'] : 'none',
            'api_key_encrypted'        => (string) ( $current['api_key_encrypted'] ?? '' ),
            'model'                    => sanitize_text_field( (string) ( $input['model'] ?? 'gpt-4o-mini' ) ),
            'default_tone'             => in_array( $input['default_tone'] ?? '', $tones, true ) ? $input['default_tone'] : 'technical',
            'max_excerpt_length'       => self::bounded_int( $input['max_excerpt_length'] ?? 1200, 100, 5000 ),
            'cron_frequency'           => in_array( $input['cron_frequency'] ?? '', $schedules, true ) ? $input['cron_frequency'] : 'hourly',
            'default_post_status'      => in_array( $input['default_post_status'] ?? '', $statuses, true ) ? $input['default_post_status'] : 'draft',
            'max_items_per_run'        => self::bounded_int( $input['max_items_per_run'] ?? 20, 1, 100 ),
            'request_timeout'          => self::bounded_int( $input['request_timeout'] ?? 20, 5, 60 ),
            'retry_limit'              => self::bounded_int( $input['retry_limit'] ?? 2, 0, 5 ),
            'max_item_age_days'        => self::bounded_int( $input['max_item_age_days'] ?? 30, 1, 365 ),
            'delete_data_on_uninstall' => empty( $input['delete_data_on_uninstall'] ) ? 0 : 1,
        );
    }

    private static function bounded_int( $value, int $minimum, int $maximum ): int {
        return min( $maximum, max( $minimum, absint( $value ) ) );
    }
}
