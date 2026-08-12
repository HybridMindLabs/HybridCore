<?php

namespace App\Services\Extensions\Registries;

use App\Models\Payment;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Lets extensions react to a payment's outcome. Handlers are keyed by
 * status; the special status "*" receives every payment.
 *
 *   $registry->payments()->on('paid', function (Payment $payment) {
 *       // deliver the reward for $payment->payable
 *   });
 *
 * Core dispatches this (from a queued job) whenever a payment's status
 * changes. A handler that throws is logged and skipped — one bad handler
 * must not drop the rest.
 */
class PaymentEventRegistry
{
    /** @var array<string, array<int, Closure>> */
    private array $listeners = [];

    /**
     * @param  string  $status  'paid' | 'failed' | 'refunded' | '*'
     * @param  Closure(Payment): void  $handler
     */
    public function on(string $status, Closure $handler): void
    {
        $this->listeners[$status][] = $handler;
    }

    /** Dispatch a payment to every matching handler. */
    public function dispatch(Payment $payment): void
    {
        $handlers = array_merge(
            $this->listeners[$payment->status] ?? [],
            $this->listeners['*'] ?? [],
        );

        foreach ($handlers as $handler) {
            try {
                $handler($payment);
            } catch (\Throwable $e) {
                Log::warning('Payment event handler failed', [
                    'status' => $payment->status,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    public function hasListeners(string $status): bool
    {
        return ! empty($this->listeners[$status]) || ! empty($this->listeners['*']);
    }

    /** @return array<int, string> */
    public function statuses(): array
    {
        return array_keys($this->listeners);
    }
}
