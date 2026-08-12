<?php

namespace App\Services\Payments\Drivers;

use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Payments\Contracts\PaymentGateway;
use App\Services\Payments\Contracts\PaymentWebhookEvent;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Only gateway shipped today. Uses Stripe's hosted Checkout so HybridCore
 * never touches card data (keeps the app out of PCI SAQ-D scope).
 *
 * A one-time payment's external_id is the Stripe PaymentIntent id, not the
 * Checkout Session id — it's the one identifier stable across the checkout,
 * failure, and refund webhooks, so a single `payments.external_id` lookup
 * works for all three. A subscription's external_id is the Stripe
 * Subscription id, which Stripe only assigns once checkout completes — see
 * createSubscriptionCheckout().
 */
class StripeGateway implements PaymentGateway
{
    private ?StripeClient $client = null;

    /** @param  array{secret_key?: string|null, webhook_secret?: string|null}  $config */
    public function __construct(private readonly array $config) {}

    /** Built lazily — parseWebhook() only needs the webhook secret, never an API key. */
    private function client(): StripeClient
    {
        return $this->client ??= new StripeClient($this->config['secret_key'] ?? '');
    }

    public function createCheckout(Payment $payment, string $successUrl, string $cancelUrl): string
    {
        $session = $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $payment->id,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($payment->currency),
                    'unit_amount' => $payment->amount,
                    'product_data' => [
                        'name' => class_basename($payment->payable_type).' #'.$payment->payable_id,
                    ],
                ],
            ]],
        ]);

        $payment->forceFill(['external_id' => $session->payment_intent])->save();

        return $session->url;
    }

    /**
     * Unlike createCheckout(), the subscription id is NOT populated on the
     * session synchronously — Stripe only creates it once the buyer actually
     * completes checkout. external_id stays null here; the
     * checkout.session.completed (mode=subscription) webhook is what sets
     * it, matched back to this row via client_reference_id.
     */
    public function createSubscriptionCheckout(Subscription $subscription, string $successUrl, string $cancelUrl): string
    {
        $session = $this->client()->checkout->sessions->create([
            'mode' => 'subscription',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $subscription->id,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($subscription->currency),
                    'unit_amount' => $subscription->amount,
                    'recurring' => ['interval' => $subscription->interval],
                    'product_data' => [
                        'name' => class_basename($subscription->payable_type).' #'.$subscription->payable_id,
                    ],
                ],
            ]],
        ]);

        return $session->url;
    }

    public function parseWebhook(Request $request): ?PaymentWebhookEvent
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
                $this->config['webhook_secret'] ?? '',
            );
        } catch (UnexpectedValueException|SignatureVerificationException) {
            return null;
        }

        $object = $event->data->object;
        $raw = $event->toArray();

        // Array access, not ->property — StripeObject's properties are dynamic
        // (implements ArrayAccess), so PHPStan can't verify them statically.
        return match ($event->type) {
            'checkout.session.completed' => $object['mode'] === 'subscription'
                ? new PaymentWebhookEvent(
                    type: 'subscription_created',
                    externalId: $object['subscription'],
                    referenceId: $object['client_reference_id'],
                    rawPayload: $raw,
                )
                : new PaymentWebhookEvent(
                    type: 'payment',
                    externalId: $object['payment_intent'],
                    status: 'paid',
                    rawPayload: $raw,
                ),
            'payment_intent.succeeded' => new PaymentWebhookEvent(
                type: 'payment', externalId: $object->id, status: 'paid', rawPayload: $raw,
            ),
            'payment_intent.payment_failed' => new PaymentWebhookEvent(
                type: 'payment', externalId: $object->id, status: 'failed', rawPayload: $raw,
            ),
            'charge.refunded' => new PaymentWebhookEvent(
                type: 'payment', externalId: $object['payment_intent'], status: 'refunded', rawPayload: $raw,
            ),
            'invoice.payment_succeeded' => new PaymentWebhookEvent(
                type: 'subscription_renewed',
                externalId: $object['subscription'], // looks up the Subscription
                referenceId: $object->id,             // the invoice id — becomes the new Payment's external_id
                status: 'paid',
                amount: $object['amount_paid'],
                currency: $object['currency'],
                rawPayload: $raw,
            ),
            'invoice.payment_failed' => new PaymentWebhookEvent(
                type: 'subscription_past_due', externalId: $object['subscription'], rawPayload: $raw,
            ),
            // Stripe fires .created right after a subscription checkout completes, in
            // addition to checkout.session.completed — that's where current_period_end
            // actually lives, so it's handled identically to .updated (sync fields only,
            // subscription_created above is what flips our own status to active).
            'customer.subscription.created', 'customer.subscription.updated' => new PaymentWebhookEvent(
                type: 'subscription_updated',
                externalId: $object->id,
                currentPeriodEnd: date(DATE_ATOM, $object['current_period_end']),
                cancelAtPeriodEnd: $object['cancel_at_period_end'],
                rawPayload: $raw,
            ),
            'customer.subscription.deleted' => new PaymentWebhookEvent(
                type: 'subscription_canceled', externalId: $object->id, rawPayload: $raw,
            ),
            default => null,
        };
    }

    public function refund(Payment $payment): void
    {
        $this->client()->refunds->create(['payment_intent' => $payment->external_id]);
    }

    public function cancelSubscription(Subscription $subscription): void
    {
        $this->client()->subscriptions->update($subscription->external_id, ['cancel_at_period_end' => true]);
    }
}
