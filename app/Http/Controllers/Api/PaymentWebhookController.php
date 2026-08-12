<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentEvent;
use App\Jobs\ProcessSubscriptionEvent;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Payments\Contracts\PaymentWebhookEvent;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inbound webhook delivery from a payment gateway.
 *
 *   POST /api/payments/webhook/{gateway}
 *
 * Signature verification happens inside the resolved PaymentGateway driver,
 * not here — the controller stays gateway-agnostic. It only branches on
 * PaymentWebhookEvent::$type, never on a gateway-specific event name.
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

        match ($event->type) {
            'payment' => $this->handlePayment($event, $gateway),
            'subscription_created' => $this->handleSubscriptionCreated($event, $gateway),
            'subscription_renewed' => $this->handleSubscriptionRenewed($event, $gateway),
            'subscription_past_due' => $this->handleSubscriptionPastDue($event, $gateway),
            'subscription_updated' => $this->handleSubscriptionUpdated($event, $gateway),
            'subscription_canceled' => $this->handleSubscriptionCanceled($event, $gateway),
            default => null,
        };

        return response()->json(['received' => true]);
    }

    private function handlePayment(PaymentWebhookEvent $event, string $gateway): void
    {
        $payment = Payment::where('gateway', $gateway)->where('external_id', $event->externalId)->first();

        if ($payment === null) {
            return;
        }

        $statusChanged = $payment->status !== $event->status;

        $payment->update(['status' => $event->status, 'metadata' => $event->rawPayload]);

        if ($statusChanged) {
            ProcessPaymentEvent::dispatch($payment->id);
        }
    }

    /** Fires once, when checkout actually completes — this is where external_id first exists. */
    private function handleSubscriptionCreated(PaymentWebhookEvent $event, string $gateway): void
    {
        $subscription = Subscription::find($event->referenceId);

        if ($subscription === null || $subscription->external_id === $event->externalId) {
            return; // not found, or a duplicate delivery already processed
        }

        $subscription->update(['gateway' => $gateway, 'external_id' => $event->externalId, 'status' => Subscription::STATUS_ACTIVE]);

        ProcessSubscriptionEvent::dispatch($subscription->id, 'created');
    }

    /**
     * Fires for every successful billing cycle, including the first — creates
     * a normal Payment row per renewal, reusing the entire existing
     * PaymentEventRegistry pipeline instead of a separate mechanism.
     */
    private function handleSubscriptionRenewed(PaymentWebhookEvent $event, string $gateway): void
    {
        $subscription = Subscription::where('gateway', $gateway)->where('external_id', $event->externalId)->first();

        if ($subscription === null) {
            return;
        }

        $payment = Payment::firstOrCreate(
            ['gateway' => $gateway, 'external_id' => $event->referenceId],
            [
                'payable_type' => $subscription->payable_type,
                'payable_id' => $subscription->payable_id,
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'amount' => $event->amount,
                'currency' => $event->currency,
                'status' => Payment::STATUS_PAID,
                'metadata' => $event->rawPayload,
            ],
        );

        if ($payment->wasRecentlyCreated) {
            ProcessPaymentEvent::dispatch($payment->id);
            ProcessSubscriptionEvent::dispatch($subscription->id, 'renewed');
        }
    }

    private function handleSubscriptionPastDue(PaymentWebhookEvent $event, string $gateway): void
    {
        $subscription = Subscription::where('gateway', $gateway)->where('external_id', $event->externalId)->first();

        if ($subscription === null || $subscription->status === Subscription::STATUS_PAST_DUE) {
            return;
        }

        $subscription->update(['status' => Subscription::STATUS_PAST_DUE]);
        ProcessSubscriptionEvent::dispatch($subscription->id, 'past_due');
    }

    /** Metadata sync only (current_period_end / cancel_at_period_end) — never touches status. */
    private function handleSubscriptionUpdated(PaymentWebhookEvent $event, string $gateway): void
    {
        $subscription = Subscription::where('gateway', $gateway)->where('external_id', $event->externalId)->first();

        if ($subscription === null) {
            return;
        }

        $subscription->update([
            'current_period_end' => $event->currentPeriodEnd,
            'cancel_at_period_end' => $event->cancelAtPeriodEnd ?? $subscription->cancel_at_period_end,
        ]);
    }

    private function handleSubscriptionCanceled(PaymentWebhookEvent $event, string $gateway): void
    {
        $subscription = Subscription::where('gateway', $gateway)->where('external_id', $event->externalId)->first();

        if ($subscription === null || $subscription->status === Subscription::STATUS_CANCELED) {
            return;
        }

        $subscription->update(['status' => Subscription::STATUS_CANCELED]);
        ProcessSubscriptionEvent::dispatch($subscription->id, 'canceled');
    }
}
