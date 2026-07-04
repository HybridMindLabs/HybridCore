<?php

namespace Tests\Feature\Admin;

use App\Models\NewsArticle;
use App\Models\NewsComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrashTest extends TestCase
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

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function article(): NewsArticle
    {
        return NewsArticle::create([
            'author_id' => User::factory()->create()->id,
            'title' => 'Trashed', 'slug' => 't-'.uniqid(), 'body' => 'b',
            'format' => 'markdown', 'status' => 'published', 'published_at' => now(),
        ]);
    }

    public function test_trash_page_lists_deleted_content(): void
    {
        $article = $this->article();
        $comment = NewsComment::create([
            'article_id' => $article->id,
            'user_id' => User::factory()->create()->id,
            'body' => 'Bye',
        ]);
        $comment->delete();
        $article->delete();

        $this->actingAs($this->admin())
            ->get(route('admin.trash.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Trash/Index')
                ->where('articles.total', 1)
                ->where('comments.total', 1));
    }

    public function test_deleted_comment_disappears_from_public_and_moderation_counts(): void
    {
        $article = $this->article();
        $comment = NewsComment::create([
            'article_id' => $article->id,
            'user_id' => User::factory()->create()->id,
            'body' => 'Hidden',
        ]);
        $comment->delete();

        $this->assertSame(0, NewsComment::count());
        $this->assertSame(1, NewsComment::withTrashed()->count());
    }

    public function test_article_can_be_restored(): void
    {
        $article = $this->article();
        $article->delete();

        $this->actingAs($this->admin())
            ->post(route('admin.trash.articles.restore', $article->id))
            ->assertRedirect();

        $this->assertNull($article->fresh()->deleted_at);
    }

    public function test_comment_can_be_restored(): void
    {
        $comment = NewsComment::create([
            'article_id' => $this->article()->id,
            'user_id' => User::factory()->create()->id,
            'body' => 'Back again',
        ]);
        $comment->delete();

        $this->actingAs($this->admin())
            ->post(route('admin.trash.comments.restore', $comment->id))
            ->assertRedirect();

        $this->assertNull($comment->fresh()->deleted_at);
    }

    public function test_article_can_be_purged_forever(): void
    {
        $article = $this->article();
        $article->delete();

        $this->actingAs($this->admin())
            ->delete(route('admin.trash.articles.force-delete', $article->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('news_articles', ['id' => $article->id]);
    }

    public function test_old_trash_is_prunable(): void
    {
        $article = $this->article();
        $article->delete();
        $article->forceFill(['deleted_at' => now()->subDays(NewsArticle::TRASH_RETENTION_DAYS + 1)])->saveQuietly();

        $this->artisan('model:prune', ['--model' => [NewsArticle::class]]);

        $this->assertDatabaseMissing('news_articles', ['id' => $article->id]);
    }

    public function test_non_admin_cannot_access_trash(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.trash.index'))
            ->assertRedirect();
    }
}
