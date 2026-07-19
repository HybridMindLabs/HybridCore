<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterRedirectTest extends TestCase
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

    public function test_registering_lands_on_the_site_rather_than_a_wizard(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'New Player',
            'email' => 'new.player@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();
    }

    public function test_the_onboarding_wizard_is_gone(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/welcome')
            ->assertNotFound();
    }
}
