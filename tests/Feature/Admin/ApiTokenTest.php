<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceAccount;
use App\Models\User;
use Database\Seeders\CorePermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        @mkdir(storage_path(), 0755, true);
        file_put_contents(storage_path('installed.lock'), 'installed');

        (new CorePermissionsSeeder)->run();

        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    /** Authenticated staff with admin.access but not api_tokens.manage — mirrors ThemeTest's staffWithoutThemesManage(). */
    private function staffWithoutApiTokensManage(): User
    {
        $user = User::factory()->create(['is_admin' => false]);

        $role = Role::create(['name' => 'Restricted', 'slug' => 'restricted-'.uniqid()]);
        $ids = Permission::whereIn('slug', ['admin.access'])->pluck('id');
        $role->permissions()->sync($ids);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_index_requires_the_manage_permission(): void
    {
        $this->actingAs($this->staffWithoutApiTokensManage())
            ->get(route('admin.api-tokens.index'))
            ->assertForbidden();
    }

    public function test_store_creates_a_service_account_with_a_first_token_and_flashes_the_plaintext_once(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.api-tokens.store'), [
            'name' => 'Discord Bot',
            'abilities' => ['notifications:read'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('plain_token');
        $plainToken = $response->getSession()->get('plain_token');

        $this->get(route('admin.api-tokens.index'))
            ->assertInertia(fn ($page) => $page->where('flash.plain_token', $plainToken));

        $account = ServiceAccount::where('name', 'Discord Bot')->firstOrFail();
        $this->assertSame($this->admin->id, $account->created_by);
        $this->assertCount(1, $account->tokens);
        $this->assertSame(['notifications:read'], $account->tokens->first()->abilities);
    }

    public function test_store_rejects_an_ability_not_in_the_registry(): void
    {
        $this->actingAs($this->admin)->post(route('admin.api-tokens.store'), [
            'name' => 'Bad Bot',
            'abilities' => ['not:a-real-ability'],
        ])->assertSessionHasErrors('abilities.0');

        $this->assertDatabaseMissing('service_accounts', ['name' => 'Bad Bot']);
    }

    public function test_a_second_token_can_be_issued_on_an_existing_account(): void
    {
        $account = ServiceAccount::factory()->create();

        $response = $this->actingAs($this->admin)->post(
            route('admin.api-tokens.tokens.store', $account),
            ['name' => 'second key', 'abilities' => ['notifications:write']],
        );

        $response->assertRedirect();
        $response->assertSessionHas('plain_token');
        $this->assertCount(1, $account->fresh()->tokens);
    }

    public function test_revoke_deletes_only_the_token_not_the_account(): void
    {
        $account = ServiceAccount::factory()->create();
        $token = $account->createToken('t', ['notifications:read'])->accessToken;

        $this->actingAs($this->admin)
            ->delete(route('admin.api-tokens.tokens.destroy', $token))
            ->assertRedirect();

        $this->assertModelMissing($token);
        $this->assertModelExists($account);
    }

    public function test_destroy_deletes_the_account_and_every_token_it_issued(): void
    {
        $account = ServiceAccount::factory()->create();
        $token = $account->createToken('t')->accessToken;

        $this->actingAs($this->admin)
            ->delete(route('admin.api-tokens.destroy', $account))
            ->assertRedirect();

        $this->assertModelMissing($account);
        $this->assertModelMissing($token);
    }

    public function test_revoke_refuses_a_token_that_does_not_belong_to_a_service_account(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->accessToken;

        $this->actingAs($this->admin)
            ->delete(route('admin.api-tokens.tokens.destroy', $token))
            ->assertNotFound();

        $this->assertModelExists($token);
    }

    public function test_index_never_exposes_a_plaintext_or_hashed_token_value(): void
    {
        $account = ServiceAccount::factory()->create(['name' => 'Discord Bot']);
        $account->createToken('t', ['notifications:read']);

        $this->actingAs($this->admin)
            ->get(route('admin.api-tokens.index'))
            ->assertInertia(fn ($page) => $page
                ->has('accounts', 1)
                ->where('accounts.0.name', 'Discord Bot')
                ->missing('accounts.0.tokens.0.token')
                ->missing('accounts.0.tokens.0.hash')
            );
    }
}
