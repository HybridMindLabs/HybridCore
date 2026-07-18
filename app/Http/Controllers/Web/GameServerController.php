<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Server;
use App\Models\ServerConnectionClick;
use App\Models\ServerReview;
use App\Models\ServerSnapshot;
use App\Services\AchievementService;
use App\Services\Extensions\Registries\FilterRegistry;
use App\Support\Filters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
            // `game` is eager loaded because formatServer() reads its slug for
            // the row artwork — without it every row fires its own query.
            ->with(['latestSnapshot', 'game:id,slug'])
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
            'insights' => $this->gameInsights($game),
        ]);
    }

    /** Bucket size and slot count per selectable range. */
    private const INSIGHT_RANGES = [
        '24h' => ['seconds' => 3600, 'slots' => 24],
        '7d' => ['seconds' => 21600, 'slots' => 28],   // six-hour buckets
        '30d' => ['seconds' => 86400, 'slots' => 30],  // daily
    ];

    /**
     * Aggregate activity for a whole game, built from the snapshots the query
     * scheduler already records. Cached for five minutes — snapshots land every
     * ~2 min, so this is never meaningfully stale.
     */
    private function gameInsights(Game $game): array
    {
        return Cache::remember("servers.game_insights.{$game->id}", 300, function () use ($game) {
            $serverIds = Server::active()->where('game_id', $game->id)->pluck('id');

            $ranges = [];
            foreach (array_keys(self::INSIGHT_RANGES) as $key) {
                $ranges[$key] = $serverIds->isEmpty()
                    ? ['history' => [], 'peak' => 0, 'average' => 0, 'uptime' => 0, 'samples' => 0]
                    : $this->insightRange($serverIds, $key);
            }

            return [
                'updated_at' => now()->toIso8601String(),
                'ranges' => $ranges,
                'maps' => $serverIds->isEmpty() ? [] : $this->topMaps($serverIds),
            ];
        });
    }

    /**
     * One range of the activity series, padded to a fixed number of buckets.
     *
     * @param  Collection<int, int>  $serverIds
     */
    private function insightRange($serverIds, string $range): array
    {
        ['seconds' => $seconds, 'slots' => $slots] = self::INSIGHT_RANGES[$range];

        $since = now()->subSeconds($seconds * ($slots - 1))->startOfMinute();

        // Average per server per bucket first, then sum across servers, so a
        // server polled several times inside one bucket is not counted twice.
        $perServerBucket = ServerSnapshot::query()
            ->selectRaw("server_id, FLOOR(UNIX_TIMESTAMP(recorded_at) / {$seconds}) AS bucket")
            ->selectRaw('AVG(CASE WHEN is_online = 1 THEN players_online ELSE 0 END) AS avg_players')
            ->whereIn('server_id', $serverIds)
            ->where('recorded_at', '>=', $since)
            ->groupBy('server_id', 'bucket');

        $recorded = DB::query()
            ->fromSub($perServerBucket, 'b')
            ->selectRaw('bucket, ROUND(SUM(avg_players)) AS players')
            ->groupBy('bucket')
            ->pluck('players', 'bucket');

        // Emit every bucket, with null where nothing was recorded. Dropping the
        // empty ones would let the chart join two distant points with a straight
        // line and imply data that was never collected.
        $history = [];
        $lastBucket = intdiv(now()->getTimestamp(), $seconds);

        for ($i = $slots - 1; $i >= 0; $i--) {
            $bucket = $lastBucket - $i;
            $history[] = [
                't' => now()->setTimestamp($bucket * $seconds)->toIso8601String(),
                'players' => isset($recorded[$bucket]) ? (int) $recorded[$bucket] : null,
            ];
        }

        $counts = array_values(array_filter(
            array_column($history, 'players'),
            fn ($v) => $v !== null,
        ));

        $uptime = ServerSnapshot::query()
            ->whereIn('server_id', $serverIds)
            ->where('recorded_at', '>=', $since)
            ->selectRaw('COUNT(*) AS total, SUM(is_online = 1) AS online')
            ->first();

        return [
            'history' => $history,
            'peak' => $counts ? max($counts) : 0,
            'average' => $counts ? (int) round(array_sum($counts) / count($counts)) : 0,
            'uptime' => $uptime && $uptime->total > 0
                ? (int) round($uptime->online / $uptime->total * 100)
                : 0,
            // Buckets actually covered, not the padded length — the frontend
            // uses this to decide whether a chart is honest yet.
            'samples' => count($counts),
        ];
    }

    /** @param  Collection<int, int>  $serverIds */
    private function topMaps($serverIds): array
    {
        $maps = ServerSnapshot::query()
            ->whereIn('server_id', $serverIds)
            ->where('recorded_at', '>=', now()->subDays(7))
            ->where('is_online', true)
            ->whereNotNull('map')
            ->where('map', '!=', '')
            ->selectRaw('map, COUNT(*) AS samples')
            ->groupBy('map')
            ->orderByDesc('samples')
            ->limit(8)
            ->get();

        $total = max(1, (int) $maps->sum('samples'));

        return $maps->map(fn ($m) => [
            'map' => (string) $m->map,
            'share' => (int) round($m->samples / $total * 100),
        ])->all();
    }

    public function show(Game $game, string $ip, int $port): Response
    {
        $server = Server::where('game_id', $game->id)
            ->where('ip', $ip)
            ->where('port', $port)
            ->firstOrFail();

        $snapshot = $server->latestSnapshot()->with('players')->first();

        // Same bucketing as the per-game page, so both charts read the same way.
        // Timestamps are ISO rather than a formatted "H:i" string — the chart
        // needs a real date to label and group by, and raw snapshots every
        // ~2 min put ~720 points behind a 600px-wide plot.
        $historyRanges = Cache::remember(
            "servers.history.{$server->id}",
            300,
            fn () => collect(array_keys(self::INSIGHT_RANGES))
                ->mapWithKeys(fn (string $range) => [
                    $range => $this->insightRange(collect([$server->id]), $range),
                ])
                ->all(),
        );

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
            'history_ranges' => $historyRanges,
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
            'row_image' => Game::rowImageUrl($server->game->slug, $snapshot?->map),
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
