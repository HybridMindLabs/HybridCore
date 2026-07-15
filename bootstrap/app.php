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
use Illuminate\Database\QueryException;
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
            // Installer — accessible only when not yet installed.
            //
            // Every middleware that touches the database is excluded: before
            // installation there is no database to touch, and the installer is
            // precisely the page that must render when the database is absent
            // or misconfigured. None of them are meaningful here anyway —
            // there are no analytics, bans, or maintenance flags to read yet.
            Route::middleware(['web', RedirectIfInstalled::class])
                ->withoutMiddleware([
                    EnsureNotInMaintenance::class,
                    TrackPageView::class,
                    TrackLastSeen::class,
                    CheckIpBan::class,
                ])
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

        // Safety net: before installation, any stray database error becomes a
        // redirect to the installer instead of a raw 500. The installer itself
        // is exempt so its own connection test errors still surface properly.
        $exceptions->render(function (QueryException $e, Request $request) {
            $installed = file_exists(storage_path('installed.lock'))
                || (bool) config('app.installed', false);

            if (! $installed && ! $request->is('install*') && ! $request->expectsJson()) {
                return redirect('/install');
            }

            return null; // default handling
        });
    })->create();
