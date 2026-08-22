<?php

use PHPUnit\Framework\TestCase;

final class UrlTest extends TestCase {
    public function test_normalizes_scheme_host_path_query_and_tracking_parameters(): void {
        $url = 'http://WWW.Example.com:80/news//story/?utm_source=email&b=2&a=one#comments';
        self::assertSame( 'https://example.com/news/story?a=one&b=2', TRB_URL::normalize( $url ) );
    }

    public function test_equivalent_http_and_https_urls_have_same_hash(): void {
        self::assertSame(
            TRB_URL::hash( 'http://example.com/story/' ),
            TRB_URL::hash( 'https://www.example.com/story?fbclid=abc' )
        );
    }

    public function test_rejects_non_http_urls_and_invalid_urls(): void {
        self::assertSame( '', TRB_URL::normalize( 'file:///etc/passwd' ) );
        self::assertSame( '', TRB_URL::normalize( 'not a url' ) );
    }

    public function test_resolves_dot_segments(): void {
        self::assertSame( 'https://example.com/security/alert', TRB_URL::normalize( 'https://example.com/news/../security/./alert/' ) );
    }
}
