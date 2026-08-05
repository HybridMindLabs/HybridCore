<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Server;
use App\Models\ServerCommand;
use App\Models\User;
use App\Services\Bridge\BridgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BridgeTest extends TestCase
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

    private function server(): Server
    {
        return Server::factory()->create(['game_id' => Game::factory()->create()->id]);
    }

    private function bridge(): BridgeService
    {
        return app(BridgeService::class);
    }

    // ── Tokens ───────────────────────────────────────────────────

    public function test_token_is_stored_hashed_and_resolves(): void
    {
        $server = $this->server();
        $plain = $this->bridge()->issueToken($server);

        $this->assertStringStartsWith('hcb_', $plain);
        $this->assertNotSame($plain, $server->fresh()->bridge_token_hash);
        $this->assertSame(hash('sha256', $plain), $server->fresh()->bridge_token_hash);
        $this->assertTrue($server->fresh()->bridge_enabled);
        $this->assertSame($server->id, $this->bridge()->resolveServer($plain)?->id);
    }

    public function test_revoked_or_bogus_token_does_not_resolve(): void
    {
        $server = $this->server();
        $plain = $this->bridge()->issueToken($server);
        $this->bridge()->revokeToken($server);

        $this->assertNull($this->bridge()->resolveServer($plain));
        $this->assertNull($this->bridge()->resolveServer('hcb_bogus'));
        $this->assertNull($this->bridge()->resolveServer(null));
    }

    public function test_inactive_server_token_does_not_resolve(): void
    {
        $server = $this->server();
        $plain = $this->bridge()->issueToken($server);
        $server->update(['is_active' => false]);

        $this->assertNull($this->bridge()->resolveServer($plain));
    }

    public function test_token_hash_is_hidden_from_serialization(): void
    {
        $server = $this->server();
        $this->bridge()->issueToken($server);

        $this->assertArrayNotHasKey('bridge_token_hash', $server->fresh()->toArray());
    }

    // ── Queue validation ─────────────────────────────────────────

    public function test_commands_with_control_characters_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->bridge()->queue($this->server(), "say hi\nrcon_password steal");
    }

    public function test_empty_and_oversized_commands_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->bridge()->queue($this->server(), str_repeat('a', 501));
    }

    // ── API protocol ─────────────────────────────────────────────

    public function test_poll_requires_valid_token(): void
    {
        $this->postJson('/api/bridge/poll')->assertUnauthorized();

        $this->postJson('/api/bridge/poll', [], ['Authorization' => 'Bearer hcb_wrong'])
            ->assertUnauthorized();
    }

    public function test_poll_delivers_commands_and_ack_confirms(): void
    {
        $server = $this->server();
        $token = $this->bridge()->issueToken($server);

        $queued = $this->bridge()->queue($server, 'hc_give_vip STEAM_0:1:1 30d', 'hybridcore/store');

        $response = $this->postJson('/api/bridge/poll', [], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('commands.0.command', 'hc_give_vip STEAM_0:1:1 30d');

        $this->assertSame(ServerCommand::STATUS_DELIVERED, $queued->fresh()->status);
        $this->assertNotNull($server->fresh()->bridge_last_seen_at);

        $id = $response->json('commands.0.id');

        $this->postJson('/api/bridge/ack', ['ids' => [$id]], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJson(['acked' => 1]);

        $this->assertSame(ServerCommand::STATUS_ACKED, $queued->fresh()->status);
    }

    public function test_commands_are_scoped_per_server(): void
    {
        $serverA = $this->server();
        $serverB = $this->server();
        $tokenB = $this->bridge()->issueToken($serverB);

        $this->bridge()->queue($serverA, 'only_for_a');

        $this->postJson('/api/bridge/poll', [], ['Authorization' => "Bearer {$tokenB}"])
            ->assertOk()
            ->assertJsonCount(0, 'commands');
    }

    public function test_expired_commands_are_never_delivered(): void
    {
        $server = $this->server();
        $token = $this->bridge()->issueToken($server);

        $command = $this->bridge()->queue($server, 'too_late', 'core', 10);
        $command->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->postJson('/api/bridge/poll', [], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonCount(0, 'commands');

        $this->assertSame(ServerCommand::STATUS_EXPIRED, $command->fresh()->status);
    }

    // ── Cancelling ──────────────────────────────────────────────

    public function test_cancel_marks_a_pending_command_cancelled_and_it_is_never_delivered(): void
    {
        $server = $this->server();
        $token = $this->bridge()->issueToken($server);
        $command = $this->bridge()->queue($server, 'hc_ban STEAM_0:1:1');

        $this->assertTrue($this->bridge()->cancel($server, $command));
        $this->assertSame(ServerCommand::STATUS_CANCELLED, $command->fresh()->status);

        $this->postJson('/api/bridge/poll', [], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonCount(0, 'commands');
    }

    public function test_cancel_refuses_a_command_already_delivered(): void
    {
        $server = $this->server();
        $command = $this->bridge()->queue($server, 'hc_ban STEAM_0:1:1');
        $this->bridge()->pull($server);

        $this->assertFalse($this->bridge()->cancel($server, $command));
        $this->assertSame(ServerCommand::STATUS_DELIVERED, $command->fresh()->status);
    }

    public function test_cancel_refuses_a_command_belonging_to_another_server(): void
    {
        $serverA = $this->server();
        $serverB = $this->server();
        $command = $this->bridge()->queue($serverA, 'hc_ban STEAM_0:1:1');

        $this->assertFalse($this->bridge()->cancel($serverB, $command));
    }

    // ── Rate limiting ───────────────────────────────────────────

    public function test_bridge_throttle_is_scoped_per_server_not_shared_across_servers(): void
    {
        config(['hybridcore.bridge_rate_limit' => 1]);

        $serverA = $this->server();
        $serverB = $this->server();
        $tokenA = $this->bridge()->issueToken($serverA);
        $tokenB = $this->bridge()->issueToken($serverB);

        $this->postJson('/api/bridge/poll', [], ['Authorization' => "Bearer {$tokenA}"])->assertOk();
        $this->postJson('/api/bridge/poll', [], ['Authorization' => "Bearer {$tokenA}"])->assertStatus(429);

        // Server B has its own budget — A being throttled doesn't touch it,
        // which would fail if the limiter were still keyed by IP (both
        // requests come from the same test client "IP").
        $this->postJson('/api/bridge/poll', [], ['Authorization' => "Bearer {$tokenB}"])->assertOk();
    }

    // ── Admin ────────────────────────────────────────────────────

    public function test_admin_can_issue_and_revoke_token(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $server = $this->server();

        $this->actingAs($admin)
            ->post(route('admin.servers.bridge.issue', $server))
            ->assertRedirect()
            ->assertSessionHas('bridge_token');

        $this->assertTrue($server->fresh()->bridge_enabled);

        $this->actingAs($admin)
            ->delete(route('admin.servers.bridge.revoke', $server))
            ->assertRedirect();

        $this->assertFalse($server->fresh()->bridge_enabled);
        $this->assertNull($server->fresh()->bridge_token_hash);
    }

    public function test_non_admin_cannot_issue_token(): void
    {
        $server = $this->server();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.servers.bridge.issue', $server))
            ->assertRedirect();

        $this->assertFalse($server->fresh()->bridge_enabled);
    }
}
