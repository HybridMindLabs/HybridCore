<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Server;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\Contracts\PaymentGateway;
use App\Services\Payments\PaymentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TransactionsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed.lock'), 'installed');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    private function paymentFor(User $user): Payment
    {
        $server = Server::factory()->create();

        return Payment::create([
            'payable_type' => $server->getMorphClass(),
            'payable_id' => $server->id,
            'user_id' => $user->id,
            'gateway' => 'stripe',
            'external_id' => 'cs_'.uniqid(),
            'amount' => 1200,
            'currency' => 'usd',
            'status' => Payment::STATUS_PAID,
        ]);
    }

    private function subscriptionFor(User $user): Subscription
    {
        $server = Server::factory()->create();

        return Subscription::create([
            'payable_type' => $server->getMorphClass(),
            'payable_id' => $server->id,
            'user_id' => $user->id,
            'gateway' => 'stripe',
            'external_id' => 'sub_'.uniqid(),
            'amount' => 999,
            'currency' => 'usd',
            'interval' => 'month',
            'status' => Subscription::STATUS_ACTIVE,
        ]);
    }

    public function test_the_page_only_lists_the_current_users_own_payments_and_subscriptions(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $mine = $this->paymentFor($user);
        $this->paymentFor($other);
        $mySub = $this->subscriptionFor($user);
        $this->subscriptionFor($other);

        $this->actingAs($user)
            ->get(route('account.transactions.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Account/Transactions')
                ->where('payments.data.0.id', $mine->id)
                ->has('payments.data', 1)
                ->where('subscriptions.0.id', $mySub->id)
                ->has('subscriptions', 1));
    }

    public function test_owner_can_cancel_their_own_subscription(): void
    {
        $user = User::factory()->create();
        $subscription = $this->subscriptionFor($user);

        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('cancelSubscription')->once();
        $manager = Mockery::mock(PaymentManager::class);
        $manager->shouldReceive('driver')->andReturn($gateway);
        $this->app->instance(PaymentManager::class, $manager);

        $this->actingAs($user)
            ->post(route('account.subscriptions.cancel', $subscription))
            ->assertRedirect();

        $this->assertTrue($subscription->fresh()->cancel_at_period_end);
    }

    public function test_a_user_cannot_cancel_someone_elses_subscription(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $subscription = $this->subscriptionFor($owner);

        $this->actingAs($stranger)
            ->post(route('account.subscriptions.cancel', $subscription))
            ->assertForbidden();

        $this->assertFalse($subscription->fresh()->cancel_at_period_end);
    }
}
