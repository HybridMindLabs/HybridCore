<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ContactNotifiesAdminsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        @mkdir(storage_path(), 0755, true);
        file_put_contents(storage_path('installed.lock'), 'installed');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    public function test_submitting_the_contact_form_notifies_every_admin(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['is_admin' => false]);

        $this->post(route('contact.store'), [
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'subject' => 'Question',
            'message' => str_repeat('a', 20),
        ])->assertRedirect();

        Notification::assertSentTo($admin, SystemNotification::class);
    }
}
