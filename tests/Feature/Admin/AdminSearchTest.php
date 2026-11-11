<?php

namespace Tests\Feature\Admin;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Server;
use App\Models\ServerSnapshot;
use App\Models\User;
use App\Support\Csv;
use Database\Seeders\CorePermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSearchTest extends TestCase
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

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /** @param  array<int, string>  $slugs */
    private function restrictedAdmin(array $slugs): User
    {
        (new CorePermissionsSeeder)->run();

        $user = User::factory()->create();
        $role = Role::create(['name' => 'Restricted', 'slug' => 'restricted-'.uniqid()]);
        $role->permissions()->sync(Permission::whereIn('slug', array_merge(['admin.access'], $slugs))->pluck('id'));
        $user->roles()->attach($role);

        return $user;
    }

    private function payment(User $buyer, int $amount = 2000): Payment
    {
        $server = Server::factory()->create();

        return Payment::create([
            'payable_type' => $server->getMorphClass(),
            'payable_id' => $server->id,
            'user_id' => $buyer->id,
            'gateway' => 'stripe',
            'external_id' => 'cs_'.uniqid(),
            'amount' => $amount,
            'currency' => 'eur',
            'status' => Payment::STATUS_PAID,
        ]);
    }

    public function test_search_finds_banned_users_and_hidden_servers(): void
    {
        User::factory()->create(['name' => 'Zed Banned', 'banned_at' => now()]);
        Server::factory()->create(['name' => 'Zed Backend', 'is_visible' => false]);

        $this->actingAs($this->admin())
            ->getJson(route('admin.search', ['q' => 'zed']))
            ->assertOk()
            ->assertJsonPath('users.0.title', 'Zed Banned')
            ->assertJsonPath('users.0.badge', 'Banned')
            ->assertJsonPath('servers.0.badge', 'Hidden');
    }

    public function test_search_finds_a_payment_by_hash_id(): void
    {
        $payment = $this->payment(User::factory()->create());

        $this->actingAs($this->admin())
            ->getJson(route('admin.search', ['q' => '#'.$payment->id, 'type' => 'payments']))
            ->assertOk()
            ->assertJsonCount(1, 'payments')
            ->assertJsonPath('payments.0.id', $payment->id);
    }

    public function test_groups_the_admin_cannot_view_stay_empty(): void
    {
        User::factory()->create(['name' => 'Zed Member']);
        Server::factory()->create(['name' => 'Zed Server']);

        $this->actingAs($this->restrictedAdmin(['servers.view']))
            ->getJson(route('admin.search', ['q' => 'zed']))
            ->assertOk()
            ->assertJsonCount(0, 'users')
            ->assertJsonCount(1, 'servers');
    }

    public function test_search_requires_an_admin(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.search', ['q' => 'zed']))
            ->assertRedirect(route('admin.login'));
    }

    public function test_payments_can_be_sorted_by_amount(): void
    {
        $buyer = User::factory()->create();
        $small = $this->payment($buyer, 500);
        $big = $this->payment($buyer, 9000);

        $this->actingAs($this->admin())
            ->get(route('admin.payments.index', ['sort' => 'amount', 'dir' => 'desc']))
            ->assertInertia(fn ($page) => $page
                ->where('payments.data.0.id', $big->id)
                ->where('payments.data.1.id', $small->id));
    }

    public function test_servers_can_be_sorted_by_current_players(): void
    {
        $quiet = Server::factory()->create();
        $busy = Server::factory()->create();
        ServerSnapshot::create(['server_id' => $quiet->id, 'is_online' => true, 'players_online' => 1, 'recorded_at' => now()]);
        ServerSnapshot::create(['server_id' => $busy->id, 'is_online' => true, 'players_online' => 20, 'recorded_at' => now()]);

        $this->actingAs($this->admin())
            ->get(route('admin.servers.index', ['sort' => 'players', 'dir' => 'desc']))
            ->assertInertia(fn ($page) => $page->where('servers.data.0.id', $busy->id));
    }

    public function test_payment_export_respects_the_date_range(): void
    {
        $buyer = User::factory()->create(['username' => 'buyer']);
        $old = $this->payment($buyer);
        $old->forceFill(['created_at' => now()->subMonths(2)])->save();
        $recent = $this->payment($buyer);

        $csv = $this->actingAs($this->admin())
            ->get(route('admin.payments.export', ['from' => now()->subWeek()->toDateString()]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString("\n{$recent->id},", $csv);
        $this->assertStringNotContainsString("\n{$old->id},", $csv);
    }

    public function test_invoice_export_lists_invoices(): void
    {
        $payment = $this->payment(User::factory()->create());
        $invoice = Invoice::create([
            'payment_id' => $payment->id,
            'user_id' => $payment->user_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'issued_at' => now(),
        ]);

        $csv = $this->actingAs($this->admin())
            ->get(route('admin.invoices.export'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString($invoice->number(), $csv);
    }

    public function test_lists_accept_only_known_page_sizes(): void
    {
        User::factory()->count(30)->create();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.users.index', ['per_page' => 25]))
            ->assertInertia(fn ($page) => $page->where('users.per_page', 25)->has('users.data', 25));

        $this->actingAs($admin)->get(route('admin.users.index', ['per_page' => 5000]))
            ->assertInertia(fn ($page) => $page->where('users.per_page', 15));
    }

    public function test_csv_cells_cannot_start_a_formula(): void
    {
        $this->assertSame("'=HYPERLINK(\"x\")", Csv::safe('=HYPERLINK("x")'));
        $this->assertSame("'@cmd", Csv::safe('@cmd'));
        $this->assertSame('Plain name', Csv::safe('Plain name'));
    }
}
