<?php

namespace Tests\Feature\Support;

use App\Games\Data\QueryResult;
use App\Jobs\DeliverWebhookJob;
use App\Models\ConnectedAccount;
use App\Models\Server;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Services\Extensions\Registries\HookRegistry;
use App\Services\Extensions\Registries\WebhookEventRegistry;
use App\Services\ServerQueryService;
use App\Services\WebhookDispatcher;
use App\Support\CoreWebhookBridge;
use App\Support\Hooks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WebhookDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatcher_only_queues_active_endpoints_subscribed_to_the_event(): void
    {
        Queue::fake();

        $subscribed = WebhookEndpoint::factory()->create(['events' => [Hooks::USER_REGISTERED], 'is_active' => true]);
        WebhookEndpoint::factory()->create(['events' => [Hooks::USER_BANNED], 'is_active' => true]);
        WebhookEndpoint::factory()->create(['events' => [Hooks::USER_REGISTERED], 'is_active' => false]);

        app(WebhookDispatcher::class)->dispatch(Hooks::USER_REGISTERED, ['user_id' => 1]);

        Queue::assertPushed(DeliverWebhookJob::class, 1);
        Queue::assertPushed(DeliverWebhookJob::class, fn (DeliverWebhookJob $job) => $job->webhookEndpointId === $subscribed->id);
    }

    public function test_job_signs_the_body_and_records_a_successful_delivery(): void
    {
        Http::fake(['example.com/*' => Http::response(['ok' => true], 200)]);

        $endpoint = WebhookEndpoint::factory()->create([
            'url' => 'https://example.com/hook',
            'secret' => 'shh-its-a-secret',
        ]);

        (new DeliverWebhookJob($endpoint->id, Hooks::USER_REGISTERED, ['user_id' => 42]))->handle();

        Http::assertSent(function ($request) {
            $body = $request->body();
            $data = json_decode($body, true);
            $expectedSignature = hash_hmac('sha256', $body, 'shh-its-a-secret');

            return $request->hasHeader('X-HybridCore-Signature', $expectedSignature)
                && $request->hasHeader('X-HybridCore-Event', Hooks::USER_REGISTERED)
                && $data['data']['user_id'] === 42;
        });

        $endpoint->refresh();
        $this->assertSame('success', $endpoint->last_status);
        $this->assertSame(200, $endpoint->last_response_code);
        $this->assertNotNull($endpoint->last_triggered_at);
    }

    public function test_job_records_a_failed_delivery_on_a_non_2xx_response(): void
    {
        Http::fake(['example.com/*' => Http::response('nope', 500)]);

        $endpoint = WebhookEndpoint::factory()->create(['url' => 'https://example.com/hook']);

        try {
            (new DeliverWebhookJob($endpoint->id, Hooks::USER_REGISTERED, []))->handle();
        } catch (RequestException) {
            // response()->throw() intentionally triggers Laravel's retry/backoff machinery.
        }

        $endpoint->refresh();
        $this->assertSame('failed', $endpoint->last_status);
        $this->assertSame(500, $endpoint->last_response_code);

        $delivery = $endpoint->deliveries()->sole();
        $this->assertFalse($delivery->success);
        $this->assertSame(500, $delivery->response_code);
    }

    public function test_job_records_a_delivery_on_a_connection_failure(): void
    {
        Http::fake(['example.com/*' => fn () => throw new ConnectionException('Connection refused')]);

        $endpoint = WebhookEndpoint::factory()->create(['url' => 'https://example.com/hook']);

        try {
            (new DeliverWebhookJob($endpoint->id, Hooks::USER_REGISTERED, []))->handle();
        } catch (ConnectionException) {
            // Rethrown so the queue's retry/backoff still applies.
        }

        $endpoint->refresh();
        $this->assertSame('failed', $endpoint->last_status);
        $this->assertNull($endpoint->last_response_code);

        $delivery = $endpoint->deliveries()->sole();
        $this->assertFalse($delivery->success);
        $this->assertNull($delivery->response_code);
        $this->assertNotNull($delivery->error);
    }

    public function test_an_extension_declared_event_is_auto_bridged_to_the_dispatcher(): void
    {
        Queue::fake();

        $events = new WebhookEventRegistry;
        $events->register('store:order.paid', 'Order Paid', 'store');
        $hooks = new HookRegistry;

        CoreWebhookBridge::bridgeExtensionEvents($events, $hooks);

        WebhookEndpoint::factory()->create(['events' => ['store:order.paid'], 'is_active' => true]);

        $hooks->fire('store:order.paid', (object) ['id' => 7]);

        Queue::assertPushed(
            DeliverWebhookJob::class,
            fn (DeliverWebhookJob $job) => $job->event === 'store:order.paid' && $job->payload['arg0'] === 7,
        );
    }

    public function test_server_offline_hook_reaches_a_subscribed_endpoint_with_server_details(): void
    {
        Queue::fake();

        WebhookEndpoint::factory()->create(['events' => [Hooks::SERVER_OFFLINE], 'is_active' => true]);
        $server = Server::factory()->create(['name' => 'Public #1']);
        $service = app(ServerQueryService::class);

        $service->record($server, new QueryResult(online: true));
        $service->record($server, QueryResult::offline('timeout'));

        Queue::assertPushed(DeliverWebhookJob::class, 1);
        Queue::assertPushed(DeliverWebhookJob::class, fn (DeliverWebhookJob $job) => $job->event === Hooks::SERVER_OFFLINE
            && $job->payload['server_id'] === $server->id
            && $job->payload['name'] === 'Public #1'
            && $job->payload['failure_reason'] === 'timeout');
    }

    public function test_a_core_event_is_delivered_once_per_endpoint(): void
    {
        Queue::fake();

        WebhookEndpoint::factory()->create(['events' => [Hooks::USER_REGISTERED], 'is_active' => true]);

        $user = User::factory()->create();
        app(HookRegistry::class)->fire(Hooks::USER_REGISTERED, $user);

        Queue::assertPushed(DeliverWebhookJob::class, 1);
        Queue::assertPushed(DeliverWebhookJob::class, fn (DeliverWebhookJob $job) => $job->payload === ['user_id' => $user->id, 'discord_id' => null]);
    }

    public function test_user_banned_payload_carries_the_linked_discord_id(): void
    {
        Queue::fake();

        WebhookEndpoint::factory()->create(['events' => [Hooks::USER_BANNED], 'is_active' => true]);
        $user = User::factory()->create();
        ConnectedAccount::create(['user_id' => $user->id, 'provider' => 'discord', 'provider_user_id' => '4242']);

        app(HookRegistry::class)->fire(Hooks::USER_BANNED, $user, 'cheating');

        Queue::assertPushed(DeliverWebhookJob::class, fn (DeliverWebhookJob $job) => $job->payload === [
            'user_id' => $user->id,
            'discord_id' => '4242',
            'reason' => 'cheating',
        ]);
    }

    public function test_an_oauth_signup_fires_user_registered_with_the_discord_id(): void
    {
        Queue::fake();
        file_put_contents(storage_path('installed.lock'), 'installed');

        WebhookEndpoint::factory()->create(['events' => [Hooks::USER_REGISTERED], 'is_active' => true]);

        $this->withSession(['oauth.pending' => [
            'provider' => 'discord',
            'provider_user_id' => '777',
            'name' => 'Newbie',
        ]])->post(route('oauth.complete-profile.store'), ['email' => 'newbie@example.com'])
            ->assertRedirect(route('account.index'));

        @unlink(storage_path('installed.lock'));

        $user = User::where('email', 'newbie@example.com')->firstOrFail();
        Queue::assertPushed(DeliverWebhookJob::class, fn (DeliverWebhookJob $job) => $job->event === Hooks::USER_REGISTERED
            && $job->payload === ['user_id' => $user->id, 'discord_id' => '777']);
    }

    public function test_dispatcher_is_a_no_op_before_the_table_exists(): void
    {
        Queue::fake();

        Schema::drop('webhook_endpoints');

        app(WebhookDispatcher::class)->dispatch(Hooks::USER_REGISTERED, []);

        Queue::assertNotPushed(DeliverWebhookJob::class);
    }
}
