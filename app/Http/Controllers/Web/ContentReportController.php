<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ContentReport;
use App\Notifications\SystemNotification;
use App\Services\AdminNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContentReportController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ContentReport::TYPES))],
            'id' => ['required', 'integer'],
            'reason' => ['required', Rule::in(ContentReport::REASONS)],
            'details' => ['nullable', 'string', 'max:500'],
        ]);

        $modelClass = ContentReport::TYPES[$data['type']];
        $reportable = $modelClass::findOrFail($data['id']);

        // Reporting your own content makes no sense.
        abort_if($reportable->user_id === $request->user()->id, 422);

        $report = ContentReport::firstOrCreate(
            [
                'reporter_id' => $request->user()->id,
                'reportable_type' => $modelClass,
                'reportable_id' => $reportable->id,
            ],
            [
                'reason' => $data['reason'],
                'details' => $data['details'] ?? null,
            ],
        );

        if ($report->wasRecentlyCreated) {
            app(AdminNotifier::class)->send('content_report', new SystemNotification(
                message: 'New report: '.class_basename($modelClass)." #{$reportable->id} ({$data['reason']})",
                level: 'warning',
                actionUrl: route('admin.moderation.index'),
                actionLabel: 'Open moderation',
            ));
        }

        return back()->with('success', __('report.submitted'));
    }
}
