<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembersTest extends TestCase
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

    public function test_members_page_renders(): void
    {
        $response = $this->get(route('members.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Web/Members'));
    }

    public function test_members_page_shows_non_banned_users(): void
    {
        $visible = User::factory()->create(['name' => 'Visible User']);
        $banned = User::factory()->create(['name' => 'Banned User', 'banned_at' => now()]);

        $response = $this->get(route('members.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Web/Members')
            ->where('members.data.0.name', $visible->name)
        );
    }

    public function test_members_search_filters_by_name(): void
    {
        User::factory()->create(['name' => 'AlphaUser', 'username' => 'alpha']);
        User::factory()->create(['name' => 'BetaUser', 'username' => 'beta']);

        $response = $this->get(route('members.index', ['search' => 'Alpha']));

        $response->assertInertia(fn ($page) => $page
            ->component('Web/Members')
            ->has('members.data', 1)
            ->where('members.data.0.name', 'AlphaUser')
        );
    }

    public function test_members_response_includes_is_online(): void
    {
        User::factory()->create(['last_seen_at' => now()->subMinute()]);

        $response = $this->get(route('members.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Web/Members')
            ->has('members.data.0.is_online')
        );
    }
}
