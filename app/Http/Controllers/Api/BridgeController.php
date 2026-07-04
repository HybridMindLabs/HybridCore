<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Models\ServerCommand;
use App\Services\Bridge\BridgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bridge API consumed by the in-game plugin (AMXX/SourceMod/…).
 *
 *   POST /api/bridge/poll  → deliver the next command batch (also heartbeat)
 *   POST /api/bridge/ack   → confirm executed command ids
 *
 * Authenticated by AuthenticateBridgeServer (per-server bearer token).
 */
class BridgeController extends Controller
{
    public function __construct(private readonly BridgeService $bridge) {}

    public function poll(Request $request): JsonResponse
    {
        /** @var Server $server */
        $server = $request->attributes->get('bridgeServer');

        $commands = $this->bridge->pull($server);

        return response()->json([
            'server' => $server->only('id', 'name'),
            'commands' => $commands->map(fn (ServerCommand $c) => [
                'id' => $c->id,
                'command' => $c->command,
            ])->values(),
        ]);
    }

    public function ack(Request $request): JsonResponse
    {
        /** @var Server $server */
        $server = $request->attributes->get('bridgeServer');

        $data = $request->validate([
            'ids' => ['required', 'array', 'max:'.BridgeService::PULL_LIMIT],
            'ids.*' => ['integer'],
        ]);

        $acked = $this->bridge->ack($server, array_map('intval', $data['ids']));

        return response()->json(['acked' => $acked]);
    }
}
