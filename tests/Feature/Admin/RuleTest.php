<?php

namespace Tests\Feature\Admin;

use App\Models\Rule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuleTest extends TestCase
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
            ->get(route('admin.rules.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_index_renders_for_admin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.rules.index'))
            ->assertOk();
    }

    public function test_create_page_renders(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.rules.create'))
            ->assertOk();
    }

    public function test_store_creates_rule(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.rules.store'), [
                'title' => 'No Cheating',
                'content' => 'Cheating is not allowed.',
                'published' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('rules', ['title' => 'No Cheating', 'published' => true]);
    }

    public function test_store_auto_generates_slug(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.rules.store'), [
                'title' => 'Be Respectful',
                'published' => false,
            ]);

        $this->assertDatabaseHas('rules', ['slug' => 'be-respectful']);
    }

    public function test_store_requires_title(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.rules.store'), ['published' => true])
            ->assertSessionHasErrors('title');
    }

    public function test_update_changes_rule(): void
    {
        $rule = Rule::create([
            'title' => 'Original Rule',
            'slug' => 'original-rule',
            'published' => false,
            'is_system' => false,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.rules.update', $rule->slug), [
                'title' => 'Updated Rule',
                'published' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('rules', ['title' => 'Updated Rule', 'published' => true]);
    }

    public function test_destroy_deletes_non_system_rule(): void
    {
        $rule = Rule::create([
            'title' => 'Deletable Rule',
            'slug' => 'deletable-rule',
            'published' => true,
            'is_system' => false,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.rules.destroy', $rule->slug))
            ->assertRedirect(route('admin.rules.index'));

        $this->assertDatabaseMissing('rules', ['id' => $rule->id]);
    }

    public function test_destroy_blocks_system_rule(): void
    {
        $rule = Rule::create([
            'title' => 'System Rule',
            'slug' => 'system-rule',
            'published' => true,
            'is_system' => true,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.rules.destroy', $rule->slug))
            ->assertForbidden();

        $this->assertDatabaseHas('rules', ['id' => $rule->id]);
    }

    public function test_edit_page_renders(): void
    {
        $rule = Rule::create([
            'title' => 'Edit Me',
            'slug' => 'edit-me',
            'published' => false,
            'is_system' => false,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.rules.edit', $rule->slug))
            ->assertOk();
    }
}
