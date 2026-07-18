<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Blocking is advertised to users as "they cannot follow you". These tests hold
 * the code to that promise — the claim used to be in the UI while the follow
 * endpoint accepted the request anyway.
 */
class BlockPreventsFollowTest extends TestCase
{
    use RefreshDatabase;

    /** Routes sit behind EnsureAppIsInstalled, which redirects without this. */
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

    private function makeUser(string $username): User
    {
        return User::factory()->create([
            'username' => $username,
            'email' => $username.'@example.test',
        ]);
    }

    public function test_a_blocked_member_cannot_follow_the_person_who_blocked_them(): void
    {
        $blocker = $this->makeUser('blocker');
        $blocked = $this->makeUser('blocked');

        UserBlock::create(['blocker_id' => $blocker->id, 'blocked_id' => $blocked->id]);

        $this->actingAs($blocked)
            ->from(route('profile.show', ['username' => $blocker->username]))
            ->post(route('profile.follow', $blocker))
            ->assertSessionHasErrors('follow');

        $this->assertDatabaseMissing('user_follows', [
            'follower_id' => $blocked->id,
            'followed_id' => $blocker->id,
        ]);
    }

    public function test_you_cannot_follow_someone_you_have_blocked(): void
    {
        $blocker = $this->makeUser('blocker2');
        $blocked = $this->makeUser('blocked2');

        UserBlock::create(['blocker_id' => $blocker->id, 'blocked_id' => $blocked->id]);

        $this->actingAs($blocker)
            ->from(route('profile.show', ['username' => $blocked->username]))
            ->post(route('profile.follow', $blocked))
            ->assertSessionHasErrors('follow');

        $this->assertDatabaseMissing('user_follows', [
            'follower_id' => $blocker->id,
            'followed_id' => $blocked->id,
        ]);
    }

    public function test_blocking_removes_an_existing_follow_in_both_directions(): void
    {
        $blocker = $this->makeUser('blocker3');
        $other = $this->makeUser('other3');

        // They follow each other before the block happens.
        $blocker->following()->attach($other->id);
        $other->following()->attach($blocker->id);

        $this->actingAs($blocker)
            ->from(route('profile.show', ['username' => $other->username]))
            ->post(route('account.block', $other))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('user_follows', [
            'follower_id' => $blocker->id,
            'followed_id' => $other->id,
        ]);
        $this->assertDatabaseMissing('user_follows', [
            'follower_id' => $other->id,
            'followed_id' => $blocker->id,
        ]);
    }

    public function test_following_still_works_between_members_with_no_block(): void
    {
        $follower = $this->makeUser('follower4');
        $target = $this->makeUser('target4');

        $this->actingAs($follower)
            ->from(route('profile.show', ['username' => $target->username]))
            ->post(route('profile.follow', $target))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('user_follows', [
            'follower_id' => $follower->id,
            'followed_id' => $target->id,
        ]);
    }

    public function test_unblocking_lets_them_follow_again(): void
    {
        $blocker = $this->makeUser('blocker5');
        $blocked = $this->makeUser('blocked5');

        UserBlock::create(['blocker_id' => $blocker->id, 'blocked_id' => $blocked->id]);

        $this->actingAs($blocker)
            ->from(route('account.blocked'))
            ->delete(route('account.unblock', $blocked));

        $this->actingAs($blocked)
            ->from(route('profile.show', ['username' => $blocker->username]))
            ->post(route('profile.follow', $blocker))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('user_follows', [
            'follower_id' => $blocked->id,
            'followed_id' => $blocker->id,
        ]);
    }
}
