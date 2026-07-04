<?php

namespace Tests\Feature\Web;

use App\Models\Game;
use App\Models\Server;
use App\Models\ServerSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerUptimeTest extends TestCase
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

    public function test_server_page_includes_30_day_uptime_timeline(): void
    {
        $game = Game::factory()->create();
        $server = Server::factory()->create(['game_id' => $game->id]);

        $this->get(route('servers.show', [$game->slug, $server->ip, $server->port]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Web/Servers/Show')
                ->has('uptime_daily', 30)
                ->has('stats.uptime_7d')
                ->has('stats.uptime_30d'));
    }

    public function test_uptime_percentages_reflect_snapshots(): void
    {
        $game = Game::factory()->create();
        $server = Server::factory()->create(['game_id' => $game->id]);

        // Today: 3 online + 1 offline snapshot → 75%
        foreach ([true, true, true, false] as $online) {
            ServerSnapshot::create([
                'server_id' => $server->id,
                'is_online' => $online,
                'players_online' => 0,
                'players_max' => 32,
                // Anchored inside today so the test can't drift into
                // yesterday when run just after midnight.
                'recorded_at' => now()->startOfDay()->addMinute(),
            ]);
        }

        $this->get(route('servers.show', [$game->slug, $server->ip, $server->port]))
            ->assertInertia(fn ($p) => $p
                ->where('uptime_daily.29.pct', 75)
                ->where('stats.uptime_30d', 75));
    }

    public function test_days_without_snapshots_are_null(): void
    {
        $game = Game::factory()->create();
        $server = Server::factory()->create(['game_id' => $game->id]);

        $this->get(route('servers.show', [$game->slug, $server->ip, $server->port]))
            ->assertInertia(fn ($p) => $p
                ->where('uptime_daily.0.pct', null)
                ->where('stats.uptime_30d', null));
    }
}
