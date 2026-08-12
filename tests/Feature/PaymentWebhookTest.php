<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Server;
use App\Models\User;
use App\Services\Extensions\Registries\PaymentEventRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['payments.gateways.stripe.webhook_secret' => self::SECRET]);
    }

    private function pendingPayment(): Payment
    {
        $server = Server::factory()->create();

        return Payment::create([
            'payable_type' => $server->getMorphClass(),
            'payable_id' => $server->id,
            'user_id' => User::factory()->create()->id,
            'gateway' => 'stripe',
            'external_id' => 'pi_test_123',
            'amount' => 999,
            'currency' => 'usd',
            'status' => Payment::STATUS_PENDING,
        ]);
    }

    /** @param  array<string, mixed>  $payload */
    private function postWebhook(array $payload, ?string $secret = self::SECRET): TestResponse
    {
        $body = json_encode($payload);
        $timestamp = time();
        $header = $secret === null
            ? 'invalid'
            : "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

        return $this->postJson('/api/payments/webhook/stripe', $payload, ['Stripe-Signature' => $header]);
    }

    private function checkoutCompletedPayload(string $paymentIntent = 'pi_test_123'): array
    {
        return [
            'id' => 'evt_1',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_1',
                    'object' => 'checkout.session',
                    'mode' => 'payment',
                    'payment_intent' => $paymentIntent,
                ],
            ],
        ];
    }

    public function test_valid_webhook_marks_payment_paid_and_dispatches_handler(): void
    {
        $payment = $this->pendingPayment();

        $seen = [];
        app(PaymentEventRegistry::class)->on('paid', function (Payment $p) use (&$seen) {
            $seen[] = $p->id;
        });

        $this->postWebhook($this->checkoutCompletedPayload())->assertOk();

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame([$payment->id], $seen);
    }

    public function test_invalid_signature_is_rejected_and_payment_is_untouched(): void
    {
        $payment = $this->pendingPayment();

        $this->postWebhook($this->checkoutCompletedPayload(), secret: 'wrong_secret')->assertStatus(400);

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_duplicate_delivery_does_not_refire_handlers(): void
    {
        $payment = $this->pendingPayment();

        $calls = 0;
        app(PaymentEventRegistry::class)->on('paid', function () use (&$calls) {
            $calls++;
        });

        $payload = $this->checkoutCompletedPayload();
        $this->postWebhook($payload)->assertOk();
        $this->postWebhook($payload)->assertOk();

        $this->assertSame(1, $calls);
        $this->assertSame(1, Payment::where('gateway', 'stripe')->where('external_id', 'pi_test_123')->count());
    }
}
