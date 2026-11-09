<?php

namespace App\Services\Payments\Drivers;

use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Payments\Contracts\PaymentGateway;
use App\Services\Payments\Contracts\PaymentWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CryptoGateway implements PaymentGateway
{
    /** @param array{provider?: string, api_key?: string|null, webhook_secret?: string|null} $config */
    public function __construct(private readonly array $config) {}

    public function createCheckout(Payment $payment, string $successUrl, string $cancelUrl): string
    {
        $apiKey = $this->config['api_key'] ?? null;
        $amount = number_format($payment->amount / 100, 2, '.', '');

        // If Coinbase Commerce / Cryptomus API is configured
        if ($apiKey) {
            $res = Http::withHeaders([
                'X-CC-Api-Key' => $apiKey,
                'X-CC-Version' => '2018-03-22',
            ])->post('https://api.commerce.coinbase.com/charges', [
                'name' => class_basename($payment->payable_type).' #'.$payment->payable_id,
                'description' => 'Payment for order #' . $payment->id,
                'pricing_type' => 'fixed_price',
                'local_price' => [
                    'amount' => $amount,
                    'currency' => strtoupper($payment->currency),
                ],
                'metadata' => [
                    'payment_id' => $payment->id,
                ],
                'redirect_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ]);

            if ($res->successful()) {
                $charge = $res->json('data', []);
                $code = $charge['code'] ?? null;
                $hostedUrl = $charge['hosted_url'] ?? null;

                if ($code && $hostedUrl) {
                    $payment->forceFill(['external_id' => $code])->save();
                    return $hostedUrl;
                }
            }
        }

        // Demo / Fallback URL if crypto gateway keys not yet set
        $mockId = 'CRYPTO_' . Str::upper(Str::random(16));
        $payment->forceFill(['external_id' => $mockId])->save();

        return $successUrl . (str_contains($successUrl, '?') ? '&' : '?') . 'gateway=crypto&charge=' . $mockId;
    }

    public function createSubscriptionCheckout(Subscription $subscription, string $successUrl, string $cancelUrl): string
    {
        throw new \RuntimeException('Cryptocurrency does not support automated recurring billing.');
    }

    public function parseWebhook(Request $request): ?PaymentWebhookEvent
    {
        $payload = $request->all();
        $event = $payload['event'] ?? [];
        $type = $event['type'] ?? '';

        if ($type === 'charge:confirmed' || $type === 'charge:resolved') {
            $data = $event['data'] ?? [];
            $code = $data['code'] ?? null;

            if ($code) {
                $amount = (int) (round(((float) ($data['pricing']['local']['amount'] ?? 0)) * 100));
                $currency = $data['pricing']['local']['currency'] ?? 'EUR';

                return new PaymentWebhookEvent(
                    type: 'payment',
                    externalId: (string) $code,
                    status: Payment::STATUS_PAID,
                    amount: $amount,
                    currency: $currency,
                    rawPayload: $payload,
                );
            }
        }

        return null;
    }

    public function refund(Payment $payment): void
    {
        // Crypto refunds are manual
    }

    public function cancelSubscription(Subscription $subscription): void
    {
    }
}
