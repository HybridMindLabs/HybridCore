<?php

namespace Tests\Feature;

use App\Models\ConnectedAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * An OAuth signup is stored with Hash::make(Str::random(40)) because the
 * password column is not nullable. Unlinking the only provider therefore used
 * to leave the account with no credential its owner knows — a silent lockout.
 */
class ConnectedAccountsTest extends TestCase
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

    private function oauthUser(): User
    {
        return User::factory()->create([
            'password' => Hash::make(Str::random(40)),
            'password_set_at' => null,
        ]);
    }

    private function link(User $user, string $provider): void
    {
        ConnectedAccount::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_user_id' => $provider.'-'.$user->id,
            'provider_username' => 'player',
        ]);
    }

    public function test_unlinking_the_only_provider_is_blocked_without_a_password(): void
    {
        $user = $this->oauthUser();
        $this->link($user, 'steam');

        $this->actingAs($user)
            ->from(route('account.index'))
            ->delete(route('oauth.disconnect', 'steam'))
            ->assertSessionHasErrors('provider');

        $this->assertDatabaseHas('connected_accounts', ['user_id' => $user->id, 'provider' => 'steam']);
    }

    public function test_unlinking_is_allowed_when_another_provider_remains(): void
    {
        $user = $this->oauthUser();
        $this->link($user, 'steam');
        $this->link($user, 'discord');

        $this->actingAs($user)
            ->from(route('account.index'))
            ->delete(route('oauth.disconnect', 'steam'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('connected_accounts', ['user_id' => $user->id, 'provider' => 'steam']);
    }

    public function test_unlinking_the_only_provider_is_allowed_once_a_password_exists(): void
    {
        $user = User::factory()->create(['password_set_at' => now()]);
        $this->link($user, 'steam');

        $this->actingAs($user)
            ->from(route('account.index'))
            ->delete(route('oauth.disconnect', 'steam'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('connected_accounts', ['user_id' => $user->id, 'provider' => 'steam']);
    }

    /**
     * The way out of the block, end to end: set a password through the normal
     * form and the provider becomes unlinkable. Uses the factory's known
     * password with the flag cleared, which is the state a backfilled account
     * with a linked provider is in.
     */
    public function test_setting_a_password_unblocks_unlinking(): void
    {
        $user = User::factory()->create(['password_set_at' => null]);
        $this->link($user, 'steam');

        $this->actingAs($user)
            ->from(route('account.index'))
            ->delete(route('oauth.disconnect', 'steam'))
            ->assertSessionHasErrors('provider');

        $this->actingAs($user)
            ->from(route('account.index'))
            ->put(route('account.password.update'), [
                'current_password' => 'password',
                'password' => 'a-brand-new-password-1',
                'password_confirmation' => 'a-brand-new-password-1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($user->fresh()->password_set_at);

        $this->actingAs($user->fresh())
            ->from(route('account.index'))
            ->delete(route('oauth.disconnect', 'steam'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('connected_accounts', ['user_id' => $user->id, 'provider' => 'steam']);
    }

    public function test_registering_with_a_password_records_that_it_was_chosen(): void
    {
        $this->post(route('register'), [
            'name' => 'Test Player',
            'email' => 'player@example.test',
            'password' => 'a-brand-new-password-1',
            'password_confirmation' => 'a-brand-new-password-1',
        ]);

        $user = User::where('email', 'player@example.test')->first();

        $this->assertNotNull($user);
        $this->assertNotNull($user->password_set_at);
    }
}
