<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Theme;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Services\Themes\ThemeSettingsResolver;
use Database\Seeders\CorePermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        @mkdir(storage_path(), 0755, true);
        file_put_contents(storage_path('installed.lock'), 'installed');

        (new CorePermissionsSeeder)->run();

        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));

        parent::tearDown();
    }

    /** Authenticated staff with admin.access but not themes.manage — mirrors PermissionProtectionTest's setup. */
    private function staffWithoutThemesManage(): User
    {
        $user = User::factory()->create(['is_admin' => false]);

        $role = Role::create(['name' => 'Restricted', 'slug' => 'restricted-'.uniqid()]);
        $ids = Permission::whereIn('slug', ['admin.access'])->pluck('id');
        $role->permissions()->sync($ids);
        $user->roles()->attach($role);

        return $user;
    }

    // ── license_key column ───────────────────────────────────────────────────

    public function test_theme_license_key_is_encrypted_at_rest(): void
    {
        $theme = Theme::factory()->create(['license_key' => 'secret-key-123']);

        $this->assertNotEquals('secret-key-123', $theme->getRawOriginal('license_key'));
        $this->assertEquals('secret-key-123', $theme->fresh()->license_key);
    }

    // ── ThemeSettingsResolver ─────────────────────────────────────────────────

    public function test_effective_returns_schema_defaults_when_no_overrides_exist(): void
    {
        $theme = Theme::factory()->create([
            'metadata' => [
                'settings_schema' => [
                    ['key' => 'accent_color', 'type' => 'color', 'label' => 'Accent', 'group' => 'Colors', 'default' => '#22d3ee'],
                ],
            ],
        ]);

        $resolver = app(ThemeSettingsResolver::class);

        $this->assertSame(['accent_color' => '#22d3ee'], $resolver->effective($theme));
    }

    public function test_effective_applies_theme_setting_override_over_default(): void
    {
        $theme = Theme::factory()->create([
            'metadata' => [
                'settings_schema' => [
                    ['key' => 'accent_color', 'type' => 'color', 'label' => 'Accent', 'group' => 'Colors', 'default' => '#22d3ee'],
                ],
            ],
        ]);

        ThemeSetting::create(['theme_id' => $theme->id, 'key' => 'accent_color', 'value' => '#ff0000', 'type' => 'string']);

        $resolver = app(ThemeSettingsResolver::class);

        $this->assertSame(['accent_color' => '#ff0000'], $resolver->effective($theme));
    }

    public function test_effective_ignores_theme_setting_row_not_in_schema(): void
    {
        // The schema must be non-empty, or effective() returns on the
        // isEmpty() short-circuit without ever reaching the override filter
        // this test exists to exercise.
        $theme = Theme::factory()->create([
            'metadata' => [
                'settings_schema' => [
                    ['key' => 'hc_accent', 'type' => 'color', 'label' => 'Accent', 'group' => 'Colors', 'default' => '#22d3ee'],
                ],
            ],
        ]);

        ThemeSetting::create(['theme_id' => $theme->id, 'key' => 'not_in_schema', 'value' => 'x', 'type' => 'string']);

        $resolver = app(ThemeSettingsResolver::class);

        $this->assertSame(['hc_accent' => '#22d3ee'], $resolver->effective($theme));
    }

    /**
     * theme.json is third-party authored and only name/slug/version are
     * validated at discovery, so a malformed schema must not reach the
     * every-request Inertia share as a TypeError.
     */
    public function test_effective_survives_a_malformed_settings_schema(): void
    {
        $resolver = app(ThemeSettingsResolver::class);

        $object = Theme::factory()->create(['metadata' => ['settings_schema' => ['accent' => '#fff']]]);
        $this->assertSame([], $resolver->effective($object));

        $scalar = Theme::factory()->create(['metadata' => ['settings_schema' => 'nope']]);
        $this->assertSame([], $resolver->effective($scalar));

        $partial = Theme::factory()->create([
            'metadata' => [
                'settings_schema' => [
                    ['label' => 'No key or type'],
                    ['key' => 'hc_accent', 'type' => 'color', 'label' => 'Accent', 'default' => '#22d3ee'],
                ],
            ],
        ]);
        $this->assertSame(['hc_accent' => '#22d3ee'], $resolver->effective($partial));
    }

    public function test_effective_returns_empty_array_when_theme_has_no_schema(): void
    {
        $theme = Theme::factory()->create(['metadata' => null]);

        $resolver = app(ThemeSettingsResolver::class);

        $this->assertSame([], $resolver->effective($theme));
    }

    // ── ThemeController::show() props ────────────────────────────────────────

    public function test_show_exposes_settings_schema_and_effective_values(): void
    {
        $theme = Theme::factory()->create([
            'metadata' => [
                'settings_schema' => [
                    ['key' => 'accent_color', 'type' => 'color', 'label' => 'Accent', 'group' => 'Colors', 'default' => '#22d3ee'],
                ],
            ],
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.themes.show', $theme))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('theme.settings.accent_color', '#22d3ee')
                ->where('theme.has_license', false)
                ->where('theme.requires_license', false)
                ->has('theme.settings_schema', 1)
            );
    }

    public function test_show_reports_has_license_true_when_key_stored(): void
    {
        $theme = Theme::factory()->create([
            'metadata' => ['requires_license' => true],
            'license_key' => 'abc123',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.themes.show', $theme))
            ->assertInertia(fn ($p) => $p
                ->where('theme.has_license', true)
                ->where('theme.requires_license', true)
            );
    }

    // ── settings() ────────────────────────────────────────────────────────────

    public function test_settings_requires_auth(): void
    {
        $theme = Theme::factory()->create();

        $this->post(route('admin.themes.settings', $theme), ['accent_color' => '#ff0000'])
            ->assertRedirect(route('admin.login'));
    }

    public function test_settings_persists_a_valid_color_value(): void
    {
        $theme = Theme::factory()->create([
            'metadata' => [
                'settings_schema' => [
                    ['key' => 'accent_color', 'type' => 'color', 'label' => 'Accent', 'group' => 'Colors', 'default' => '#22d3ee'],
                ],
            ],
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.themes.settings', $theme), ['accent_color' => '#ff0000'])
            ->assertRedirect();

        $this->assertDatabaseHas('theme_settings', [
            'theme_id' => $theme->id,
            'key' => 'accent_color',
            'value' => '#ff0000',
        ]);
    }

    public function test_settings_rejects_invalid_color_value(): void
    {
        $theme = Theme::factory()->create([
            'metadata' => [
                'settings_schema' => [
                    ['key' => 'accent_color', 'type' => 'color', 'label' => 'Accent', 'group' => 'Colors', 'default' => '#22d3ee'],
                ],
            ],
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.themes.settings', $theme), ['accent_color' => 'not-a-color'])
            ->assertSessionHasErrors('accent_color');

        $this->assertDatabaseMissing('theme_settings', ['theme_id' => $theme->id, 'key' => 'accent_color']);
    }

    public function test_settings_ignores_key_not_in_schema(): void
    {
        $theme = Theme::factory()->create(['metadata' => ['settings_schema' => []]]);

        $this->actingAs($this->admin)
            ->post(route('admin.themes.settings', $theme), ['not_in_schema' => 'x'])
            ->assertRedirect();

        $this->assertDatabaseMissing('theme_settings', ['theme_id' => $theme->id, 'key' => 'not_in_schema']);
    }

    public function test_settings_validates_select_against_declared_options(): void
    {
        $theme = Theme::factory()->create([
            'metadata' => [
                'settings_schema' => [
                    ['key' => 'layout_density', 'type' => 'select', 'label' => 'Density', 'group' => 'Content', 'default' => 'comfortable', 'options' => ['compact', 'comfortable']],
                ],
            ],
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.themes.settings', $theme), ['layout_density' => 'roomy'])
            ->assertSessionHasErrors('layout_density');
    }

    // ── license() ────────────────────────────────────────────────────────────

    public function test_license_requires_auth(): void
    {
        $theme = Theme::factory()->create();

        $this->post(route('admin.themes.license', $theme), ['license_key' => 'abc123'])
            ->assertRedirect(route('admin.login'));
    }

    public function test_license_saves_key(): void
    {
        $theme = Theme::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.themes.license', $theme), ['license_key' => 'abc123'])
            ->assertRedirect();

        $this->assertSame('abc123', $theme->fresh()->license_key);
    }

    public function test_license_removes_key_when_submitted_blank(): void
    {
        $theme = Theme::factory()->create(['license_key' => 'abc123']);

        $this->actingAs($this->admin)
            ->post(route('admin.themes.license', $theme), ['license_key' => ''])
            ->assertRedirect();

        $this->assertNull($theme->fresh()->license_key);
    }

    // ── ThemeManager::activate() license gate ───────────────────────────────

    public function test_activate_blocks_licensed_theme_without_key(): void
    {
        $theme = Theme::factory()->create(['metadata' => ['requires_license' => true]]);

        $this->actingAs($this->admin)
            ->post(route('admin.themes.activate', $theme))
            ->assertSessionHasErrors('license');

        $this->assertFalse($theme->fresh()->active);
    }

    public function test_activate_succeeds_once_license_key_is_set(): void
    {
        $theme = Theme::factory()->create([
            'metadata' => ['requires_license' => true],
            'license_key' => 'abc123',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.themes.activate', $theme))
            ->assertRedirect();

        $this->assertTrue($theme->fresh()->active);
    }

    public function test_activate_unaffected_for_theme_without_license_requirement(): void
    {
        $theme = Theme::factory()->create(['metadata' => null]);

        $this->actingAs($this->admin)
            ->post(route('admin.themes.activate', $theme))
            ->assertRedirect();

        $this->assertTrue($theme->fresh()->active);
    }

    public function test_removing_a_license_deactivates_the_running_paid_theme(): void
    {
        $theme = Theme::factory()->create([
            'metadata' => ['requires_license' => true],
            'license_key' => 'abc123',
            'active' => true,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.themes.license', $theme), ['license_key' => ''])
            ->assertRedirect();

        $this->assertFalse($theme->fresh()->active);
    }

    public function test_removing_a_license_leaves_a_free_theme_running(): void
    {
        $theme = Theme::factory()->create(['metadata' => null, 'active' => true]);

        $this->actingAs($this->admin)
            ->post(route('admin.themes.license', $theme), ['license_key' => ''])
            ->assertRedirect();

        $this->assertTrue($theme->fresh()->active);
    }

    // ── themes.manage gate ───────────────────────────────────────────────────

    public function test_settings_requires_themes_manage_permission(): void
    {
        $theme = Theme::factory()->create(['metadata' => ['settings_schema' => []]]);

        $this->actingAs($this->staffWithoutThemesManage())
            ->post(route('admin.themes.settings', $theme), ['hc_accent' => '#ff0000'])
            ->assertForbidden();
    }

    public function test_license_requires_themes_manage_permission(): void
    {
        $theme = Theme::factory()->create();

        $this->actingAs($this->staffWithoutThemesManage())
            ->post(route('admin.themes.license', $theme), ['license_key' => 'abc123'])
            ->assertForbidden();

        $this->assertNull($theme->fresh()->license_key);
    }

    // ── full round trip ──────────────────────────────────────────────────────

    public function test_saved_setting_comes_back_through_show(): void
    {
        $theme = Theme::factory()->create([
            'metadata' => [
                'settings_schema' => [
                    ['key' => 'hc_accent', 'type' => 'color', 'label' => 'Accent', 'group' => 'Colors', 'default' => '#22d3ee'],
                ],
            ],
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.themes.settings', $theme), ['hc_accent' => '#ff0000'])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->get(route('admin.themes.show', $theme))
            ->assertInertia(fn ($p) => $p->where('theme.settings.hc_accent', '#ff0000'));
    }

    public function test_toggle_field_round_trips_through_both_states(): void
    {
        $theme = Theme::factory()->create([
            'metadata' => [
                'settings_schema' => [
                    ['key' => 'show_hero', 'type' => 'toggle', 'label' => 'Show hero', 'group' => 'Content', 'default' => true],
                ],
            ],
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.themes.settings', $theme), ['show_hero' => false])
            ->assertRedirect();

        $this->assertSame(['show_hero' => false], app(ThemeSettingsResolver::class)->effective($theme));

        $this->actingAs($this->admin)
            ->post(route('admin.themes.settings', $theme), ['show_hero' => true])
            ->assertRedirect();

        $this->assertSame(['show_hero' => true], app(ThemeSettingsResolver::class)->effective($theme));
    }
}
