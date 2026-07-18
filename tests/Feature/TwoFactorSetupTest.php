<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * setup() used to return the raw otpauth:// URI as "qr_url", which the page fed
 * to <img src> — so the QR was always blank. These tests pin down that the
 * endpoint hands back something a browser can actually draw.
 */
class TwoFactorSetupTest extends TestCase
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

    public function test_setup_returns_a_renderable_qr_image(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('account.2fa.setup'))
            ->assertOk()
            ->assertJsonStructure(['secret', 'qr_svg', 'otpauth_uri']);

        $qr = $response->json('qr_svg');

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $qr);

        $svg = base64_decode(substr($qr, strlen('data:image/svg+xml;base64,')));
        $this->assertStringContainsString('<svg', $svg);

        // The scannable payload still has to be the otpauth URI, not a URL.
        $this->assertStringStartsWith('otpauth://totp/', $response->json('otpauth_uri'));
    }

    public function test_a_valid_code_turns_two_factor_on(): void
    {
        $user = User::factory()->create();
        $google2fa = app(Google2FA::class);

        $secret = $this->actingAs($user)
            ->postJson(route('account.2fa.setup'))
            ->json('secret');

        $this->actingAs($user)
            ->postJson(route('account.2fa.confirm'), ['code' => $google2fa->getCurrentOtp($secret)])
            ->assertOk();

        $user->refresh();

        $this->assertSame($secret, $user->two_factor_secret);
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertCount(8, $user->two_factor_recovery_codes);
    }

    public function test_a_wrong_code_leaves_two_factor_off(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('account.2fa.setup'));

        $this->actingAs($user)
            ->postJson(route('account.2fa.confirm'), ['code' => '000000'])
            ->assertStatus(422);

        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    /** The page called /account/two-factor/disable, which is not a route — it 404'd. */
    public function test_the_disable_endpoint_the_page_calls_exists(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret-password'),
            'two_factor_secret' => 'ABCDEFGHIJKLMNOP',
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($user)
            ->deleteJson(route('account.2fa.disable'), ['password' => 'secret-password'])
            ->assertOk();

        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_disabling_requires_the_current_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret-password'),
            'two_factor_secret' => 'ABCDEFGHIJKLMNOP',
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($user)
            ->deleteJson(route('account.2fa.disable'), ['password' => 'wrong-password'])
            ->assertStatus(422);

        $this->assertNotNull($user->fresh()->two_factor_secret);
    }
}
