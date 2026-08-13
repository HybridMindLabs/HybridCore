<?php

namespace Tests\Feature\Admin;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Server;
use App\Models\User;
use App\Services\Payments\Contracts\PaymentGateway;
use App\Services\Payments\PaymentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PaymentLedgerTest extends TestCase
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

    private function payment(string $status = Payment::STATUS_PAID): Payment
    {
        $server = Server::factory()->create();

        return Payment::create([
            'payable_type' => $server->getMorphClass(),
            'payable_id' => $server->id,
            'user_id' => User::factory()->create()->id,
            'gateway' => 'stripe',
            'external_id' => 'cs_'.uniqid(),
            'amount' => 2000,
            'currency' => 'usd',
            'status' => $status,
        ]);
    }

    public function test_admin_can_view_the_payment_ledger(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $payment = $this->payment();

        $this->actingAs($admin)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Payments/Index')
                ->where('payments.data.0.id', $payment->id));
    }

    public function test_ledger_links_to_the_invoice_when_one_exists(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $payment = $this->payment();
        $invoice = Invoice::create([
            'payment_id' => $payment->id, 'user_id' => $payment->user_id,
            'amount' => $payment->amount, 'currency' => $payment->currency, 'issued_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.payments.index'))
            ->assertInertia(fn ($page) => $page
                ->where('payments.data.0.invoice_url', route('invoices.download', ['invoice' => $invoice->id])));
    }

    public function test_ledger_has_no_invoice_link_when_none_exists(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->payment();

        $this->actingAs($admin)
            ->get(route('admin.payments.index'))
            ->assertInertia(fn ($page) => $page->where('payments.data.0.invoice_url', null));
    }

    public function test_status_filter_narrows_the_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->payment(Payment::STATUS_PAID);
        $failed = $this->payment(Payment::STATUS_FAILED);

        $this->actingAs($admin)
            ->get(route('admin.payments.index', ['status' => 'failed']))
            ->assertInertia(fn ($page) => $page
                ->has('payments.data', 1)
                ->where('payments.data.0.id', $failed->id));
    }

    public function test_non_admin_cannot_view_the_ledger(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('admin.payments.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_refund_a_paid_payment(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $payment = $this->payment(Payment::STATUS_PAID);

        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('refund')->once();
        $manager = Mockery::mock(PaymentManager::class);
        $manager->shouldReceive('driver')->andReturn($gateway);
        $this->app->instance(PaymentManager::class, $manager);

        $this->actingAs($admin)
            ->post(route('admin.payments.refund', $payment))
            ->assertRedirect();

        $this->assertSame(Payment::STATUS_REFUNDED, $payment->fresh()->status);
    }

    public function test_a_payment_that_is_not_paid_cannot_be_refunded(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $payment = $this->payment(Payment::STATUS_FAILED);

        $this->actingAs($admin)
            ->post(route('admin.payments.refund', $payment))
            ->assertStatus(422);

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
    }
}
