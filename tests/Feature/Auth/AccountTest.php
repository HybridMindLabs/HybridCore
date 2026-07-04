<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountTest extends TestCase
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

    public function test_account_page_requires_auth(): void
    {
        $this->get('/account')->assertRedirect(route('login'));
    }

    public function test_account_page_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/account')->assertOk();
    }

    public function test_profile_update_works(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/account/profile', [
                'username' => $user->username,
                'display_name' => 'Cool Name',
                'email' => $user->email,
                'bio' => null,
                'location' => null,
                'website' => null,
                'profile_privacy' => 'public',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Cool Name', $user->fresh()->getRawOriginal('display_name'));
    }

    public function test_email_change_resets_verification(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->put('/account/profile', [
                'email' => 'changed-'.$user->id.'@example.com',
                'profile_privacy' => 'public',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_password_change_works(): void
    {
        $user = User::factory()->create(['password' => Hash::make('oldpassw0rd1')]);

        $this->actingAs($user)
            ->put('/account/password', [
                'current_password' => 'oldpassw0rd1',
                'password' => 'newpassw0rd99',
                'password_confirmation' => 'newpassw0rd99',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('newpassw0rd99', $user->fresh()->password));
    }

    public function test_password_change_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('oldpassw0rd1')]);

        $this->actingAs($user)
            ->put('/account/password', [
                'current_password' => 'wrong-password',
                'password' => 'newpassw0rd99',
                'password_confirmation' => 'newpassw0rd99',
            ])
            ->assertSessionHasErrors('current_password');
    }
}
