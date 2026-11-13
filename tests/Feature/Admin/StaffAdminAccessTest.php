<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\CorePermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        @mkdir(storage_path(), 0755, true);
        file_put_contents(storage_path('installed.lock'), 'installed');

        (new CorePermissionsSeeder)->run();
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));

        parent::tearDown();
    }

    private function staff(array $slugs): User
    {
        $user = User::factory()->create(['is_admin' => false]);
        $role = Role::create(['name' => 'Staff', 'slug' => 'staff-'.uniqid()]);
        $role->permissions()->sync(Permission::whereIn('slug', $slugs)->pluck('id'));
        $user->roles()->attach($role);

        return $user;
    }

    public function test_staff_with_admin_access_see_the_panel_link_and_only_their_sections(): void
    {
        $staff = $this->staff(['admin.access', 'users.view']);
        $admin = User::factory()->create(['is_admin' => true]);

        $staffNav = $this->actingAs($staff)->get(route('home'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('auth.user.can_access_admin', true))
            ->viewData('page')['props']['adminNav'];
        $adminNav = $this->actingAs($admin)->get(route('home'))
            ->viewData('page')['props']['adminNav'];

        $items = collect($staffNav)->flatMap(fn ($section) => $section['items']);
        $this->assertNotEmpty($items);
        $this->assertTrue($items->every(fn ($item) => $item['permission'] === null || $staff->hasPermission($item['permission'])));
        $this->assertLessThan(
            collect($adminNav)->flatMap(fn ($section) => $section['items'])->count(),
            $items->count(),
        );
    }

    public function test_users_without_admin_access_get_no_panel_link_or_sidebar(): void
    {
        $member = $this->staff(['users.view']);

        $this->actingAs($member)->get(route('home'))->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.can_access_admin', false)
                ->where('adminNav', []));
    }

    public function test_the_register_link_follows_the_registration_setting(): void
    {
        $this->get(route('home'))->assertInertia(fn ($page) => $page->where('registrationEnabled', true));

        app(SettingsService::class)->set('registration_enabled', '0');

        $this->get(route('home'))->assertInertia(fn ($page) => $page->where('registrationEnabled', false));
    }
}
