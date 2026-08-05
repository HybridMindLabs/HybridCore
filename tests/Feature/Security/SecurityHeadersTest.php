<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * The response hardening every page relies on: the framing/sniffing headers on
 * ordinary pages, and no-store on the credential-entry pages so a shared cache
 * never hands the next person a filled-in login screen.
 */
class SecurityHeadersTest extends TestCase
{
    public function test_ordinary_pages_carry_the_hardening_headers(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        $response->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
    }

    public function test_auth_pages_are_not_cacheable(): void
    {
        foreach (['/login', '/register', '/forgot-password', '/admin/login'] as $path) {
            $cacheControl = $this->get($path)->headers->get('Cache-Control');

            $this->assertStringContainsString('no-store', (string) $cacheControl, "{$path} must not be cacheable");
        }
    }

    public function test_ordinary_public_pages_are_not_forced_no_store(): void
    {
        // The blanket no-store belongs only on credential pages; slapping it on
        // everything would throw away caching the rest of the site benefits
        // from. The home page must not inherit it.
        $cacheControl = (string) $this->get('/')->headers->get('Cache-Control');

        $this->assertStringNotContainsString('no-store', $cacheControl);
    }
}
