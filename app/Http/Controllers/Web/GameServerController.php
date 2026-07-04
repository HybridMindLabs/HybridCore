<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Server;
use App\Models\ServerConnectionClick;
use App\Models\ServerReview;
use App\Services\AchievementService;
use App\Services\Extensions\Registries\FilterRegistry;
use App\Support\Filters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class GameServerController extends Controller
{
    public function __construct(private readonly AchievementService $achievements) {}

    public function index(): Response
    {
        // No per-user data here — safe to share across visitors. Snapshot
        // data refreshes every ~2 min, so 60s staleness is invisible.
        ['games' => $games, 'totals' => $totals] = Cache::remember('servers.index_overview', 60, function () {
            $games = Game::active()
                ->withCount(['activeServers as servers_count'])
                ->with(['activeServers' => fn ($q) => $q->with('latestSnapshot')])
                ->get()
                ->map(fn (Game $g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'slug' => $g->slug,
                    'icon' => $g->icon,
                    'cover_url' => $g->cover_url,
                    'color' => $g->color,
                    'servers_count' => $g->servers_count,
                    'players_online' => $g->activeServers->sum(
                        fn ($s) => $s->latestSnapshot?->is_online ? $s->latestSnapshot->players_online : 0
                    ),
                    'online_servers' => $g->activeServers->filter(
                        fn ($s) => $s->latestSnapshot?->is_online
                    )->count(),
                ]);

            return [
                'games' => $games->values()->all(),
                'totals' => [
                    'games' => $games->count(),
                    'servers' => $games->sum('servers_count'),
                    'players' => $games->sum('players_online'),
                    'online_servers' => $games->sum('online_servers'),
                ],
            ];
        });

        return Inertia::render('Web/Servers/Index', [
            'games' => $games,
            'totals' => $totals,
        ]);
    }

    public function game(Game $game): Response
    {
        $servers = Server::active()
            ->where('game_id', $game->id)
            ->with(['latestSnapshot'])
            ->withExists(['favouritedBy as is_favourited' => fn ($q) => $q->where('user_id', auth()->id())])
            ->get()
            ->map(fn (Server $s) => $this->formatServer($s));

        $onlineServers = $servers->filter(fn ($s) => $s['status']['is_online'] ?? false);

        return Inertia::render('Web/Servers/Game', [
            'game' => [
                'id' => $game->id,
                'name' => $game->name,
                'slug' => $game->slug,
                'icon' => $game->icon,
                'color' => $game->color,
                'cover_url' => $game->cover_url,
            ],
            'servers' => $servers->values(),
            'stats' => [
                'total' => $servers->count(),
                'online' => $onlineServers->count(),
                'players' => $onlineServers->sum(fn ($s) => $s['status']['players_online'] ?? 0),
            ],
        ]);
    }

    public function show(Game $game, string $ip, int $port): Response
    {
        $server = Server::where('game_id', $game->id)
            ->where('ip', $ip)
            ->where('port', $port)
            ->firstOrFail();

        $snapshot = $server->latestSnapshot()->with('players')->first();

        // Player history last 24h (one per 10 min bucket)
        $history = $server->snapshots()
            ->where('recorded_at', '>=', now()->subHours(24))
            ->orderBy('recorded_at')
            ->get(['players_online', 'players_max', 'is_online', 'recorded_at'])
            ->map(fn ($s) => [
                'time' => $s->recorded_at->format('H:i'),
                'players' => $s->is_online ? $s->players_online : null,
                'max' => $s->players_max,
            ]);

        $stats = [
            'total_clicks' => $server->connectionClicks()->count(),
            'clicks_today' => $server->connectionClicks()->whereDate('created_at', today())->count(),
            'peak_players' => $server->snapshots()->max('players_online') ?? 0,
            'uptime_24h' => $this->uptimePercent($server),
        ];

        // Daily uptime for the last 30 days (snapshots are pruned at 30d).
        // DATE()/AVG() are portable across MariaDB and SQLite.
        $dailyRows = $server->snapshots()
            ->where('recorded_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(recorded_at) as day, ROUND(AVG(is_online) * 100) as pct')
            ->groupBy('day')
            ->pluck('pct', 'day');

        $uptimeDaily = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $uptimeDaily[] = [
                'date' => $day,
                'pct' => isset($dailyRows[$day]) ? (int) $dailyRows[$day] : null,
            ];
        }

        $window = fn (int $days) => collect($uptimeDaily)
            ->slice(30 - $days)
            ->pluck('pct')
            ->filter(fn ($p) => $p !== null);

        $stats['uptime_7d'] = $window(7)->isEmpty() ? null : (int) round($window(7)->avg());
        $stats['uptime_30d'] = $window(30)->isEmpty() ? null : (int) round($window(30)->avg());

        $userReview = auth()->check()
            ? $server->serverReviews()->where('user_id', auth()->id())->first()
            : null;

        return Inertia::render('Web/Servers/Show', app(FilterRegistry::class)->apply(Filters::SERVER_SHOW_PROPS, [
            'game' => ['id' => $game->id, 'name' => $game->name, 'slug' => $game->slug, 'icon' => $game->icon, 'color' => $game->color, 'cover_url' => $game->cover_url],
            'server' => $this->formatServer($server, $snapshot),
            'map_image' => Game::mapImageUrl($game->slug, $snapshot?->map),
            'history' => $history,
            'stats' => $stats,
            'uptime_daily' => $uptimeDaily,
            'reviews' => $server->serverReviews()
                ->with('user')
                ->latest()
                ->paginate(10)
                ->through(fn (ServerReview $r) => [
                    'id' => $r->id,
                    'rating' => $r->rating,
                    'body' => $r->body,
                    'created_at' => $r->created_at->diffForHumans(),
                    'is_mine' => $r->user_id === auth()->id(),
                    'user' => [
                        'username' => $r->user?->username,
                        'name' => $r->user?->name ?? 'Deleted user',
                        'avatar' => $r->user?->avatar,
                    ],
                ]),
            'user_review' => $userReview ? ['rating' => $userReview->rating, 'body' => $userReview->body] : null,
            'average_rating' => $server->average_rating,
        ], $server));
    }

    public function connect(Game $game, string $ip, int $port): RedirectResponse
    {
        $server = Server::where('ip', $ip)->where('port', $port)->firstOrFail();

        ServerConnectionClick::create([
            'server_id' => $server->id,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
        ]);

        $url = match ($game->query_driver) {
            'fivem' => "fivem://connect/{$ip}:{$port}",
            'minecraft_java', 'minecraft_bedrock' => "minecraft://?addExternalServer=Server|{$ip}:{$port}",
            default => "steam://connect/{$ip}:{$port}",
        };

        return redirect($url);
    }

    public function favourite(Request $request, Server $server): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $exists = $server->favouritedBy()->where('user_id', $user->id)->exists();

        if ($exists) {
            $server->favouritedBy()->detach($user->id);
            $favourited = false;
        } else {
            $server->favouritedBy()->attach($user->id);
            $favourited = true;
            $this->achievements->check($user);
        }

        if ($request->wantsJson()) {
            return response()->json(['favourited' => $favourited]);
        }

        return back();
    }

    private function formatServer(Server $server, $snapshot = null): array
    {
        $snapshot = $snapshot ?? $server->latestSnapshot;

        return [
            'id' => $server->id,
            'ip' => $server->ip,
            'port' => $server->port,
            'address' => $server->address,
            'name' => $server->name ?? $snapshot?->name ?? $server->address,
            'country_code' => $server->country_code,
            'tags' => $server->tags ?? [],
            'is_favourited' => (bool) ($server->is_favourited ?? false),
            'status' => $snapshot ? [
                'is_online' => $snapshot->is_online,
                'map' => $snapshot->map,
                'players_online' => $snapshot->players_online,
                'players_max' => $snapshot->players_max,
                'ping' => $snapshot->ping,
                'is_password_protected' => $snapshot->is_password_protected,
                'vac_secured' => $snapshot->vac_secured,
                'game_version' => $snapshot->game_version,
                'players' => $snapshot->relationLoaded('players')
                    ? $snapshot->players->map(fn ($p) => [
                        'name' => $p->name,
                        'score' => $p->score,
                        'duration' => $p->duration_formatted,
                    ])->toArray()
                    : [],
            ] : null,
        ];
    }

    private function uptimePercent(Server $server): int
    {
        $total = $server->snapshots()->where('recorded_at', '>=', now()->subHours(24))->count();
        if ($total === 0) {
            return 0;
        }
        $online = $server->snapshots()->where('recorded_at', '>=', now()->subHours(24))->where('is_online', true)->count();

        return (int) round(($online / $total) * 100);
    }
}
