<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ContactMessage $contactMessage,
        public readonly string $replyBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Re: '.($this->contactMessage->subject ?: 'Your message'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-reply',
            with: [
                'msg' => $this->contactMessage,
                'replyBody' => $this->replyBody,
            ],
        );
    }
}
