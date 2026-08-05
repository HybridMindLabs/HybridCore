<?php

namespace App\Services;

use App\Jobs\DeliverWebhookJob;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Schema;

/**
 * Fans a core Hooks:: event out to every active endpoint subscribed to it.
 * One queued delivery per endpoint — a slow or dead receiver never blocks
 * the request that fired the hook, and one endpoint's failure never affects
 * another's delivery.
 */
class WebhookDispatcher
{
    /** @param  array<string, mixed>  $payload */
    public function dispatch(string $event, array $payload): void
    {
        if (! Schema::hasTable('webhook_endpoints')) {
            return;
        }

        WebhookEndpoint::query()
            ->where('is_active', true)
            ->whereJsonContains('events', $event)
            ->get(['id'])
            ->each(fn (WebhookEndpoint $endpoint) => DeliverWebhookJob::dispatch($endpoint->id, $event, $payload));
    }
}
