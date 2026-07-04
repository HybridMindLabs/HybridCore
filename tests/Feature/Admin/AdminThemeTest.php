<?php

namespace Tests\Feature\Admin;

use App\Models\Theme;
use App\Models\User;
use App\Services\Themes\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class AdminThemeTest extends TestCase
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

    public function test_theme_index_requires_admin(): void
    {
        $guest = User::factory()->create(['is_admin' => false]);
        $this->actingAs($guest)->get('/admin/themes')->assertRedirect('/admin/login');
    }

    public function test_theme_index_accessible_to_admin(): void
    {
        $this->actingAs($this->admin)->get('/admin/themes')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Admin/Themes/Index')->has('themes'));
    }

    public function test_show_returns_theme_detail(): void
    {
        $theme = Theme::factory()->create();
        $this->actingAs($this->admin)
            ->get("/admin/themes/{$theme->id}")
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Themes/Show')
                ->has('theme')
                ->where('theme.id', $theme->id)
            );
    }

    public function test_sync_discovers_and_stores_themes(): void
    {
        $this->mock(ThemeManager::class, function (MockInterface $mock) {
            $mock->shouldReceive('sync')->once()->andReturn(1);
        });
        $this->actingAs($this->admin)->post('/admin/themes/sync')
            ->assertRedirect('/admin/themes')
            ->assertSessionHas('success');
    }

    public function test_activate_sets_theme_active(): void
    {
        Theme::query()->update(['active' => false]);
        $theme = Theme::factory()->create(['active' => false]);
        $this->actingAs($this->admin)
            ->post("/admin/themes/{$theme->id}/activate")
            ->assertRedirect('/admin/themes')
            ->assertSessionHas('success');
        $this->assertDatabaseHas('themes', ['id' => $theme->id, 'active' => true]);
    }

    public function test_activate_deactivates_previous_active_theme(): void
    {
        $old = Theme::factory()->create(['active' => true]);
        $new = Theme::factory()->create(['active' => false]);
        $this->actingAs($this->admin)->post("/admin/themes/{$new->id}/activate");
        $this->assertDatabaseHas('themes', ['id' => $old->id, 'active' => false]);
        $this->assertDatabaseHas('themes', ['id' => $new->id, 'active' => true]);
    }

    public function test_deactivate_removes_active_flag(): void
    {
        $theme = Theme::factory()->create(['active' => true]);
        $this->actingAs($this->admin)
            ->post("/admin/themes/{$theme->id}/deactivate")
            ->assertRedirect('/admin/themes')
            ->assertSessionHas('success');
        $this->assertDatabaseHas('themes', ['id' => $theme->id, 'active' => false]);
    }

    public function test_inertia_shared_props_include_theme_object(): void
    {
        Theme::factory()->create(['active' => true, 'slug' => 'default', 'metadata' => [
            'settings' => ['accent_color' => '#22d3ee'],
        ]]);
        $this->actingAs($this->admin)->get('/admin/themes')
            ->assertInertia(fn ($page) => $page
                ->where('app.theme.slug', 'default')
                ->has('app.theme.settings')
            );
    }
}
