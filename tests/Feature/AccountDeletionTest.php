<?php

namespace Tests\Feature;

use App\Jobs\GenerateDataExportJob;
use App\Models\ConnectedAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed.lock'), 'installed');
        config(['session.driver' => 'database']);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    private function oauthUser(): User
    {
        return User::factory()->create(['password_set_at' => null]);
    }

    private function link(User $user): void
    {
        ConnectedAccount::create([
            'user_id' => $user->id,
            'provider' => 'steam',
            'provider_user_id' => 'steam-'.$user->id,
            'provider_username' => 'player',
        ]);
    }

    public function test_deleting_anonymises_the_profile(): void
    {
        $user = User::factory()->create(['username' => 'realname', 'bio' => 'about me']);

        $this->actingAs($user)->post(route('account.delete'), [
            'username_confirm' => 'realname',
            'password' => 'password',
        ]);

        $fresh = $user->fresh();

        $this->assertSame('deleted_'.$user->id, $fresh->username);
        $this->assertNull($fresh->bio);
        $this->assertNotNull($fresh->banned_at);
    }

    /**
     * The row survives so conversations keep their foreign keys, which means
     * the cascades never fire and this data has to be cleared explicitly.
     */
    public function test_deleting_clears_provider_links_sessions_and_login_history(): void
    {
        $user = User::factory()->create([
            'username' => 'realname',
            'two_factor_secret' => 'ABCDEFGHIJKLMNOP',
            'two_factor_confirmed_at' => now(),
        ]);
        $this->link($user);

        $user->loginHistories()->create(['ip_address' => '203.0.113.10', 'user_agent' => 'Chrome']);

        DB::table('sessions')->insert([
            'id' => 'a-session',
            'user_id' => $user->id,
            'ip_address' => '203.0.113.10',
            'user_agent' => 'Chrome',
            'payload' => base64_encode(serialize([])),
            'last_activity' => time(),
        ]);

        $this->actingAs($user)->post(route('account.delete'), [
            'username_confirm' => 'realname',
            'password' => 'password',
        ]);

        $this->assertDatabaseMissing('connected_accounts', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('login_histories', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'a-session']);
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_a_wrong_username_does_not_delete(): void
    {
        $user = User::factory()->create(['username' => 'realname']);

        $this->actingAs($user)
            ->from(route('account.index'))
            ->post(route('account.delete'), [
                'username_confirm' => 'someone-else',
                'password' => 'password',
            ])
            ->assertSessionHasErrors('username_confirm');

        $this->assertSame('realname', $user->fresh()->username);
    }

    public function test_a_wrong_password_does_not_delete(): void
    {
        $user = User::factory()->create(['username' => 'realname']);

        $this->actingAs($user)
            ->from(route('account.index'))
            ->post(route('account.delete'), [
                'username_confirm' => 'realname',
                'password' => 'not-the-password',
            ])
            ->assertSessionHasErrors('password');

        $this->assertSame('realname', $user->fresh()->username);
    }

    /** Requiring a password made deletion impossible for provider-only accounts. */
    public function test_a_provider_only_account_can_delete_itself(): void
    {
        $user = $this->oauthUser();
        $user->update(['username' => 'steamplayer']);
        $this->link($user);

        $this->actingAs($user)
            ->from(route('account.index'))
            ->post(route('account.delete'), ['username_confirm' => 'steamplayer'])
            ->assertSessionHasNoErrors();

        $this->assertSame('deleted_'.$user->id, $user->fresh()->username);
    }

    public function test_a_provider_only_account_can_export_its_data(): void
    {
        Queue::fake();

        $user = $this->oauthUser();
        $user->update(['username' => 'steamplayer']);

        $this->actingAs($user)
            ->from(route('account.index'))
            ->post(route('account.export'), ['username_confirm' => 'steamplayer'])
            ->assertSessionHasNoErrors();

        Queue::assertPushed(GenerateDataExportJob::class);
    }

    public function test_export_still_requires_the_password_when_there_is_one(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('account.index'))
            ->post(route('account.export'), ['password' => 'not-the-password'])
            ->assertSessionHasErrors('password');

        // Scoped to the export job — unrelated queued work may exist.
        Queue::assertNotPushed(GenerateDataExportJob::class);
    }
}
