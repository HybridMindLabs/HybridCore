<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemHealthTest extends TestCase
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

    public function test_health_page_requires_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.system-health.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_health_page_renders_checks(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.system-health.index'))
            ->assertOk();
    }

    public function test_clear_cache_action(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.system-health.clear-cache'))
            ->assertRedirect();
    }

    public function test_updates_page_renders(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.updates.index'))
            ->assertOk();
    }

    public function test_update_channel_can_be_changed(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.updates.channel'), ['channel' => 'beta'])
            ->assertRedirect();

        $this->assertDatabaseHas('settings', ['key' => 'update_channel', 'value' => 'beta']);
    }

    public function test_update_channel_rejects_invalid_value(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.updates.channel'), ['channel' => 'hacked'])
            ->assertSessionHasErrors('channel');
    }
}
