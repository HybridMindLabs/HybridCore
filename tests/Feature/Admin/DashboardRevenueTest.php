<?php

namespace Tests\Feature\Admin;

use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use Database\Seeders\CorePermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRevenueTest extends TestCase
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

    private function payment(int $amount, string $status, string $currency = 'eur', ?string $at = null): void
    {
        $server = Server::factory()->create();
        $p = Payment::create([
            'payable_type' => $server->getMorphClass(),
            'payable_id' => $server->id,
            'user_id' => null,
            'gateway' => 'stripe',
            'amount' => $amount,
            'currency' => $currency,
            'status' => $status,
        ]);

        if ($at) {
            $p->forceFill(['created_at' => $at])->save();
        }
    }

    public function test_revenue_sums_paid_payments_in_the_main_currency(): void
    {
        $this->payment(1000, Payment::STATUS_PAID);
        $this->payment(2500, Payment::STATUS_PAID);
        $this->payment(9999, Payment::STATUS_FAILED);
        $this->payment(500, Payment::STATUS_PAID, 'usd');
        $this->payment(4000, Payment::STATUS_PAID, 'eur', now()->subDays(40)->toDateTimeString());

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.dashboard', ['range' => 30]))
            ->assertInertia(fn ($page) => $page
                ->where('revenue.currency', 'EUR')
                ->where('revenue.today', 35)
                ->where('revenue.period', 35)
                ->where('revenue.paid_count', 2)
                ->where('revenue.average', 17.5)
                ->where('revenue.failed_count', 1)
                ->has('revenue.series', 30));
    }

    public function test_staff_without_payments_access_see_no_revenue(): void
    {
        (new CorePermissionsSeeder)->run();
        $staff = User::factory()->create();
        $role = Role::create(['name' => 'Staff', 'slug' => 'staff-'.uniqid()]);
        $role->permissions()->sync(Permission::whereIn('slug', ['admin.access', 'users.view'])->pluck('id'));
        $staff->roles()->attach($role);

        $this->actingAs($staff)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn ($page) => $page->where('revenue', null));
    }
}
