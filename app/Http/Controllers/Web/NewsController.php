<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\NewsArticle;
use App\Models\NewsArticleView;
use App\Models\NewsCategory;
use App\Models\NewsComment;
use App\Models\NewsTag;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;

class NewsController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $query = NewsArticle::published()
            ->with(['category', 'author', 'tags'])
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at');

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->category));
        }

        if ($request->filled('tag')) {
            $query->whereHas('tags', fn ($q) => $q->where('slug', $request->tag));
        }

        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('excerpt', 'like', "%{$search}%"));
        }

        return Inertia::render('Web/News/Index', [
            'articles' => $query->paginate(12)->withQueryString()->through(fn (NewsArticle $a) => $this->articleCard($a)),
            'categories' => NewsCategory::withCount('publishedArticles')->orderBy('sort_order')->get(['id', 'name', 'slug', 'color', 'icon']),
            'featuredArticles' => NewsArticle::published()->where('is_featured', true)->with(['category', 'author'])->orderByDesc('published_at')->limit(3)->get()->map(fn ($a) => $this->articleCard($a)),
            'currentCategory' => $request->category,
            'currentTag' => $request->tag,
            'search' => $request->q,
        ]);
    }

    public function show(Request $request, NewsArticle $article): \Inertia\Response
    {
        if (! $article->isPublished()) {
            abort(404);
        }

        $article->load(['category', 'author', 'tags']);

        $this->recordView($request, $article);

        $related = NewsArticle::published()
            ->where('id', '!=', $article->id)
            ->where('category_id', $article->category_id)
            ->with(['category', 'author'])
            ->orderByDesc('published_at')
            ->limit(3)
            ->get()
            ->map(fn ($a) => $this->articleCard($a));

        $prev = NewsArticle::published()
            ->where('published_at', '<', $article->published_at)
            ->orderByDesc('published_at')
            ->first(['id', 'title', 'slug']);

        $next = NewsArticle::published()
            ->where('published_at', '>', $article->published_at)
            ->orderBy('published_at')
            ->first(['id', 'title', 'slug']);

        return Inertia::render('Web/News/Show', [
            'article' => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => $article->excerpt,
                'body' => $article->body,
                'format' => $article->format,
                'featured_image_url' => $article->featured_image_url,
                'status' => $article->status,
                'is_pinned' => $article->is_pinned,
                'is_featured' => $article->is_featured,
                'reading_time' => $article->reading_time,
                'views' => $article->views,
                'published_at' => $article->published_at?->format('d M Y'),
                'published_at_iso' => $article->published_at?->toIso8601String(),
                'category' => $article->category?->only(['id', 'name', 'slug', 'color']),
                'author' => $article->author ? [
                    'id' => $article->author->id,
                    'name' => $article->author->name,
                    'username' => $article->author->username,
                    'avatar' => $article->author->avatar,
                ] : null,
                'tags' => $article->tags->map->only(['id', 'name', 'slug']),
                'meta_title' => $article->meta_title ?: $article->title,
                'meta_description' => $article->meta_description ?: $article->excerpt,
                'og_image_url' => $article->og_image_url,
                'canonical' => Seo::canonical('/news/'.$article->slug),
            ],
            'related' => $related,
            'prev' => $prev,
            'next' => $next,
            'comments' => $article->comments()
                ->with('user')
                ->latest()
                ->get()
                ->map(fn (NewsComment $c) => [
                    'id' => $c->id,
                    'body' => $c->body,
                    'created_at' => $c->created_at->diffForHumans(),
                    'is_mine' => auth()->id() === $c->user_id,
                    'can_delete' => auth()->id() === $c->user_id || (bool) auth()->user()?->is_admin,
                    'user' => [
                        'username' => $c->user?->username,
                        'name' => $c->user?->name ?? 'Deleted user',
                        'avatar' => $c->user?->avatar,
                    ],
                ]),
        ]);
    }

    public function category(Request $request, NewsCategory $category): \Inertia\Response
    {
        $articles = NewsArticle::published()
            ->where('category_id', $category->id)
            ->with(['category', 'author', 'tags'])
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (NewsArticle $a) => $this->articleCard($a));

        return Inertia::render('Web/News/Category', [
            'category' => $category->only(['id', 'name', 'slug', 'description', 'color', 'icon', 'meta_title', 'meta_description']),
            'articles' => $articles,
        ]);
    }

    public function tag(Request $request, NewsTag $tag): \Inertia\Response
    {
        $articles = NewsArticle::published()
            ->whereHas('tags', fn ($q) => $q->where('news_tags.id', $tag->id))
            ->with(['category', 'author', 'tags'])
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (NewsArticle $a) => $this->articleCard($a));

        return Inertia::render('Web/News/Tag', [
            'tag' => $tag->only(['id', 'name', 'slug']),
            'articles' => $articles,
        ]);
    }

    public function feed(): Response
    {
        $articles = NewsArticle::published()
            ->with(['category', 'author'])
            ->orderByDesc('published_at')
            ->limit(20)
            ->get();

        $appName = config('app.name');
        $siteUrl = Seo::canonical('/');
        $feedUrl = Seo::canonical('/news/feed.xml');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">'."\n";
        $xml .= "<channel>\n";
        $xml .= '  <title>'.e($appName)." News</title>\n";
        $xml .= '  <link>'.e($siteUrl)."</link>\n";
        $xml .= '  <description>Latest news from '.e($appName)."</description>\n";
        $xml .= '  <language>en-us</language>'."\n";
        $xml .= '  <atom:link href="'.e($feedUrl).'" rel="self" type="application/rss+xml"/>'."\n";

        foreach ($articles as $a) {
            $url = Seo::canonical('/news/'.$a->slug);
            $xml .= "  <item>\n";
            $xml .= '    <title>'.e($a->title)."</title>\n";
            $xml .= '    <link>'.e($url)."</link>\n";
            $xml .= '    <guid isPermaLink="true">'.e($url)."</guid>\n";
            $xml .= '    <pubDate>'.$a->published_at->format('r')."</pubDate>\n";
            if ($a->category) {
                $xml .= '    <category>'.e($a->category->name)."</category>\n";
            }
            if ($a->excerpt) {
                $xml .= '    <description>'.e($a->excerpt)."</description>\n";
            }
            $xml .= "  </item>\n";
        }

        $xml .= "</channel>\n</rss>";

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }

    private function articleCard(NewsArticle $a): array
    {
        return [
            'id' => $a->id,
            'title' => $a->title,
            'slug' => $a->slug,
            'excerpt' => $a->excerpt,
            'featured_image_url' => $a->featured_image_url,
            'reading_time' => $a->reading_time,
            'views' => $a->views,
            'is_pinned' => $a->is_pinned,
            'is_featured' => $a->is_featured,
            'published_at' => $a->published_at?->format('d M Y'),
            'published_at_iso' => $a->published_at?->toIso8601String(),
            'category' => $a->category?->only(['id', 'name', 'slug', 'color']),
            'author' => $a->author ? ['id' => $a->author->id, 'name' => $a->author->name, 'avatar' => $a->author->avatar] : null,
            'tags' => $a->relationLoaded('tags') ? $a->tags->map->only(['id', 'name', 'slug']) : [],
        ];
    }

    private function recordView(Request $request, NewsArticle $article): void
    {
        $userId = $request->user()?->id;
        $ip = $request->ip();

        $alreadyViewed = NewsArticleView::where('article_id', $article->id)
            ->where(fn ($q) => $userId
                ? $q->where('user_id', $userId)
                : $q->where('ip', $ip)->whereNull('user_id')
            )
            ->where('viewed_at', '>=', now()->subHours(6))
            ->exists();

        if (! $alreadyViewed) {
            NewsArticleView::create([
                'article_id' => $article->id,
                'user_id' => $userId,
                'ip' => $ip,
                'user_agent' => substr($request->userAgent() ?? '', 0, 255),
                'viewed_at' => now(),
            ]);

            $article->increment('views');
        }
    }
}
