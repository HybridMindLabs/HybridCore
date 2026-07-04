<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function __construct(private readonly ActivityLogService $activity) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Pages/Index', [
            'pages' => Page::orderByDesc('updated_at')->get()->map(fn (Page $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'status' => $p->status,
                'updated_at' => $p->updated_at?->diffForHumans(),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Pages/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['slug'] = $data['slug'] ?: $this->uniqueSlug($data['title']);
        $data['published_at'] = $data['status'] === 'published' ? now() : null;
        $data['format'] ??= 'markdown';
        $data['layout'] ??= 'default';

        $page = Page::create($data);
        $this->activity->log('page.created', "Created page \"{$page->title}\"");

        return redirect()->route('admin.pages.index')->with('success', 'Page created.');
    }

    public function edit(Page $page): Response
    {
        return Inertia::render('Admin/Pages/Edit', [
            'page' => $page->only([
                'id', 'title', 'slug', 'body', 'format', 'layout', 'status',
                'seo_title', 'seo_description', 'seo_og_image',
            ]),
        ]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $data = $this->validateData($request, $page);

        if ($data['status'] === 'published' && ! $page->published_at) {
            $data['published_at'] = now();
        }

        $wasPublished = $page->isPublished();
        $page->update($data);

        if (! $wasPublished && $page->isPublished()) {
            $this->activity->log('page.published', "Published page \"{$page->title}\"");
        } elseif ($wasPublished && ! $page->isPublished()) {
            $this->activity->log('page.unpublished', "Unpublished page \"{$page->title}\"");
        } else {
            $this->activity->log('page.updated', "Updated page \"{$page->title}\"");
        }

        return redirect()->route('admin.pages.index')->with('success', 'Page updated.');
    }

    public function destroy(Page $page): RedirectResponse
    {
        $this->activity->log('page.deleted', "Deleted page \"{$page->title}\"");
        $page->delete();

        return redirect()->route('admin.pages.index')->with('success', 'Page deleted.');
    }

    /** @return array<string, mixed> */
    private function validateData(Request $request, ?Page $page = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/',
                Rule::unique('pages', 'slug')->ignore($page?->id)->withoutTrashed(),
            ],
            'body' => ['nullable', 'string'],
            'format' => ['nullable', Rule::in(Page::FORMATS)],
            'layout' => ['nullable', Rule::in(Page::LAYOUTS)],
            'status' => ['required', Rule::in(Page::STATUSES)],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'seo_og_image' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'page';
        $slug = $base;
        $i = 1;

        while (Page::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
