<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterPrecognitionTest extends TestCase
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

    public function test_precognitive_request_validates_single_field_without_registering(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson(route('register'), ['email' => 'taken@example.com'], [
            'Precognition' => 'true',
            'Precognition-Validate-Only' => 'email',
        ])
            ->assertStatus(422)
            ->assertHeader('Precognition', 'true')
            ->assertJsonValidationErrors('email')
            ->assertJsonMissingValidationErrors(['name', 'password']);

        $this->assertSame(1, User::count());
    }

    public function test_precognitive_success_returns_no_content_and_creates_nothing(): void
    {
        $this->postJson(route('register'), ['email' => 'free@example.com'], [
            'Precognition' => 'true',
            'Precognition-Validate-Only' => 'email',
        ])
            ->assertNoContent()
            ->assertHeader('Precognition', 'true');

        $this->assertSame(0, User::count());
    }

    public function test_real_registration_still_works_through_the_new_limiter(): void
    {
        $this->post(route('register'), [
            'name' => 'Precog User',
            'email' => 'precog@example.com',
            'password' => 'Sup3rSecret!x',
            'password_confirmation' => 'Sup3rSecret!x',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'precog@example.com']);
    }
}
