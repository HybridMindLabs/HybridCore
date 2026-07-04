<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        @mkdir(storage_path(), 0755, true);
        file_put_contents(storage_path('installed.lock'), 'installed');

        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    public function test_settings_page_is_accessible_to_admin(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/settings');
        $response->assertStatus(200);
    }

    public function test_admin_can_update_settings(): void
    {
        $response = $this->actingAs($this->admin)->put('/admin/settings', [
            'app_name' => 'My Community',
            'app_url' => 'https://example.com',
            'default_locale' => 'en',
            'timezone' => 'UTC',
            'maintenance_mode' => false,
            'active_theme' => 'hybridcore/default',
        ]);

        $response->assertRedirect('/admin/settings');
        $this->assertDatabaseHas('settings', ['key' => 'app_name', 'value' => 'My Community']);
    }

    public function test_settings_update_requires_valid_url(): void
    {
        $response = $this->actingAs($this->admin)->put('/admin/settings', [
            'app_name' => 'Test',
            'app_url' => 'not-a-url',
            'default_locale' => 'en',
            'timezone' => 'UTC',
            'maintenance_mode' => false,
        ]);

        $response->assertSessionHasErrors('app_url');
    }

    public function test_settings_page_not_accessible_to_non_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get('/admin/settings');
        $response->assertRedirect('/admin/login');
    }
}
