<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate on the 'admin.access' permission rather than the raw is_admin flag,
 * so a restricted staff role (admin.access + a handful of perm:* grants, no
 * is_admin) can actually reach the panel — is_admin still passes every check
 * via User::hasPermission()'s super-admin short-circuit.
 */
class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->hasPermission('admin.access')) {
            return redirect()->route('admin.login')
                ->with('error', 'You do not have permission to access the admin panel.');
        }

        return $next($request);
    }
}
