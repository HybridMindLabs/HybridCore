<?php

namespace Tests\Feature\Auth;

use App\Mail\NewLoginMail;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NewLoginNotificationTest extends TestCase
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

    public function test_a_brand_new_accounts_first_login_sends_no_alert(): void
    {
        Mail::fake();
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('account.index'));

        Mail::assertNothingQueued();
    }

    public function test_a_second_login_from_the_same_ip_sends_no_alert(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);
        LoginHistory::create(['user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test']);

        Mail::fake();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('account.index'));

        Mail::assertNothingQueued();
    }

    public function test_a_login_from_a_never_seen_ip_sends_an_alert(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);
        LoginHistory::create(['user_id' => $user->id, 'ip_address' => '10.0.0.99', 'user_agent' => 'test']);

        Mail::fake();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('account.index'));

        Mail::assertQueued(NewLoginMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_admin_login_is_recorded_and_alerted_on_a_new_ip(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'password' => bcrypt('password')]);
        LoginHistory::create(['user_id' => $admin->id, 'ip_address' => '10.0.0.99', 'user_agent' => 'test']);

        Mail::fake();

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('login_histories', ['user_id' => $admin->id]);
        Mail::assertQueued(NewLoginMail::class, fn ($mail) => $mail->hasTo($admin->email));
    }
}
