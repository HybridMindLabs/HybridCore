<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkUserActionsTest extends TestCase
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

    private function adminUser(): User
    {
        return User::factory()->create(['email' => 'admin@test.com', 'is_admin' => true]);
    }

    public function test_bulk_ban_sets_banned_at(): void
    {
        $admin = $this->adminUser();
        $targets = User::factory(3)->create();

        $this->actingAs($admin)
            ->post(route('admin.users.bulk'), [
                'action' => 'ban',
                'user_ids' => $targets->pluck('id')->toArray(),
            ])
            ->assertRedirect();

        foreach ($targets as $user) {
            $this->assertNotNull($user->fresh()->banned_at);
        }
    }

    public function test_bulk_ban_cannot_ban_self(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post(route('admin.users.bulk'), [
                'action' => 'ban',
                'user_ids' => [$admin->id],
            ])
            ->assertRedirect();

        $this->assertNull($admin->fresh()->banned_at);
    }

    public function test_bulk_unban_clears_banned_at(): void
    {
        $admin = $this->adminUser();
        $targets = User::factory(2)->create(['banned_at' => now()]);

        $this->actingAs($admin)
            ->post(route('admin.users.bulk'), [
                'action' => 'unban',
                'user_ids' => $targets->pluck('id')->toArray(),
            ])
            ->assertRedirect();

        foreach ($targets as $user) {
            $this->assertNull($user->fresh()->banned_at);
        }
    }

    public function test_export_csv_returns_csv_file(): void
    {
        $admin = $this->adminUser();
        User::factory(3)->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.users.export'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
