<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ServerResource;
use App\Models\Server;
use Illuminate\Http\JsonResponse;

class ServerController extends Controller
{
    public function index(): JsonResponse
    {
        $servers = Server::active()->visible()->with(['game', 'latestSnapshot'])
            ->paginate(20);

        return ServerResource::collection($servers)
            ->response()
            ->header('Cache-Control', 'public, max-age=30, stale-while-revalidate=60');
    }

    public function show(Server $server): JsonResponse
    {
        abort_unless($server->is_active && $server->is_visible, 404);

        $server->loadMissing(['game', 'latestSnapshot']);

        $etag = '"'.md5((string) ($server->updated_at?->timestamp ?? $server->id)).'"';

        if (request()->header('If-None-Match') === $etag) {
            return response()->json(null, 304);
        }

        return (new ServerResource($server))
            ->response()
            ->header('ETag', $etag)
            ->header('Cache-Control', 'public, max-age=30');
    }

    /** Who is on the server as of its latest query. Empty when it's offline. */
    public function players(Server $server): JsonResponse
    {
        abort_unless($server->is_active && $server->is_visible, 404);

        $snapshot = $server->latestSnapshot()->with('players')->first();
        $players = $snapshot?->is_online ? $snapshot->players : collect();

        return response()->json([
            'data' => $players->sortByDesc('score')->map(fn ($p) => [
                'name' => $p->name,
                'score' => $p->score,
                'duration' => $p->duration,
            ])->values(),
            'meta' => [
                'online' => (bool) $snapshot?->is_online,
                'players' => $snapshot?->players_online ?? 0,
                'max_players' => $snapshot?->players_max ?? 0,
                'recorded_at' => $snapshot?->recorded_at?->toIso8601String(),
            ],
        ])->header('Cache-Control', 'public, max-age=30');
    }
}
