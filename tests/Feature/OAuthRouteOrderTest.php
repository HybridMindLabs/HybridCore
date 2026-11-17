<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * The generic /auth/{provider}/... routes redirect to the core provider
 * routes. If they matched the core URLs first, every sign-in looped until
 * the throttle answered 429.
 */
class OAuthRouteOrderTest extends TestCase
{
    public function test_core_provider_urls_reach_their_own_routes(): void
    {
        foreach (['discord', 'steam', 'google'] as $provider) {
            foreach (['redirect', 'callback'] as $step) {
                $route = $this->app['router']->getRoutes()->match(Request::create("/auth/{$provider}/{$step}"));

                $this->assertSame("oauth.{$provider}.{$step}", $route->getName());
            }
        }
    }
}
