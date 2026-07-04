<?php

namespace Tests\Feature\Admin;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactTest extends TestCase
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
            ->get(route('admin.contact.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_index_renders_for_admin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.contact.index'))
            ->assertOk();
    }

    public function test_index_shows_messages(): void
    {
        ContactMessage::create(['name' => 'Alice', 'email' => 'alice@example.com', 'subject' => 'Hello', 'message' => 'Hi there!']);
        ContactMessage::create(['name' => 'Bob',   'email' => 'bob@example.com',   'subject' => 'Test',  'message' => 'Testing.']);

        $this->actingAs($this->admin)
            ->get(route('admin.contact.index'))
            ->assertOk();
    }

    public function test_show_renders_message(): void
    {
        $msg = ContactMessage::create(['name' => 'Alice', 'email' => 'alice@example.com', 'subject' => 'Hello', 'message' => 'Hi!']);

        $this->actingAs($this->admin)
            ->get(route('admin.contact.show', $msg))
            ->assertOk();
    }

    public function test_show_marks_message_as_read(): void
    {
        $msg = ContactMessage::create(['name' => 'Alice', 'email' => 'alice@example.com', 'subject' => 'Hello', 'message' => 'Hi!']);
        $this->assertNull($msg->read_at);

        $this->actingAs($this->admin)->get(route('admin.contact.show', $msg));

        $this->assertNotNull($msg->fresh()->read_at);
    }

    public function test_destroy_deletes_message(): void
    {
        $msg = ContactMessage::create(['name' => 'Alice', 'email' => 'alice@example.com', 'subject' => 'Hello', 'message' => 'Hi!']);

        $this->actingAs($this->admin)
            ->delete(route('admin.contact.destroy', $msg))
            ->assertRedirect(route('admin.contact.index'));

        $this->assertDatabaseMissing('contact_messages', ['id' => $msg->id]);
    }

    public function test_non_admin_cannot_delete_message(): void
    {
        $msg = ContactMessage::create(['name' => 'Alice', 'email' => 'alice@example.com', 'subject' => 'Hi', 'message' => 'Hello']);

        $this->actingAs(User::factory()->create())
            ->delete(route('admin.contact.destroy', $msg))
            ->assertRedirect(route('admin.login'));

        $this->assertDatabaseHas('contact_messages', ['id' => $msg->id]);
    }
}
