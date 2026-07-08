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

    /**
     * Create the mailable for the given concern record.
     */
    public function __construct(Concern $concern)
    {
        $this->concern = $concern;
    }

    /**
     * Build the envelope with subject "Concern notification: {title}"
     * (falling back to "new request" when the concern has no title).
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Concern notification: ' . ($this->concern->title ?? 'new request')
        );
    }

    /**
     * Render the email using the emails.esoa.concern-notification view.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.esoa.concern-notification',
        );
    }

    /**
     * No attachments are sent with this notification.
     */
    public function attachments(): array
    {
        return [];
    }
}
