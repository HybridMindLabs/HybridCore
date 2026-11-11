<?php

namespace App\Services\Payments\Drivers;

use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Payments\Contracts\PaymentGateway;
use App\Services\Payments\Contracts\PaymentWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RevolutGateway implements PaymentGateway
{
    /** @param array{secret_key?: string|null, webhook_secret?: string|null, mode?: string} $config */
    public function __construct(private readonly array $config) {}

    private function baseUrl(): string
    {
        return ($this->config['mode'] ?? 'sandbox') === 'live'
            ? 'https://merchant.revolut.com'
            : 'https://sandbox-merchant.revolut.com';
    }

    public function createCheckout(Payment $payment, string $successUrl, string $cancelUrl): string
    {
        $secretKey = $this->config['secret_key'] ?? null;

        if ($secretKey) {
            $res = Http::withToken($secretKey)
                ->post("{$this->baseUrl()}/api/orders", [
                    'amount' => $payment->amount,
                    'currency' => strtoupper($payment->currency),
                    'description' => class_basename($payment->payable_type).' #'.$payment->payable_id,
                    'redirect_url' => $successUrl,
                ]);

            if ($res->successful()) {
                $orderId = $res->json('id');
                $checkoutUrl = $res->json('checkout_url');

                if ($orderId && $checkoutUrl) {
                    $payment->forceFill(['external_id' => $orderId])->save();

                    return $checkoutUrl;
                }
            }
        }

        // Demo / Fallback URL
        $mockId = 'REV_'.Str::upper(Str::random(16));
        $payment->forceFill(['external_id' => $mockId])->save();

        return $successUrl.(str_contains($successUrl, '?') ? '&' : '?').'gateway=revolut&order_id='.$mockId;
    }

    public function createSubscriptionCheckout(Subscription $subscription, string $successUrl, string $cancelUrl): string
    {
        $mockId = 'REV_SUB_'.Str::upper(Str::random(16));

        return $successUrl.(str_contains($successUrl, '?') ? '&' : '?').'subscription_id='.$mockId;
    }

    public function parseWebhook(Request $request): ?PaymentWebhookEvent
    {
        $payload = $request->all();
        $event = $payload['event'] ?? '';

        if ($event === 'ORDER_COMPLETED') {
            $order = $payload['order'] ?? $payload;
            $orderId = $order['id'] ?? null;

            if ($orderId) {
                return new PaymentWebhookEvent(
                    type: 'payment',
                    externalId: (string) $orderId,
                    status: Payment::STATUS_PAID,
                    amount: (int) ($order['order_amount']['value'] ?? 0),
                    currency: $order['order_amount']['currency'] ?? 'EUR',
                    rawPayload: $payload,
                );
            }
        }

        return null;
    }

    public function refund(Payment $payment): void
    {
        $secretKey = $this->config['secret_key'] ?? null;
        if ($secretKey && $payment->external_id) {
            Http::withToken($secretKey)
                ->post("{$this->baseUrl()}/api/orders/{$payment->external_id}/refund", [
                    'amount' => $payment->amount,
                    'description' => 'Refund for payment #'.$payment->id,
                ]);
        }
    }

    public function cancelSubscription(Subscription $subscription): void {}
}
