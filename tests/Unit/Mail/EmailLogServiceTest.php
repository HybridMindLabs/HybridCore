<?php

namespace Tests\Unit\Mail;

use App\Models\EmailLog;
use App\Services\Mail\EmailLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailLogServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmailLogService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmailLogService;
    }

    public function test_log_creates_record(): void
    {
        $log = $this->service->log('user@example.com', 'Test subject', 'sent', 'test');

        $this->assertDatabaseHas('email_logs', [
            'to' => 'user@example.com',
            'subject' => 'Test subject',
            'status' => 'sent',
            'template_slug' => 'test',
        ]);
        $this->assertNotNull($log->sent_at);
    }

    public function test_failed_log_has_null_sent_at(): void
    {
        $log = $this->service->log('user@example.com', 'Fail', 'failed', 'test', 'Connection refused');

        $this->assertNull($log->sent_at);
        $this->assertSame('Connection refused', $log->error);
    }

    public function test_paginate_returns_all_logs(): void
    {
        EmailLog::factory()->count(3)->create();

        $result = $this->service->paginate();

        $this->assertSame(3, $result->total());
    }

    public function test_paginate_filters_by_status(): void
    {
        EmailLog::factory()->create(['status' => 'sent']);
        EmailLog::factory()->create(['status' => 'failed']);

        $result = $this->service->paginate(['status' => 'sent']);

        $this->assertSame(1, $result->total());
    }
}
