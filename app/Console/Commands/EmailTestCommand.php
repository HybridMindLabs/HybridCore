<?php

namespace App\Console\Commands;

use App\Services\Mail\EmailLogService;
use App\Services\Mail\MailConfigService;
use App\Services\Mail\MailTemplateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class EmailTestCommand extends Command
{
    protected $signature = 'hybridcore:email:test {email : Recipient email address}';

    protected $description = 'Send a test email to verify SMTP configuration';

    public function handle(
        MailConfigService $config,
        MailTemplateService $templates,
        EmailLogService $logs,
    ): int {
        $email = $this->argument('email');

        $this->info('Applying mail config from database...');
        $config->applyFromDatabase();

        $rendered = $templates->renderSlug('test', [
            'sent_at' => now()->toDateTimeString(),
        ]);

        $subject = $rendered['subject'] ?? 'Test Email from '.config('app.name');
        $body = $rendered['body'] ?? '<p>Test email.</p>';

        try {
            Mail::html($body, function ($message) use ($email, $subject) {
                $message->to($email)->subject($subject);
            });

            $logs->log($email, $subject, 'sent', 'test');
            $this->info("Test email sent to {$email}.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $logs->log($email, $subject, 'failed', 'test', $e->getMessage());
            $this->error("Failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
