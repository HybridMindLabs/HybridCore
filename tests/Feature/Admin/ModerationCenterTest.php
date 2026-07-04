<?php

namespace Tests\Feature\Admin;

use App\Models\NewsArticle;
use App\Models\NewsComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModerationCenterTest extends TestCase
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

    public function test_admin_can_view_moderation_center(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.moderation.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Moderation/Index')
                ->has('reports')
                ->has('comments')
                ->has('reviews')
                ->has('counts'));
    }

    public function test_moderation_center_lists_comments(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $author = User::factory()->create();
        $article = NewsArticle::create([
            'author_id' => $author->id,
            'title' => 'T', 'slug' => 't-'.uniqid(), 'body' => 'b',
            'format' => 'markdown', 'status' => 'published', 'published_at' => now(),
        ]);
        NewsComment::create(['article_id' => $article->id, 'user_id' => $author->id, 'body' => 'Test comment']);

        $this->actingAs($admin)
            ->get(route('admin.moderation.index'))
            ->assertInertia(fn ($page) => $page
                ->where('counts.comments', 1)
                ->where('comments.data.0.body', 'Test comment'));
    }

    public function test_non_admin_cannot_view_moderation_center(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.moderation.index'))
            ->assertRedirect();
    }
}
