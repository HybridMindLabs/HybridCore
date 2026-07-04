<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsArticle;
use App\Models\Server;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global search (navbar "/" shortcut) — powered by Laravel Scout so the
 * driver can be swapped (database locally, Meilisearch/Typesense at scale)
 * without touching this controller.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['users' => [], 'servers' => [], 'articles' => []]);
        }

        // The database driver queries tables directly, so visibility rules
        // are applied as query constraints (an external index driver would
        // enforce them via shouldBeSearchable instead).
        $users = User::search($q)
            ->query(fn ($query) => $query->whereNull('banned_at')->whereNotNull('username'))
            ->take(6)
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'username' => $u->username,
                'display_name' => $u->display_name ?: $u->username,
                'avatar' => $u->avatar,
                'url' => route('profile.show', $u->username),
            ])
            ->values();

        $servers = Server::search($q)
            ->query(fn ($query) => $query->where('is_active', true))
            ->take(6)
            ->get()
            ->load('game')
            ->map(fn (Server $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'game' => $s->game?->name,
                'game_icon' => $s->game?->icon,
                'url' => route('servers.show', [$s->game?->slug, $s->ip, $s->port]),
            ])
            ->values();

        $articles = NewsArticle::search($q)
            ->query(fn ($query) => $query->where('status', 'published')->where('published_at', '<=', now()))
            ->take(4)
            ->get()
            ->map(fn (NewsArticle $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'excerpt' => $a->excerpt,
                'url' => route('news.show', $a->slug),
            ])
            ->values();

        return response()->json([
            'users' => $users,
            'servers' => $servers,
            'articles' => $articles,
        ]);
    }
}
