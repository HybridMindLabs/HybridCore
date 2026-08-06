<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\QueryServerJob;
use App\Models\Game;
use App\Models\Server;
use App\Models\ServerCommand;
use App\Services\ActivityLogService;
use App\Services\Bridge\BridgeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Torann\GeoIP\Facades\GeoIP;

class ServerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $gameId = $request->query('game_id');

        $filter = fn ($q) => $q
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('ip', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            }))
            ->when($gameId, fn ($q) => $q->where('game_id', $gameId));

        // Stats must reflect every server matching the current filters, not just
        // the 20 on this page — computed from the same filtered set, one extra
        // query, before pagination narrows it down.
        $filteredWithStatus = $filter(Server::query())->with('latestSnapshot')->get();
        $stats = [
            'total' => $filteredWithStatus->count(),
            'online' => $filteredWithStatus->filter(fn (Server $s) => $s->latestSnapshot?->is_online)->count(),
            'players' => $filteredWithStatus->sum(fn (Server $s) => $s->latestSnapshot->players_online ?? 0),
        ];

        $servers = $filter(Server::query())
            ->with([
                'game',
                'latestSnapshot',
                'bridgeCommands' => fn ($q) => $q->orderByDesc('created_at')->orderByDesc('id')->limit(10),
            ])
            ->withCount(['bridgeCommands as pending_command_count' => fn ($q) => $q->where('status', ServerCommand::STATUS_PENDING)])
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
                    'pending_count' => $s->pending_command_count,
                ],
                'commands' => $s->bridgeCommands->map(fn (ServerCommand $c) => [
                    'id' => $c->id,
                    'command' => $c->command,
                    'source' => $c->source,
                    'status' => $c->status,
                    'attempts' => $c->attempts,
                    'created_at' => $c->created_at?->diffForHumans(),
                    'delivered_at' => $c->delivered_at?->diffForHumans(),
                    'acked_at' => $c->acked_at?->diffForHumans(),
                    'expires_at' => $c->expires_at?->diffForHumans(),
                ])->values()->all(),
                'game' => ['id' => $s->game->id, 'name' => $s->game->name, 'slug' => $s->game->slug, 'color' => $s->game->color, 'icon' => $s->game->icon],
                'status' => $s->latestSnapshot ? [
                    'is_online' => $s->latestSnapshot->is_online,
                    'failure_reason' => $s->latestSnapshot->failure_reason,
                    'players_online' => $s->latestSnapshot->players_online,
                    'players_max' => $s->latestSnapshot->players_max,
                    'map' => $s->latestSnapshot->map,
                ] : null,
            ]);

        $games = Game::orderBy('sort_order')->get(['id', 'name', 'default_port', 'default_query_port']);

        return Inertia::render('Admin/Servers/Index', [
            'servers' => $servers,
            'games' => $games,
            'filters' => ['search' => $search, 'game_id' => $gameId],
            'stats' => $stats,
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

    /** Cancel a still-pending bridge command so it's never delivered — the source shows whether core or an extension queued it. */
    public function cancelCommand(Server $server, ServerCommand $command): RedirectResponse
    {
        abort_unless($command->server_id === $server->id, 404);

        if (! app(BridgeService::class)->cancel($server, $command)) {
            return back()->with('error', 'Only pending commands can be cancelled.');
        }

        app(ActivityLogService::class)
            ->log('server.command_cancelled', "Cancelled a queued command ({$command->source}) for {$server->address}", $server);

        return back()->with('success', 'Command cancelled.');
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
            'ip' => [
                'required', 'string', 'max:45',
                Rule::unique('servers')->where(fn ($q) => $q->where('port', $request->input('port'))),
            ],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'query_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:30'],
        ], [
            'ip.unique' => 'A server with this IP and port already exists.',
        ]);

        $data['added_by'] = auth()->id();

        try {
            $location = GeoIP::getLocation($data['ip']);
            if ($location->iso_code !== 'ZZ') {
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
            'ip' => [
                'required', 'string', 'max:45',
                Rule::unique('servers')->ignore($server->id)->where(fn ($q) => $q->where('port', $request->input('port'))),
            ],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'query_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'name' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:30'],
            'is_active' => ['boolean'],
        ], [
            'ip.unique' => 'A server with this IP and port already exists.',
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
                default => throw new \InvalidArgumentException("Unknown bulk action: {$data['action']}"),
            };
        }

        return back()->with('success', ucfirst($data['action']).' applied to '.count($servers).' server(s).');
    }
}
