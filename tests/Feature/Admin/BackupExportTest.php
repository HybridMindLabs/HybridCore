<?php

namespace Tests\Feature\Admin;

use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupExportTest extends TestCase
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

    public function test_backup_page_renders(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.backup.index'))
            ->assertOk();
    }

    public function test_settings_export_excludes_sensitive_keys(): void
    {
        Setting::create(['key' => 'app_name', 'value' => 'HybridCore']);
        Setting::create(['key' => 'smtp_password', 'value' => 'super-secret']);
        Setting::create(['key' => 'api_token', 'value' => 'tok-123']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.backup.export.settings'))
            ->assertOk();

        $data = $response->json('data');

        $this->assertArrayHasKey('app_name', $data);
        $this->assertArrayNotHasKey('smtp_password', $data);
        $this->assertArrayNotHasKey('api_token', $data);
        $response->assertDontSee('super-secret');
    }

    public function test_content_export_returns_pages_and_menus(): void
    {
        Page::factory()->published()->create(['title' => 'Exported Page']);

        $this->actingAs($this->admin)
            ->get(route('admin.backup.export.content'))
            ->assertOk()
            ->assertSee('Exported Page');
    }

    public function test_exports_require_authentication(): void
    {
        $this->get(route('admin.backup.export.settings'))
            ->assertRedirect();
    }
}
