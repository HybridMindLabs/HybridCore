<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
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
            ->get(route('admin.activity-log.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('admin.activity-log.index'))
            ->assertRedirect();
    }

    public function test_index_renders_for_admin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.activity-log.index'))
            ->assertOk();
    }

    public function test_index_shows_log_entries(): void
    {
        ActivityLog::create([
            'event' => 'user.login',
            'description' => 'User logged in',
            'causer_id' => $this->admin->id,
            'causer_type' => User::class,
        ]);

        $this->assertDatabaseCount('activity_log', 1);

        $this->actingAs($this->admin)
            ->get(route('admin.activity-log.index'))
            ->assertOk();
    }

    public function test_index_renders_with_empty_log(): void
    {
        $this->assertDatabaseCount('activity_log', 0);

        $this->actingAs($this->admin)
            ->get(route('admin.activity-log.index'))
            ->assertOk();
    }
}
