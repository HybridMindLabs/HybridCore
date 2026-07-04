<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rule;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RuleController extends Controller
{
    public function __construct(private readonly ActivityLogService $activity) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Rules/Index', [
            'rules' => Rule::orderBy('sort_order')->orderBy('id')->get([
                'id', 'slug', 'title', 'excerpt', 'is_system', 'published', 'sort_order', 'updated_at',
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Rules/Edit', ['rule' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = Rule::generateSlug($data['title']);

        $rule = Rule::create(array_merge($data, ['is_system' => false]));

        $this->activity->log('rules.created', "Created rule: {$rule->title}");

        return redirect()->route('admin.rules.edit', $rule->slug)
            ->with('success', 'Rule created.');
    }

    public function edit(string $slug): Response
    {
        return Inertia::render('Admin/Rules/Edit', [
            'rule' => Rule::where('slug', $slug)->firstOrFail(),
        ]);
    }

    public function update(Request $request, string $slug): RedirectResponse
    {
        $rule = Rule::where('slug', $slug)->firstOrFail();
        $data = $this->validated($request);

        // Regenerate slug only for non-system rules when title changed
        if (! $rule->is_system && $data['title'] !== $rule->title) {
            $newSlug = Rule::generateSlug($data['title']);
            $data['slug'] = $newSlug;
        }

        $rule->update($data);

        $this->activity->log('rules.updated', "Updated rule: {$rule->title}");

        return redirect()->route('admin.rules.edit', $rule->fresh()->slug)
            ->with('success', 'Rule saved.');
    }

    public function destroy(string $slug): RedirectResponse
    {
        $rule = Rule::where('slug', $slug)->firstOrFail();

        abort_if($rule->is_system, 403, 'System rules cannot be deleted.');

        $this->activity->log('rules.deleted', "Deleted rule: {$rule->title}");
        $rule->delete();

        return redirect()->route('admin.rules.index')
            ->with('success', 'Rule deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string', 'max:200000'],
            'published' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
    }
}
