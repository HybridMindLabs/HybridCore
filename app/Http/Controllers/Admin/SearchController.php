<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsArticle;
use App\Models\Payment;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin-wide search across records. Unlike the public /api/search it includes
 * banned users, hidden servers and drafts, so every group is gated by the
 * same permission that guards its admin page.
 */
class SearchController extends Controller
{
    private const TYPES = ['users', 'servers', 'articles', 'payments'];

    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', 'all');
        $limit = $type === 'all' ? 5 : 20;

        $results = array_fill_keys(self::TYPES, []);

        if (mb_strlen($q) < 2) {
            return response()->json($results);
        }

        $user = $request->user();
        $like = '%'.addcslashes($q, '%_\\').'%';
        $wants = fn (string $t, string $perm) => ($type === 'all' || $type === $t) && $user->can($perm);

        if ($wants('users', 'users.view')) {
            $results['users'] = User::query()
                ->where(fn (Builder $w) => $w->where('name', 'like', $like)
                    ->orWhere('username', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->when(ctype_digit($q), fn ($w) => $w->orWhere('id', (int) $q)))
                ->orderByDesc('last_login_at')
                ->limit($limit)
                ->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'title' => $u->name,
                    'subtitle' => trim(($u->username ? '@'.$u->username.' · ' : '').$u->email),
                    'badge' => $u->isBanned() ? 'Banned' : ($u->is_admin ? 'Admin' : null),
                    'url' => route('admin.users.show', $u->id),
                ])->all();
        }

        if ($wants('servers', 'servers.view')) {
            $results['servers'] = Server::with('game')
                ->where(fn (Builder $w) => $w->where('name', 'like', $like)
                    ->orWhere('ip', 'like', $like)
                    ->orWhere('display_address', 'like', $like))
                ->orderBy('name')
                ->limit($limit)
                ->get()
                ->map(fn (Server $s) => [
                    'id' => $s->id,
                    'title' => $s->name ?? $s->address,
                    'subtitle' => trim(($s->game?->name ? $s->game->name.' · ' : '').$s->address),
                    'badge' => ! $s->is_active ? 'Inactive' : (! $s->is_visible ? 'Hidden' : null),
                    'url' => route('admin.servers.index', ['search' => $s->ip]),
                ])->all();
        }

        if ($wants('articles', 'news.view')) {
            $results['articles'] = NewsArticle::query()
                ->where(fn (Builder $w) => $w->where('title', 'like', $like)->orWhere('slug', 'like', $like))
                ->latest()
                ->limit($limit)
                ->get()
                ->map(fn (NewsArticle $a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'subtitle' => '/news/'.$a->slug,
                    'badge' => $a->status === 'published' ? null : ucfirst($a->status),
                    'url' => route('admin.news.articles.edit', $a->id),
                ])->all();
        }

        if ($wants('payments', 'payments.view')) {
            $id = ltrim($q, '#');
            $results['payments'] = Payment::with('user')
                ->where(fn (Builder $w) => $w->where('external_id', 'like', $like)
                    ->orWhereHas('user', fn ($u) => $u->where('username', 'like', $like)->orWhere('email', 'like', $like))
                    ->when(ctype_digit($id), fn ($w) => $w->orWhere('id', (int) $id)))
                ->latest()
                ->limit($limit)
                ->get()
                ->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'title' => '#'.$p->id.' · '.number_format($p->amount / 100, 2).' '.strtoupper($p->currency),
                    'subtitle' => trim(($p->user?->username ? '@'.$p->user->username.' · ' : '').$p->gateway.' · '.$p->created_at->toDateString()),
                    'badge' => ucfirst($p->status),
                    'url' => route('admin.payments.index', ['search' => '#'.$p->id]),
                ])->all();
        }

        return response()->json($results);
    }
}
