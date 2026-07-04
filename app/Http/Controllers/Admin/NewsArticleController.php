<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\NewsTag;
use App\Services\ActivityLogService;
use App\Services\Extensions\Registries\HookRegistry;
use App\Support\Hooks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NewsArticleController extends Controller
{
    public function __construct(private readonly ActivityLogService $activity) {}

    public function index(): Response
    {
        return Inertia::render('Admin/News/Articles/Index', [
            'articles' => NewsArticle::with(['category', 'author'])
                ->withCount('articleViews')
                ->orderByDesc('created_at')
                ->paginate(25)
                ->through(fn (NewsArticle $a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'slug' => $a->slug,
                    'status' => $a->status,
                    'is_scheduled' => $a->status === 'published' && ($a->published_at?->isFuture() ?? false),
                    'is_pinned' => $a->is_pinned,
                    'is_featured' => $a->is_featured,
                    'category' => $a->category?->only(['id', 'name', 'color']),
                    'author' => $a->author?->only(['id', 'name']),
                    'views' => $a->article_views_count,
                    'reading_time' => $a->reading_time,
                    'published_at' => $a->published_at?->format('d M Y'),
                    'created_at' => $a->created_at->diffForHumans(),
                ]),
            'categories' => NewsCategory::orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/News/Articles/Create', [
            'categories' => NewsCategory::orderBy('name')->get(['id', 'name', 'color']),
            'tags' => NewsTag::orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['title']);
        $data['author_id'] = $request->user()->id;
        $data['excerpt'] = ($data['excerpt'] ?? '') ?: $this->autoExcerpt($data['body']);
        $data['meta_title'] = ($data['meta_title'] ?? '') ?: $data['title'];
        $data['meta_description'] = ($data['meta_description'] ?? '') ?: $data['excerpt'];
        $data['og_image'] = ($data['og_image'] ?? '') ?: ($data['featured_image'] ?? null);

        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $tags = $data['tags'] ?? [];
        unset($data['tags']);

        $article = NewsArticle::create($data);
        $article->tags()->sync($this->resolveTags($tags));

        $this->activity->log('news.article.created', "Created article \"{$article->title}\"");

        if ($article->isPublished()) {
            app(HookRegistry::class)->fire(Hooks::ARTICLE_PUBLISHED, $article);
        }

        return redirect()->route('admin.news.articles.edit', $article)->with('success', 'Article created.');
    }

    public function edit(NewsArticle $article): Response
    {
        return Inertia::render('Admin/News/Articles/Edit', [
            'article' => array_merge(
                $article->only([
                    'id', 'title', 'slug', 'excerpt', 'body', 'format', 'featured_image',
                    'status', 'is_pinned', 'is_featured', 'meta_title', 'meta_description',
                    'og_image', 'reading_time', 'views',
                ]),
                [
                    'category_id' => $article->category_id,
                    'published_at' => $article->published_at?->format('Y-m-d\TH:i'),
                    'featured_image_url' => $article->featured_image_url,
                    'tags' => $article->tags()->get(['news_tags.id', 'name', 'slug']),
                ]
            ),
            'categories' => NewsCategory::orderBy('name')->get(['id', 'name', 'color']),
            'tags' => NewsTag::orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function update(Request $request, NewsArticle $article): RedirectResponse
    {
        $data = $this->validated($request, $article);

        // Re-generate auto fields only when they are empty
        if (empty($data['excerpt'])) {
            $data['excerpt'] = $this->autoExcerpt($data['body']);
        }
        if (empty($data['meta_title'])) {
            $data['meta_title'] = $data['title'];
        }
        if (empty($data['meta_description'])) {
            $data['meta_description'] = $data['excerpt'];
        }
        if (empty($data['og_image'])) {
            $data['og_image'] = $data['featured_image'] ?? $article->featured_image;
        }

        if ($data['status'] === 'published' && ! $article->published_at && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $tags = $data['tags'] ?? [];
        unset($data['tags']);

        $wasPublished = $article->isPublished();
        $article->update($data);
        $article->tags()->sync($this->resolveTags($tags));

        if (! $wasPublished && $article->isPublished()) {
            $this->activity->log('news.article.published', "Published article \"{$article->title}\"");
            app(HookRegistry::class)->fire(Hooks::ARTICLE_PUBLISHED, $article);
        } else {
            $this->activity->log('news.article.updated', "Updated article \"{$article->title}\"");
        }

        return back()->with('success', 'Article saved.');
    }

    public function destroy(NewsArticle $article): RedirectResponse
    {
        $this->activity->log('news.article.deleted', "Deleted article \"{$article->title}\"");
        $article->delete();

        return redirect()->route('admin.news.articles.index')->with('success', 'Article deleted.');
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:publish,archive,delete'],
            'article_ids' => ['required', 'array', 'min:1'],
            'article_ids.*' => ['integer', 'exists:news_articles,id'],
        ]);

        $articles = NewsArticle::whereIn('id', $data['article_ids'])->get();

        foreach ($articles as $article) {
            match ($data['action']) {
                'publish' => $article->update(['status' => 'published', 'published_at' => $article->published_at ?? now()]),
                'archive' => $article->update(['status' => 'archived']),
                'delete' => $article->delete(),
            };
        }

        $this->activity->log('news.article.bulk_action', "Bulk {$data['action']} on ".count($articles).' article(s)');

        return back()->with('success', ucfirst($data['action']).' applied to '.count($articles).' article(s).');
    }

    /** Strip markdown/html and return first 160 characters. */
    private function autoExcerpt(string $body): string
    {
        $plain = strip_tags(preg_replace('/[#*`_\[\]>!]/', '', $body) ?? $body);
        $plain = preg_replace('/\s+/', ' ', $plain) ?? $plain;

        return mb_substr(trim($plain), 0, 160);
    }

    private function validated(Request $request, ?NewsArticle $article = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:news_categories,id'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string'],
            'format' => ['nullable', Rule::in(NewsArticle::FORMATS)],
            'featured_image' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(NewsArticle::STATUSES)],
            'is_pinned' => ['boolean'],
            'is_featured' => ['boolean'],
            'published_at' => ['nullable', 'date'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'og_image' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'article';
        $slug = $base;
        $i = 1;

        while (NewsArticle::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    private function resolveTags(array $tags): array
    {
        return collect($tags)->map(function (string $nameOrId) {
            if (is_numeric($nameOrId)) {
                return (int) $nameOrId;
            }

            $tag = NewsTag::firstOrCreate(
                ['slug' => Str::slug($nameOrId)],
                ['name' => $nameOrId]
            );

            return $tag->id;
        })->toArray();
    }
}
