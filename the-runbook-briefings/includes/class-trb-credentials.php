<?php
/**
 * Authenticated encryption for provider credentials.
 */

defined( 'ABSPATH' ) || exit;

final class TRB_Credentials {
    /**
     * Encrypt an API key with a key derived from WordPress salts.
     *
     * @return string|WP_Error
     */
    public static function encrypt( string $plaintext ) {
        $plaintext = trim( $plaintext );
        if ( '' === $plaintext ) {
            return '';
        }
        $key = self::key();

        if ( function_exists( 'sodium_crypto_secretbox' ) ) {
            try {
                $nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
                $ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, $key );
                return 'sodium:v1:' . base64_encode( $nonce . $ciphertext );
            } catch ( Throwable $exception ) {
                return new WP_Error( 'trb_credential_encryption', __( 'The API key could not be encrypted.', 'the-runbook-briefings' ) );
            }
        }

        if ( function_exists( 'openssl_encrypt' ) ) {
            try {
                $iv         = random_bytes( 12 );
                $tag        = '';
                $ciphertext = openssl_encrypt( $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
                if ( false === $ciphertext ) {
                    throw new RuntimeException( 'Encryption failed.' );
                }
                return 'openssl:v1:' . base64_encode( $iv . $tag . $ciphertext );
            } catch ( Throwable $exception ) {
                return new WP_Error( 'trb_credential_encryption', __( 'The API key could not be encrypted.', 'the-runbook-briefings' ) );
            }
        }

        return new WP_Error(
            'trb_no_encryption',
            __( 'This server has no supported authenticated-encryption extension. The API key was not saved.', 'the-runbook-briefings' )
        );
    }

    /**
     * Decrypt an API key. Never log the returned value.
     */
    public static function decrypt( string $stored ): string {
        if ( '' === $stored || ! str_contains( $stored, ':v1:' ) ) {
            return '';
        }
        list( $driver, , $encoded ) = array_pad( explode( ':', $stored, 3 ), 3, '' );
        $payload = base64_decode( $encoded, true );
        if ( false === $payload ) {
            return '';
        }
        $key = self::key();

        try {
            if ( 'sodium' === $driver && function_exists( 'sodium_crypto_secretbox_open' ) ) {
                $nonce      = substr( $payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
                $ciphertext = substr( $payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
                $plaintext  = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );
                return false === $plaintext ? '' : $plaintext;
            }
            if ( 'openssl' === $driver && function_exists( 'openssl_decrypt' ) ) {
                $iv         = substr( $payload, 0, 12 );
                $tag        = substr( $payload, 12, 16 );
                $ciphertext = substr( $payload, 28 );
                $plaintext  = openssl_decrypt( $ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
                return false === $plaintext ? '' : $plaintext;
            }
        } catch ( Throwable $exception ) {
            return '';
        }
        return '';
    }

    private static function key(): string {
        return hash( 'sha256', wp_salt( 'auth' ) . '|the-runbook-briefings|credentials', true );
    }
}
