<?php

namespace Hybridcore\Demo\Tests\Feature;

use App\Http\Middleware\EnsureIsAdmin;
use App\Models\ServiceAccount;
use App\Models\User;
use App\Services\Extensions\ExtensionAutoloader;
use Hybridcore\Demo\DemoServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Smoke coverage for the reference extension: confirms every registered
 * piece boots and its routes respond, not that each behaves correctly in
 * every state — that level of coverage belongs to a real extension's own
 * tests, not the SDK reference.
 */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        file_put_contents(storage_path('installed.lock'), 'installed');

        $base = base_path('extensions/hybridcore/demo');
        ExtensionAutoloader::register($base, ['namespace' => 'Hybridcore\\Demo']);

        $this->app->register(DemoServiceProvider::class);

        Route::middleware('web')->group($base.'/routes/web.php');
        Route::middleware(['web', 'auth', EnsureIsAdmin::class])
            ->prefix('admin')->group($base.'/routes/admin.php');
        Route::middleware('api')->prefix('api')->group($base.'/routes/api.php');

        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    public function test_extension_translations_load(): void
    {
        app('translator')->addNamespace('demo', base_path('extensions/hybridcore/demo/resources/lang'));

        $this->assertNotSame('demo::messages.welcome', trans('demo::messages.welcome'));
    }

    /**
     * Not a full HTTP dispatch: in this test's manually-wired route order
     * (registered in setUp, after the app already booted core's routes/web.php)
     * a real request to "/demo" is shadowed by core's CMS page catch-all route
     * — a test-only artifact. In a real request extension routes register
     * during provider boot, before routes/web.php loads, so "/demo" wins;
     * see tests/Feature/Extensions/DemoExtensionTest.php for that coverage.
     */
    public function test_public_web_route_is_registered(): void
    {
        $this->assertTrue(app('router')->has('demo.index'));
    }

    public function test_admin_page_requires_admin_and_then_loads(): void
    {
        $this->get(route('admin.demo.index'))->assertRedirect();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.demo.index'))->assertOk();
    }

    public function test_admin_settings_page_loads_and_saves(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.settings.extensions.demo'))->assertOk();

        $this->actingAs($admin)
            ->patch(route('admin.settings.extensions.demo.update'), [
                'greeting' => 'Yo',
                'show_onboarding_step' => true,
            ])
            ->assertRedirect();
    }

    public function test_notify_action_sends_a_database_notification(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.demo.notify'))->assertRedirect();

        $this->assertSame(1, $admin->fresh()->notifications()->count());
    }

    public function test_account_tab_requires_auth_and_then_loads(): void
    {
        $this->get(route('account.demo.index'))->assertRedirect();

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('account.demo.index'))->assertOk();
    }

    public function test_api_ping_requires_authentication(): void
    {
        $this->getJson('/api/demo/ping')->assertUnauthorized();
    }

    public function test_api_ping_rejects_a_token_without_the_ability(): void
    {
        $account = ServiceAccount::factory()->create();
        $token = $account->createToken('t', [])->plainTextToken;

        $this->withToken($token)->getJson('/api/demo/ping')->assertForbidden();
    }

    public function test_api_ping_accepts_a_token_with_the_ability(): void
    {
        $account = ServiceAccount::factory()->create();
        $token = $account->createToken('t', ['demo:ping'])->plainTextToken;

        $this->withToken($token)->getJson('/api/demo/ping')->assertOk()->assertJson(['message' => 'pong']);
    }
}
