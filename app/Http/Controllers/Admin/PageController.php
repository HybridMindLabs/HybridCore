<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Page;
use App\Models\PageRevision;
use App\Services\ActivityLogService;
use App\Services\Extensions\Registries\PageBlockRegistry;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    private const CONTENT_FIELDS = ['title', 'format', 'layout', 'body', 'blocks'];

    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly PageBlockRegistry $blocks,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Pages/Index', [
            'pages' => Page::orderByDesc('updated_at')->get()->map(fn (Page $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'status' => $p->isScheduled() ? 'scheduled' : $p->status,
                'format' => $p->format,
                'published_at' => $p->published_at?->toIso8601String(),
                'updated_at' => $p->updated_at?->diffForHumans(),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Pages/Create', $this->editorProps());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['slug'] = ($data['slug'] ?? null) ?: $this->uniqueSlug($data['title']);
        $data['published_at'] = $this->publishTime($data);
        $data['format'] ??= 'markdown';
        $data['layout'] ??= 'default';

        $page = Page::create($data);
        $this->activity->log('page.created', "Created page \"{$page->title}\"");

        return redirect()->route('admin.pages.edit', $page)->with('success', 'Page created.');
    }

    public function edit(Page $page): Response
    {
        return Inertia::render('Admin/Pages/Edit', [
            ...$this->editorProps(),
            'page' => [
                ...$page->only([
                    'id', 'title', 'slug', 'body', 'format', 'layout', 'status',
                    'seo_title', 'seo_description', 'seo_og_image',
                ]),
                'blocks' => $this->blocks->sanitize($page->blocks ?? [], keepUnknown: true),
                'published_at' => $page->published_at?->format('Y-m-d\TH:i'),
                'is_scheduled' => $page->isScheduled(),
                'is_live' => Page::published()->whereKey($page->id)->exists(),
            ],
            'revisions' => $page->revisions()->with('user:id,username')->limit(Page::MAX_REVISIONS)->get()
                ->map(fn (PageRevision $r) => [
                    'id' => $r->id,
                    'title' => $r->title,
                    'format' => $r->format,
                    'blocks' => count($r->blocks ?? []),
                    'user' => $r->user?->username,
                    'created_at' => $r->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $data = $this->validateData($request, $page);
        $data['published_at'] = $this->publishTime($data, $page);

        $page->fill($data);
        if ($page->isDirty(self::CONTENT_FIELDS)) {
            // The revision holds the page as it was before this save.
            $page->saveRevision($request->user(), $page->getOriginal());
        }

        $wasPublished = $page->getOriginal('status') === 'published';
        $page->save();

        if (! $wasPublished && $page->isPublished()) {
            $this->activity->log('page.published', "Published page \"{$page->title}\"");
        } elseif ($wasPublished && ! $page->isPublished()) {
            $this->activity->log('page.unpublished', "Unpublished page \"{$page->title}\"");
        } else {
            $this->activity->log('page.updated', "Updated page \"{$page->title}\"");
        }

        return back()->with('success', 'Page saved.');
    }

    public function restoreRevision(Request $request, Page $page, PageRevision $revision): RedirectResponse
    {
        abort_unless($revision->page_id === $page->id, 404);

        $page->saveRevision($request->user());
        $page->update($revision->only(self::CONTENT_FIELDS));
        $this->activity->log('page.restored', "Restored page \"{$page->title}\" to an earlier version");

        return back()->with('success', 'Earlier version restored. The version you replaced is in the history.');
    }

    /** Unsaved blocks rendered exactly as the public page would show them. */
    public function preview(Request $request): JsonResponse
    {
        $request->validate(['blocks' => ['present', 'array', 'max:'.PageBlockRegistry::MAX_BLOCKS]]);

        return response()->json(['blocks' => $this->blocks->render($request->input('blocks', []))]);
    }

    public function uploadImage(Request $request, MediaLibrary $library): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp,gif'],
        ]);

        $media = $library->upload($request->file('file'), 'Pages', $request->user());

        return response()->json(['url' => $media->url]);
    }

    public function destroy(Page $page): RedirectResponse
    {
        $this->activity->log('page.deleted', "Deleted page \"{$page->title}\"");
        $page->delete();

        return redirect()->route('admin.pages.index')->with('success', 'Page deleted.');
    }

    /** @return array<string, mixed> */
    private function editorProps(): array
    {
        return [
            'blockTypes' => $this->blocks->definitions(),
            'games' => Game::orderBy('sort_order')->get(['id', 'name']),
        ];
    }

    /**
     * Published pages go live at the chosen time, or now when none is set;
     * a page published earlier keeps its original date.
     *
     * @param  array<string, mixed>  $data
     */
    private function publishTime(array $data, ?Page $page = null): ?Carbon
    {
        if (! empty($data['published_at'])) {
            return Carbon::parse($data['published_at']);
        }

        if ($data['status'] !== 'published') {
            return null;
        }

        return $page?->published_at && ! $page->published_at->isFuture() ? $page->published_at : now();
    }

    /** @return array<string, mixed> */
    private function validateData(Request $request, ?Page $page = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/',
                Rule::unique('pages', 'slug')->ignore($page?->id)->withoutTrashed(),
            ],
            'body' => ['nullable', 'string'],
            'blocks' => ['nullable', 'array', 'max:'.PageBlockRegistry::MAX_BLOCKS],
            'format' => ['nullable', Rule::in(Page::FORMATS)],
            'layout' => ['nullable', Rule::in(Page::LAYOUTS)],
            'status' => ['required', Rule::in(Page::STATUSES)],
            'published_at' => ['nullable', 'date'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'seo_og_image' => ['nullable', 'string', 'max:255'],
        ]);

        if (array_key_exists('blocks', $data)) {
            $data['blocks'] = $this->blocks->sanitize($data['blocks'] ?? [], keepUnknown: true);
        }

        return $data;
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
