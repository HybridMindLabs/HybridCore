<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\Extensions\Registries\PaymentEventRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatches a payment's outcome to the extension handlers registered on
 * PaymentEventRegistry. Runs on the queue so the webhook HTTP response
 * returns fast — Stripe (and most gateways) retry/flag slow webhooks.
 */
class ProcessPaymentEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $paymentId) {}

    public function handle(PaymentEventRegistry $registry): void
    {
        $payment = Payment::find($this->paymentId);
        if ($payment === null) {
            return;
        }

        $registry->dispatch($payment);
    }
}
