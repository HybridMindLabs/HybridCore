<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Settings-based maintenance mode for the PUBLIC site only.
 * Admins (and the /admin panel, which doesn't use this middleware)
 * are never blocked, so maintenance can always be disabled.
 */
class EnsureNotInMaintenance
{
    public function __construct(private readonly SettingsService $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->settings->get('maintenance_mode', '0') !== '1') {
            return $next($request);
        }

        // Always allow the admin panel (login page included)
        if ($request->is('admin') || $request->is('admin/*')) {
            return $next($request);
        }

        if ($request->user()?->is_admin) {
            return $next($request);
        }

        $message = (string) ($this->settings->get('maintenance_message') ?: 'We are performing scheduled maintenance. We\'ll be back soon.');
        abort(503, $message);
    }
}
