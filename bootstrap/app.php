<?php

use App\Http\Middleware\CheckIpBan;
use App\Http\Middleware\EnsureAppIsInstalled;
use App\Http\Middleware\EnsureIsAdmin;
use App\Http\Middleware\EnsureNotInMaintenance;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectIfInstalled;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackLastSeen;
use App\Http\Middleware\TrackPageView;
use App\Services\Installer\InstallationStateService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            // Installer — accessible only when not yet installed
            Route::middleware(['web', RedirectIfInstalled::class])
                ->prefix('install')
                ->group(base_path('routes/installer.php'));

            // Admin auth (login/logout) — accessible when installed, no auth required
            Route::middleware(['web', EnsureAppIsInstalled::class])
                ->prefix('admin')
                ->group(base_path('routes/admin_auth.php'));

            // Admin panel — requires installation + authentication + admin role
            Route::middleware(['web', EnsureAppIsInstalled::class, 'auth', EnsureIsAdmin::class])
                ->prefix('admin')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        $middleware->web(append: [
            SetLocale::class,
            EnsureNotInMaintenance::class,
            HandleInertiaRequests::class,
            TrackPageView::class,
            TrackLastSeen::class,
            CheckIpBan::class,
        ]);

        // When not authenticated: redirect to admin login if on admin path and app is installed;
        // otherwise redirect to the installer.
        $middleware->redirectGuestsTo(function (Request $request) {
            $installed = app(InstallationStateService::class)->isInstalled();

            if ($installed) {
                return ($request->is('admin') || $request->is('admin/*'))
                    ? route('admin.login')
                    : route('login');
            }

            return route('installer.welcome');
        });

        $middleware->alias([
            'installed' => EnsureAppIsInstalled::class,
            'not_installed' => RedirectIfInstalled::class,
            'admin' => EnsureIsAdmin::class,
            'perm' => EnsurePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            // Precognition validation pings expect JSON regardless of path.
            fn (Request $request) => $request->is('api/*') || $request->isPrecognitive(),
        );
    })->create();
