<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewSoaUploaded extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * soa details
     *
     * @var $soa
     */
    public $soa;

    /**
     * Create a new message instance.
     */
    public function __construct($soa)
    {
        $this->soa = $soa;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('labels.new_soa_uploaded_subject', [
                'soanum' => $this->soa->up_soanum
            ])
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.esoa.new-soa-uploaded',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
