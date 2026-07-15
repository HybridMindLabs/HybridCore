<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Most installs run behind Cloudflare or another reverse proxy, which reaches
 * the origin over plain HTTP from its own address. If the forwarded headers are
 * ignored the site builds http:// URLs on an https:// domain (redirects then die
 * as mixed content) and every visitor looks like the proxy, breaking rate limits
 * and IP bans.
 */
class TrustedProxyTest extends TestCase
{
    private function probeRoute(): void
    {
        Route::get('/_proxy_probe', fn (Request $request) => [
            'secure' => $request->secure(),
            'ip' => $request->ip(),
            'url' => url('/somewhere'),
        ]);
    }

    public function test_a_proxied_https_request_is_seen_as_https(): void
    {
        $this->probeRoute();

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7']) // the proxy
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->getJson('/_proxy_probe')
            ->assertOk()
            ->assertJson(['secure' => true]);
    }

    public function test_generated_urls_keep_the_visitors_scheme(): void
    {
        $this->probeRoute();

        $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'example.test'])
            ->getJson('/_proxy_probe');

        // An http:// redirect issued to an https:// page is blocked by the
        // browser as mixed content — this is what white-screened the installer.
        $this->assertStringStartsWith('https://', $response->json('url'));
    }

    public function test_the_real_visitor_ip_is_used_not_the_proxys(): void
    {
        $this->probeRoute();

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7']) // the proxy
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9'])       // the visitor
            ->getJson('/_proxy_probe')
            ->assertOk()
            ->assertJson(['ip' => '203.0.113.9']);
    }

    public function test_a_direct_http_request_is_not_mistaken_for_https(): void
    {
        $this->probeRoute();

        // Absolute http:// URL: trusting proxies must not blanket-upgrade a
        // request that arrived without any forwarded scheme.
        $this->getJson('http://localhost/_proxy_probe')->assertJson(['secure' => false]);
    }
}
