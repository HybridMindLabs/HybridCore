<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\LoginHistory;
use App\Models\NewsArticle;
use App\Models\NewsComment;
use App\Models\Server;
use App\Models\ServerReview;
use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchievementTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AchievementService
    {
        return app(AchievementService::class);
    }

    private function assertHasAchievement(User $user, string $slug): void
    {
        $this->assertDatabaseHas('user_achievements', ['user_id' => $user->id, 'slug' => $slug]);
    }

    private function assertLacksAchievement(User $user, string $slug): void
    {
        $this->assertDatabaseMissing('user_achievements', ['user_id' => $user->id, 'slug' => $slug]);
    }

    public function test_reviewer_pro_awarded_at_10_reviews(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        ServerReview::factory()->count(9)->create([
            'user_id' => $user->id,
        ]);

        $this->service()->check($user);
        $this->assertLacksAchievement($user, 'reviewer_pro');

        $server = Server::factory()->create(['game_id' => $game->id]);
        ServerReview::create(['user_id' => $user->id, 'server_id' => $server->id, 'rating' => 5]);

        $this->service()->check($user);
        $this->assertHasAchievement($user, 'reviewer_pro');
    }

    public function test_regular_awarded_at_50_logins(): void
    {
        $user = User::factory()->create();
        LoginHistory::factory()->count(50)->create(['user_id' => $user->id]);

        $this->service()->check($user);
        $this->assertHasAchievement($user, 'regular');
    }

    public function test_explorer_awarded_for_favourites_across_3_games(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 3) as $i) {
            $game = Game::factory()->create();
            $server = Server::factory()->create(['game_id' => $game->id]);
            $user->favouriteServers()->attach($server->id);
        }

        $this->service()->check($user);
        $this->assertHasAchievement($user, 'explorer');
    }

    public function test_commentator_awarded_at_10_comments(): void
    {
        $user = User::factory()->create();
        $article = NewsArticle::create([
            'author_id' => User::factory()->create()->id,
            'title' => 'T', 'slug' => 't', 'body' => 'b',
            'format' => 'markdown', 'status' => 'published', 'published_at' => now(),
        ]);

        foreach (range(1, 10) as $i) {
            NewsComment::create(['article_id' => $article->id, 'user_id' => $user->id, 'body' => "Comment {$i}"]);
        }

        $this->service()->check($user);
        $this->assertHasAchievement($user, 'commentator');
    }

    public function test_popular_awarded_at_10_followers(): void
    {
        $user = User::factory()->create();
        User::factory()->count(10)->create()
            ->each(fn (User $f) => $f->following()->attach($user->id));

        $this->service()->check($user);
        $this->assertHasAchievement($user, 'popular');
    }

    public function test_award_is_idempotent(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->service()->award($user, 'verified'));
        $this->assertFalse($this->service()->award($user, 'verified'));
        $this->assertSame(1, $user->achievements()->where('slug', 'verified')->count());
    }
}
