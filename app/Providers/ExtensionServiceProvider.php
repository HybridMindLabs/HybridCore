<?php

namespace App\Providers;

use App\Http\Middleware\EnsureIsAdmin;
use App\Models\Extension;
use App\Services\Auth\OAuthProviderRegistry;
use App\Services\Extensions\ExtensionAutoloader;
use App\Services\Extensions\Registries\AccountTabRegistry;
use App\Services\Extensions\Registries\ExtensionRegistry;
use App\Services\Extensions\Registries\FilterRegistry;
use App\Services\Extensions\Registries\FooterLinkRegistry;
use App\Services\Extensions\Registries\HookRegistry;
use App\Services\Extensions\Registries\NavigationRegistry;
use App\Services\Extensions\Registries\PermissionRegistry;
use App\Services\Extensions\Registries\ProfileTabRegistry;
use App\Services\Extensions\Registries\PublicNavigationRegistry;
use App\Services\Extensions\Registries\QuickActionRegistry;
use App\Services\Extensions\Registries\SearchProviderRegistry;
use App\Services\Extensions\Registries\SettingsRegistry;
use App\Services\Extensions\Registries\SlotRegistry;
use App\Services\Extensions\Registries\UserMenuRegistry;
use App\Services\Extensions\Registries\WidgetRegistry;
use App\Support\CoreNavigation;
use App\Support\CorePermissions;
use App\Support\CoreWidgets;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Boots the Extension SDK.
 *
 * Registry singletons are always available. Enabled extensions may declare,
 * in their extension.json manifest:
 *
 *   "routes":     { "web": "routes/web.php", "admin": "routes/admin.php" }
 *   "migrations": "database/migrations"
 *   "provider":   "Vendor\\Name\\NameServiceProvider"
 *
 * Route files and providers are PHP and only ever loaded for extensions the
 * administrator explicitly enabled. Discovery itself stays JSON-only.
 */
class ExtensionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NavigationRegistry::class);
        $this->app->singleton(PublicNavigationRegistry::class);
        $this->app->singleton(AccountTabRegistry::class);
        $this->app->singleton(ProfileTabRegistry::class);
        $this->app->singleton(UserMenuRegistry::class);
        $this->app->singleton(SearchProviderRegistry::class);
        $this->app->singleton(FooterLinkRegistry::class);
        $this->app->singleton(QuickActionRegistry::class);
        $this->app->singleton(WidgetRegistry::class);
        $this->app->singleton(PermissionRegistry::class);
        $this->app->singleton(SettingsRegistry::class);
        $this->app->singleton(SlotRegistry::class);
        $this->app->singleton(HookRegistry::class);
        $this->app->singleton(FilterRegistry::class);
        $this->app->singleton(ExtensionRegistry::class);
        $this->app->singleton(OAuthProviderRegistry::class);
    }

    public function boot(): void
    {
        $registry = $this->app->make(ExtensionRegistry::class);

        // Core registrations first — extensions append after.
        $registry->permissions()->registerMany(CorePermissions::ALL);
        CoreNavigation::register($registry->navigation());
        CoreWidgets::register($registry->widgets());

        $this->bootEnabledExtensions($registry);
    }

    private function bootEnabledExtensions(ExtensionRegistry $registry): void
    {
        try {
            if (! Schema::hasTable('extensions')) {
                return;
            }

            $enabled = Extension::where('enabled', true)->get();
        } catch (Throwable) {
            return; // No DB during install — extensions simply don't load.
        }

        foreach ($enabled as $extension) {
            $base = base_path('extensions/'.$extension->path);

            if (! is_dir($base)) {
                continue;
            }

            $manifest = $extension->metadata ?? [];

            try {
                ExtensionAutoloader::register($base, $manifest);
                $this->loadExtensionRoutes($base, $manifest);
                $this->loadExtensionMigrations($base, $manifest);
                $this->loadExtensionTranslations($base, $manifest);
                $this->loadExtensionConfig($base, $manifest);
                $this->loadExtensionViews($base, $manifest);
                $this->loadExtensionSchedule($base, $manifest);
                $this->registerExtensionCommands($manifest);
                $this->registerExtensionProvider($base, $manifest);
            } catch (Throwable $e) {
                // A broken extension must never take down the platform.
                // Log class + message only — never manifest values.
                Log::warning('Extension failed to boot', [
                    'path' => $extension->path,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);

                continue;
            }
        }

        // Route names are assigned fluently after add(); refresh the lookup
        // tables so named extension routes resolve even when extensions are
        // (re)booted after the application has already booted.
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['router']->getRoutes()->refreshActionLookups();

        unset($registry);
    }

    /**
     * Load the extension's scheduled tasks file (manifest "schedule",
     * e.g. "routes/schedule.php") — console only, since that's when the
     * scheduler evaluates its entries. The file uses the Schedule facade.
     *
     * @param  array<string, mixed>  $manifest
     */
    private function loadExtensionSchedule(string $base, array $manifest): void
    {
        $file = $manifest['schedule'] ?? null;

        if (! $this->app->runningInConsole() || ! is_string($file) || $file === '') {
            return;
        }

        $path = $base.'/'.$file;

        if (is_file($path) && $this->isInside($base, $path)) {
            require $path;
        }
    }

    /** @param array<string, mixed> $manifest */
    private function loadExtensionRoutes(string $base, array $manifest): void
    {
        $routes = $manifest['routes'] ?? [];

        if (isset($routes['web']) && $this->isInside($base, $base.'/'.$routes['web'])) {
            Route::middleware('web')->group($base.'/'.$routes['web']);
        }

        if (isset($routes['admin']) && $this->isInside($base, $base.'/'.$routes['admin'])) {
            Route::middleware(['web', 'auth', EnsureIsAdmin::class])
                ->prefix('admin')
                ->group($base.'/'.$routes['admin']);
        }

        // Stateless JSON routes under /api — auth is the extension's choice
        // per route (auth:sanctum, custom token middleware, or public).
        if (isset($routes['api']) && $this->isInside($base, $base.'/'.$routes['api'])) {
            Route::middleware('api')
                ->prefix('api')
                ->group($base.'/'.$routes['api']);
        }
    }

    /**
     * Merge the extension's config file as config('ext.{namespace}.*').
     *
     * @param  array<string, mixed>  $manifest
     */
    private function loadExtensionConfig(string $base, array $manifest): void
    {
        $file = $manifest['config'] ?? null;

        if (! is_string($file) || $file === '') {
            return;
        }

        $path = $base.'/'.$file;

        if (is_file($path) && $this->isInside($base, $path)) {
            $this->mergeConfigFrom($path, 'ext.'.$this->namespaceSlug($manifest));
        }
    }

    /**
     * Register the extension's Blade views under its namespace — mainly for
     * mail templates: view('store::mail.receipt').
     *
     * @param  array<string, mixed>  $manifest
     */
    private function loadExtensionViews(string $base, array $manifest): void
    {
        $dir = is_string($manifest['views'] ?? null) ? $manifest['views'] : 'resources/views';
        $path = $base.'/'.$dir;

        if (is_dir($path) && $this->isInside($base, $path)) {
            $this->loadViewsFrom($path, $this->namespaceSlug($manifest));
        }
    }

    /**
     * Register the extension's Artisan commands (manifest "commands": [FQCN]).
     *
     * @param  array<string, mixed>  $manifest
     */
    private function registerExtensionCommands(array $manifest): void
    {
        $commands = $manifest['commands'] ?? [];

        if (! $this->app->runningInConsole() || ! is_array($commands)) {
            return;
        }

        $classes = array_values(array_filter($commands, fn ($c) => is_string($c) && class_exists($c)));

        if ($classes !== []) {
            $this->commands($classes);
        }
    }

    /** Namespace slug shared by lang/config/views: "hybridcore/store" -> "store". */
    private function namespaceSlug(array $manifest): string
    {
        return is_string($manifest['lang_namespace'] ?? null) && $manifest['lang_namespace'] !== ''
            ? $manifest['lang_namespace']
            : (string) str(($manifest['slug'] ?? 'extension'))->afterLast('-')->afterLast('/');
    }

    /** @param array<string, mixed> $manifest */
    private function loadExtensionMigrations(string $base, array $manifest): void
    {
        $dir = $manifest['migrations'] ?? null;

        if (is_string($dir) && $this->isInside($base, $base.'/'.$dir) && is_dir($base.'/'.$dir)) {
            $this->loadMigrationsFrom($base.'/'.$dir);
        }
    }

    /** @param array<string, mixed> $manifest */
    private function registerExtensionProvider(string $base, array $manifest): void
    {
        $provider = $manifest['provider'] ?? null;

        if (! is_string($provider) || $provider === '') {
            return;
        }

        // Providers are expected to be autoloadable (composer or extension autoload).
        if (class_exists($provider)) {
            $this->app->register($provider);
        }
    }

    /**
     * Load extension translations under a namespace.
     *
     * Manifest keys:
     *   "lang":           directory, default "resources/lang"
     *   "lang_namespace": namespace, default = slug part after the vendor
     *                     ("hybridcore-store" -> "store")
     *
     * Usage: trans('store::messages.product_created')
     *
     * @param  array<string, mixed>  $manifest
     */
    private function loadExtensionTranslations(string $base, array $manifest): void
    {
        $dir = is_string($manifest['lang'] ?? null) ? $manifest['lang'] : 'resources/lang';
        $path = $base.'/'.$dir;

        if (! is_dir($path) || ! $this->isInside($base, $path)) {
            return;
        }

        $namespace = is_string($manifest['lang_namespace'] ?? null) && $manifest['lang_namespace'] !== ''
            ? $manifest['lang_namespace']
            : (string) str(($manifest['slug'] ?? 'extension'))->afterLast('-')->afterLast('/');

        $this->loadTranslationsFrom($path, $namespace);
    }

    /** Path traversal guard: resolved path must stay inside the extension dir. */
    private function isInside(string $base, string $path): bool
    {
        $real = realpath($path);
        $realBase = realpath($base);

        return $real !== false && $realBase !== false && str_starts_with($real, $realBase);
    }
}
