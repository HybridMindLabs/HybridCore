<?php

namespace App\Http\Middleware;

use App\Services\Installer\InstallationStateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfInstalled
{
    public function __construct(private readonly InstallationStateService $state) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->state->isInstalled()) {
            // Send authenticated admins to the dashboard, everyone else to
            // the admin login. Both targets pass EnsureAppIsInstalled while
            // installed, so a redirect loop is impossible.
            return redirect()->route($request->user()?->is_admin ? 'admin.dashboard' : 'admin.login');
        }

        return $next($request);
    }
}
