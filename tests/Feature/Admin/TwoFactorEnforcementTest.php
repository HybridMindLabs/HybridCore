<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorEnforcementTest extends TestCase
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
        return User::factory()->create([
            'is_admin' => true,
            'two_factor_secret' => 'BASE32SECRET3232',
            'two_factor_recovery_codes' => Collection::times(8, fn () => Str::random(10).'-'.Str::random(10))->all(),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function test_policy_off_never_blocks_or_starts_the_clock(): void
    {
        app(SettingsService::class)->set('security.require_2fa_for_admins', '0');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $this->assertNull($admin->fresh()->two_factor_required_since);
    }

    public function test_first_admin_request_without_2fa_starts_the_clock_but_does_not_block(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $this->assertNotNull($admin->fresh()->two_factor_required_since);
    }

    public function test_within_the_grace_period_the_admin_panel_still_loads(): void
    {
        app(SettingsService::class)->set('security.require_2fa_grace_days', '3');
        $admin = User::factory()->create(['is_admin' => true, 'two_factor_required_since' => now()->subDay()]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_after_the_grace_period_the_admin_panel_redirects_to_account_security(): void
    {
        app(SettingsService::class)->set('security.require_2fa_grace_days', '3');
        $admin = User::factory()->create(['is_admin' => true, 'two_factor_required_since' => now()->subDays(4)]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('account.index', ['tab' => 'security']));
    }

    public function test_an_admin_with_2fa_enabled_is_never_blocked_and_gets_no_clock(): void
    {
        $admin = $this->userWith2FA();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $this->assertNull($admin->fresh()->two_factor_required_since);
    }

    public function test_confirming_2fa_resets_a_previously_started_clock(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'two_factor_required_since' => now()->subDays(10)]);
        session(['2fa_setup_secret' => 'BASE32SECRET3232']);

        $this->mock(Google2FA::class)
            ->shouldReceive('verifyKey')
            ->once()
            ->andReturn(true);

        $this->actingAs($admin)
            ->post(route('account.2fa.confirm'), ['code' => '123456'])
            ->assertOk();

        $this->assertNull($admin->fresh()->two_factor_required_since);
    }

    public function test_settings_page_persists_the_policy(): void
    {
        $admin = $this->userWith2FA();

        $this->actingAs($admin)->put(route('admin.settings.update'), array_merge($this->minimalSettingsPayload(), [
            'require_2fa_for_admins' => false,
            'require_2fa_grace_days' => 14,
        ]))->assertRedirect();

        $settings = app(SettingsService::class);
        $this->assertSame('0', $settings->get('security.require_2fa_for_admins'));
        $this->assertSame('14', $settings->get('security.require_2fa_grace_days'));
    }

    /** @return array<string, mixed> */
    private function minimalSettingsPayload(): array
    {
        return [
            'app_name' => 'HybridCore',
            'app_url' => 'https://example.com',
            'default_locale' => 'en',
            'timezone' => 'UTC',
            'maintenance_mode' => false,
        ];
    }
}
