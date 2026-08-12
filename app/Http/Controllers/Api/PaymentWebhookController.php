<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentEvent;
use App\Models\Payment;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inbound webhook delivery from a payment gateway.
 *
 *   POST /api/payments/webhook/{gateway}
 *
 * Signature verification happens inside the resolved PaymentGateway driver,
 * not here — the controller stays gateway-agnostic.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(private readonly PaymentManager $payments) {}

    public function __invoke(Request $request, string $gateway): JsonResponse
    {
        $event = $this->payments->driver($gateway)->parseWebhook($request);

        if ($event === null) {
            return response()->json(['error' => 'Invalid webhook'], 400);
        }

        $payment = Payment::where('gateway', $gateway)->where('external_id', $event->externalId)->first();

        if ($payment === null) {
            return response()->json(['received' => true]);
        }

        $statusChanged = $payment->status !== $event->status;

        $payment->update([
            'status' => $event->status,
            'metadata' => $event->rawPayload,
        ]);

        if ($statusChanged) {
            ProcessPaymentEvent::dispatch($payment->id);
        }

        return response()->json(['received' => true]);
    }
}
