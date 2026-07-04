<?php

namespace Tests\Feature\Web;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed.lock'), 'installed');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    public function test_onboarding_page_renders_for_new_user(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => null]);

        $this->actingAs($user)
            ->get(route('onboarding.show'))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Onboarding/Welcome'));
    }

    public function test_onboarding_complete_sets_timestamp(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => null]);

        $this->actingAs($user)
            ->post(route('onboarding.complete'), [
                'display_name' => 'Test User',
                'bio' => 'Hello world',
                'location' => 'Sofia',
            ])
            ->assertRedirect();

        $this->assertNotNull($user->fresh()->onboarding_completed_at);
    }

    public function test_completed_onboarding_redirects_home(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);

        $this->actingAs($user)
            ->get(route('onboarding.show'))
            ->assertRedirect(route('home'));
    }

    public function test_onboarding_can_follow_suggested_members(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => null]);
        $targets = User::factory()->count(2)->create();

        $this->actingAs($user)
            ->post(route('onboarding.complete'), [
                'follow_users' => $targets->pluck('id')->all(),
            ])
            ->assertRedirect();

        foreach ($targets as $target) {
            $this->assertDatabaseHas('user_follows', [
                'follower_id' => $user->id,
                'followed_id' => $target->id,
            ]);
        }
    }

    public function test_onboarding_cannot_follow_self(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => null]);

        $this->actingAs($user)
            ->post(route('onboarding.complete'), ['follow_users' => [$user->id]])
            ->assertRedirect();

        $this->assertDatabaseMissing('user_follows', ['follower_id' => $user->id]);
    }

    public function test_onboarding_stores_game_preferences(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => null]);
        $games = Game::factory()->count(2)->create();

        $this->actingAs($user)
            ->post(route('onboarding.complete'), [
                'favourite_games' => $games->pluck('id')->all(),
            ])
            ->assertRedirect();

        foreach ($games as $game) {
            $this->assertDatabaseHas('user_game_preferences', [
                'user_id' => $user->id,
                'game_id' => $game->id,
            ]);
        }
    }

    public function test_onboarding_shows_suggested_members(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => null]);
        User::factory()->count(3)->create();

        $this->actingAs($user)
            ->get(route('onboarding.show'))
            ->assertInertia(fn ($page) => $page->has('suggestedMembers', 3));
    }
}
