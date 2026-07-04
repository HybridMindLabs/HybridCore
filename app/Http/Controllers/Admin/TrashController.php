<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsArticle;
use App\Models\NewsComment;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recycle bin for soft-deleted content. Items are purged automatically
 * after TRASH_RETENTION_DAYS by the daily model:prune schedule.
 */
class TrashController extends Controller
{
    public function __construct(private readonly ActivityLogService $activity) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Trash/Index', [
            'articles' => NewsArticle::onlyTrashed()
                ->with('author:id,name')
                ->orderByDesc('deleted_at')
                ->paginate(15, ['*'], 'articles_page')
                ->withQueryString()
                ->through(fn (NewsArticle $a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'author' => $a->author?->name ?? 'unknown',
                    'deleted_at' => $a->deleted_at->diffForHumans(),
                    'purge_at' => $a->deleted_at->addDays(NewsArticle::TRASH_RETENTION_DAYS)->toDateString(),
                ]),
            'comments' => NewsComment::onlyTrashed()
                ->with(['user:id,name,username', 'article' => fn ($q) => $q->withTrashed()->select('id', 'title')])
                ->orderByDesc('deleted_at')
                ->paginate(15, ['*'], 'comments_page')
                ->withQueryString()
                ->through(fn (NewsComment $c) => [
                    'id' => $c->id,
                    'body' => \Str::limit($c->body, 160),
                    'author' => $c->user?->username ?? $c->user?->name ?? 'unknown',
                    'article_title' => $c->article?->title,
                    'deleted_at' => $c->deleted_at->diffForHumans(),
                    'purge_at' => $c->deleted_at->addDays(NewsComment::TRASH_RETENTION_DAYS)->toDateString(),
                ]),
            'retentionDays' => NewsArticle::TRASH_RETENTION_DAYS,
        ]);
    }

    public function restoreArticle(int $id): RedirectResponse
    {
        $article = NewsArticle::onlyTrashed()->findOrFail($id);
        $article->restore();

        $this->activity->log('trash.article_restored', "Restored article \"{$article->title}\"", $article);

        return back()->with('success', 'Article restored.');
    }

    public function restoreComment(int $id): RedirectResponse
    {
        $comment = NewsComment::onlyTrashed()->findOrFail($id);
        $comment->restore();

        $this->activity->log('trash.comment_restored', "Restored comment #{$comment->id}");

        return back()->with('success', 'Comment restored.');
    }

    public function forceDeleteArticle(int $id): RedirectResponse
    {
        $article = NewsArticle::onlyTrashed()->findOrFail($id);
        $title = $article->title;
        $article->forceDelete();

        $this->activity->log('trash.article_purged', "Permanently deleted article \"{$title}\"");

        return back()->with('success', 'Article permanently deleted.');
    }

    public function forceDeleteComment(int $id): RedirectResponse
    {
        $comment = NewsComment::onlyTrashed()->findOrFail($id);
        $comment->forceDelete();

        $this->activity->log('trash.comment_purged', "Permanently deleted comment #{$id}");

        return back()->with('success', 'Comment permanently deleted.');
    }
}
