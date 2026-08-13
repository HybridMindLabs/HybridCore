<?php

namespace Tests\Feature\Admin;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceLedgerTest extends TestCase
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

    private function invoiceFor(User $user): Invoice
    {
        $server = Server::factory()->create();
        $payment = Payment::create([
            'payable_type' => $server->getMorphClass(), 'payable_id' => $server->id,
            'user_id' => $user->id, 'gateway' => 'stripe', 'external_id' => 'cs_'.uniqid(),
            'amount' => 2000, 'currency' => 'usd', 'status' => Payment::STATUS_PAID,
        ]);

        return Invoice::create([
            'payment_id' => $payment->id, 'user_id' => $user->id,
            'amount' => $payment->amount, 'currency' => $payment->currency, 'issued_at' => now(),
        ]);
    }

    public function test_admin_can_view_the_invoice_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $buyer = User::factory()->create(['username' => 'Buyer1']);
        $invoice = $this->invoiceFor($buyer);

        $this->actingAs($admin)
            ->get(route('admin.invoices.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Invoices/Index')
                ->where('invoices.data.0.number', $invoice->number())
                ->where('invoices.data.0.user', 'Buyer1'));
    }

    public function test_stats_reflect_total_and_this_month(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->invoiceFor(User::factory()->create());
        $this->invoiceFor(User::factory()->create());

        $this->actingAs($admin)
            ->get(route('admin.invoices.index'))
            ->assertInertia(fn ($page) => $page
                ->where('stats.total', 2)
                ->where('stats.totalAmount', fn ($amount) => (float) $amount === 40.0)
                ->where('stats.monthCount', 2));
    }

    public function test_search_filters_by_username(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->invoiceFor(User::factory()->create(['username' => 'Findme']));
        $this->invoiceFor(User::factory()->create(['username' => 'Someoneelse']));

        $this->actingAs($admin)
            ->get(route('admin.invoices.index', ['q' => 'Findme']))
            ->assertInertia(fn ($page) => $page
                ->has('invoices.data', 1)
                ->where('invoices.data.0.user', 'Findme'));
    }

    public function test_non_admin_cannot_view_invoices(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('admin.invoices.index'))
            ->assertRedirect(route('admin.login'));
    }
}
