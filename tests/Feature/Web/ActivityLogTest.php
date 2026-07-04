<?php

namespace Tests\Feature\Web;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
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

    public function test_activity_log_requires_auth(): void
    {
        $this->get(route('account.activity'))->assertRedirect(route('login'));
    }

    public function test_account_index_contains_login_history(): void
    {
        $user = User::factory()->create();
        LoginHistory::factory()->create(['user_id' => $user->id, 'ip_address' => '1.2.3.4']);

        $response = $this->actingAs($user)->get(route('account.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Account/Index')
            ->has('loginHistory.data', 1)
            ->where('loginHistory.data.0.ip', '1.2.3.4')
        );
    }

    public function test_activity_log_only_shows_own_history(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        LoginHistory::factory()->create(['user_id' => $user->id, 'ip_address' => '10.0.0.1']);
        LoginHistory::factory()->create(['user_id' => $other->id, 'ip_address' => '10.0.0.2']);

        $response = $this->actingAs($user)->get(route('account.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('loginHistory.data', 1)
            ->where('loginHistory.data.0.ip', '10.0.0.1')
        );
    }
}
