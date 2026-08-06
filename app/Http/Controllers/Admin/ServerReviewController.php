<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServerReview;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServerReviewController extends Controller
{
    public function __construct(private readonly ActivityLogService $activity) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $reviews = ServerReview::with(['user:id,name,username', 'server:id,ip,port,name,game_id', 'server.game:id,name,color'])
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%"))
                    ->orWhereHas('server', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('ip', 'like', "%{$search}%"))
                    ->orWhere('body', 'like', "%{$search}%");
            }))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ServerReview $r) => [
                'id' => $r->id,
                'rating' => $r->rating,
                'body' => $r->body,
                'created_at' => $r->created_at->diffForHumans(),
                'user' => $r->user?->only(['id', 'name', 'username']),
                'server' => $r->server ? [
                    'id' => $r->server->id,
                    'label' => $r->server->name ?? ($r->server->ip.':'.$r->server->port),
                    'game' => $r->server->game?->only(['name', 'color']),
                ] : null,
            ]);

        return Inertia::render('Admin/Servers/Reviews/Index', [
            'reviews' => $reviews,
            'total' => ServerReview::count(),
            'filters' => ['search' => $search],
        ]);
    }

    public function destroy(ServerReview $review): RedirectResponse
    {
        $this->activity->log('servers.review.deleted', "Deleted review #{$review->id} (rating {$review->rating})");
        $review->delete();

        return back()->with('success', 'Review deleted.');
    }
}
