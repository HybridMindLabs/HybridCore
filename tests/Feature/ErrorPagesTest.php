<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
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

    public function test_404_uses_custom_error_page(): void
    {
        $this->get('/this-page-definitely-does-not-exist')
            ->assertNotFound()
            ->assertSee('Page Not Found')
            ->assertSee('HybridCore');
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.login.store'), [
                'email' => 'attacker@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $this->post(route('admin.login.store'), [
            'email' => 'attacker@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }
}
