<?php

namespace App\Http\Middleware;

use App\Services\TwoFactorPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces Admin > Settings > Security's "Require 2FA for admin access"
 * policy — starts the admin's personal grace clock on first touch, then
 * blocks the panel once it elapses. /account stays reachable throughout
 * (it lives outside this route group) so a blocked admin can still set 2FA
 * up and get back in.
 */
class EnsureTwoFactorEnabled
{
    public function __construct(private readonly TwoFactorPolicy $policy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $this->policy->isRequired() || $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        $this->policy->ensureClockStarted($user);

        if ($this->policy->isBlocked($user)) {
            return redirect()->route('account.index', ['tab' => 'security'])
                ->with('error', 'Two-factor authentication is required to keep using the admin panel. Set it up to continue.');
        }

        return $next($request);
    }
}
