<?php

namespace Tests\Feature\Admin;

use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminEmailTest extends TestCase
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

    public function test_settings_page_requires_admin(): void
    {
        $guest = User::factory()->create(['is_admin' => false]);

        $this->actingAs($guest)->get('/admin/email/settings')
            ->assertRedirect('/admin/login');
    }

    public function test_settings_page_accessible_to_admin(): void
    {
        $this->actingAs($this->admin)->get('/admin/email/settings')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Email/Settings')
                ->has('settings')
            );
    }

    public function test_settings_page_has_required_keys(): void
    {
        $this->actingAs($this->admin)->get('/admin/email/settings')
            ->assertInertia(fn ($page) => $page
                ->has('settings.mail_mailer')
                ->has('settings.mail_host')
                ->has('settings.mail_port')
                ->has('settings.mail_from_address')
            );
    }

    public function test_save_settings_stores_to_database(): void
    {
        $this->actingAs($this->admin)->post('/admin/email/settings', [
            'mail_mailer' => 'log',
            'mail_from_address' => 'test@example.com',
            'mail_from_name' => 'Test Site',
        ])->assertRedirect();

        $this->assertDatabaseHas('settings', ['key' => 'mail_mailer', 'value' => 'log']);
        $this->assertDatabaseHas('settings', ['key' => 'mail_from_address', 'value' => 'test@example.com']);
    }

    public function test_templates_index_returns_templates(): void
    {
        EmailTemplate::create([
            'slug' => 'welcome', 'name' => 'Welcome', 'subject' => 'Hello',
            'body_html' => '<p>Hi</p>', 'system' => true, 'active' => true,
        ]);

        $this->actingAs($this->admin)->get('/admin/email/templates')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Email/Templates/Index')
                ->has('templates', 1)
            );
    }

    public function test_template_edit_page_returns_template(): void
    {
        $template = EmailTemplate::create([
            'slug' => 'welcome', 'name' => 'Welcome', 'subject' => 'Hello {{username}}',
            'body_html' => '<p>Welcome</p>', 'system' => true, 'active' => true,
        ]);

        $this->actingAs($this->admin)->get("/admin/email/templates/{$template->id}/edit")
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Email/Templates/Edit')
                ->has('template')
                ->where('template.slug', 'welcome')
            );
    }

    public function test_template_update_saves_changes(): void
    {
        $template = EmailTemplate::create([
            'slug' => 'welcome', 'name' => 'Welcome', 'subject' => 'Old subject',
            'body_html' => '<p>Old body</p>', 'system' => true, 'active' => true,
        ]);

        $this->actingAs($this->admin)->put("/admin/email/templates/{$template->id}", [
            'name' => 'Welcome Email',
            'subject' => 'New subject',
            'body_html' => '<p>New body</p>',
            'active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('email_templates', [
            'id' => $template->id,
            'subject' => 'New subject',
        ]);
    }

    public function test_template_preview_renders_variables(): void
    {
        $this->actingAs($this->admin)->post('/admin/email/templates/preview', [
            'subject' => 'Hello {{username}}',
            'body_html' => '<p>Hi {{username}}</p>',
            'variables' => ['username' => 'John'],
        ])->assertOk()
            ->assertJson(['subject' => 'Hello John', 'body' => '<p>Hi John</p>']);
    }

    public function test_logs_page_returns_paginated_logs(): void
    {
        EmailLog::create([
            'to' => 'user@example.com', 'subject' => 'Test', 'status' => 'sent', 'sent_at' => now(),
        ]);

        $this->actingAs($this->admin)->get('/admin/email/logs')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Email/Logs')
                ->has('logs')
                ->has('logs.data', 1)
            );
    }

    public function test_send_test_email_queues_mail(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)->post('/admin/email/send-test', [
            'email' => 'dest@example.com',
        ])->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_logs_are_created_after_send_test(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)->post('/admin/email/send-test', [
            'email' => 'dest@example.com',
        ]);

        $this->assertDatabaseHas('email_logs', [
            'to' => 'dest@example.com',
            'template_slug' => 'test',
            'status' => 'sent',
        ]);
    }
}
