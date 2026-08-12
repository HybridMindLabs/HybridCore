<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Server;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Extensions\Registries\PaymentEventRegistry;
use App\Services\Extensions\Registries\SubscriptionEventRegistry;
use App\Services\Payments\Contracts\PaymentGateway;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\TestCase;

class SubscriptionWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['payments.gateways.stripe.webhook_secret' => self::SECRET]);
    }

    private function pendingSubscription(): Subscription
    {
        $server = Server::factory()->create();

        return Subscription::create([
            'payable_type' => $server->getMorphClass(),
            'payable_id' => $server->id,
            'user_id' => User::factory()->create()->id,
            'gateway' => 'stripe',
            'amount' => 999,
            'currency' => 'usd',
            'interval' => 'month',
            'status' => Subscription::STATUS_INCOMPLETE,
        ]);
    }

    /** @param  array<string, mixed>  $payload */
    private function postWebhook(array $payload): TestResponse
    {
        $body = json_encode($payload);
        $timestamp = time();
        $header = "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$body}", self::SECRET);

        return $this->postJson('/api/payments/webhook/stripe', $payload, ['Stripe-Signature' => $header]);
    }

    private function checkoutCompletedPayload(string $referenceId, string $subscriptionId = 'sub_test_1'): array
    {
        return [
            'id' => 'evt_created', 'object' => 'event', 'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_1', 'object' => 'checkout.session', 'mode' => 'subscription',
                'client_reference_id' => $referenceId, 'subscription' => $subscriptionId,
            ]],
        ];
    }

    private function invoicePaidPayload(string $subscriptionId, string $invoiceId, int $amount = 999): array
    {
        return [
            'id' => 'evt_renewed', 'object' => 'event', 'type' => 'invoice.payment_succeeded',
            'data' => ['object' => [
                'id' => $invoiceId, 'object' => 'invoice', 'subscription' => $subscriptionId,
                'amount_paid' => $amount, 'currency' => 'usd',
            ]],
        ];
    }

    public function test_checkout_completed_activates_the_subscription_and_sets_external_id(): void
    {
        $subscription = $this->pendingSubscription();

        $fired = [];
        app(SubscriptionEventRegistry::class)->on('created', function (Subscription $s) use (&$fired) {
            $fired[] = $s->id;
        });

        $this->postWebhook($this->checkoutCompletedPayload((string) $subscription->id))->assertOk();

        $subscription->refresh();
        $this->assertSame('sub_test_1', $subscription->external_id);
        $this->assertSame(Subscription::STATUS_ACTIVE, $subscription->status);
        $this->assertSame([$subscription->id], $fired);
    }

    public function test_invoice_paid_creates_a_linked_payment_and_fires_the_payment_pipeline(): void
    {
        $subscription = $this->pendingSubscription();
        $subscription->update(['external_id' => 'sub_test_1', 'status' => Subscription::STATUS_ACTIVE]);

        $paid = [];
        app(PaymentEventRegistry::class)->on('paid', function (Payment $p) use (&$paid) {
            $paid[] = $p->id;
        });
        $renewed = [];
        app(SubscriptionEventRegistry::class)->on('renewed', function (Subscription $s) use (&$renewed) {
            $renewed[] = $s->id;
        });

        $this->postWebhook($this->invoicePaidPayload('sub_test_1', 'in_test_1'))->assertOk();

        $payment = Payment::where('gateway', 'stripe')->where('external_id', 'in_test_1')->first();
        $this->assertNotNull($payment);
        $this->assertSame($subscription->id, $payment->subscription_id);
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame([$payment->id], $paid);
        $this->assertSame([$subscription->id], $renewed);
    }

    public function test_duplicate_invoice_delivery_does_not_create_a_second_payment_or_refire(): void
    {
        $subscription = $this->pendingSubscription();
        $subscription->update(['external_id' => 'sub_test_1', 'status' => Subscription::STATUS_ACTIVE]);

        $calls = 0;
        app(PaymentEventRegistry::class)->on('paid', function () use (&$calls) {
            $calls++;
        });

        $payload = $this->invoicePaidPayload('sub_test_1', 'in_test_1');
        $this->postWebhook($payload)->assertOk();
        $this->postWebhook($payload)->assertOk();

        $this->assertSame(1, $calls);
        $this->assertSame(1, Payment::where('external_id', 'in_test_1')->count());
    }

    public function test_invoice_payment_failed_marks_the_subscription_past_due(): void
    {
        $subscription = $this->pendingSubscription();
        $subscription->update(['external_id' => 'sub_test_1', 'status' => Subscription::STATUS_ACTIVE]);

        $payload = [
            'id' => 'evt_failed', 'object' => 'event', 'type' => 'invoice.payment_failed',
            'data' => ['object' => ['id' => 'in_test_2', 'object' => 'invoice', 'subscription' => 'sub_test_1']],
        ];

        $this->postWebhook($payload)->assertOk();

        $this->assertSame(Subscription::STATUS_PAST_DUE, $subscription->fresh()->status);
    }

    public function test_subscription_deleted_marks_canceled_and_fires_the_event(): void
    {
        $subscription = $this->pendingSubscription();
        $subscription->update(['external_id' => 'sub_test_1', 'status' => Subscription::STATUS_ACTIVE]);

        $canceled = [];
        app(SubscriptionEventRegistry::class)->on('canceled', function (Subscription $s) use (&$canceled) {
            $canceled[] = $s->id;
        });

        $payload = [
            'id' => 'evt_deleted', 'object' => 'event', 'type' => 'customer.subscription.deleted',
            'data' => ['object' => ['id' => 'sub_test_1', 'object' => 'subscription']],
        ];

        $this->postWebhook($payload)->assertOk();

        $this->assertSame(Subscription::STATUS_CANCELED, $subscription->fresh()->status);
        $this->assertSame([$subscription->id], $canceled);
    }

    public function test_cancel_subscription_sets_cancel_at_period_end_and_calls_the_gateway(): void
    {
        $subscription = $this->pendingSubscription();
        $subscription->update(['external_id' => 'sub_test_1', 'status' => Subscription::STATUS_ACTIVE]);

        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('cancelSubscription')->once()->with(Mockery::on(fn ($s) => $s->id === $subscription->id));

        $manager = Mockery::mock(PaymentManager::class);
        $manager->shouldReceive('driver')->andReturn($gateway);
        $this->app->instance(PaymentManager::class, $manager);

        app(PaymentService::class)->cancelSubscription($subscription);

        $this->assertTrue($subscription->fresh()->cancel_at_period_end);
    }
}
