<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\ConnectedAccountService;
use App\Services\Auth\OAuthProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConnectedAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        @mkdir(storage_path(), 0755, true);
        file_put_contents(storage_path('installed.lock'), 'installed');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));

        parent::tearDown();
    }

    public function test_connected_account_can_be_stored_with_encrypted_tokens(): void
    {
        $user = User::factory()->create();
        $service = app(ConnectedAccountService::class);

        $account = $service->connect($user, 'example', [
            'provider_user_id' => '12345',
            'provider_username' => 'player1',
            'access_token' => 'secret-token-value',
        ]);

        $this->assertDatabaseHas('connected_accounts', [
            'user_id' => $user->id,
            'provider' => 'example',
            'provider_user_id' => '12345',
        ]);

        // Token decrypts via cast but is stored encrypted (raw column differs).
        $raw = DB::table('connected_accounts')->value('access_token');
        $this->assertNotSame('secret-token-value', $raw);
        $this->assertSame('secret-token-value', $account->fresh()->access_token);

        // Hidden from serialization.
        $this->assertArrayNotHasKey('access_token', $account->toArray());
    }

    public function test_duplicate_connected_account_is_prevented(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $service = app(ConnectedAccountService::class);

        $service->connect($userA, 'example', ['provider_user_id' => '12345']);

        $this->expectException(\DomainException::class);

        $service->connect($userB, 'example', ['provider_user_id' => '12345']);
    }

    public function test_unknown_oauth_provider_returns_404(): void
    {
        $this->get('/auth/nonexistent/redirect')->assertNotFound();
    }

    public function test_disabled_oauth_provider_returns_403(): void
    {
        app(OAuthProviderRegistry::class)->register('example', 'Example');

        $this->get('/auth/example/redirect')->assertForbidden();
    }

    public function test_disconnect_removes_account(): void
    {
        $user = User::factory()->create();
        app(OAuthProviderRegistry::class)->register('example', 'Example');
        app(ConnectedAccountService::class)->connect($user, 'example', ['provider_user_id' => '99']);

        $this->actingAs($user)
            ->delete('/account/connected-accounts/example')
            ->assertRedirect();

        $this->assertDatabaseMissing('connected_accounts', ['user_id' => $user->id, 'provider' => 'example']);
    }
}
