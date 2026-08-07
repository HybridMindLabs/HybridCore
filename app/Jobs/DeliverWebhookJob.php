<?php

namespace App\Jobs;

use App\Models\WebhookEndpoint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Delivers one webhook payload to one endpoint. The body is signed with
 * HMAC-SHA256 over the exact bytes sent, so the receiver can verify it
 * without trusting the transport — the same scheme GitHub and Stripe use.
 */
class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public int $timeout = 15;

    /** @param  array<string, mixed>  $payload */
    public function __construct(
        public readonly int $webhookEndpointId,
        public readonly string $event,
        public readonly array $payload,
    ) {}

    public function handle(): void
    {
        $endpoint = WebhookEndpoint::find($this->webhookEndpointId);

        if (! $endpoint || ! $endpoint->is_active) {
            return;
        }

        $body = json_encode([
            'event' => $this->event,
            'data' => $this->payload,
            'timestamp' => now()->toIso8601String(),
        ]);

        $signature = hash_hmac('sha256', $body, $endpoint->secret);

        try {
            $response = Http::withBody($body, 'application/json')
                ->withHeaders([
                    'X-HybridCore-Event' => $this->event,
                    'X-HybridCore-Signature' => $signature,
                ])
                ->timeout(10)
                ->post($endpoint->url);
        } catch (ConnectionException $e) {
            $this->recordDelivery($endpoint, false, null, $e->getMessage());

            throw $e;
        }

        $this->recordDelivery(
            $endpoint,
            $response->successful(),
            $response->status(),
            $response->successful() ? null : str($response->body())->limit(500)->value(),
        );

        $response->throw();
    }

    private function recordDelivery(WebhookEndpoint $endpoint, bool $success, ?int $responseCode, ?string $error): void
    {
        $endpoint->update([
            'last_triggered_at' => now(),
            'last_status' => $success ? 'success' : 'failed',
            'last_response_code' => $responseCode,
        ]);

        $endpoint->deliveries()->create([
            'event' => $this->event,
            'payload' => $this->payload,
            'success' => $success,
            'response_code' => $responseCode,
            'error' => $error,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        WebhookEndpoint::whereKey($this->webhookEndpointId)->update(['last_status' => 'failed']);
    }
}
