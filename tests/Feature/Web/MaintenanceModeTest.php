<?php

namespace Tests\Feature\Web;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MaintenanceModeTest extends TestCase
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

    private function enableMaintenance(string $message = ''): void
    {
        Setting::create(['key' => 'maintenance_mode', 'value' => '1']);
        Setting::create(['key' => 'maintenance_message', 'value' => $message]);
        Cache::forget('hybridcore.settings');
    }

    public function test_public_site_returns_503_in_maintenance(): void
    {
        $this->enableMaintenance();

        $this->get('/')->assertStatus(503);
    }

    public function test_admin_user_bypasses_maintenance_on_public_site(): void
    {
        $this->enableMaintenance();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/')->assertOk();
    }

    public function test_admin_panel_stays_reachable_in_maintenance(): void
    {
        $this->enableMaintenance();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_public_site_accessible_when_maintenance_off(): void
    {
        $this->get('/')->assertOk();
    }
}
