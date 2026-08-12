<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Services\Extensions\Registries\SubscriptionEventRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatches a subscription lifecycle event to the extension handlers
 * registered on SubscriptionEventRegistry. Runs on the queue so the webhook
 * HTTP response returns fast.
 */
class ProcessSubscriptionEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @param  string  $event  'created' | 'renewed' | 'past_due' | 'canceled' */
    public function __construct(public int $subscriptionId, public string $event) {}

    public function handle(SubscriptionEventRegistry $registry): void
    {
        $subscription = Subscription::find($this->subscriptionId);
        if ($subscription === null) {
            return;
        }

        $registry->dispatch($subscription, $this->event);
    }
}
