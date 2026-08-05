<?php

namespace Tests\Feature\Console;

use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityDisableTwoFactorEnforcementCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_refuses_without_force(): void
    {
        $this->artisan('hybridcore:security:disable-2fa-enforcement')
            ->assertExitCode(1);

        $this->assertSame('1', app(SettingsService::class)->get('security.require_2fa_for_admins', '1'));
    }

    public function test_disables_the_policy_with_force(): void
    {
        $this->artisan('hybridcore:security:disable-2fa-enforcement', ['--force' => true])
            ->assertExitCode(0);

        $this->assertSame('0', app(SettingsService::class)->get('security.require_2fa_for_admins'));
    }
}
