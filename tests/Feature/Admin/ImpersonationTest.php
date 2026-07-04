<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonationTest extends TestCase
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

    public function test_admin_can_impersonate_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $target))
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($target);
        $this->assertEquals($admin->id, session('impersonator_id'));
    }

    public function test_impersonation_can_be_stopped(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.impersonate', $target));

        $this->post(route('impersonation.stop'))
            ->assertRedirect(route('admin.users.show', $target->id));

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonator_id'));
    }

    public function test_cannot_impersonate_another_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $otherAdmin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $otherAdmin))
            ->assertForbidden();

        $this->assertAuthenticatedAs($admin);
    }

    public function test_cannot_impersonate_banned_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $banned = User::factory()->create(['banned_at' => now()]);

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $banned))
            ->assertForbidden();
    }

    public function test_non_admin_cannot_impersonate(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.users.impersonate', $target))
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_stop_without_impersonating_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('impersonation.stop'))
            ->assertForbidden();
    }
}
