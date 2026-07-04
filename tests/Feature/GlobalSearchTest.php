<?php

namespace Tests\Feature;

use App\Models\NewsArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
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

    public function test_search_finds_users_and_articles(): void
    {
        $user = User::factory()->create(['username' => 'searchtarget', 'name' => 'Search Target']);
        NewsArticle::create([
            'author_id' => $user->id,
            'title' => 'Searchtarget grand opening',
            'slug' => 'grand-'.uniqid(), 'body' => 'b', 'excerpt' => 'e',
            'format' => 'markdown', 'status' => 'published', 'published_at' => now()->subMinute(),
        ]);

        $this->getJson('/api/search?q=searchtarget')
            ->assertOk()
            ->assertJsonPath('users.0.username', 'searchtarget')
            ->assertJsonPath('articles.0.title', 'Searchtarget grand opening');
    }

    public function test_banned_users_and_drafts_are_hidden(): void
    {
        $banned = User::factory()->create(['username' => 'hiddenban', 'banned_at' => now()]);
        NewsArticle::create([
            'author_id' => $banned->id,
            'title' => 'Hiddenban draft article',
            'slug' => 'd-'.uniqid(), 'body' => 'b',
            'format' => 'markdown', 'status' => 'draft',
        ]);

        $this->getJson('/api/search?q=hiddenban')
            ->assertOk()
            ->assertJsonCount(0, 'users')
            ->assertJsonCount(0, 'articles');
    }

    public function test_short_queries_return_empty(): void
    {
        $this->getJson('/api/search?q=a')
            ->assertOk()
            ->assertJson(['users' => [], 'servers' => [], 'articles' => []]);
    }
}
