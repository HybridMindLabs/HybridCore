<?php

namespace App\Http\Middleware;

use App\Events\UserOnlineStatusChanged;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TrackLastSeen
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (Auth::check()) {
            $user = Auth::user();
            if (! $user->last_seen_at || $user->last_seen_at->lt(now()->subMinutes(2))) {
                $wasOffline = ! $user->last_seen_at || $user->last_seen_at->lt(now()->subMinutes(5));

                $user->updateQuietly(['last_seen_at' => now()]);

                if ($wasOffline) {
                    UserOnlineStatusChanged::dispatch($user->id, $user->username, true);
                }
            }
        }

        return $next($request);
    }
}
