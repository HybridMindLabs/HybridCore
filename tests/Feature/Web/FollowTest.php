<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Notifications\NewFollowerNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FollowTest extends TestCase
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

    public function test_guest_cannot_follow(): void
    {
        $target = User::factory()->create();

        $this->post(route('profile.follow', $target))
            ->assertRedirect(route('login'));
    }

    public function test_user_can_follow_another_user(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.follow', $target))
            ->assertRedirect();

        $this->assertDatabaseHas('user_follows', [
            'follower_id' => $user->id,
            'followed_id' => $target->id,
        ]);
    }

    public function test_follow_notifies_target(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($user)->post(route('profile.follow', $target));

        Notification::assertSentTo($target, NewFollowerNotification::class);
    }

    public function test_refollow_does_not_duplicate_or_renotify(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($user)->post(route('profile.follow', $target));

        Notification::fake();
        $this->actingAs($user)->post(route('profile.follow', $target));

        Notification::assertNothingSent();
        $this->assertSame(1, $user->following()->count());
    }

    public function test_user_cannot_follow_self(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.follow', $user))
            ->assertStatus(422);
    }

    public function test_user_can_unfollow(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();
        $user->following()->attach($target->id);

        $this->actingAs($user)
            ->delete(route('profile.unfollow', $target))
            ->assertRedirect();

        $this->assertDatabaseMissing('user_follows', [
            'follower_id' => $user->id,
            'followed_id' => $target->id,
        ]);
    }

    public function test_profile_shows_follow_counts(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create(['username' => 'famous', 'profile_privacy' => 'public']);
        $user->following()->attach($target->id);

        $this->actingAs($user)
            ->get(route('profile.show', 'famous'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('profile.followers_count', 1)
                ->where('profile.is_following', true));
    }
}
