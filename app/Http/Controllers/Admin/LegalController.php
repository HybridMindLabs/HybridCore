<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegalPage;
use App\Services\ActivityLogService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly ActivityLogService $activity,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Legal/Index', [
            'pages' => LegalPage::orderBy('sort_order')->orderBy('id')->get([
                'id', 'slug', 'title', 'subtitle', 'is_system', 'sort_order', 'content_updated_at', 'updated_at',
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Legal/Edit', [
            'page' => null,
            'seoDescription' => $this->settings->get('seo_meta_description', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $page = LegalPage::create(array_merge($data, ['is_system' => false]));

        $this->activity->log('legal.created', "Created legal page: {$page->slug}");

        return redirect()->route('admin.legal.edit', $page->slug)
            ->with('success', 'Page created.');
    }

    public function edit(string $slug): Response
    {
        $page = LegalPage::where('slug', $slug)->firstOrFail();

        return Inertia::render('Admin/Legal/Edit', [
            'page' => $page,
            'seoDescription' => $this->settings->get('seo_meta_description', ''),
        ]);
    }

    public function update(Request $request, string $slug): RedirectResponse
    {
        $page = LegalPage::where('slug', $slug)->firstOrFail();
        $data = $this->validated($request, $page->slug);

        $page->update($data);

        $this->activity->log('legal.updated', "Updated legal page: {$page->slug}");

        return redirect()->route('admin.legal.edit', $page->slug)
            ->with('success', 'Page saved.');
    }

    public function destroy(string $slug): RedirectResponse
    {
        $page = LegalPage::where('slug', $slug)->firstOrFail();

        abort_if($page->is_system, 403, 'System pages cannot be deleted.');

        $this->activity->log('legal.deleted', "Deleted legal page: {$page->slug}");
        $page->delete();

        return redirect()->route('admin.legal.index')
            ->with('success', 'Page deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?string $lockedSlug = null): array
    {
        $slugRule = $lockedSlug
            ? ['nullable']
            : ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', 'unique:legal_pages,slug'];

        return $request->validate([
            'slug' => $slugRule,
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string', 'max:200000'],
            'content_updated_at' => ['nullable', 'date_format:Y-m-d'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
    }
}
