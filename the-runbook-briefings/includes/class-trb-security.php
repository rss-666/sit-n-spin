<?php
/**
 * Central capability and nonce policy.
 */

defined( 'ABSPATH' ) || exit;

final class TRB_Security {
    public static function can_manage(): bool {
        return current_user_can( 'manage_options' );
    }

    public static function can_review(): bool {
        return current_user_can( 'review_runbook_briefings' );
    }

    public static function require_manage( string $nonce_action ): void {
        if ( ! self::can_manage() ) {
            wp_die(
                esc_html__( 'You are not allowed to manage Runbook Briefings settings.', 'the-runbook-briefings' ),
                '',
                array( 'response' => 403 )
            );
        }
        check_admin_referer( $nonce_action );
    }

    public static function require_review( string $nonce_action ): void {
        if ( ! self::can_review() ) {
            wp_die(
                esc_html__( 'You are not allowed to review Runbook Briefings.', 'the-runbook-briefings' ),
                '',
                array( 'response' => 403 )
            );
        }
        check_admin_referer( $nonce_action );
    }
}
