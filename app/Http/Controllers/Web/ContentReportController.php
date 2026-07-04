<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ContentReport;
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

        ContentReport::firstOrCreate(
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

        return back()->with('success', __('report.submitted'));
    }
}
