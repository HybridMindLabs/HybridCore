<?php

namespace App\Services\Extensions\Registries;

use App\Models\Subscription;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Lets extensions react to subscription lifecycle events that aren't payment
 * events — most importantly "canceled", which an extension needs to react to
 * independently of any single payment (e.g. stop re-granting access).
 * Renewals themselves still flow through PaymentEventRegistry via the normal
 * Payment row each renewal creates; this registry is for state transitions a
 * Payment can't express.
 *
 *   $registry->subscriptions()->on('canceled', function (Subscription $subscription) {
 *       // revoke whatever the subscription granted
 *   });
 *
 * Unlike PaymentEventRegistry, the event isn't read off the model's current
 * status — "created" and "renewed" are transitions, not persisted states
 * (both land on status=active). The caller passes the event explicitly.
 */
class SubscriptionEventRegistry
{
    /** @var array<string, array<int, Closure>> */
    private array $listeners = [];

    /**
     * @param  string  $event  'created' | 'renewed' | 'past_due' | 'canceled' | '*'
     * @param  Closure(Subscription): void  $handler
     */
    public function on(string $event, Closure $handler): void
    {
        $this->listeners[$event][] = $handler;
    }

    /** Dispatch a subscription lifecycle event to every matching handler. */
    public function dispatch(Subscription $subscription, string $event): void
    {
        $handlers = array_merge(
            $this->listeners[$event] ?? [],
            $this->listeners['*'] ?? [],
        );

        foreach ($handlers as $handler) {
            try {
                $handler($subscription);
            } catch (\Throwable $e) {
                Log::warning('Subscription event handler failed', [
                    'event' => $event,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    public function hasListeners(string $event): bool
    {
        return ! empty($this->listeners[$event]) || ! empty($this->listeners['*']);
    }

    /** @return array<int, string> */
    public function events(): array
    {
        return array_keys($this->listeners);
    }
}
