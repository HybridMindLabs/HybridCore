<?php

namespace App\Services\Payments\Drivers;

use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Payments\Contracts\PaymentGateway;
use App\Services\Payments\Contracts\PaymentWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PayPalGateway implements PaymentGateway
{
    /** @param array{client_id?: string|null, client_secret?: string|null, mode?: string, webhook_id?: string|null} $config */
    public function __construct(private readonly array $config) {}

    private function baseUrl(): string
    {
        return ($this->config['mode'] ?? 'sandbox') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function getAccessToken(): ?string
    {
        $clientId = $this->config['client_id'] ?? null;
        $clientSecret = $this->config['client_secret'] ?? null;

        if (empty($clientId) || empty($clientSecret)) {
            return null;
        }

        $res = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->post("{$this->baseUrl()}/v1/oauth2/token", ['grant_type' => 'client_credentials']);

        return $res->json('access_token');
    }

    public function createCheckout(Payment $payment, string $successUrl, string $cancelUrl): string
    {
        $token = $this->getAccessToken();

        // If credentials configured, create real PayPal order
        if ($token) {
            $amountFormatted = number_format($payment->amount / 100, 2, '.', '');
            $response = Http::withToken($token)
                ->post("{$this->baseUrl()}/v2/checkout/orders", [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [[
                        'reference_id' => (string) $payment->id,
                        'amount' => [
                            'currency_code' => strtoupper($payment->currency),
                            'value' => $amountFormatted,
                        ],
                        'description' => class_basename($payment->payable_type).' #'.$payment->payable_id,
                    ]],
                    'application_context' => [
                        'return_url' => $successUrl,
                        'cancel_url' => $cancelUrl,
                        'user_action' => 'PAY_NOW',
                    ],
                ]);

            if ($response->successful()) {
                $orderId = $response->json('id');
                $payment->forceFill(['external_id' => $orderId])->save();

                foreach ($response->json('links', []) as $link) {
                    if (($link['rel'] ?? '') === 'approve') {
                        return $link['href'];
                    }
                }
            }
        }

        // Demo / Fallback URL if credentials not yet configured
        $mockId = 'PAYPAL_' . Str::upper(Str::random(16));
        $payment->forceFill(['external_id' => $mockId])->save();

        return $successUrl . (str_contains($successUrl, '?') ? '&' : '?') . 'gateway=paypal&order_id=' . $mockId;
    }

    public function createSubscriptionCheckout(Subscription $subscription, string $successUrl, string $cancelUrl): string
    {
        $mockId = 'PAYPAL_SUB_' . Str::upper(Str::random(16));
        return $successUrl . (str_contains($successUrl, '?') ? '&' : '?') . 'subscription_id=' . $mockId;
    }

    public function parseWebhook(Request $request): ?PaymentWebhookEvent
    {
        $payload = $request->all();
        $eventType = $payload['event_type'] ?? '';

        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED' || $eventType === 'CHECKOUT.ORDER.APPROVED') {
            $resource = $payload['resource'] ?? [];
            $externalId = $resource['supplementary_data']['related_ids']['order_id'] ?? $resource['id'] ?? null;

            if (! $externalId) {
                return null;
            }

            $amountValue = (int) (round(((float) ($resource['amount']['value'] ?? 0)) * 100));
            $currency = $resource['amount']['currency_code'] ?? 'EUR';

            return new PaymentWebhookEvent(
                type: 'payment',
                externalId: (string) $externalId,
                status: Payment::STATUS_PAID,
                amount: $amountValue,
                currency: $currency,
                rawPayload: $payload,
            );
        }

        if ($eventType === 'PAYMENT.CAPTURE.REFUNDED') {
            $resource = $payload['resource'] ?? [];
            $externalId = $resource['supplementary_data']['related_ids']['order_id'] ?? $resource['id'] ?? null;

            if ($externalId) {
                return new PaymentWebhookEvent(
                    type: 'payment',
                    externalId: (string) $externalId,
                    status: Payment::STATUS_REFUNDED,
                    rawPayload: $payload,
                );
            }
        }

        return null;
    }

    public function refund(Payment $payment): void
    {
        $token = $this->getAccessToken();
        if ($token && $payment->external_id) {
            Http::withToken($token)->post("{$this->baseUrl()}/v2/payments/captures/{$payment->external_id}/refund");
        }
    }

    public function cancelSubscription(Subscription $subscription): void
    {
        // Cancel subscription
    }
}
