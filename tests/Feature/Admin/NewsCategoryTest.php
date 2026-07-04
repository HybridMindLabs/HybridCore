<?php

namespace Tests\Feature\Admin;

use App\Models\NewsCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsCategoryTest extends TestCase
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
            ->get(route('admin.news.categories.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_index_renders_for_admin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.news.categories.index'))
            ->assertOk();
    }

    public function test_create_page_renders(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.news.categories.create'))
            ->assertOk();
    }

    public function test_store_creates_category(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.news.categories.store'), ['name' => 'Gaming'])
            ->assertRedirect(route('admin.news.categories.index'));

        $this->assertDatabaseHas('news_categories', ['name' => 'Gaming', 'slug' => 'gaming']);
    }

    public function test_store_auto_generates_slug_from_name(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.news.categories.store'), ['name' => 'Counter Strike']);

        $this->assertDatabaseHas('news_categories', ['slug' => 'counter-strike']);
    }

    public function test_store_rejects_duplicate_slug(): void
    {
        NewsCategory::create(['name' => 'Existing', 'slug' => 'existing']);

        $this->actingAs($this->admin)
            ->post(route('admin.news.categories.store'), ['name' => 'Other', 'slug' => 'existing'])
            ->assertSessionHasErrors('slug');
    }

    public function test_store_requires_name(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.news.categories.store'), [])
            ->assertSessionHasErrors('name');
    }

    public function test_store_rejects_invalid_color(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.news.categories.store'), ['name' => 'Test', 'color' => 'not-a-color'])
            ->assertSessionHasErrors('color');
    }

    public function test_update_changes_category(): void
    {
        $category = NewsCategory::create(['name' => 'Old Name', 'slug' => 'old-name']);

        $this->actingAs($this->admin)
            ->put(route('admin.news.categories.update', $category), [
                'name' => 'New Name',
                'slug' => 'old-name',
                'color' => '#ff0000',
            ])
            ->assertRedirect(route('admin.news.categories.index'));

        $this->assertDatabaseHas('news_categories', ['id' => $category->id, 'name' => 'New Name', 'color' => '#ff0000']);
    }

    public function test_destroy_deletes_category(): void
    {
        $category = NewsCategory::create(['name' => 'To Delete', 'slug' => 'to-delete']);

        $this->actingAs($this->admin)
            ->delete(route('admin.news.categories.destroy', $category))
            ->assertRedirect(route('admin.news.categories.index'));

        $this->assertDatabaseMissing('news_categories', ['id' => $category->id]);
    }

    public function test_edit_page_renders(): void
    {
        $category = NewsCategory::create(['name' => 'Edit Me', 'slug' => 'edit-me']);

        $this->actingAs($this->admin)
            ->get(route('admin.news.categories.edit', $category))
            ->assertOk();
    }
}
