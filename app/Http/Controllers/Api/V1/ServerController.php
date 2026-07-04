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
        $servers = Server::with(['game', 'latestSnapshot'])
            ->paginate(20);

        return ServerResource::collection($servers)
            ->response()
            ->header('Cache-Control', 'public, max-age=30, stale-while-revalidate=60');
    }

    public function show(Server $server): JsonResponse
    {
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
}
