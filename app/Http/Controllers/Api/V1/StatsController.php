<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class StatsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        // Snapshots only change every query cycle (~2 min); same window as the home page.
        $stats = Cache::remember('api.v1.stats', 60, function () {
            $servers = Server::active()->visible()->with('latestSnapshot')->get();
            $online = $servers->filter(fn (Server $s) => (bool) $s->latestSnapshot?->is_online);

            return [
                'members' => User::count(),
                'servers' => $servers->count(),
                'online_servers' => $online->count(),
                'players_online' => $online->sum(fn (Server $s) => $s->latestSnapshot->players_online ?? 0),
                'max_players' => $online->sum(fn (Server $s) => $s->latestSnapshot->players_max ?? 0),
            ];
        });

        return response()->json(['data' => $stats])
            ->header('Cache-Control', 'public, max-age=60');
    }
}
