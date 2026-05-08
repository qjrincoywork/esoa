<?php

namespace App\Mail;

use App\Models\Concern;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConcernNotification extends Mailable
{
    use Queueable, SerializesModels;

    public Concern $concern;

    public function __construct(Concern $concern)
    {
        $this->concern = $concern;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Concern notification: ' . ($this->concern->title ?? 'new request')
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.esoa.concern-notification',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
