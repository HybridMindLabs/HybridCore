<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterUsernameTest extends TestCase
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

    private function register(array $overrides = []): void
    {
        $this->post(route('register'), [
            'name' => 'Arishem The Judge',
            'email' => 'arishem@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            ...$overrides,
        ]);
    }

    public function test_the_chosen_username_is_the_profile_link_and_the_full_name_is_shown(): void
    {
        $this->register(['username' => 'Arishem_Judge']);

        $user = User::where('email', 'arishem@example.com')->firstOrFail();
        $this->assertSame('arishem_judge', $user->username);
        $this->assertSame('Arishem The Judge', $user->display_name);
    }

    public function test_without_a_username_the_whole_name_is_used(): void
    {
        $this->register();

        $this->assertSame('arishem_the_judge', User::where('email', 'arishem@example.com')->value('username'));
    }

    public function test_a_taken_username_is_rejected(): void
    {
        User::factory()->create(['username' => 'arishem']);

        $this->register(['username' => 'arishem']);

        $this->assertDatabaseMissing('users', ['email' => 'arishem@example.com']);
    }

    public function test_accounts_without_a_display_name_show_their_sign_up_name(): void
    {
        $user = User::factory()->create(['name' => 'Arishem The Judge', 'username' => 'arishem', 'display_name' => null]);

        $this->assertSame('Arishem The Judge', $user->display_name);
    }
}
