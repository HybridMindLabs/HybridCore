<?php

namespace Tests\Feature\Admin;

use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BackupTest extends TestCase
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

        // Clean up any backup files created during tests
        $dir = storage_path('app/backups');
        if (is_dir($dir)) {
            foreach (scandir($dir) as $file) {
                if ($file !== '.' && $file !== '..') {
                    @unlink($dir.'/'.$file);
                }
            }
        }

        parent::tearDown();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_requires_auth(): void
    {
        $this->get(route('admin.backup.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_index_renders_for_admin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.backup.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Admin/System/Backup'));
    }

    public function test_index_passes_counts(): void
    {
        Setting::updateOrCreate(['key' => 'app_name'], ['value' => 'Test']);

        $this->actingAs($this->admin)
            ->get(route('admin.backup.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->has('counts.settings'));
    }

    public function test_index_lists_existing_backup_files(): void
    {
        $dir = storage_path('app/backups');
        @mkdir($dir, 0755, true);
        file_put_contents($dir.'/hybridcore-backup-2026-01-01-000000.json', '{}');

        $this->actingAs($this->admin)
            ->get(route('admin.backup.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->has('backups', 1));
    }

    // ── Export settings ───────────────────────────────────────────────────────

    public function test_export_settings_returns_json(): void
    {
        Setting::updateOrCreate(['key' => 'app_name'], ['value' => 'HybridCore']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.backup.export.settings'));

        $response->assertOk();
        $data = $response->json();
        $this->assertEquals('settings', $data['type']);
    }

    public function test_export_settings_excludes_sensitive_keys(): void
    {
        Setting::updateOrCreate(['key' => 'smtp_password'], ['value' => 'secret123']);
        Setting::updateOrCreate(['key' => 'app_name'], ['value' => 'HybridCore']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.backup.export.settings'));

        $data = $response->json('data');
        $this->assertArrayNotHasKey('smtp_password', $data);
        $this->assertArrayHasKey('app_name', $data);
    }

    public function test_export_settings_excludes_keys_with_token(): void
    {
        Setting::updateOrCreate(['key' => 'steam_api_token'], ['value' => 'abc123']);
        Setting::updateOrCreate(['key' => 'site_title'], ['value' => 'My Site']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.backup.export.settings'));

        $data = $response->json('data');
        $this->assertArrayNotHasKey('steam_api_token', $data);
        $this->assertArrayHasKey('site_title', $data);
    }

    // ── Export extensions / themes / content ──────────────────────────────────

    public function test_export_extensions_returns_json(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.backup.export.extensions'));

        $response->assertOk();
        $this->assertEquals('extensions', $response->json('type'));
    }

    public function test_export_themes_returns_json(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.backup.export.themes'));

        $response->assertOk();
        $this->assertEquals('themes', $response->json('type'));
    }

    public function test_export_content_includes_pages_and_menus(): void
    {
        Page::create(['title' => 'About', 'slug' => 'about', 'body' => 'Content', 'status' => 'published']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.backup.export.content'));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('pages', $data);
        $this->assertArrayHasKey('menus', $data);
        $this->assertCount(1, $data['pages']);
    }

    // ── Generate full backup ──────────────────────────────────────────────────

    public function test_generate_backup_returns_full_json_with_type_full(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.backup.export.all'));

        $response->assertOk();
        $this->assertEquals('full', $response->json('type'));
    }

    public function test_generate_backup_saves_file_to_disk(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.backup.export.all'));

        $dir = storage_path('app/backups');
        $files = glob($dir.'/hybridcore-backup-*.json');
        $this->assertNotEmpty($files);
    }

    public function test_generate_backup_does_not_include_sensitive_settings(): void
    {
        Setting::updateOrCreate(['key' => 'smtp_secret'], ['value' => 'secret']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.backup.export.all'));

        $settings = $response->json('data.settings');
        $this->assertArrayNotHasKey('smtp_secret', $settings);
    }

    // ── Delete backup ─────────────────────────────────────────────────────────

    public function test_delete_backup_removes_file(): void
    {
        $dir = storage_path('app/backups');
        @mkdir($dir, 0755, true);
        $filename = 'hybridcore-backup-test.json';
        file_put_contents($dir.'/'.$filename, '{}');

        $this->actingAs($this->admin)
            ->delete(route('admin.backup.delete', $filename))
            ->assertRedirect();

        $this->assertFileDoesNotExist($dir.'/'.$filename);
    }

    public function test_delete_backup_is_idempotent_for_missing_file(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.backup.delete', 'nonexistent.json'))
            ->assertRedirect();
    }

    public function test_delete_backup_prevents_path_traversal(): void
    {
        // Laravel's router rejects URLs with path traversal — 404 is the expected security response
        $this->actingAs($this->admin)
            ->delete(route('admin.backup.delete', '../../../etc/passwd'))
            ->assertNotFound();
    }

    // ── Download backup ───────────────────────────────────────────────────────

    public function test_download_backup_streams_file(): void
    {
        $dir = storage_path('app/backups');
        @mkdir($dir, 0755, true);
        $filename = 'hybridcore-backup-test.json';
        file_put_contents($dir.'/'.$filename, '{"type":"full"}');

        $this->actingAs($this->admin)
            ->get(route('admin.backup.download', $filename))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_download_backup_404_for_missing_file(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.backup.download', 'nonexistent.json'))
            ->assertNotFound();
    }

    // ── Import backup ─────────────────────────────────────────────────────────

    public function test_import_restores_settings(): void
    {
        $payload = json_encode([
            'type' => 'full',
            'exported_at' => now()->toIso8601String(),
            'data' => [
                'settings' => ['site_title' => 'Restored Title'],
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('backup.json', $payload);

        $this->actingAs($this->admin)
            ->post(route('admin.backup.import'), ['backup_file' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('settings', ['key' => 'site_title', 'value' => 'Restored Title']);
    }

    public function test_import_skips_sensitive_settings(): void
    {
        $payload = json_encode([
            'type' => 'full',
            'exported_at' => now()->toIso8601String(),
            'data' => [
                'settings' => [
                    'smtp_password' => 'should-be-skipped',
                    'app_name' => 'Should Be Restored',
                ],
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('backup.json', $payload);

        $this->actingAs($this->admin)
            ->post(route('admin.backup.import'), ['backup_file' => $file]);

        $this->assertDatabaseMissing('settings', ['key' => 'smtp_password']);
        $this->assertDatabaseHas('settings', ['key' => 'app_name', 'value' => 'Should Be Restored']);
    }

    public function test_import_restores_pages(): void
    {
        $payload = json_encode([
            'type' => 'full',
            'exported_at' => now()->toIso8601String(),
            'data' => [
                'content' => [
                    'pages' => [
                        ['slug' => 'about', 'title' => 'About Us', 'body' => 'Content', 'status' => 'published'],
                    ],
                ],
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('backup.json', $payload);

        $this->actingAs($this->admin)
            ->post(route('admin.backup.import'), ['backup_file' => $file]);

        $this->assertDatabaseHas('pages', ['slug' => 'about', 'title' => 'About Us']);
    }

    public function test_import_rejects_non_json_file(): void
    {
        $file = UploadedFile::fake()->create('backup.txt', 100, 'text/plain');

        $this->actingAs($this->admin)
            ->post(route('admin.backup.import'), ['backup_file' => $file])
            ->assertSessionHasErrors('backup_file');
    }

    public function test_import_rejects_invalid_backup_structure(): void
    {
        $payload = json_encode(['type' => 'unknown', 'data' => []]);
        $file = UploadedFile::fake()->createWithContent('backup.json', $payload);

        $this->actingAs($this->admin)
            ->post(route('admin.backup.import'), ['backup_file' => $file])
            ->assertStatus(422);
    }
}
