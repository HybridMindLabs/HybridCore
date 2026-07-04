<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        @mkdir(storage_path(), 0755, true);
        file_put_contents(storage_path('installed.lock'), 'installed');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));

        parent::tearDown();
    }

    public function test_admin_login_works(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'password' => Hash::make('adminpassw0rd'),
        ]);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'adminpassw0rd'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_normal_user_cannot_login_to_admin(): void
    {
        $user = User::factory()->create(['password' => Hash::make('userpassw0rd1')]);

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'userpassw0rd1'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_normal_user_cannot_access_admin_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_user_can_access_admin_panel(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_public_and_admin_login_pages_are_distinct(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/admin/login')->assertOk();
    }
}
