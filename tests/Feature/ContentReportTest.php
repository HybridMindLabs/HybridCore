<?php

namespace Tests\Feature;

use App\Models\ContentReport;
use App\Models\NewsArticle;
use App\Models\NewsComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentReportTest extends TestCase
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

    private function comment(?User $author = null): NewsComment
    {
        $article = NewsArticle::create([
            'author_id' => User::factory()->create()->id,
            'title' => 'T', 'slug' => 't-'.uniqid(), 'body' => 'b',
            'format' => 'markdown', 'status' => 'published', 'published_at' => now(),
        ]);

        return NewsComment::create([
            'article_id' => $article->id,
            'user_id' => ($author ?? User::factory()->create())->id,
            'body' => 'A comment',
        ]);
    }

    public function test_guest_cannot_report(): void
    {
        $comment = $this->comment();

        $this->post(route('report.store'), ['type' => 'comment', 'id' => $comment->id, 'reason' => 'spam'])
            ->assertRedirect(route('login'));
    }

    public function test_user_can_report_a_comment(): void
    {
        $user = User::factory()->create();
        $comment = $this->comment();

        $this->actingAs($user)
            ->post(route('report.store'), ['type' => 'comment', 'id' => $comment->id, 'reason' => 'spam'])
            ->assertRedirect();

        $this->assertDatabaseHas('content_reports', [
            'reporter_id' => $user->id,
            'reportable_id' => $comment->id,
            'reason' => 'spam',
            'status' => 'open',
        ]);
    }

    public function test_duplicate_report_is_not_created(): void
    {
        $user = User::factory()->create();
        $comment = $this->comment();

        $this->actingAs($user)->post(route('report.store'), ['type' => 'comment', 'id' => $comment->id, 'reason' => 'spam']);
        $this->actingAs($user)->post(route('report.store'), ['type' => 'comment', 'id' => $comment->id, 'reason' => 'abuse']);

        $this->assertSame(1, ContentReport::count());
    }

    public function test_cannot_report_own_content(): void
    {
        $user = User::factory()->create();
        $comment = $this->comment($user);

        $this->actingAs($user)
            ->post(route('report.store'), ['type' => 'comment', 'id' => $comment->id, 'reason' => 'spam'])
            ->assertStatus(422);
    }

    public function test_invalid_type_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('report.store'), ['type' => 'user', 'id' => 1, 'reason' => 'spam'])
            ->assertSessionHasErrors('type');
    }

    public function test_admin_sees_open_reports(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $reporter = User::factory()->create();
        $comment = $this->comment();

        $this->actingAs($reporter)->post(route('report.store'), ['type' => 'comment', 'id' => $comment->id, 'reason' => 'abuse']);

        $this->actingAs($admin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Admin/Reports/Index')
                ->has('reports.data', 1)
                ->where('reports.data.0.reason', 'abuse'));
    }

    public function test_admin_can_resolve_report(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $reporter = User::factory()->create();
        $comment = $this->comment();

        $this->actingAs($reporter)->post(route('report.store'), ['type' => 'comment', 'id' => $comment->id, 'reason' => 'spam']);
        $report = ContentReport::first();

        $this->actingAs($admin)
            ->post(route('admin.reports.resolve', $report->id))
            ->assertRedirect();

        $this->assertSame('resolved', $report->fresh()->status);
    }

    public function test_admin_can_delete_reported_content(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $reporter = User::factory()->create();
        $comment = $this->comment();

        $this->actingAs($reporter)->post(route('report.store'), ['type' => 'comment', 'id' => $comment->id, 'reason' => 'abuse']);
        $report = ContentReport::first();

        $this->actingAs($admin)
            ->delete(route('admin.reports.destroy-content', $report->id))
            ->assertRedirect();

        $this->assertSoftDeleted('news_comments', ['id' => $comment->id]);
        $this->assertSame('resolved', $report->fresh()->status);
    }
}
