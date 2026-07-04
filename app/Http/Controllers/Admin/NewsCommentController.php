<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsComment;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NewsCommentController extends Controller
{
    public function __construct(private readonly ActivityLogService $activity) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $comments = NewsComment::with(['user', 'article'])
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('body', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('username', 'like', "%{$search}%"));
            }))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (NewsComment $c) => [
                'id' => $c->id,
                'body' => $c->body,
                'created_at' => $c->created_at->diffForHumans(),
                'user' => [
                    'username' => $c->user?->username,
                    'name' => $c->user?->name ?? 'Deleted user',
                ],
                'article' => $c->article ? [
                    'title' => $c->article->title,
                    'slug' => $c->article->slug,
                ] : null,
            ]);

        return Inertia::render('Admin/News/Comments/Index', [
            'comments' => $comments,
            'filters' => ['search' => $search],
        ]);
    }

    public function destroy(NewsComment $comment): RedirectResponse
    {
        $comment->delete();
        $this->activity->log('news.comment.deleted', "Deleted comment #{$comment->id} by ".($comment->user?->username ?? 'unknown'));

        return back()->with('success', 'Comment deleted.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        $count = NewsComment::whereIn('id', $ids)->delete();
        $this->activity->log('news.comment.bulk_deleted', "Bulk-deleted {$count} news comments");

        return back()->with('success', "{$count} comments deleted.");
    }
}
