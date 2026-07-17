<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\QueryServerJob;
use App\Models\Game;
use App\Models\Server;
use App\Services\ActivityLogService;
use App\Services\Bridge\BridgeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Torann\GeoIP\Facades\GeoIP;

class ServerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $gameId = $request->query('game_id');

        $servers = Server::with(['game', 'latestSnapshot'])
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('ip', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            }))
            ->when($gameId, fn ($q) => $q->where('game_id', $gameId))
            ->orderBy('game_id')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Server $s) => [
                'id' => $s->id,
                'ip' => $s->ip,
                'port' => $s->port,
                'query_port' => $s->query_port,
                'address' => $s->address,
                'name' => $s->name,
                'country_code' => $s->country_code,
                'tags' => $s->tags ?? [],
                'is_active' => $s->is_active,
                'last_queried_at' => $s->last_queried_at?->diffForHumans(),
                'bridge' => [
                    'enabled' => $s->bridge_enabled,
                    'last_seen' => $s->bridge_last_seen_at?->diffForHumans(),
                    'online' => $s->bridge_last_seen_at !== null && $s->bridge_last_seen_at->gt(now()->subMinutes(2)),
                ],
                'game' => ['id' => $s->game->id, 'name' => $s->game->name, 'color' => $s->game->color, 'icon' => $s->game->icon],
                'status' => $s->latestSnapshot ? [
                    'is_online' => $s->latestSnapshot->is_online,
                    'failure_reason' => $s->latestSnapshot->failure_reason,
                    'players_online' => $s->latestSnapshot->players_online,
                    'players_max' => $s->latestSnapshot->players_max,
                    'map' => $s->latestSnapshot->map,
                ] : null,
            ]);

        $games = Game::orderBy('sort_order')->get(['id', 'name']);

        return Inertia::render('Admin/Servers/Index', [
            'servers' => $servers,
            'games' => $games,
            'filters' => ['search' => $search, 'game_id' => $gameId],
        ]);
    }

    /** Generate (or rotate) the bridge token — the plain value is flashed once. */
    public function issueBridgeToken(Request $request, Server $server): RedirectResponse
    {
        $token = app(BridgeService::class)->issueToken($server);

        app(ActivityLogService::class)
            ->log('server.bridge_token_issued', "Issued bridge token for {$server->address}", $server);

        return back()
            ->with('success', 'Bridge token generated — copy it now, it will not be shown again.')
            ->with('bridge_token', $token);
    }

    public function revokeBridgeToken(Server $server): RedirectResponse
    {
        app(BridgeService::class)->revokeToken($server);

        app(ActivityLogService::class)
            ->log('server.bridge_token_revoked', "Revoked bridge token for {$server->address}", $server);

        return back()->with('success', 'Bridge token revoked — the game server can no longer connect.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'game_id' => ['required', 'exists:games,id'],
            'ip' => ['required', 'string', 'max:45'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'query_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:30'],
        ]);

        $data['added_by'] = auth()->id();

        try {
            $location = GeoIP::getLocation($data['ip']);
            if ($location && $location->iso_code !== 'ZZ') {
                $data['country_code'] = strtolower($location->iso_code);
            }
        } catch (\Throwable) {
            // GeoIP unavailable — country_code stays null
        }

        $server = Server::create($data);

        // Trigger first query immediately
        QueryServerJob::dispatch($server)->onQueue('default');

        return back()->with('success', 'Server added. First query dispatched.');
    }

    public function update(Request $request, Server $server): RedirectResponse
    {
        $data = $request->validate([
            'game_id' => ['required', 'exists:games,id'],
            'ip' => ['required', 'string', 'max:45'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'query_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'name' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:30'],
            'is_active' => ['boolean'],
        ]);

        $server->update($data);

        return back()->with('success', 'Server updated.');
    }

    public function refresh(Server $server): RedirectResponse
    {
        QueryServerJob::dispatch($server)->onQueue('default');

        return back()->with('success', 'Query dispatched for '.$server->address);
    }

    public function destroy(Server $server): RedirectResponse
    {
        $server->delete();

        return back()->with('success', 'Server deleted.');
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:activate,deactivate,refresh,delete'],
            'server_ids' => ['required', 'array', 'min:1'],
            'server_ids.*' => ['integer', 'exists:servers,id'],
        ]);

        $servers = Server::whereIn('id', $data['server_ids'])->get();

        foreach ($servers as $server) {
            match ($data['action']) {
                'activate' => $server->update(['is_active' => true]),
                'deactivate' => $server->update(['is_active' => false]),
                'refresh' => QueryServerJob::dispatch($server)->onQueue('default'),
                'delete' => $server->delete(),
            };
        }

        return back()->with('success', ucfirst($data['action']).' applied to '.count($servers).' server(s).');
    }
}
