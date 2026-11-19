<?php

namespace Tests\Feature\Admin;

use App\Models\Payment;
use App\Models\Server;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Server $product;

    protected function setUp(): void
    {
        parent::setUp();

        @mkdir(storage_path(), 0755, true);
        file_put_contents(storage_path('installed.lock'), 'installed');

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->product = Server::factory()->create();
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));

        parent::tearDown();
    }

    private function payment(int $amount, string $status = Payment::STATUS_PAID, string $currency = 'eur', ?User $user = null, ?string $at = null, string $gateway = 'stripe'): Payment
    {
        $payment = Payment::create([
            'payable_type' => $this->product->getMorphClass(),
            'payable_id' => $this->product->id,
            'user_id' => ($user ?? $this->admin)->id,
            'gateway' => $gateway,
            'external_id' => 'p_'.uniqid('', true),
            'amount' => $amount,
            'currency' => $currency,
            'status' => $status,
        ]);

        if ($at) {
            $payment->forceFill(['created_at' => $at])->save();
        }

        return $payment;
    }

    private function subscription(int $amount, string $interval, string $status = Subscription::STATUS_ACTIVE): void
    {
        Subscription::create([
            'payable_type' => $this->product->getMorphClass(),
            'payable_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'gateway' => 'stripe',
            'external_id' => 'sub_'.uniqid('', true),
            'amount' => $amount,
            'currency' => 'eur',
            'interval' => $interval,
            'status' => $status,
        ]);
    }

    public function test_totals_split_paid_and_refunded_and_compare_with_the_previous_period(): void
    {
        $member = User::factory()->create();
        $this->payment(1000);
        $this->payment(500, user: $member, gateway: 'paypal');
        $this->payment(300, Payment::STATUS_REFUNDED);
        $this->payment(999, Payment::STATUS_FAILED);
        $this->payment(750, at: now()->subDays(40)->toDateTimeString());

        $this->actingAs($this->admin)->get(route('admin.finance.index'))->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.currency', 'EUR')
                ->where('report.totals.net', 1500)
                ->where('report.totals.refunded', 300)
                ->where('report.totals.gross', 1800)
                ->where('report.totals.orders', 2)
                ->where('report.totals.average', 750)
                ->where('report.totals.customers', 2)
                ->where('report.totals.previous_net', 750)
                ->where('report.by_gateway.0.name', 'Stripe')
                ->where('report.by_gateway.0.revenue', 1000)
                ->where('report.by_source.0.name', 'Server')
                ->has('report.series', 30));
    }

    public function test_currencies_are_never_added_together(): void
    {
        $this->payment(1000, currency: 'eur');
        $this->payment(1000, currency: 'eur');
        $this->payment(5000, currency: 'usd');

        $this->actingAs($this->admin)->get(route('admin.finance.index'))
            ->assertInertia(fn ($page) => $page->where('report.currency', 'EUR')->where('report.totals.net', 2000)
                ->where('report.currencies', ['EUR', 'USD']));

        $this->actingAs($this->admin)->get(route('admin.finance.index', ['currency' => 'usd']))
            ->assertInertia(fn ($page) => $page->where('report.currency', 'USD')->where('report.totals.net', 5000));
    }

    public function test_mrr_normalises_every_billing_interval_to_a_month(): void
    {
        $this->subscription(1000, 'month');
        $this->subscription(12000, 'year');
        $this->subscription(500, 'month', Subscription::STATUS_CANCELED);

        $this->actingAs($this->admin)->get(route('admin.finance.index'))
            ->assertInertia(fn ($page) => $page->where('report.subscriptions.active', 2)->where('report.subscriptions.mrr', 2000));
    }

    public function test_long_ranges_are_grouped_by_month_and_a_reversed_custom_range_is_fixed(): void
    {
        $this->actingAs($this->admin)->get(route('admin.finance.index', ['range' => '12m']))
            ->assertInertia(fn ($page) => $page->has('report.series', 12));

        $this->actingAs($this->admin)->get(route('admin.finance.index', ['range' => 'custom', 'from' => '2026-03-10', 'to' => '2026-03-01']))
            ->assertInertia(fn ($page) => $page->where('filters.from', '2026-03-01')->where('filters.to', '2026-03-10'));
    }

    public function test_staff_without_payment_access_cannot_open_it(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.finance.index'))->assertRedirect();
    }
}
