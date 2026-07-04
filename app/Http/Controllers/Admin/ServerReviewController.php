<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServerReview;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ServerReviewController extends Controller
{
    public function __construct(private readonly ActivityLogService $activity) {}

    public function index(): Response
    {
        $reviews = ServerReview::with(['user:id,name,username', 'server:id,ip,port,name,game_id', 'server.game:id,name,color'])
            ->orderByDesc('created_at')
            ->paginate(25)
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
        ]);
    }

    public function destroy(ServerReview $review): RedirectResponse
    {
        $this->activity->log('servers.review.deleted', "Deleted review #{$review->id} (rating {$review->rating})");
        $review->delete();

        return back()->with('success', 'Review deleted.');
    }
}
