<?php

namespace Tests\Feature\Web;

use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\NewsTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        @mkdir(storage_path(), 0755, true);
        file_put_contents(storage_path('installed.lock'), 'installed');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    private function author(): User
    {
        return User::factory()->create();
    }

    private function article(array $attrs = []): NewsArticle
    {
        return NewsArticle::create(array_merge([
            'author_id' => $this->author()->id,
            'title' => 'Test Article',
            'slug' => 'test-article',
            'body' => 'Body content',
            'format' => 'markdown',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ], $attrs));
    }

    // ── Index ────────────────────────────────────────────────────────────────

    public function test_index_renders(): void
    {
        $this->get(route('news.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Web/News/Index'));
    }

    public function test_index_only_shows_published_articles(): void
    {
        $this->article(['slug' => 'pub', 'status' => 'published', 'published_at' => now()->subMinute()]);
        $this->article(['slug' => 'draft', 'status' => 'draft']);
        $this->article(['slug' => 'future', 'status' => 'published', 'published_at' => now()->addHour()]);

        $this->get(route('news.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->has('articles.data', 1));
    }

    public function test_index_featured_articles_are_separated(): void
    {
        $this->article(['slug' => 'feat', 'is_featured' => true]);
        $this->article(['slug' => 'norm', 'is_featured' => false]);

        $this->get(route('news.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->has('featuredArticles', 1)
                ->has('articles.data', 2) // featured articles also appear in main list
            );
    }

    // ── Show ─────────────────────────────────────────────────────────────────

    public function test_show_renders_article(): void
    {
        $article = $this->article();

        $this->get(route('news.show', $article->slug))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Web/News/Show')
                ->where('article.slug', $article->slug)
            );
    }

    public function test_show_404_for_draft(): void
    {
        $article = $this->article(['status' => 'draft']);

        $this->get(route('news.show', $article->slug))
            ->assertNotFound();
    }

    public function test_show_404_for_unknown_slug(): void
    {
        $this->get(route('news.show', 'no-such-article'))
            ->assertNotFound();
    }

    // ── Category ─────────────────────────────────────────────────────────────

    public function test_category_page_renders(): void
    {
        $cat = NewsCategory::create(['name' => 'Gaming', 'slug' => 'gaming', 'color' => '#3b82f6']);
        $this->article(['slug' => 'cat-art', 'category_id' => $cat->id]);

        $this->get(route('news.category', $cat->slug))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Web/News/Category')
                ->where('category.slug', $cat->slug)
                ->has('articles.data', 1)
            );
    }

    public function test_category_404_for_unknown_slug(): void
    {
        $this->get(route('news.category', 'no-such-cat'))
            ->assertNotFound();
    }

    // ── Tag ──────────────────────────────────────────────────────────────────

    public function test_tag_page_renders(): void
    {
        $tag = NewsTag::create(['name' => 'CS2', 'slug' => 'cs2']);
        $article = $this->article(['slug' => 'tag-art']);
        $article->tags()->attach($tag);

        $this->get(route('news.tag', $tag->slug))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Web/News/Tag')
                ->where('tag.slug', $tag->slug)
                ->has('articles.data', 1)
            );
    }

    public function test_tag_404_for_unknown_slug(): void
    {
        $this->get(route('news.tag', 'no-such-tag'))
            ->assertNotFound();
    }

    // ── RSS Feed ─────────────────────────────────────────────────────────────

    public function test_rss_feed_returns_xml(): void
    {
        $this->article();

        $this->get(route('news.feed'))
            ->assertOk()
            ->assertHeader('content-type', 'application/rss+xml; charset=UTF-8');
    }
}
