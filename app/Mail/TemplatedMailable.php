<?php

namespace App\Mail;

use App\Services\Mail\MailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

abstract class TemplatedMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    protected string $templateSlug;

    protected array $vars = [];

    private string $renderedSubject = '';

    private string $renderedBody = '';

    abstract protected function templateSlug(): string;

    abstract protected function templateVars(): array;

    public function envelope(): Envelope
    {
        $this->resolveTemplate();

        return new Envelope(subject: $this->renderedSubject);
    }

    public function content(): Content
    {
        $this->resolveTemplate();

        return new Content(htmlString: $this->renderedBody);
    }

    private function resolveTemplate(): void
    {
        if ($this->renderedSubject !== '') {
            return;
        }

        /** @var MailTemplateService $service */
        $service = app(MailTemplateService::class);

        $vars = array_merge(['app_name' => config('app.name')], $this->templateVars());
        $result = $service->renderSlug($this->templateSlug(), $vars);

        if ($result) {
            $this->renderedSubject = $result['subject'];
            $this->renderedBody = $result['body'];
        } else {
            $this->renderedSubject = $this->fallbackSubject();
            $this->renderedBody = $this->fallbackBody();
        }
    }

    protected function fallbackSubject(): string
    {
        return 'Notification from '.config('app.name');
    }

    protected function fallbackBody(): string
    {
        return '<p>This is an automated message from '.config('app.name').'.</p>';
    }
}
