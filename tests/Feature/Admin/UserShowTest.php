<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\UserAdminNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserShowTest extends TestCase
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

    public function test_admin_can_view_user_detail_page(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.users.show', $target))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Users/Show')
                ->where('user.id', $target->id)
                ->has('stats')
                ->has('notes'));
    }

    public function test_non_admin_cannot_view_user_detail_page(): void
    {
        $target = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.users.show', $target))
            ->assertRedirect();
    }

    public function test_admin_can_add_note(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.notes.store', $target), ['body' => 'Warned about spam.'])
            ->assertRedirect();

        $this->assertDatabaseHas('user_admin_notes', [
            'user_id' => $target->id,
            'author_id' => $admin->id,
            'body' => 'Warned about spam.',
        ]);
    }

    public function test_note_body_is_required(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.users.notes.store', $target), ['body' => ''])
            ->assertSessionHasErrors('body');
    }

    public function test_admin_can_delete_note(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $note = UserAdminNote::create(['user_id' => $target->id, 'author_id' => $admin->id, 'body' => 'Old note']);

        $this->actingAs($admin)
            ->delete(route('admin.users.notes.destroy', [$target, $note]))
            ->assertRedirect();

        $this->assertDatabaseMissing('user_admin_notes', ['id' => $note->id]);
    }

    public function test_note_must_belong_to_user_in_url(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $other = User::factory()->create();
        $note = UserAdminNote::create(['user_id' => $other->id, 'author_id' => $admin->id, 'body' => 'Note']);

        $this->actingAs($admin)
            ->delete(route('admin.users.notes.destroy', [$target, $note]))
            ->assertNotFound();

        $this->assertDatabaseHas('user_admin_notes', ['id' => $note->id]);
    }
}
