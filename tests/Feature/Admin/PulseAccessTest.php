<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PulseAccessTest extends TestCase
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

    public function test_guest_cannot_view_pulse(): void
    {
        $this->get('/pulse')->assertForbidden();
    }

    public function test_regular_user_cannot_view_pulse(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/pulse')
            ->assertForbidden();
    }

    public function test_admin_can_view_pulse(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/pulse')
            ->assertOk();
    }
}
