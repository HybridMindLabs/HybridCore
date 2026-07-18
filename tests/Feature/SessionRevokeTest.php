<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SessionRevokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed.lock'), 'installed');
        config(['session.driver' => 'database']);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    private function seedSession(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '203.0.113.10',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120',
            'payload' => base64_encode(serialize([])),
            'last_activity' => time(),
        ]);
    }

    public function test_revoking_another_session_removes_its_row(): void
    {
        $user = User::factory()->create();
        $this->seedSession($user, 'other-device-session');

        $this->actingAs($user)
            ->deleteJson(route('account.sessions.destroy', 'other-device-session'))
            ->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'other-device-session']);
    }

    public function test_you_cannot_revoke_a_session_belonging_to_someone_else(): void
    {
        $victim = User::factory()->create();
        $attacker = User::factory()->create();
        $this->seedSession($victim, 'victim-session');

        $this->actingAs($attacker)
            ->deleteJson(route('account.sessions.destroy', 'victim-session'));

        $this->assertDatabaseHas('sessions', ['id' => 'victim-session']);
    }

    public function test_signing_out_others_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-password')]);
        $this->seedSession($user, 'other-device-session');

        $this->actingAs($user)
            ->deleteJson(route('account.sessions.destroy-others'), ['password' => 'wrong-password'])
            ->assertStatus(422);

        $this->assertDatabaseHas('sessions', ['id' => 'other-device-session']);
    }

    public function test_signing_out_others_clears_their_rows(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-password')]);
        $this->seedSession($user, 'other-device-session');

        $this->actingAs($user)
            ->deleteJson(route('account.sessions.destroy-others'), ['password' => 'secret-password'])
            ->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'other-device-session']);
    }

    /**
     * Deleting the session row alone does not stop a device that logged in with
     * "remember me": its cookie re-authenticates it into a fresh session. The
     * remember token has to be cycled for a sign-out to actually hold.
     */
    public function test_signing_out_others_invalidates_remember_me_cookies(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret-password'),
            'remember_token' => 'the-old-remember-token',
        ]);
        $this->seedSession($user, 'other-device-session');

        $this->actingAs($user)
            ->deleteJson(route('account.sessions.destroy-others'), ['password' => 'secret-password'])
            ->assertOk();

        $this->assertNotSame('the-old-remember-token', $user->fresh()->remember_token);
    }

    /** The page reads res.json(); a redirect made it throw and report a network error. */
    public function test_the_endpoints_answer_json_to_json_requests(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-password')]);
        $this->seedSession($user, 'other-device-session');

        $this->actingAs($user)
            ->deleteJson(route('account.sessions.destroy', 'other-device-session'))
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->actingAs($user)
            ->deleteJson(route('account.sessions.destroy-others'), ['password' => 'secret-password'])
            ->assertOk()
            ->assertJsonStructure(['message']);
    }
}
