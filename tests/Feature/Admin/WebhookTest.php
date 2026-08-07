<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Support\Hooks;
use Database\Seeders\CorePermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        @mkdir(storage_path(), 0755, true);
        file_put_contents(storage_path('installed.lock'), 'installed');

        (new CorePermissionsSeeder)->run();

        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    private function staffWithoutWebhooksManage(): User
    {
        $user = User::factory()->create(['is_admin' => false]);

        $role = Role::create(['name' => 'Restricted', 'slug' => 'restricted-'.uniqid()]);
        $ids = Permission::whereIn('slug', ['admin.access'])->pluck('id');
        $role->permissions()->sync($ids);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_index_requires_the_manage_permission(): void
    {
        $this->actingAs($this->staffWithoutWebhooksManage())
            ->get(route('admin.webhooks.index'))
            ->assertForbidden();
    }

    public function test_store_creates_an_endpoint_and_flashes_the_secret_once(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.webhooks.store'), [
            'name' => 'Discord Relay',
            'url' => 'https://example.com/hook',
            'events' => [Hooks::USER_REGISTERED],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('webhook_secret');
        $secret = $response->getSession()->get('webhook_secret');

        $this->get(route('admin.webhooks.index'))
            ->assertInertia(fn ($page) => $page->where('flash.webhook_secret', $secret));

        $endpoint = WebhookEndpoint::where('name', 'Discord Relay')->firstOrFail();
        $this->assertSame($this->admin->id, $endpoint->created_by);
        $this->assertSame([Hooks::USER_REGISTERED], $endpoint->events);
        $this->assertTrue($endpoint->is_active);
    }

    public function test_store_rejects_an_event_not_in_the_hook_list(): void
    {
        $this->actingAs($this->admin)->post(route('admin.webhooks.store'), [
            'name' => 'Bad Hook',
            'url' => 'https://example.com/hook',
            'events' => ['not.a.real.hook'],
        ])->assertSessionHasErrors('events.0');

        $this->assertDatabaseMissing('webhook_endpoints', ['name' => 'Bad Hook']);
    }

    public function test_index_never_exposes_the_secret(): void
    {
        WebhookEndpoint::factory()->create(['name' => 'Discord Relay']);

        $this->actingAs($this->admin)
            ->get(route('admin.webhooks.index'))
            ->assertInertia(fn ($page) => $page
                ->has('endpoints', 1)
                ->where('endpoints.0.name', 'Discord Relay')
                ->missing('endpoints.0.secret')
            );
    }

    public function test_update_changes_name_url_events_and_active_state(): void
    {
        $endpoint = WebhookEndpoint::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin)->put(route('admin.webhooks.update', $endpoint), [
            'name' => 'Renamed',
            'url' => 'https://example.com/new-hook',
            'events' => [Hooks::USER_BANNED],
            'is_active' => false,
        ])->assertRedirect();

        $endpoint->refresh();
        $this->assertSame('Renamed', $endpoint->name);
        $this->assertSame('https://example.com/new-hook', $endpoint->url);
        $this->assertSame([Hooks::USER_BANNED], $endpoint->events);
        $this->assertFalse($endpoint->is_active);
    }

    public function test_regenerate_secret_issues_a_new_secret_and_flashes_it_once(): void
    {
        $endpoint = WebhookEndpoint::factory()->create();
        $oldSecret = $endpoint->secret;

        $response = $this->actingAs($this->admin)
            ->post(route('admin.webhooks.regenerate-secret', $endpoint));

        $response->assertRedirect();
        $response->assertSessionHas('webhook_secret');

        $endpoint->refresh();
        $this->assertNotSame($oldSecret, $endpoint->secret);
        $this->assertSame($endpoint->secret, $response->getSession()->get('webhook_secret'));
    }

    public function test_destroy_deletes_the_endpoint(): void
    {
        $endpoint = WebhookEndpoint::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.webhooks.destroy', $endpoint))
            ->assertRedirect();

        $this->assertModelMissing($endpoint);
    }

    public function test_send_test_delivers_immediately_and_logs_it(): void
    {
        Http::fake(['example.com/*' => Http::response(['ok' => true], 200)]);

        $endpoint = WebhookEndpoint::factory()->create(['url' => 'https://example.com/hook']);

        $this->actingAs($this->admin)
            ->post(route('admin.webhooks.test', $endpoint))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, $endpoint->deliveries()->count());
        $this->assertSame('webhook.test', $endpoint->deliveries()->first()->event);
    }

    public function test_send_test_flashes_an_error_when_delivery_fails(): void
    {
        Http::fake(['example.com/*' => Http::response('nope', 500)]);

        $endpoint = WebhookEndpoint::factory()->create(['url' => 'https://example.com/hook']);

        $this->actingAs($this->admin)
            ->post(route('admin.webhooks.test', $endpoint))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_index_includes_recent_delivery_history(): void
    {
        $endpoint = WebhookEndpoint::factory()->create();
        $endpoint->deliveries()->create(['event' => Hooks::USER_REGISTERED, 'success' => true, 'response_code' => 200]);
        $endpoint->deliveries()->create(['event' => Hooks::USER_BANNED, 'success' => false, 'response_code' => 500, 'error' => 'nope']);

        $this->actingAs($this->admin)
            ->get(route('admin.webhooks.index'))
            ->assertInertia(fn ($page) => $page
                ->has('endpoints.0.deliveries', 2)
                ->where('endpoints.0.deliveries.0.event', Hooks::USER_BANNED)
                ->where('endpoints.0.deliveries.0.success', false)
                ->where('endpoints.0.deliveries.1.success', true)
            );
    }

    public function test_a_failed_delivery_with_a_stored_payload_is_marked_retryable(): void
    {
        $endpoint = WebhookEndpoint::factory()->create();
        $endpoint->deliveries()->create(['event' => Hooks::USER_BANNED, 'payload' => ['id' => 1], 'success' => false, 'response_code' => 500]);
        $endpoint->deliveries()->create(['event' => Hooks::USER_BANNED, 'payload' => ['id' => 2], 'success' => true, 'response_code' => 200]);

        $this->actingAs($this->admin)
            ->get(route('admin.webhooks.index'))
            ->assertInertia(fn ($page) => $page
                ->where('endpoints.0.deliveries.0.retryable', false)
                ->where('endpoints.0.deliveries.1.retryable', true)
            );
    }

    public function test_retry_redelivers_the_original_payload(): void
    {
        Http::fake(['example.com/*' => Http::response(['ok' => true], 200)]);

        $endpoint = WebhookEndpoint::factory()->create(['url' => 'https://example.com/hook']);
        $delivery = $endpoint->deliveries()->create([
            'event' => Hooks::USER_BANNED, 'payload' => ['id' => 42], 'success' => false, 'response_code' => 500,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.webhooks.deliveries.retry', [$endpoint, $delivery]))
            ->assertRedirect()
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->url() === 'https://example.com/hook'
            && $request->data()['data']['id'] === 42);
    }

    public function test_retry_refuses_a_delivery_that_already_succeeded(): void
    {
        $endpoint = WebhookEndpoint::factory()->create();
        $delivery = $endpoint->deliveries()->create([
            'event' => Hooks::USER_BANNED, 'payload' => ['id' => 1], 'success' => true, 'response_code' => 200,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.webhooks.deliveries.retry', [$endpoint, $delivery]))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_retry_refuses_a_delivery_belonging_to_another_endpoint(): void
    {
        $endpoint = WebhookEndpoint::factory()->create();
        $other = WebhookEndpoint::factory()->create();
        $delivery = $other->deliveries()->create([
            'event' => Hooks::USER_BANNED, 'payload' => ['id' => 1], 'success' => false, 'response_code' => 500,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.webhooks.deliveries.retry', [$endpoint, $delivery]))
            ->assertNotFound();
    }
}
