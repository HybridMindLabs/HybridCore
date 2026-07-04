<?php

namespace Tests\Feature\Admin;

use App\Models\NewsArticle;
use App\Models\NewsComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsCommentModerationTest extends TestCase
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

    private function comment(array $attrs = []): NewsComment
    {
        $article = NewsArticle::create([
            'author_id' => User::factory()->create()->id,
            'title' => 'T', 'slug' => 't-'.uniqid(), 'body' => 'b',
            'format' => 'markdown', 'status' => 'published', 'published_at' => now(),
        ]);

        return NewsComment::create(array_merge([
            'article_id' => $article->id,
            'user_id' => User::factory()->create()->id,
            'body' => 'A comment',
        ], $attrs));
    }

    public function test_regular_user_cannot_access_moderation(): void
    {
        $user = User::factory()->create();
        $comment = $this->comment();

        // Non-admins are redirected away from the admin panel.
        $this->actingAs($user)
            ->get(route('admin.news.comments.index'))
            ->assertRedirect();

        $this->actingAs($user)
            ->delete(route('admin.news.comments.destroy', $comment->id))
            ->assertRedirect();

        $this->assertDatabaseHas('news_comments', ['id' => $comment->id]);
    }

    public function test_admin_sees_comment_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->comment(['body' => 'Listed comment']);

        $this->actingAs($admin)
            ->get(route('admin.news.comments.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Admin/News/Comments/Index')
                ->has('comments.data', 1));
    }

    public function test_admin_can_delete_comment(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $comment = $this->comment();

        $this->actingAs($admin)
            ->delete(route('admin.news.comments.destroy', $comment->id))
            ->assertRedirect();

        $this->assertSoftDeleted('news_comments', ['id' => $comment->id]);
    }

    public function test_admin_can_bulk_delete(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $a = $this->comment();
        $b = $this->comment();
        $keep = $this->comment();

        $this->actingAs($admin)
            ->post(route('admin.news.comments.bulk'), ['ids' => [$a->id, $b->id]])
            ->assertRedirect();

        $this->assertSoftDeleted('news_comments', ['id' => $a->id]);
        $this->assertSoftDeleted('news_comments', ['id' => $b->id]);
        $this->assertDatabaseHas('news_comments', ['id' => $keep->id]);
    }
}
