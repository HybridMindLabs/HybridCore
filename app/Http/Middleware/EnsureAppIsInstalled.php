<?php

namespace App\Http\Middleware;

use App\Services\Installer\InstallationStateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppIsInstalled
{
    public function __construct(private readonly InstallationStateService $state) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->state->isInstalled()) {
            return redirect()->route('installer.welcome');
        }

        return $next($request);
    }
}
