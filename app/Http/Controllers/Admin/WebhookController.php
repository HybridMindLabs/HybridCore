<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\DeliverWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\ActivityLogService;
use App\Services\Extensions\Registries\WebhookEventRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class WebhookController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activityLog,
        private readonly WebhookEventRegistry $extensionEvents,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Webhooks/Index', [
            'endpoints' => WebhookEndpoint::with(['deliveries' => fn ($q) => $q->orderByDesc('created_at')->orderByDesc('id')->limit(10)])
                ->orderByDesc('created_at')->get()
                ->map(fn (WebhookEndpoint $endpoint) => $this->toArray($endpoint))
                ->values()->all(),
            'available_events' => $this->availableEvents(),
        ]);
    }

    /** @return array<string, array{label: string, group: string}> Every core Hooks:: event plus every key an enabled extension registered — both go through WebhookEventRegistry now, core registers at boot in ExtensionServiceProvider. */
    private function availableEvents(): array
    {
        return $this->extensionEvents->all();
    }

    /**
     * @return array{id: int, name: string, url: string, events: array<int, string>, is_active: bool, last_triggered_at: string|null, last_status: string|null, last_response_code: int|null, created_at: string, deliveries: array<int, array{id: int, event: string, success: bool, response_code: int|null, error: string|null, created_at: string|null, retryable: bool}>}
     */
    private function toArray(WebhookEndpoint $endpoint): array
    {
        return [
            'id' => $endpoint->id,
            'name' => $endpoint->name,
            'url' => $endpoint->url,
            'events' => $endpoint->events,
            'is_active' => $endpoint->is_active,
            'last_triggered_at' => $endpoint->last_triggered_at?->toDateTimeString(),
            'last_status' => $endpoint->last_status,
            'last_response_code' => $endpoint->last_response_code,
            'created_at' => $endpoint->created_at->toDateTimeString(),
            'deliveries' => $endpoint->deliveries->map(fn (WebhookDelivery $d) => [
                'id' => $d->id,
                'event' => $d->event,
                'success' => $d->success,
                'response_code' => $d->response_code,
                'error' => $d->error,
                'created_at' => $d->created_at?->toDateTimeString(),
                'retryable' => ! $d->success && $d->payload !== null,
            ])->values()->all(),
        ];
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'in:'.implode(',', array_keys($this->availableEvents()))],
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $secret = Str::random(40);

        $endpoint = WebhookEndpoint::create([
            'name' => $data['name'],
            'url' => $data['url'],
            'events' => $data['events'],
            'secret' => $secret,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        $this->activityLog->log('webhook.created', "Created webhook \"{$endpoint->name}\"", $endpoint);

        return back()->with('success', "\"{$endpoint->name}\" created.")->with('webhook_secret', $secret);
    }

    public function update(Request $request, WebhookEndpoint $webhook): RedirectResponse
    {
        $data = $request->validate($this->rules() + [
            'is_active' => ['required', 'boolean'],
        ]);

        $webhook->update($data);

        $this->activityLog->log('webhook.updated', "Updated webhook \"{$webhook->name}\"", $webhook);

        return back()->with('success', 'Webhook updated.');
    }

    /** Issues a new secret — the old one stops verifying immediately. Shown once, like on creation. */
    public function regenerateSecret(WebhookEndpoint $webhook): RedirectResponse
    {
        $secret = Str::random(40);
        $webhook->update(['secret' => $secret]);

        $this->activityLog->log('webhook.secret_regenerated', "Regenerated the secret for webhook \"{$webhook->name}\"", $webhook);

        return back()->with('success', 'Secret regenerated.')->with('webhook_secret', $secret);
    }

    /** Delivers a synthetic payload right now so an admin can confirm an endpoint actually works before subscribing it to a real event. */
    public function sendTest(WebhookEndpoint $webhook): RedirectResponse
    {
        try {
            (new DeliverWebhookJob($webhook->id, 'webhook.test', ['message' => 'This is a test delivery from HybridCore.']))->handle();

            return back()->with('success', 'Test delivery sent — check the log below.');
        } catch (Throwable $e) {
            return back()->with('error', 'Test delivery failed: '.$e->getMessage());
        }
    }

    /** Re-fires a failed delivery's exact original payload — for a receiver that was down, not a broken one that needs a code fix first. */
    public function retryDelivery(WebhookEndpoint $webhook, WebhookDelivery $delivery): RedirectResponse
    {
        abort_unless($delivery->webhook_endpoint_id === $webhook->id, 404);

        if ($delivery->success || $delivery->payload === null) {
            return back()->with('error', 'This delivery cannot be retried.');
        }

        DeliverWebhookJob::dispatch($webhook->id, $delivery->event, $delivery->payload);

        $this->activityLog->log('webhook.delivery_retried', "Retried a delivery for webhook \"{$webhook->name}\"", $webhook);

        return back()->with('success', 'Retry queued.');
    }

    public function destroy(WebhookEndpoint $webhook): RedirectResponse
    {
        $name = $webhook->name;
        $webhook->delete();

        $this->activityLog->log('webhook.deleted', "Deleted webhook \"{$name}\"");

        return back()->with('success', "\"{$name}\" deleted.");
    }
}
