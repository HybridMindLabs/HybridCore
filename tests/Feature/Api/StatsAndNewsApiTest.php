<?php

namespace Tests\Feature\Api;

use App\Models\NewsArticle;
use App\Models\Server;
use App\Models\ServerSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsAndNewsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_count_only_active_visible_servers(): void
    {
        User::factory()->count(3)->create();

        foreach ([['is_visible' => true, 'players' => 5], ['is_visible' => false, 'players' => 40]] as $s) {
            $server = Server::factory()->create(['is_visible' => $s['is_visible']]);
            ServerSnapshot::create(['server_id' => $server->id, 'is_online' => true, 'players_online' => $s['players'], 'players_max' => 10, 'recorded_at' => now()]);
        }
        Server::factory()->create(['is_active' => false]);

        $this->getJson('/api/v1/stats')
            ->assertOk()
            ->assertJsonPath('data.members', 3)
            ->assertJsonPath('data.servers', 1)
            ->assertJsonPath('data.online_servers', 1)
            ->assertJsonPath('data.players_online', 5)
            ->assertJsonPath('data.max_players', 10);
    }

    public function test_news_lists_only_published_articles_newest_first(): void
    {
        $author = User::factory()->create(['name' => 'Editor']);
        $make = fn (string $slug, string $status, $publishedAt) => NewsArticle::create([
            'author_id' => $author->id, 'title' => $slug, 'slug' => $slug, 'body' => 'b',
            'format' => 'markdown', 'status' => $status, 'published_at' => $publishedAt,
        ]);

        $make('older', 'published', now()->subDays(2));
        $make('newer', 'published', now()->subDay());
        $make('draft', 'draft', null);
        $make('scheduled', 'published', now()->addDay());

        $this->getJson('/api/v1/news')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'newer')
            ->assertJsonPath('data.0.author', 'Editor')
            ->assertJsonPath('data.0.url', route('news.show', 'newer'));
    }
}
