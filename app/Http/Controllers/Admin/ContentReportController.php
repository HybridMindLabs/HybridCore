<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentReport;
use App\Models\NewsComment;
use App\Models\ServerReview;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContentReportController extends Controller
{
    public function __construct(private readonly ActivityLogService $activity) {}

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: 'open';

        $reports = ContentReport::with(['reporter', 'reportable.user'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(25)
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
            ]);

        return Inertia::render('Admin/Reports/Index', [
            'reports' => $reports,
            'filters' => ['status' => $status],
            'openCount' => ContentReport::where('status', 'open')->count(),
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

    public function resolve(Request $request, ContentReport $report): RedirectResponse
    {
        $report->update(['status' => 'resolved', 'resolved_by' => $request->user()->id]);
        $this->activity->log('report.resolved', "Resolved content report #{$report->id}");

        return back()->with('success', 'Report resolved.');
    }

    /** Delete the reported content and resolve every report pointing at it. */
    public function destroyContent(Request $request, ContentReport $report): RedirectResponse
    {
        $report->reportable?->delete();

        ContentReport::where('reportable_type', $report->reportable_type)
            ->where('reportable_id', $report->reportable_id)
            ->update(['status' => 'resolved', 'resolved_by' => $request->user()->id]);

        $this->activity->log('report.content_deleted', "Deleted reported content via report #{$report->id}");

        return back()->with('success', 'Content deleted and report resolved.');
    }
}
