<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\NewsComment;
use App\Models\ServerReview;
use App\Models\User;
use App\Services\Extensions\Registries\FilterRegistry;
use App\Services\Extensions\Registries\ProfileTabRegistry;
use App\Services\SettingsService;
use App\Support\Filters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function show(Request $request, string $username): Response|RedirectResponse
    {
        $user = User::where('username', $username)
            ->whereNull('banned_at')
            ->with([
                'roles' => fn ($q) => $q->wherePivot('is_primary', true),
                'achievements',
                'favouriteServers.game',
                'favouriteServers.latestSnapshot',
            ])
            ->firstOrFail();

        $authUser = $request->user();
        $isSelf = $authUser?->id === $user->id;

        // Respect profile privacy
        $privacy = $user->profile_privacy ?? 'public';
        if ($privacy === 'private' && ! $isSelf) {
            abort(404);
        }
        if ($privacy === 'members' && ! $authUser) {
            abort(404);
        }

        // Blocked status
        $isBlocked = false;
        $canMessage = false;
        if ($authUser && ! $isSelf) {
            $isBlocked = $authUser->hasBlocked($user->id);
            $blockedByThem = $user->hasBlocked($authUser->id);
            $dmEnabled = $this->settings->get('dm_enabled', '1') === '1';
            $canMessage = $dmEnabled && ! $isBlocked && ! $blockedByThem;
        }

        $favouriteServers = $user->favouriteServers->map(fn ($server) => [
            'id' => $server->id,
            'name' => $server->name ?? $server->latestSnapshot?->name ?? $server->address,
            'game' => $server->game?->name,
            'game_slug' => $server->game?->slug,
            'game_icon' => $server->game?->cover_url,
            'map' => $server->latestSnapshot?->map,
            'map_image' => $server->game ? Game::mapImageUrl($server->game->slug, $server->latestSnapshot?->map) : null,
            'players' => $server->latestSnapshot?->players_online ?? 0,
            'max_players' => $server->latestSnapshot?->players_max ?? 0,
            'online' => $server->is_online,
            'connect_url' => $server->game
                ? route('servers.connect', [$server->game->slug, $server->ip, $server->port])
                : null,
            'show_route' => $server->game
                ? route('servers.show', [$server->game->slug, $server->ip, $server->port])
                : null,
        ]);

        return Inertia::render('Web/Profile', app(FilterRegistry::class)->apply(Filters::PROFILE_SHOW_PROPS, [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'display_name' => $user->display_name,
                'avatar' => $user->avatar,
                'banner' => $user->banner,
                'bio' => $user->bio,
                'location' => $user->location,
                'website' => $user->website,
                'profile_privacy' => $privacy,
                'role' => $user->roles->first() ? [
                    'name' => $user->roles->first()->name,
                    'color' => $user->roles->first()->color,
                    'icon' => $user->roles->first()->icon,
                ] : null,
                'joined_at' => $user->created_at->format('F j, Y'),
                'verified' => $user->hasVerifiedEmail(),
                'is_self' => $isSelf,
                'achievements' => $user->achievements->map(fn ($a) => [
                    'slug' => $a->slug,
                    'awarded_at' => $a->awarded_at?->toDateString(),
                ]),
                'favourite_servers' => $favouriteServers,
                'stats' => [
                    'joined_days_ago' => (int) $user->created_at->diffInDays(),
                    'favourite_servers_count' => $user->favouriteServers->count(),
                ],
                'can_message' => $canMessage,
                'is_blocked' => $isBlocked,
                'is_viewer_member' => (bool) $authUser,
                'is_online' => $user->isOnline(),
                'last_seen_at' => $user->last_seen_at?->diffForHumans(),
                'followers_count' => $user->followers()->count(),
                'following_count' => $user->following()->count(),
                'is_following' => $authUser && ! $isSelf
                    ? $authUser->following()->where('followed_id', $user->id)->exists()
                    : false,
            ],
            'activity' => $this->recentActivity($user),
            'followers' => $this->miniUsers($user->followers()),
            'following' => $this->miniUsers($user->following()),
            // Extension-registered profile panels (rendered via global slot components).
            'extensionPanels' => app(ProfileTabRegistry::class)->compose($user),
        ], $user));
    }

    /** @return array<int, array{username: string|null, name: string, avatar: string|null}> */
    private function miniUsers($relation): array
    {
        return $relation
            ->whereNull('banned_at')
            ->latest('user_follows.created_at')
            ->limit(24)
            ->get()
            ->map(fn (User $u) => [
                'username' => $u->username,
                'name' => $u->name,
                'avatar' => $u->avatar,
            ])
            ->all();
    }

    /**
     * Public activity feed: badges earned, reviews and comments posted,
     * merged and sorted by date (most recent 10). Returns structured
     * type + params so the label is translated client-side.
     *
     * @return array<int, array{type: string, params: array<string, string|int>, at: string, url: string|null}>
     */
    private function recentActivity(User $user): array
    {
        $items = collect();

        foreach ($user->achievements as $achievement) {
            $items->push([
                'type' => 'badge',
                'params' => ['badge_slug' => $achievement->slug],
                'at' => $achievement->awarded_at,
                'url' => null,
            ]);
        }

        $reviews = ServerReview::where('user_id', $user->id)
            ->with('server.game')
            ->latest()
            ->limit(10)
            ->get();

        foreach ($reviews as $review) {
            $server = $review->server;
            $items->push([
                'type' => 'review',
                'params' => ['name' => $server?->name ?? '—', 'rating' => $review->rating],
                'at' => $review->created_at,
                'url' => $server?->game
                    ? route('servers.show', [$server->game->slug, $server->ip, $server->port])
                    : null,
            ]);
        }

        $comments = NewsComment::where('user_id', $user->id)
            ->with('article')
            ->latest()
            ->limit(10)
            ->get();

        foreach ($comments as $comment) {
            $items->push([
                'type' => 'comment',
                'params' => ['title' => $comment->article?->title ?? '—'],
                'at' => $comment->created_at,
                'url' => $comment->article ? route('news.show', $comment->article->slug) : null,
            ]);
        }

        return $items
            ->filter(fn ($i) => $i['at'] !== null)
            ->sortByDesc('at')
            ->take(10)
            ->map(fn ($i) => [
                'type' => $i['type'],
                'params' => $i['params'],
                'at' => $i['at']->diffForHumans(),
                'url' => $i['url'],
            ])
            ->values()
            ->all();
    }
}
