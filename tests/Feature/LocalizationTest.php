<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\Localization\LocaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LocalizationTest extends TestCase
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

    private function setSetting(string $key, string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('hybridcore.settings');
    }

    public function test_default_locale_is_applied(): void
    {
        $this->get('/');

        $this->assertSame('en', app()->getLocale());
    }

    public function test_configured_default_locale_is_applied(): void
    {
        $this->setSetting('localization.default_locale', 'bg');
        $this->setSetting('localization.supported_locales', 'en,bg');

        $this->get('/');

        $this->assertSame('bg', app()->getLocale());
    }

    public function test_unsupported_locale_is_rejected_on_switch(): void
    {
        $this->post('/locale', ['locale' => 'xx'])
            ->assertSessionHasErrors('locale');
    }

    public function test_locale_can_be_switched_and_redirects_back(): void
    {
        $this->from('/servers')
            ->post('/locale', ['locale' => 'bg'])
            ->assertRedirect('/servers');

        $this->assertSame('bg', session('locale'));
    }

    public function test_session_locale_is_used_for_guest(): void
    {
        $this->withSession(['locale' => 'bg'])->get('/');

        $this->assertSame('bg', app()->getLocale());
    }

    public function test_authenticated_user_locale_is_saved_and_used(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $this->actingAs($user)->post('/locale', ['locale' => 'bg']);

        $this->assertSame('bg', $user->fresh()->locale);

        // User preference wins on next request even without session.
        $this->actingAs($user->fresh())->get('/');
        $this->assertSame('bg', app()->getLocale());
    }

    public function test_unsupported_user_locale_falls_back_to_default(): void
    {
        $user = User::factory()->create(['locale' => 'xx']);

        $this->actingAs($user)->get('/');

        $this->assertSame('en', app()->getLocale());
    }

    public function test_inertia_receives_localization_props(): void
    {
        $this->get('/')->assertInertia(fn ($page) => $page
            ->has('localization.currentLocale')
            ->has('localization.supportedLocales')
            ->has('localization.fallbackLocale')
            ->has('localization.localeDirection')
            ->has('localization.languageSwitcherEnabled')
            ->where('localization.currentLocale', 'en')
        );
    }

    public function test_admin_localization_settings_can_be_updated(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'app_name' => 'HybridCore',
            'app_url' => 'https://example.com',
            'default_locale' => 'en',
            'timezone' => 'UTC',
            'maintenance_mode' => false,
            'loc_default_locale' => 'bg',
            'loc_fallback_locale' => 'en',
            'loc_supported_locales' => 'en,bg',
            'loc_public_switcher' => false,
            'loc_admin_switcher' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('settings', ['key' => 'localization.default_locale', 'value' => 'bg']);
        $this->assertDatabaseHas('settings', ['key' => 'localization.public_switcher_enabled', 'value' => '0']);
    }

    public function test_admin_locale_route_works(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->from('/admin')
            ->post('/admin/locale', ['locale' => 'bg'])
            ->assertRedirect('/admin');

        $this->assertSame('bg', $admin->fresh()->locale);
    }

    public function test_locale_service_rejects_unknown_codes_in_supported_list(): void
    {
        $this->setSetting('localization.supported_locales', 'en,zz,bg');

        $codes = app(LocaleService::class)->supportedCodes();

        $this->assertSame(['en', 'bg'], $codes);
    }

    public function test_navigation_labels_are_translated(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'locale' => 'bg']);

        $this->actingAs($admin)->get('/admin')->assertInertia(fn ($page) => $page
            ->where('adminNav.0.items.0.label', 'Табло')
        );
    }

    public function test_extension_translation_namespace_can_be_loaded(): void
    {
        // Simulate what ExtensionServiceProvider does for an enabled extension
        // with resources/lang in its manifest (Example extension fixture).
        app('translator')->addNamespace('example', base_path('extensions/HybridCore/Example/resources/lang'));

        $this->assertSame('Hello from the Example extension!', trans('example::messages.hello'));

        app()->setLocale('bg');
        $this->assertSame('Здравей от примерното разширение!', trans('example::messages.hello'));
    }
}
