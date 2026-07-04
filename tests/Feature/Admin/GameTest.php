<?php

namespace Tests\Feature\Admin;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        @mkdir(storage_path(), 0755, true);
        file_put_contents(storage_path('installed.lock'), 'installed');

        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    public function test_index_requires_admin(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.servers.games'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_index_renders_for_admin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.servers.games'))
            ->assertOk();
    }

    public function test_store_creates_game(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.servers.games.store'), [
                'name' => 'Counter-Strike',
                'slug' => 'counter-strike',
                'icon' => 'Gamepad2',
                'color' => '#ff6600',
                'query_driver' => 'source',
                'default_port' => 27015,
                'is_active' => true,
                'sort_order' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('games', ['name' => 'Counter-Strike', 'slug' => 'counter-strike']);
    }

    public function test_store_requires_name_slug_and_port(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.servers.games.store'), [])
            ->assertSessionHasErrors(['name', 'slug', 'default_port']);
    }

    public function test_store_rejects_duplicate_slug(): void
    {
        Game::factory()->create(['slug' => 'existing-game']);

        $this->actingAs($this->admin)
            ->post(route('admin.servers.games.store'), [
                'name' => 'Other',
                'slug' => 'existing-game',
                'icon' => 'Gamepad2',
                'color' => '#000000',
                'query_driver' => 'source',
                'default_port' => 27015,
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_store_rejects_invalid_port(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.servers.games.store'), [
                'name' => 'Test',
                'slug' => 'test-game',
                'icon' => 'Gamepad2',
                'color' => '#000000',
                'query_driver' => 'source',
                'default_port' => 99999,
            ])
            ->assertSessionHasErrors('default_port');
    }

    public function test_update_changes_game(): void
    {
        $game = Game::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->admin)
            ->put(route('admin.servers.games.update', $game), [
                'name' => 'New Name',
                'slug' => $game->slug,
                'icon' => 'Gamepad2',
                'color' => '#ff0000',
                'query_driver' => 'source',
                'default_port' => 27016,
                'is_active' => true,
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('games', ['id' => $game->id, 'name' => 'New Name', 'default_port' => 27016]);
    }

    public function test_destroy_deletes_game(): void
    {
        $game = Game::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.servers.games.destroy', $game))
            ->assertRedirect();

        $this->assertDatabaseMissing('games', ['id' => $game->id]);
    }

    public function test_store_rejects_invalid_color(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.servers.games.store'), [
                'name' => 'Test',
                'slug' => 'test-game2',
                'icon' => 'Gamepad2',
                'color' => 'red',
                'query_driver' => 'source',
                'default_port' => 27015,
            ])
            ->assertSessionHasErrors('color');
    }
}
