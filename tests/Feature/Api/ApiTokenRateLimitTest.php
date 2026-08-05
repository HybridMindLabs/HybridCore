<?php

namespace Tests\Feature\Api;

use App\Models\ServiceAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ApiTokenRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_token_is_throttled_independently_of_ip(): void
    {
        config(['hybridcore.api_token_rate_limit' => 2]);

        $account = ServiceAccount::factory()->create();
        $token = $account->createToken('t', ['notifications:read']);

        $this->withToken($token->plainTextToken)->getJson('/api/v1/notifications')->assertOk();
        $this->withToken($token->plainTextToken)->getJson('/api/v1/notifications')->assertOk();
        $this->withToken($token->plainTextToken)->getJson('/api/v1/notifications')->assertStatus(429);

        RateLimiter::clear('api-token:'.$token->accessToken->id);
    }

    public function test_two_tokens_have_independent_limits(): void
    {
        config(['hybridcore.api_token_rate_limit' => 1]);

        $account = ServiceAccount::factory()->create();
        $tokenA = $account->createToken('a', ['notifications:read']);
        $tokenB = $account->createToken('b', ['notifications:read']);

        $this->withToken($tokenA->plainTextToken)->getJson('/api/v1/notifications')->assertOk();

        // The "sanctum" guard memoizes its resolved user per guard instance,
        // which persists across requests within one test method — without
        // this, the second call below would silently re-check tokenA's
        // already-spent budget instead of actually re-authenticating.
        $this->app['auth']->forgetGuards();

        // A different token, even on the same ServiceAccount, gets its own budget.
        $this->withToken($tokenB->plainTextToken)->getJson('/api/v1/notifications')->assertOk();

        RateLimiter::clear('api-token:'.$tokenA->accessToken->id);
        RateLimiter::clear('api-token:'.$tokenB->accessToken->id);
    }
}
