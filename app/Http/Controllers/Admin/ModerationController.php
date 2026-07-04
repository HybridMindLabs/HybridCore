<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentReport;
use App\Models\NewsComment;
use App\Models\ServerReview;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Unified moderation queue: content reports, news comments and server
 * reviews in one place. Actions reuse the dedicated controllers' routes.
 */
class ModerationController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: 'open';

        return Inertia::render('Admin/Moderation/Index', [
            'reports' => ContentReport::with(['reporter', 'reportable.user'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->latest()
                ->paginate(15, ['*'], 'reports_page')
                ->withQueryString()
                ->through(fn (ContentReport $r) => [
                    'id' => $r->id,
                    'type' => array_search($r->reportable_type, ContentReport::TYPES, true) ?: $r->reportable_type,
                    'reason' => $r->reason,
                    'details' => $r->details,
                    'status' => $r->status,
                    'created_at' => $r->created_at->diffForHumans(),
                    'reporter' => $r->reporter?->username ?? 'unknown',
                    'content' => $this->contentPreview($r),
                ]),
            'comments' => NewsComment::with(['user:id,name,username', 'article:id,title,slug'])
                ->latest()
                ->paginate(15, ['*'], 'comments_page')
                ->withQueryString()
                ->through(fn (NewsComment $c) => [
                    'id' => $c->id,
                    'body' => $c->body,
                    'author' => $c->user?->username ?? $c->user?->name ?? 'unknown',
                    'article_title' => $c->article?->title,
                    'article_slug' => $c->article?->slug,
                    'created_at' => $c->created_at->diffForHumans(),
                ]),
            'reviews' => ServerReview::with(['user:id,name,username', 'server.game'])
                ->latest()
                ->paginate(15, ['*'], 'reviews_page')
                ->withQueryString()
                ->through(fn (ServerReview $r) => [
                    'id' => $r->id,
                    'rating' => $r->rating,
                    'body' => $r->body,
                    'author' => $r->user?->username ?? $r->user?->name ?? 'unknown',
                    'server_name' => $r->server?->name,
                    'server_url' => $r->server?->game
                        ? route('servers.show', [$r->server->game->slug, $r->server->ip, $r->server->port])
                        : null,
                    'created_at' => $r->created_at->diffForHumans(),
                ]),
            'counts' => [
                'open_reports' => ContentReport::where('status', 'open')->count(),
                'comments' => NewsComment::count(),
                'reviews' => ServerReview::count(),
            ],
            'filters' => ['status' => $status],
        ]);
    }

    /** @return array{body: string, author: string, url: string|null}|null */
    private function contentPreview(ContentReport $report): ?array
    {
        $reportable = $report->reportable;

        if ($reportable instanceof NewsComment) {
            return [
                'body' => $reportable->body,
                'author' => $reportable->user?->username ?? 'unknown',
                'url' => $reportable->article ? route('news.show', $reportable->article->slug) : null,
            ];
        }

        if ($reportable instanceof ServerReview) {
            $server = $reportable->server;

            return [
                'body' => "({$reportable->rating}/5) ".($reportable->body ?? ''),
                'author' => $reportable->user?->username ?? 'unknown',
                'url' => $server?->game
                    ? route('servers.show', [$server->game->slug, $server->ip, $server->port])
                    : null,
            ];
        }

        return null; // content already deleted
    }
}
