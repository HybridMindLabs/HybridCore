<?php

namespace Tests\Feature\Web;

use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class NewsIndexTest extends TestCase
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
            'excerpt' => 'An excerpt for '.$title,
            'body' => 'Body text.',
            'format' => 'markdown',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'category_id' => $category?->id,
            'author_id' => User::factory()->create()->id,
        ]);
    }

    private function category(string $name): NewsCategory
    {
        return NewsCategory::create(['name' => $name, 'slug' => \Str::slug($name), 'color' => '#3b82f6']);
    }

    /** The page sent ?search=, the controller read ?q= — the box did nothing. */
    public function test_the_search_parameter_the_page_sends_actually_filters(): void
    {
        $this->article('Season one begins');
        $this->article('Server maintenance window');

        $this->get(route('news.index', ['q' => 'maintenance']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Web/News/Index')
                ->has('articles.data', 1)
                ->where('articles.data.0.title', 'Server maintenance window')
            );
    }

    /** withCount names the column published_articles_count; the page reads articles_count. */
    public function test_category_pills_receive_a_usable_article_count(): void
    {
        $category = $this->category('Updates');
        $this->article('First update', $category);
        $this->article('Second update', $category);

        $this->get(route('news.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('categories.0.articles_count', 2)
            );
    }

    /** % and _ are LIKE wildcards, so they matched everything instead of themselves. */
    public function test_wildcard_characters_are_searched_literally(): void
    {
        $this->article('Server maintenance window');
        $this->article('Uptime hit 100% last month');

        $this->get(route('news.index', ['q' => '100%']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('articles.data', 1)
                ->where('articles.data.0.title', 'Uptime hit 100% last month')
            );
    }

    public function test_cards_carry_an_iso_date_for_locale_aware_rendering(): void
    {
        $this->article('Season one begins');

        $this->get(route('news.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->whereNot('articles.data.0.published_at_iso', null)
            );
    }

    public function test_a_category_query_resolves_to_its_name_for_the_heading(): void
    {
        $category = $this->category('Patch Notes');
        $this->article('Patch 1.2', $category);

        $this->get(route('news.index', ['category' => 'patch-notes']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('currentCategoryName', 'Patch Notes')
            );
    }

    public function test_the_feed_reports_the_application_locale(): void
    {
        $this->article('Season one begins');

        $response = $this->get(route('news.feed'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
        $this->assertStringContainsString('<language>'.app()->getLocale().'</language>', $response->getContent());
    }
}
