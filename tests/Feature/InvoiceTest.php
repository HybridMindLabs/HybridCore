<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Server;
use App\Models\User;
use App\Notifications\PaymentReceiptNotification;
use App\Services\Extensions\Registries\PaymentEventRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InvoiceTest extends TestCase
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

    private function paidPayment(?User $user = null): Payment
    {
        $server = Server::factory()->create();

        return Payment::create([
            'payable_type' => $server->getMorphClass(),
            'payable_id' => $server->id,
            'user_id' => $user?->id,
            'gateway' => 'stripe',
            'external_id' => 'cs_'.uniqid(),
            'amount' => 1500,
            'currency' => 'usd',
            'status' => Payment::STATUS_PAID,
        ]);
    }

    public function test_paid_event_creates_an_invoice_and_notifies_the_buyer(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $payment = $this->paidPayment($user);

        app(PaymentEventRegistry::class)->dispatch($payment);

        $this->assertDatabaseHas('invoices', [
            'payment_id' => $payment->id,
            'user_id' => $user->id,
            'amount' => 1500,
            'currency' => 'usd',
        ]);
        Notification::assertSentTo($user, PaymentReceiptNotification::class);
    }

    public function test_repeated_dispatch_of_the_same_payment_does_not_duplicate_the_invoice(): void
    {
        $user = User::factory()->create();
        $payment = $this->paidPayment($user);

        app(PaymentEventRegistry::class)->dispatch($payment);
        app(PaymentEventRegistry::class)->dispatch($payment);

        $this->assertSame(1, Invoice::where('payment_id', $payment->id)->count());
    }

    public function test_guest_payment_creates_an_invoice_without_notifying_anyone(): void
    {
        Notification::fake();

        $payment = $this->paidPayment(null);

        app(PaymentEventRegistry::class)->dispatch($payment);

        $this->assertDatabaseHas('invoices', ['payment_id' => $payment->id, 'user_id' => null]);
        Notification::assertNothingSent();
    }

    public function test_invoice_number_is_derived_from_its_id(): void
    {
        $payment = $this->paidPayment(User::factory()->create());

        $invoice = Invoice::create([
            'payment_id' => $payment->id,
            'user_id' => $payment->user_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'issued_at' => now(),
        ]);

        $this->assertSame('INV-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT), $invoice->number());
    }

    public function test_owner_can_download_their_invoice(): void
    {
        $user = User::factory()->create();
        $payment = $this->paidPayment($user);
        $invoice = Invoice::create([
            'payment_id' => $payment->id, 'user_id' => $user->id,
            'amount' => $payment->amount, 'currency' => $payment->currency, 'issued_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('invoices.download', $invoice))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_another_user_cannot_download_someone_elses_invoice(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $payment = $this->paidPayment($owner);
        $invoice = Invoice::create([
            'payment_id' => $payment->id, 'user_id' => $owner->id,
            'amount' => $payment->amount, 'currency' => $payment->currency, 'issued_at' => now(),
        ]);

        $this->actingAs($stranger)
            ->get(route('invoices.download', $invoice))
            ->assertForbidden();
    }

    public function test_admin_with_payments_permission_can_download_any_invoice(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $payment = $this->paidPayment($owner);
        $invoice = Invoice::create([
            'payment_id' => $payment->id, 'user_id' => $owner->id,
            'amount' => $payment->amount, 'currency' => $payment->currency, 'issued_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('invoices.download', $invoice))
            ->assertOk();
    }

    public function test_payment_description_falls_back_to_the_payable_class_when_it_defines_no_description(): void
    {
        $payment = $this->paidPayment(User::factory()->create());

        $this->assertSame('Server #'.$payment->payable_id, $payment->description());
    }
}
