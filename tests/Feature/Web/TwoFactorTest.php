<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
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

    private function userWith2FA(): User
    {
        $secret = 'BASE32SECRET3232';
        $codes = Collection::times(8, fn () => Str::random(10).'-'.Str::random(10))->all();

        return User::factory()->create([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ]);
    }

    // ── Login flow ────────────────────────────────────────────────────────────

    public function test_login_without_2fa_redirects_to_account(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('account.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_2fa_enabled_redirects_to_challenge(): void
    {
        $user = $this->userWith2FA();
        $user->password = bcrypt('password');
        $user->save();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('auth.2fa.challenge'));

        $this->assertGuest();
        $this->assertEquals($user->id, session('2fa_user_id'));
    }

    // ── Challenge page ────────────────────────────────────────────────────────

    public function test_challenge_page_renders_with_session(): void
    {
        $user = $this->userWith2FA();
        session(['2fa_user_id' => $user->id]);

        $this->get(route('auth.2fa.challenge'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Auth/TwoFactorChallenge'));
    }

    public function test_challenge_page_redirects_to_login_without_session(): void
    {
        $this->get(route('auth.2fa.challenge'))
            ->assertRedirect(route('login'));
    }

    public function test_challenge_post_redirects_to_login_without_session(): void
    {
        $this->post(route('auth.2fa.verify'), ['code' => '123456'])
            ->assertRedirect(route('login'));
    }

    // ── Challenge verification — TOTP ─────────────────────────────────────────

    public function test_challenge_with_valid_totp_authenticates_user(): void
    {
        $user = $this->userWith2FA();
        session(['2fa_user_id' => $user->id]);

        $this->mock(Google2FA::class)
            ->shouldReceive('verifyKey')
            ->once()
            ->andReturn(true);

        $this->post(route('auth.2fa.verify'), ['code' => '123456'])
            ->assertRedirect(route('account.index'));

        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('2fa_user_id'));
        $this->assertTrue(session('2fa_verified'));
    }

    public function test_challenge_with_invalid_totp_fails(): void
    {
        $user = $this->userWith2FA();
        session(['2fa_user_id' => $user->id]);

        $this->mock(Google2FA::class)
            ->shouldReceive('verifyKey')
            ->once()
            ->andReturn(false);

        $this->post(route('auth.2fa.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    // ── Challenge verification — recovery codes ───────────────────────────────

    public function test_challenge_with_valid_recovery_code_authenticates_user(): void
    {
        $user = $this->userWith2FA();
        $codes = $user->two_factor_recovery_codes;
        $validCode = $codes[0];
        session(['2fa_user_id' => $user->id]);

        $this->mock(Google2FA::class)
            ->shouldReceive('verifyKey')
            ->andReturn(false);

        $this->post(route('auth.2fa.verify'), ['code' => $validCode])
            ->assertRedirect(route('account.index'));

        $this->assertAuthenticatedAs($user);

        $remaining = $user->fresh()->two_factor_recovery_codes;
        $this->assertNotContains($validCode, $remaining);
        $this->assertCount(count($codes) - 1, $remaining);
    }

    public function test_challenge_with_invalid_recovery_code_fails(): void
    {
        $user = $this->userWith2FA();
        session(['2fa_user_id' => $user->id]);

        $this->mock(Google2FA::class)
            ->shouldReceive('verifyKey')
            ->andReturn(false);

        $this->post(route('auth.2fa.verify'), ['code' => 'invalid-recovery-code'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    // ── Setup & confirm ───────────────────────────────────────────────────────

    public function test_setup_returns_secret_and_qr_image(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('account.2fa.setup'));

        $response->assertOk()
            ->assertJsonStructure(['secret', 'qr_svg']);

        $this->assertNotEmpty($response->json('secret'));
        $this->assertNotEmpty($response->json('qr_svg'));
    }

    public function test_setup_stores_secret_in_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('account.2fa.setup'));

        $this->assertNotNull(session('2fa_setup_secret'));
    }

    public function test_confirm_enables_2fa_with_valid_code(): void
    {
        $user = User::factory()->create();
        session(['2fa_setup_secret' => 'BASE32SECRET3232']);

        $this->mock(Google2FA::class)
            ->shouldReceive('verifyKey')
            ->once()
            ->andReturn(true);

        $this->actingAs($user)
            ->post(route('account.2fa.confirm'), ['code' => '123456'])
            ->assertOk()
            ->assertJson(['message' => __('account.2fa_was_enabled')]);

        $this->assertNotNull($user->fresh()->two_factor_secret);
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->assertCount(8, $user->fresh()->two_factor_recovery_codes);
    }

    public function test_confirm_rejects_wrong_code(): void
    {
        $user = User::factory()->create();
        session(['2fa_setup_secret' => 'BASE32SECRET3232']);

        $this->mock(Google2FA::class)
            ->shouldReceive('verifyKey')
            ->once()
            ->andReturn(false);

        $this->actingAs($user)
            ->post(route('account.2fa.confirm'), ['code' => '000000'])
            ->assertStatus(422)
            ->assertJson(['message' => __('account.2fa_code_invalid')]);

        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_confirm_requires_6_digit_code(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('account.2fa.confirm'), ['code' => 'abc'])
            ->assertSessionHasErrors('code');
    }

    // ── Disable ───────────────────────────────────────────────────────────────

    public function test_disable_removes_2fa_with_correct_password(): void
    {
        $user = $this->userWith2FA();
        $user->password = bcrypt('password');
        $user->save();

        $this->actingAs($user)
            ->delete(route('account.2fa.disable'), ['password' => 'password'])
            ->assertOk()
            ->assertJson(['message' => __('account.2fa_was_disabled')]);

        $fresh = $user->fresh();
        $this->assertNull($fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_recovery_codes);
        $this->assertNull($fresh->two_factor_confirmed_at);
    }

    public function test_disable_rejects_wrong_password(): void
    {
        $user = $this->userWith2FA();
        $user->password = bcrypt('password');
        $user->save();

        $this->actingAs($user)
            ->delete(route('account.2fa.disable'), ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');

        $this->assertNotNull($user->fresh()->two_factor_secret);
    }

    // ── Regenerate codes ──────────────────────────────────────────────────────

    public function test_regenerate_codes_returns_new_codes(): void
    {
        $user = $this->userWith2FA();
        $user->password = bcrypt('password');
        $user->save();
        $oldCodes = $user->two_factor_recovery_codes;

        $response = $this->actingAs($user)
            ->post(route('account.2fa.recovery-codes'), ['password' => 'password'])
            ->assertOk();

        $newCodes = $response->json('recovery_codes');
        $this->assertCount(8, $newCodes);
        $this->assertNotEquals($oldCodes, $newCodes);
        $this->assertEquals($newCodes, $user->fresh()->two_factor_recovery_codes);
    }

    public function test_regenerate_codes_rejects_wrong_password(): void
    {
        $user = $this->userWith2FA();
        $user->password = bcrypt('password');
        $user->save();

        $this->actingAs($user)
            ->post(route('account.2fa.recovery-codes'), ['password' => 'wrong'])
            ->assertSessionHasErrors('password');
    }
}
