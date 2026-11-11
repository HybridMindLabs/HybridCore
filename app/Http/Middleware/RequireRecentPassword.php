<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Sudo mode" for destructive admin actions: the admin must have re-entered
 * their password within the last few minutes, so an unattended session can't
 * delete users, issue tokens or pull backups.
 *
 * Inertia and fetch() calls get a 423 the frontend answers with a password
 * prompt and then retries; plain page loads (file downloads) are redirected
 * to the confirm page and sent back afterwards.
 */
class RequireRecentPassword
{
    public const SESSION_KEY = 'auth.password_confirmed_at';

    public const TIMEOUT_SECONDS = 900;

    public static function isFresh(Request $request): bool
    {
        return time() - (int) $request->session()->get(self::SESSION_KEY, 0) < self::TIMEOUT_SECONDS;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (self::isFresh($request)) {
            return $next($request);
        }

        if ($request->header('X-Inertia') || $request->expectsJson() || ! $request->isMethod('GET')) {
            return response()->json(['message' => 'Password confirmation required.'], 423);
        }

        return redirect()->guest(route('admin.password.confirm'));
    }
}
