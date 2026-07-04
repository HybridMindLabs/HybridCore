<?php

namespace Tests\Feature\Web;

use App\Models\Game;
use App\Models\Server;
use App\Models\ServerReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerReviewTest extends TestCase
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

    private function makeServer(): Server
    {
        $game = Game::factory()->create();

        return Server::factory()->create(['game_id' => $game->id]);
    }

    public function test_guest_cannot_post_review(): void
    {
        $server = $this->makeServer();

        $this->post(route('servers.reviews.store', $server), ['rating' => 5, 'body' => 'Great!'])
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_post_review(): void
    {
        $user = User::factory()->create();
        $server = $this->makeServer();

        $response = $this->actingAs($user)->post(
            route('servers.reviews.store', $server),
            ['rating' => 4, 'body' => 'Nice server']
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('server_reviews', [
            'server_id' => $server->id,
            'user_id' => $user->id,
            'rating' => 4,
        ]);
    }

    public function test_second_review_updates_existing_one(): void
    {
        $user = User::factory()->create();
        $server = $this->makeServer();
        ServerReview::factory()->create(['user_id' => $user->id, 'server_id' => $server->id, 'rating' => 3]);

        $this->actingAs($user)->post(
            route('servers.reviews.store', $server),
            ['rating' => 5, 'body' => 'Updated review']
        )->assertRedirect();

        $this->assertDatabaseCount('server_reviews', 1);
        $this->assertDatabaseHas('server_reviews', ['user_id' => $user->id, 'rating' => 5]);
    }

    public function test_rating_must_be_between_1_and_5(): void
    {
        $user = User::factory()->create();
        $server = $this->makeServer();

        $this->actingAs($user)
            ->post(route('servers.reviews.store', $server), ['rating' => 6])
            ->assertRedirect()
            ->assertSessionHasErrors('rating');

        $this->actingAs($user)
            ->post(route('servers.reviews.store', $server), ['rating' => 0])
            ->assertRedirect()
            ->assertSessionHasErrors('rating');
    }

    public function test_user_can_delete_own_review(): void
    {
        $user = User::factory()->create();
        $server = $this->makeServer();
        $review = ServerReview::factory()->create(['user_id' => $user->id, 'server_id' => $server->id]);

        $this->actingAs($user)
            ->delete(route('servers.reviews.destroy', [$server, $review]))
            ->assertRedirect();

        $this->assertDatabaseMissing('server_reviews', ['id' => $review->id]);
    }
}
