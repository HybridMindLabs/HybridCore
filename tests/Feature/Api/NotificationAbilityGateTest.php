<?php

namespace Tests\Feature\Api;

use App\Models\ServiceAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationAbilityGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_token_without_the_ability_is_rejected(): void
    {
        $account = ServiceAccount::factory()->create();
        $token = $account->createToken('t', ['notifications:write'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/notifications')->assertStatus(403);
    }

    public function test_a_token_with_the_ability_is_accepted(): void
    {
        $account = ServiceAccount::factory()->create();
        $token = $account->createToken('t', ['notifications:read'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/notifications')->assertOk();
    }

    public function test_the_existing_wildcard_login_token_still_works_unaffected(): void
    {
        // Api\V1\AuthController::login creates ['*']-scoped tokens today —
        // this must keep working exactly as before this plan.
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken; // default abilities: ['*']

        $this->withToken($token)->getJson('/api/v1/notifications')->assertOk();
    }
}
