<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\NewsArticle;
use App\Models\NewsComment;
use App\Models\Server;
use App\Models\ServerReview;
use App\Models\User;
use App\Models\UserAchievement;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        // Snapshot data only changes when the query scheduler runs (~2 min),
        // so the whole server/game overview is shared across visitors for
        // 60s. Per-user favourites are overlaid after the cache read.
        $overview = Cache::remember('home.overview', 60, function () {
            $games = Game::active()
                ->with(['activeServers' => fn ($q) => $q->with('latestSnapshot')])
                ->orderBy('sort_order')
                ->get();

            $servers = Server::active()
                ->with(['latestSnapshot', 'game'])
                ->get()
                ->sortByDesc(fn (Server $s) => ($s->latestSnapshot?->is_online ?? false)
                    ? ($s->latestSnapshot->players_online ?? 0)
                    : -1
                )
                ->map(fn (Server $s) => [
                    'id' => $s->id,
                    'name' => $s->name ?? $s->latestSnapshot?->name ?? $s->address,
                    'game_slug' => $s->game->slug,
                    'game_name' => $s->game->name,
                    'game_id' => $s->game_id,
                    'tags' => $s->tags ?? [],
                    'is_online' => (bool) ($s->latestSnapshot?->is_online ?? false),
                    'map' => $s->latestSnapshot?->map ?? '—',
                    'map_image' => Game::mapImageUrl($s->game->slug, $s->latestSnapshot?->map),
                    'players' => $s->latestSnapshot?->players_online ?? 0,
                    'max_players' => $s->latestSnapshot?->players_max ?? 0,
                    'ping' => $s->latestSnapshot?->ping ?? 0,
                    'show_route' => route('servers.show', [$s->game->slug, $s->ip, $s->port]),
                    'connect_route' => route('servers.connect', [$s->game->slug, $s->ip, $s->port]),
                ])
                ->values();

            $gameStats = $games->map(fn (Game $g) => [
                'slug' => $g->slug,
                'name' => $g->name,
                'players' => $g->activeServers->sum(
                    fn ($s) => ($s->latestSnapshot?->is_online ?? false) ? ($s->latestSnapshot->players_online ?? 0) : 0
                ),
                'max_players' => $g->activeServers->sum(
                    fn ($s) => ($s->latestSnapshot?->is_online ?? false) ? ($s->latestSnapshot->players_max ?? 0) : 0
                ),
            ]);

            return [
                'games' => $games->map(fn (Game $g) => ['id' => $g->id, 'slug' => $g->slug, 'name' => $g->name])->values()->all(),
                'servers' => $servers->all(),
                'gameStats' => $gameStats->values()->all(),
                'totalPlayers' => $gameStats->sum('players'),
                'totalMaxPlayers' => $games->flatMap->activeServers->sum(fn ($s) => $s->latestSnapshot?->players_max ?? 0),
                'gamesCount' => $games->count(),
            ];
        });

        $favouriteIds = auth()->check()
            ? auth()->user()->favouriteServers()->pluck('servers.id')->flip()
            : collect();

        $servers = collect($overview['servers'])->map(fn (array $s) => [
            ...$s,
            'is_favourited' => isset($favouriteIds[$s['id']]),
        ]);

        $latestNews = [];
        if (Schema::hasTable('news_articles')) {
            $latestNews = NewsArticle::published()
                ->with(['category', 'author'])
                ->orderByDesc('published_at')
                ->limit(5)
                ->get()
                ->map(fn (NewsArticle $a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'slug' => $a->slug,
                    'excerpt' => $a->excerpt,
                    'featured_image_url' => $a->featured_image_url,
                    'reading_time' => $a->reading_time,
                    'views' => $a->views,
                    'published_at' => $a->published_at?->diffForHumans(),
                    'category' => $a->category?->only(['id', 'name', 'slug', 'color']),
                    'author' => $a->author?->only(['id', 'name']),
                ])
                ->values()
                ->toArray();
        }

        return Inertia::render('Web/Home', [
            'servers' => $servers,
            'games' => $overview['games'],
            'gameStats' => $overview['gameStats'],
            'totalPlayers' => $overview['totalPlayers'],
            'maxPlayers' => max($overview['totalMaxPlayers'], 1),
            'stats' => [
                'games' => $overview['gamesCount'],
                'servers' => $servers->count(),
                'members' => User::count(),
                'online_servers' => $servers->filter(fn ($s) => $s['is_online'])->count(),
            ],
            'latestNews' => $latestNews,
            'whoIsOnline' => Schema::hasTable('page_views') ? app(AnalyticsService::class)->whoIsOnline() : null,
            'activeToday' => Schema::hasTable('page_views') ? app(AnalyticsService::class)->activeToday() : null,
            'playerHistory' => $this->playerHistory(),
            'viewer' => $this->viewerSummary(),
            'communityActivity' => $this->communityActivity(),
            'preferredGameSlugs' => auth()->check()
                ? auth()->user()->preferredGames()->pluck('slug')
                : [],
        ]);
    }

    /**
     * Latest community-wide activity (reviews, comments, badges) for the
     * sidebar feed. Same for all visitors — cached for a minute.
     *
     * @return array<int, array{type: string, username: string|null, avatar: string|null, label: string, at: string, url: string|null}>
     */
    private function communityActivity(): array
    {
        return Cache::remember('home.community_activity', 60, function () {
            $items = collect();

            if (Schema::hasTable('server_reviews')) {
                ServerReview::with(['user', 'server.game'])->latest()->limit(8)->get()
                    ->each(function ($review) use ($items) {
                        $items->push([
                            'type' => 'review',
                            'username' => $review->user?->username,
                            'avatar' => $review->user?->avatar,
                            'params' => ['name' => $review->server?->name ?? '—', 'rating' => $review->rating],
                            'at' => $review->created_at,
                            'url' => $review->server?->game
                                ? route('servers.show', [$review->server->game->slug, $review->server->ip, $review->server->port])
                                : null,
                        ]);
                    });
            }

            if (Schema::hasTable('news_comments')) {
                NewsComment::with(['user', 'article'])->latest()->limit(8)->get()
                    ->each(function ($comment) use ($items) {
                        $items->push([
                            'type' => 'comment',
                            'username' => $comment->user?->username,
                            'avatar' => $comment->user?->avatar,
                            'params' => ['title' => Str::limit($comment->article?->title ?? '—', 40)],
                            'at' => $comment->created_at,
                            'url' => $comment->article ? route('news.show', $comment->article->slug) : null,
                        ]);
                    });
            }

            if (Schema::hasTable('user_achievements')) {
                UserAchievement::with('user')->latest('awarded_at')->limit(8)->get()
                    ->each(function ($achievement) use ($items) {
                        $items->push([
                            'type' => 'badge',
                            'username' => $achievement->user?->username,
                            'avatar' => $achievement->user?->avatar,
                            'params' => ['badge_slug' => $achievement->slug],
                            'at' => $achievement->awarded_at,
                            'url' => $achievement->user?->username ? route('profile.show', $achievement->user->username) : null,
                        ]);
                    });
            }

            return $items
                ->filter(fn ($i) => $i['at'] !== null && $i['username'] !== null)
                ->sortByDesc('at')
                ->take(8)
                ->map(fn ($i) => [...$i, 'at' => $i['at']->diffForHumans(null, true, true)])
                ->values()
                ->all();
        });
    }

    /**
     * Extra profile-card data for the logged-in sidebar (banner, primary
     * role, badges) — kept out of the globally-shared auth.user prop since
     * it's only needed on the homepage, not on every page load.
     *
     * @return array{banner: ?string, role: ?array, achievements: array<int, string>}|null
     */
    private function viewerSummary(): ?array
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        $user->loadMissing(['roles' => fn ($q) => $q->wherePivot('is_primary', true), 'achievements']);
        $role = $user->roles->first();

        return [
            'banner' => $user->banner,
            'role' => $role ? ['name' => $role->name, 'color' => $role->color] : null,
            'achievements' => $user->achievements->pluck('slug'),
        ];
    }

    /**
     * Total players online across all active servers, one point per hour
     * for the last 24 hours. For each server, only its most recent
     * snapshot within each hour bucket counts (a server is queried every
     * couple of minutes, so summing every snapshot would wildly overcount).
     *
     * @return array<int, int>
     */
    private function playerHistory(): array
    {
        return Cache::remember('home.player_history', 300, function () {
            if (! Schema::hasTable('server_snapshots')) {
                return [];
            }

            // Fetched in PHP (rather than a raw date_format/window-function
            // query) so this works identically on MySQL/MariaDB and SQLite
            // (used in tests).
            $snapshots = DB::table('server_snapshots as ss')
                ->join('servers as s', 's.id', '=', 'ss.server_id')
                ->where('s.is_active', 1)
                ->where('ss.recorded_at', '>=', now()->subHours(24))
                ->orderBy('ss.recorded_at')
                ->get(['ss.server_id', 'ss.recorded_at', 'ss.players_online']);

            // Keep only the latest snapshot per server per hour bucket.
            $latestPerServerHour = [];
            foreach ($snapshots as $snapshot) {
                $bucket = Carbon::parse($snapshot->recorded_at)->format('Y-m-d H:00:00');
                $latestPerServerHour["{$snapshot->server_id}|{$bucket}"] = (int) $snapshot->players_online;
            }

            $totalsByBucket = [];
            foreach ($latestPerServerHour as $key => $players) {
                $bucket = explode('|', $key, 2)[1];
                $totalsByBucket[$bucket] = ($totalsByBucket[$bucket] ?? 0) + $players;
            }

            // Fill in the full 24-hour range so gaps (no query ran that
            // hour) render as a flat continuation rather than a dip to zero.
            $history = [];
            $last = 0;

            for ($i = 23; $i >= 0; $i--) {
                $bucket = now()->subHours($i)->format('Y-m-d H:00:00');
                $last = $totalsByBucket[$bucket] ?? $last;
                $history[] = $last;
            }

            return $history;
        });
    }
}
