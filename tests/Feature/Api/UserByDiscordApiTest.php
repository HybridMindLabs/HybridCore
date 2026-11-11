<?php

namespace Tests\Feature\Api;

use App\Models\ConnectedAccount;
use App\Models\ServiceAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserByDiscordApiTest extends TestCase
{
    use RefreshDatabase;

    private function linkDiscord(User $user, string $discordId): void
    {
        ConnectedAccount::create([
            'user_id' => $user->id,
            'provider' => 'discord',
            'provider_user_id' => $discordId,
            'provider_username' => 'someone',
        ]);
    }

    public function test_a_service_token_with_the_ability_finds_the_linked_member(): void
    {
        $user = User::factory()->create(['username' => 'linked', 'banned_at' => now()]);
        $this->linkDiscord($user, '123456789012345678');

        $token = ServiceAccount::factory()->create()->createToken('bot', ['users:lookup'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/users/by-discord/123456789012345678')
            ->assertOk()
            ->assertJsonPath('data.username', 'linked')
            ->assertJsonPath('data.is_banned', true)
            ->assertJsonStructure(['data' => ['id', 'username', 'roles']]);
    }

    public function test_an_unlinked_discord_id_is_not_found(): void
    {
        $token = ServiceAccount::factory()->create()->createToken('bot', ['users:lookup'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/users/by-discord/999')->assertNotFound();
    }

    public function test_a_service_token_without_the_ability_is_forbidden(): void
    {
        $token = ServiceAccount::factory()->create()->createToken('bot', ['notifications:read'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/users/by-discord/123')->assertForbidden();
    }

    public function test_a_members_own_token_cannot_look_up_discord_ids(): void
    {
        $other = User::factory()->create();
        $this->linkDiscord($other, '123');

        $token = User::factory()->create()->createToken('api')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/users/by-discord/123')->assertForbidden();
    }

    public function test_anonymous_callers_are_rejected(): void
    {
        $this->getJson('/api/v1/users/by-discord/123')->assertUnauthorized();
    }
}
