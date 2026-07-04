<?php

namespace Tests\Feature\Admin;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPageTest extends TestCase
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
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.pages.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_index_lists_pages(): void
    {
        Page::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.pages.index'))
            ->assertOk();
    }

    public function test_store_creates_page_with_auto_slug(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.pages.store'), [
                'title' => 'About Us',
                'slug' => '',
                'body' => '<p>Hello</p>',
                'status' => 'published',
            ])
            ->assertRedirect(route('admin.pages.index'));

        $this->assertDatabaseHas('pages', ['slug' => 'about-us', 'status' => 'published']);
        $this->assertNotNull(Page::where('slug', 'about-us')->first()->published_at);
    }

    public function test_store_rejects_duplicate_slug(): void
    {
        Page::factory()->create(['slug' => 'about']);

        $this->actingAs($this->admin)
            ->post(route('admin.pages.store'), [
                'title' => 'Another',
                'slug' => 'about',
                'status' => 'draft',
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_update_changes_page(): void
    {
        $page = Page::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.pages.update', $page), [
                'title' => 'Updated Title',
                'slug' => $page->slug,
                'body' => $page->body,
                'status' => 'published',
            ])
            ->assertRedirect(route('admin.pages.index'));

        $this->assertDatabaseHas('pages', ['id' => $page->id, 'title' => 'Updated Title']);
    }

    public function test_destroy_soft_deletes_page(): void
    {
        $page = Page::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.pages.destroy', $page))
            ->assertRedirect(route('admin.pages.index'));

        $this->assertSoftDeleted('pages', ['id' => $page->id]);
    }
}
