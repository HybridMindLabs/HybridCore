<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The public profile API must obey the same privacy setting as the web profile.
 * Without that, /api/v1/users/{username} is a way around a profile a member
 * deliberately hid.
 */
class UserApiPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_public_profile_is_returned_to_anyone(): void
    {
        $user = User::factory()->create(['username' => 'publicguy', 'profile_privacy' => 'public']);

        $this->getJson('/api/v1/users/publicguy')
            ->assertOk()
            ->assertJsonPath('data.username', 'publicguy');
    }

    public function test_a_private_profile_is_hidden_from_anonymous_callers(): void
    {
        User::factory()->create(['username' => 'ghost', 'profile_privacy' => 'private']);

        $this->getJson('/api/v1/users/ghost')->assertNotFound();
    }

    public function test_a_private_profile_is_hidden_even_from_another_member(): void
    {
        User::factory()->create(['username' => 'ghost', 'profile_privacy' => 'private']);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/users/ghost')->assertNotFound();
    }

    public function test_the_owner_can_still_read_their_own_private_profile(): void
    {
        $owner = User::factory()->create(['username' => 'ghost', 'profile_privacy' => 'private']);
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/users/ghost')->assertOk()->assertJsonPath('data.username', 'ghost');
    }

    public function test_a_members_only_profile_is_hidden_from_anonymous_callers(): void
    {
        User::factory()->create(['username' => 'clubby', 'profile_privacy' => 'members']);

        $this->getJson('/api/v1/users/clubby')->assertNotFound();
    }

    public function test_a_members_only_profile_is_visible_to_an_authenticated_member(): void
    {
        User::factory()->create(['username' => 'clubby', 'profile_privacy' => 'members']);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/users/clubby')->assertOk()->assertJsonPath('data.username', 'clubby');
    }

    public function test_the_api_never_exposes_the_email_address(): void
    {
        User::factory()->create(['username' => 'publicguy', 'email' => 'secret@example.test', 'profile_privacy' => 'public']);

        $this->getJson('/api/v1/users/publicguy')
            ->assertOk()
            ->assertJsonMissing(['email' => 'secret@example.test']);
    }
}
