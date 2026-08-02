<?php

namespace Tests\Feature\Admin;

use App\Models\IpBan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpBanTest extends TestCase
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

    public function test_ip_bans_page_renders(): void
    {
        $admin = $this->adminUser();
        $this->actingAs($admin)
            ->get(route('admin.ip-bans.index'))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Admin/IpBans/Index'));
    }

    public function test_admin_can_create_ip_ban(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post(route('admin.ip-bans.store'), [
                'ip' => '192.168.1.1',
                'reason' => 'Spam',
                'expires_at' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ip_bans', ['ip' => '192.168.1.1', 'reason' => 'Spam']);
    }

    public function test_admin_can_delete_ip_ban(): void
    {
        $admin = $this->adminUser();
        $ban = IpBan::factory()->create(['ip' => '10.0.0.1']);

        $this->actingAs($admin)
            ->delete(route('admin.ip-bans.destroy', $ban))
            ->assertRedirect();

        $this->assertDatabaseMissing('ip_bans', ['id' => $ban->id]);
    }

    public function test_banned_ip_is_blocked_from_web(): void
    {
        IpBan::factory()->create(['ip' => '5.5.5.5', 'expires_at' => null]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '5.5.5.5'])
            ->get('/');

        $response->assertStatus(403);
    }

    public function test_a_banned_ip_cannot_reach_the_admin_login_page(): void
    {
        IpBan::factory()->create(['ip' => '5.5.5.5', 'expires_at' => null]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '5.5.5.5'])
            ->get(route('admin.login'));

        $response->assertStatus(403);
    }

    public function test_an_already_authenticated_admin_is_not_locked_out_by_their_own_ip_ban(): void
    {
        $admin = $this->adminUser();
        IpBan::factory()->create(['ip' => '5.5.5.5', 'expires_at' => null]);

        $response = $this->actingAs($admin)
            ->withServerVariables(['REMOTE_ADDR' => '5.5.5.5'])
            ->get(route('admin.dashboard'));

        $response->assertStatus(200);
    }
}
