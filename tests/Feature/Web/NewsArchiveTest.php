<?php

namespace Tests\Feature\Web;

use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\NewsTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** The category and tag listings. */
class NewsArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed.lock'), 'installed');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    private function article(string $title, ?NewsCategory $category = null): NewsArticle
    {
        return NewsArticle::create([
            'title' => $title,
            'slug' => \Str::slug($title),
            'excerpt' => 'An excerpt.',
            'body' => 'Body.',
            'format' => 'html',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'category_id' => $category?->id,
            'author_id' => User::factory()->create()->id,
        ]);
    }

    public function test_a_category_lists_only_its_own_articles(): void
    {
        $updates = NewsCategory::create(['name' => 'Updates', 'slug' => 'updates', 'color' => '#3b82f6']);
        $events = NewsCategory::create(['name' => 'Events', 'slug' => 'events', 'color' => '#10b981']);

        $this->article('An update', $updates);
        $this->article('An event', $events);

        $this->get(route('news.category', 'updates'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Web/News/Category')
                ->has('articles.data', 1)
                ->where('articles.data.0.title', 'An update')
            );
    }

    public function test_a_category_page_carries_a_canonical_url(): void
    {
        NewsCategory::create(['name' => 'Updates', 'slug' => 'updates', 'color' => '#3b82f6']);

        $this->get(route('news.category', 'updates'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canonical', fn (string $url) => str_ends_with($url, '/news/category/updates'))
            );
    }

    public function test_a_tag_lists_the_articles_carrying_it(): void
    {
        $tag = NewsTag::create(['name' => 'Patch', 'slug' => 'patch']);

        $tagged = $this->article('A tagged post');
        $tagged->tags()->attach($tag->id);
        $this->article('An untagged post');

        $this->get(route('news.tag', 'patch'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Web/News/Tag')
                ->has('articles.data', 1)
                ->where('articles.data.0.title', 'A tagged post')
            );
    }

    public function test_a_tag_page_carries_a_canonical_url(): void
    {
        NewsTag::create(['name' => 'Patch', 'slug' => 'patch']);

        $this->get(route('news.tag', 'patch'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canonical', fn (string $url) => str_ends_with($url, '/news/tag/patch'))
            );
    }

    /** The shared card renders dates from the ISO value, so it has to be sent. */
    public function test_archive_cards_carry_an_iso_date(): void
    {
        $category = NewsCategory::create(['name' => 'Updates', 'slug' => 'updates', 'color' => '#3b82f6']);
        $this->article('An update', $category);

        $this->get(route('news.category', 'updates'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->whereNot('articles.data.0.published_at_iso', null)
            );
    }

    public function test_an_empty_category_still_renders(): void
    {
        NewsCategory::create(['name' => 'Empty', 'slug' => 'empty', 'color' => '#3b82f6']);

        $this->get(route('news.category', 'empty'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('articles.data', 0));
    }
}
