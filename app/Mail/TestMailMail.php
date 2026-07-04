<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestMailMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $sentTo) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[HybridCore] Test Email',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.test-mail',
            with: ['sentTo' => $this->sentTo],
        );
    }
}
