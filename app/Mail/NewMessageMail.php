<?php

namespace App\Mail;

use App\Models\Message;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class NewMessageMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Message $message, public User $recipient) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New message from '.($this->message->sender->display_name ?: $this->message->sender->username),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-message',
            with: [
                'senderName' => $this->message->sender->display_name ?: $this->message->sender->username,
                'preview' => Str::limit($this->message->body, 100),
                'url' => route('account.messages.show', $this->message->conversation_id),
            ],
        );
    }
}
