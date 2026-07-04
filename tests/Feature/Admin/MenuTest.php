<?php

namespace Tests\Feature\Admin;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        @mkdir(storage_path(), 0755, true);
        file_put_contents(storage_path('installed.lock'), 'installed');

        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    public function test_index_requires_admin(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.menus.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_index_renders_for_admin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.menus.index'))
            ->assertOk();
    }

    public function test_store_creates_menu(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.menus.store'), ['name' => 'Main Nav', 'location' => 'header'])
            ->assertRedirect();

        $this->assertDatabaseHas('menus', ['name' => 'Main Nav', 'slug' => 'main-nav', 'location' => 'header']);
    }

    public function test_store_requires_name(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.menus.store'), [])
            ->assertSessionHasErrors('name');
    }

    public function test_edit_page_renders(): void
    {
        $menu = Menu::create(['name' => 'Footer', 'slug' => 'footer']);

        $this->actingAs($this->admin)
            ->get(route('admin.menus.edit', $menu))
            ->assertOk();
    }

    public function test_update_changes_menu(): void
    {
        $menu = Menu::create(['name' => 'Old Name', 'slug' => 'old-name']);

        $this->actingAs($this->admin)
            ->put(route('admin.menus.update', $menu), ['name' => 'New Name', 'location' => 'footer'])
            ->assertRedirect();

        $this->assertDatabaseHas('menus', ['id' => $menu->id, 'name' => 'New Name', 'location' => 'footer']);
    }

    public function test_destroy_deletes_menu(): void
    {
        $menu = Menu::create(['name' => 'Temp Menu', 'slug' => 'temp-menu']);

        $this->actingAs($this->admin)
            ->delete(route('admin.menus.destroy', $menu))
            ->assertRedirect(route('admin.menus.index'));

        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
    }

    public function test_store_item_adds_to_menu(): void
    {
        $menu = Menu::create(['name' => 'Nav', 'slug' => 'nav']);

        $this->actingAs($this->admin)
            ->post(route('admin.menus.items.store', $menu), [
                'label' => 'Home',
                'url' => '/',
                'target' => '_self',
                'sort' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('menu_items', ['menu_id' => $menu->id, 'label' => 'Home']);
    }
}
