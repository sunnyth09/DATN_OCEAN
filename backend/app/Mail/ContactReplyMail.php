<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactReplyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $contactName;

    public string $contactSubject;

    public string $replyContent;

    public function __construct(string $contactName, string $contactSubject, string $replyContent)
    {
        $this->contactName = $contactName;
        $this->contactSubject = $contactSubject;
        $this->replyContent = $replyContent;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Ocean Sport] Phản hồi hỗ trợ: {$this->contactSubject}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact.reply',
            with: [
                'contactName' => $this->contactName,
                'contactSubject' => $this->contactSubject,
                'replyContent' => $this->replyContent,
            ],
        );
    }
}
