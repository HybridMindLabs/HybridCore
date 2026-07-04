<?php

namespace Tests\Feature\Admin;

use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsArticleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        @mkdir(storage_path(), 0755, true);
        file_put_contents(storage_path('installed.lock'), 'installed');

        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    public function test_index_requires_admin(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.news.articles.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_index_renders_for_admin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.news.articles.index'))
            ->assertOk();
    }

    public function test_create_page_renders(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.news.articles.create'))
            ->assertOk();
    }

    public function test_store_creates_article(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.news.articles.store'), [
                'title' => 'Hello World',
                'body' => 'Some content here.',
                'status' => 'draft',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('news_articles', ['title' => 'Hello World', 'status' => 'draft']);
    }

    public function test_store_auto_generates_slug(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.news.articles.store'), [
                'title' => 'Auto Slug Test',
                'body' => 'Body text.',
                'status' => 'draft',
            ]);

        $this->assertDatabaseHas('news_articles', ['slug' => 'auto-slug-test']);
    }

    public function test_store_sets_published_at_when_published(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.news.articles.store'), [
                'title' => 'Published Article',
                'body' => 'Body.',
                'status' => 'published',
            ]);

        $article = NewsArticle::where('title', 'Published Article')->first();
        $this->assertNotNull($article?->published_at);
    }

    public function test_store_requires_title_and_body(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.news.articles.store'), ['status' => 'draft'])
            ->assertSessionHasErrors(['title', 'body']);
    }

    public function test_store_rejects_invalid_status(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.news.articles.store'), [
                'title' => 'Test',
                'body' => 'Body.',
                'status' => 'invalid-status',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_update_changes_article(): void
    {
        $article = NewsArticle::create([
            'title' => 'Original',
            'slug' => 'original',
            'body' => 'Old body.',
            'status' => 'draft',
            'author_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.news.articles.update', $article), [
                'title' => 'Updated Title',
                'body' => 'New body.',
                'status' => 'draft',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('news_articles', ['id' => $article->id, 'title' => 'Updated Title']);
    }

    public function test_destroy_deletes_article(): void
    {
        $article = NewsArticle::create([
            'title' => 'To Delete',
            'slug' => 'to-delete',
            'body' => 'Body.',
            'status' => 'draft',
            'author_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.news.articles.destroy', $article))
            ->assertRedirect(route('admin.news.articles.index'));

        $this->assertDatabaseMissing('news_articles', ['id' => $article->id, 'deleted_at' => null]);
    }

    public function test_article_with_category(): void
    {
        $category = NewsCategory::create(['name' => 'Tech', 'slug' => 'tech']);

        $this->actingAs($this->admin)
            ->post(route('admin.news.articles.store'), [
                'title' => 'Category Article',
                'body' => 'Body.',
                'status' => 'draft',
                'category_id' => $category->id,
            ]);

        $this->assertDatabaseHas('news_articles', ['title' => 'Category Article', 'category_id' => $category->id]);
    }
}
