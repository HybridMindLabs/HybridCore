<?php

namespace Tests\Unit\Mail;

use App\Models\EmailTemplate;
use App\Services\Mail\MailTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    private MailTemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MailTemplateService;
    }

    public function test_render_replaces_double_brace_variables(): void
    {
        $template = new EmailTemplate([
            'subject' => 'Hello {{username}}',
            'body_html' => '<p>Welcome {{username}}</p>',
        ]);

        $result = $this->service->render($template, ['username' => 'Alice']);

        $this->assertSame('Hello Alice', $result['subject']);
        $this->assertSame('<p>Welcome Alice</p>', $result['body']);
    }

    public function test_render_replaces_spaced_brace_variables(): void
    {
        $template = new EmailTemplate([
            'subject' => 'Hello {{ username }}',
            'body_html' => '<p>{{ username }}</p>',
        ]);

        $result = $this->service->render($template, ['username' => 'Bob']);

        $this->assertSame('Hello Bob', $result['subject']);
        $this->assertSame('<p>Bob</p>', $result['body']);
    }

    public function test_render_ignores_unknown_variables(): void
    {
        $template = new EmailTemplate([
            'subject' => 'Hello {{username}} from {{app_name}}',
            'body_html' => '<p>test</p>',
        ]);

        $result = $this->service->render($template, ['username' => 'Carol']);

        $this->assertStringContainsString('Carol', $result['subject']);
        $this->assertStringContainsString('{{app_name}}', $result['subject']);
    }

    public function test_find_returns_null_for_missing_slug(): void
    {
        $this->assertNull($this->service->find('nonexistent'));
    }

    public function test_find_returns_template_by_slug(): void
    {
        EmailTemplate::create([
            'slug' => 'welcome', 'name' => 'Welcome', 'subject' => 'Hi',
            'body_html' => '<p>Hi</p>', 'active' => true,
        ]);

        $template = $this->service->find('welcome');

        $this->assertNotNull($template);
        $this->assertSame('welcome', $template->slug);
    }

    public function test_find_returns_null_for_inactive_template(): void
    {
        EmailTemplate::create([
            'slug' => 'disabled', 'name' => 'Disabled', 'subject' => 'Hi',
            'body_html' => '<p>Hi</p>', 'active' => false,
        ]);

        $this->assertNull($this->service->find('disabled'));
    }

    public function test_render_slug_returns_null_for_missing_template(): void
    {
        $this->assertNull($this->service->renderSlug('missing', []));
    }

    public function test_default_templates_returns_four_slugs(): void
    {
        $defaults = $this->service->defaultTemplates();
        $slugs = array_column($defaults, 'slug');

        $this->assertContains('welcome', $slugs);
        $this->assertContains('account_banned', $slugs);
        $this->assertContains('digest', $slugs);
        $this->assertContains('test', $slugs);
    }
}
