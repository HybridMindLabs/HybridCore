<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\WebauthnCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebauthnTest extends TestCase
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

    public function test_a_credential_alone_counts_as_two_factor_enabled(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->hasTwoFactorEnabled());

        WebauthnCredential::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_register_options_returns_a_challenge_and_stores_it_in_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('account.2fa.webauthn.options'));

        $response->assertOk()->assertJsonStructure(['publicKey' => ['challenge', 'rp', 'user']]);
        $this->assertNotNull(session('webauthn_challenge'));
    }

    public function test_register_rejects_garbage_attestation_data(): void
    {
        $user = User::factory()->create();
        session(['webauthn_challenge' => 'ZmFrZS1jaGFsbGVuZ2U']);

        $this->actingAs($user)->postJson(route('account.2fa.webauthn.register'), [
            'name' => 'Test Device',
            'clientDataJSON' => base64_encode('not real client data'),
            'attestationObject' => base64_encode('not real attestation'),
        ])->assertStatus(422);

        $this->assertDatabaseCount('webauthn_credentials', 0);
    }

    public function test_a_user_can_only_delete_their_own_passkey(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $credential = WebauthnCredential::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)
            ->deleteJson(route('account.2fa.webauthn.destroy', $credential))
            ->assertForbidden();

        $this->actingAs($owner)
            ->deleteJson(route('account.2fa.webauthn.destroy', $credential))
            ->assertOk();

        $this->assertModelMissing($credential);
    }

    public function test_challenge_page_reports_available_methods(): void
    {
        $user = User::factory()->create();
        WebauthnCredential::factory()->create(['user_id' => $user->id]);
        session(['2fa_user_id' => $user->id]);

        $this->get(route('auth.2fa.challenge'))
            ->assertInertia(fn ($page) => $page
                ->where('hasTotp', false)
                ->where('hasWebauthn', true)
            );
    }

    public function test_challenge_options_requires_a_pending_login_session(): void
    {
        $this->postJson(route('auth.2fa.webauthn.options'))->assertStatus(419);
    }

    public function test_challenge_options_returns_the_users_credential_ids(): void
    {
        $user = User::factory()->create();
        WebauthnCredential::factory()->create(['user_id' => $user->id]);
        session(['2fa_user_id' => $user->id]);

        $this->postJson(route('auth.2fa.webauthn.options'))
            ->assertOk()
            ->assertJsonStructure(['publicKey' => ['challenge', 'allowCredentials']]);
    }

    public function test_challenge_verify_rejects_a_bad_assertion_without_logging_in(): void
    {
        $user = User::factory()->create();
        $credential = WebauthnCredential::factory()->create(['user_id' => $user->id]);
        session(['2fa_user_id' => $user->id, 'webauthn_challenge' => 'ZmFrZQ']);

        $this->post(route('auth.2fa.webauthn.verify'), [
            'id' => $credential->credential_id,
            'clientDataJSON' => base64_encode('nope'),
            'authenticatorData' => base64_encode('nope'),
            'signature' => base64_encode('nope'),
        ])->assertSessionHasErrors('code');

        $this->assertGuest();
    }
}
