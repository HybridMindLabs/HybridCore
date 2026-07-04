<?php

namespace App\Http\Middleware;

use App\Services\Bridge\BridgeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a game server calling the bridge API with its bearer token.
 * The resolved Server is attached to the request as "bridgeServer".
 */
class AuthenticateBridgeServer
{
    public function __construct(private readonly BridgeService $bridge) {}

    public function handle(Request $request, Closure $next): Response
    {
        $server = $this->bridge->resolveServer($request->bearerToken());

        if ($server === null) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        // Heartbeat — lets the admin see when the plugin last checked in.
        // saveQuietly: no model events, no cache invalidation storms.
        $server->forceFill(['bridge_last_seen_at' => now()])->saveQuietly();

        $request->attributes->set('bridgeServer', $server);

        return $next($request);
    }
}
