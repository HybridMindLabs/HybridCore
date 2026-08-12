<?php

namespace App\Services\Payments\Drivers;

use App\Models\Payment;
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
 * external_id is the Stripe PaymentIntent id, not the Checkout Session id —
 * it's the one identifier stable across the checkout, failure, and refund
 * webhooks, so a single `payments.external_id` lookup works for all three.
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

        $status = match ($event->type) {
            'checkout.session.completed', 'payment_intent.succeeded' => 'paid',
            'payment_intent.payment_failed' => 'failed',
            'charge.refunded' => 'refunded',
            default => null,
        };

        if ($status === null) {
            return null;
        }

        $object = $event->data->object;
        $externalId = $object->payment_intent ?? $object->id;

        return new PaymentWebhookEvent($externalId, $status, $event->toArray());
    }

    public function refund(Payment $payment): void
    {
        $this->client()->refunds->create(['payment_intent' => $payment->external_id]);
    }
}
