<?php

namespace Tests\Feature\Admin;

use App\Models\LegalPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalTest extends TestCase
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
            ->get(route('admin.legal.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_index_renders_for_admin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.legal.index'))
            ->assertOk();
    }

    public function test_create_page_renders(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.legal.create'))
            ->assertOk();
    }

    public function test_store_creates_legal_page(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.legal.store'), [
                'slug' => 'terms',
                'title' => 'Terms of Service',
                'content' => 'By using this service...',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('legal_pages', ['slug' => 'terms', 'title' => 'Terms of Service']);
    }

    public function test_store_requires_slug_and_title(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.legal.store'), [])
            ->assertSessionHasErrors(['slug', 'title']);
    }

    public function test_store_rejects_duplicate_slug(): void
    {
        LegalPage::create(['slug' => 'privacy', 'title' => 'Privacy Policy']);

        $this->actingAs($this->admin)
            ->post(route('admin.legal.store'), ['slug' => 'privacy', 'title' => 'Another'])
            ->assertSessionHasErrors('slug');
    }

    public function test_store_rejects_invalid_slug_format(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.legal.store'), ['slug' => 'UPPER CASE!', 'title' => 'Test'])
            ->assertSessionHasErrors('slug');
    }

    public function test_edit_page_renders(): void
    {
        $page = LegalPage::create(['slug' => 'cookies', 'title' => 'Cookie Policy']);

        $this->actingAs($this->admin)
            ->get(route('admin.legal.edit', $page->slug))
            ->assertOk();
    }

    public function test_update_changes_legal_page(): void
    {
        $page = LegalPage::create(['slug' => 'terms', 'title' => 'Old Title']);

        $this->actingAs($this->admin)
            ->put(route('admin.legal.update', $page->slug), [
                'slug' => 'terms',
                'title' => 'New Terms Title',
                'content' => 'Updated content.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('legal_pages', ['slug' => 'terms', 'title' => 'New Terms Title']);
    }

    public function test_destroy_deletes_legal_page(): void
    {
        $page = LegalPage::create(['slug' => 'old-page', 'title' => 'Old Page']);

        $this->actingAs($this->admin)
            ->delete(route('admin.legal.destroy', $page->slug))
            ->assertRedirect(route('admin.legal.index'));

        $this->assertDatabaseMissing('legal_pages', ['slug' => 'old-page']);
    }
}
