<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    private function createLockFile(): void
    {
        @mkdir(storage_path(), 0755, true);
        file_put_contents(storage_path('installed.lock'), 'installed');
    }

    private function removeLockFile(): void
    {
        @unlink(storage_path('installed.lock'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->createLockFile();
    }

    protected function tearDown(): void
    {
        $this->removeLockFile();
        parent::tearDown();
    }

    public function test_login_page_is_accessible_when_installed(): void
    {
        $response = $this->get('/admin/login');
        $response->assertStatus(200);
    }

    public function test_guest_accessing_admin_is_redirected_to_login_when_installed(): void
    {
        $response = $this->get('/admin');
        $response->assertRedirect('/admin/login');
    }

    public function test_non_admin_user_cannot_login_to_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_user_can_login(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_can_logout(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/logout');

        $this->assertGuest();
    }

    public function test_authenticated_non_admin_is_redirected_from_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get('/admin');
        $response->assertRedirect('/admin/login');
    }

    public function test_authenticated_admin_can_access_dashboard(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get('/admin');
        $response->assertStatus(200);
    }
}
