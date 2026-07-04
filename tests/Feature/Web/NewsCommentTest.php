<?php

namespace Tests\Feature\Web;

use App\Models\NewsArticle;
use App\Models\NewsComment;
use App\Models\User;
use App\Notifications\MentionNotification;
use App\Notifications\NewCommentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NewsCommentTest extends TestCase
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

    private function article(array $attrs = []): NewsArticle
    {
        return NewsArticle::create(array_merge([
            'author_id' => User::factory()->create()->id,
            'title' => 'Test Article',
            'slug' => 'test-article',
            'body' => 'Body content',
            'format' => 'markdown',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ], $attrs));
    }

    public function test_guest_cannot_comment(): void
    {
        $article = $this->article();

        $this->post(route('news.comments.store', $article->slug), ['body' => 'Hello'])
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_comment(): void
    {
        $user = User::factory()->create();
        $article = $this->article();

        $this->actingAs($user)
            ->post(route('news.comments.store', $article->slug), ['body' => 'Great article!'])
            ->assertRedirect();

        $this->assertDatabaseHas('news_comments', [
            'article_id' => $article->id,
            'user_id' => $user->id,
            'body' => 'Great article!',
        ]);
    }

    public function test_comment_requires_min_length(): void
    {
        $user = User::factory()->create();
        $article = $this->article();

        $this->actingAs($user)
            ->post(route('news.comments.store', $article->slug), ['body' => 'x'])
            ->assertSessionHasErrors('body');
    }

    public function test_cannot_comment_on_draft_article(): void
    {
        $user = User::factory()->create();
        $article = $this->article(['status' => 'draft']);

        $this->actingAs($user)
            ->post(route('news.comments.store', $article->slug), ['body' => 'Hello there'])
            ->assertNotFound();
    }

    public function test_author_is_notified_of_comment(): void
    {
        Notification::fake();

        $author = User::factory()->create();
        $commenter = User::factory()->create();
        $article = $this->article(['author_id' => $author->id]);

        $this->actingAs($commenter)
            ->post(route('news.comments.store', $article->slug), ['body' => 'Nice one!']);

        Notification::assertSentTo($author, NewCommentNotification::class);
    }

    public function test_mentioned_user_is_notified(): void
    {
        Notification::fake();

        $mentioned = User::factory()->create(['username' => 'target_user']);
        $commenter = User::factory()->create();
        $article = $this->article();

        $this->actingAs($commenter)
            ->post(route('news.comments.store', $article->slug), ['body' => 'Hey @target_user check this']);

        Notification::assertSentTo($mentioned, MentionNotification::class);
    }

    public function test_user_can_delete_own_comment(): void
    {
        $user = User::factory()->create();
        $article = $this->article();
        $comment = NewsComment::create(['article_id' => $article->id, 'user_id' => $user->id, 'body' => 'Mine']);

        $this->actingAs($user)
            ->delete(route('news.comments.destroy', [$article->slug, $comment->id]))
            ->assertRedirect();

        $this->assertSoftDeleted('news_comments', ['id' => $comment->id]);
    }

    public function test_user_cannot_delete_others_comment(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $article = $this->article();
        $comment = NewsComment::create(['article_id' => $article->id, 'user_id' => $owner->id, 'body' => 'Not yours']);

        $this->actingAs($other)
            ->delete(route('news.comments.destroy', [$article->slug, $comment->id]))
            ->assertForbidden();

        $this->assertDatabaseHas('news_comments', ['id' => $comment->id]);
    }

    public function test_admin_can_delete_any_comment(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $article = $this->article();
        $comment = NewsComment::create(['article_id' => $article->id, 'user_id' => $owner->id, 'body' => 'Spam']);

        $this->actingAs($admin)
            ->delete(route('news.comments.destroy', [$article->slug, $comment->id]))
            ->assertRedirect();

        $this->assertSoftDeleted('news_comments', ['id' => $comment->id]);
    }

    public function test_comments_appear_on_article_page(): void
    {
        $user = User::factory()->create();
        $article = $this->article();
        NewsComment::create(['article_id' => $article->id, 'user_id' => $user->id, 'body' => 'Visible comment']);

        $this->get(route('news.show', $article->slug))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Web/News/Show')
                ->has('comments', 1)
                ->where('comments.0.body', 'Visible comment'));
    }
}
